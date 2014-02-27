<?php

/**
 * The default controller providing a basic queue managment interface and poll action.
 */
class QueueController extends Controller
{
    public function filters() {
        return array(
            'accessControl',
        );
    }

    public function accessRules() {
        return array(
            array('allow', 'actions' => array('index'), 'users' => array('@'), 'roles' => array('nfy.queue.read')),
            array('allow', 'actions' => array('messages', 'message', 'subscribe', 'unsubscribe'), 'users' => array('@')),
            array('allow', 'actions' => array('poll'), 'users' => array('@')),
            array('deny', 'users' => array('*')),
        );
    }
    
	/**
	 * Displays a list of queues and their subscriptions.
	 */
	public function actionIndex()
	{
		/** @var CWebUser */
		$user = Yii::app()->user;
        $subscribedOnly = $user->checkAccess('nfy.queue.read.subscribed', array(), true, false);
		$queues = array();
		foreach($this->module->queues as $queueId) {
			/** @var NfyQueue */
			$queue = Yii::app()->getComponent($queueId);
			if (!($queue instanceof NfyQueueInterface) || ($subscribedOnly && !$queue->isSubscribed($user->getId()))) continue;
			$queues[$queueId] = $queue;
		}
		$this->render('index', array('queues'=>$queues, 'subscribedOnly' => $subscribedOnly));
	}

	/**
	 * Subscribe current user to selected queue.
	 * @param string $queue_name
	 */
	public function actionSubscribe($queue_name)
	{
        list($queue, $authItems) = $this->loadQueue($queue_name, array('nfy.queue.subscribe'));

		$formModel = new SubscriptionForm('create');
        if (isset($_POST['SubscriptionForm'])) {
			$formModel->attributes=$_POST['SubscriptionForm'];
			if($formModel->validate()) {
				$queue->subscribe(Yii::app()->user->getId(), $formModel->label, $formModel->categories, $formModel->exceptions);
				$this->redirect(array('index'));
			}
        }
        $this->render('subscription', array('queue' => $queue, 'model' => $formModel));
	}

	/**
	 * Unsubscribe current user from selected queue.
	 * @param string $queue_name
	 */
	public function actionUnsubscribe($queue_name)
	{
        list($queue, $authItems) = $this->loadQueue($queue_name, array('nfy.queue.unsubscribe'));
		$queue->unsubscribe(Yii::app()->user->getId());
		$this->redirect(array('index'));
	}

	/**
	 * Displays and send messages in the specified queue.
	 * @param string $queue_name
	 * @param string $subscriber_id
	 */
	public function actionMessages($queue_name, $subscriber_id=null)
	{
		if (($subscriber_id=trim($subscriber_id))==='')
			$subscriber_id = null;
        list($queue, $authItems) = $this->loadQueue($queue_name, array('nfy.message.read', 'nfy.message.create'));
		$this->verifySubscriber($queue, $subscriber_id);

		$formModel = new MessageForm('create');
        if ($authItems['nfy.message.create'] && isset($_POST['MessageForm'])) {
			$formModel->attributes=$_POST['MessageForm'];
			if($formModel->validate()) {
				$queue->send($formModel->content, $formModel->category);
				$this->redirect(array('messages', 'queue_name'=>$queue_name, 'subscriber_id'=>$subscriber_id));
			}
        }

        $dataProvider = null;
        if ($authItems['nfy.message.read']) {
			$dataProvider = new CArrayDataProvider(
				$queue->peek($subscriber_id, 200, array(NfyMessage::AVAILABLE, NfyMessage::RESERVED, NfyMessage::DELETED)),
				array('sort'=>array('attributes'=>array('id'), 'defaultOrder' => array('id' => CSort::SORT_DESC)))
			);
            // reverse display order to simulate a chat window, where latest message is right above the message form
            $dataProvider->setData(array_reverse($dataProvider->getData()));
        }

		$this->render('messages', array(
			'queue' => $queue,
			'queue_name' => $queue_name,
			'dataProvider' => $dataProvider,
			'model' => $formModel,
			'authItems' => $authItems,
		));
	}

	/**
	 * Fetches details of a single message, allows to release or delete it or sends a new message.
	 * @param string $id queue name
	 * @param string $subscriber_id
	 * @param string $message_id
	 */
	public function actionMessage($queue_name, $subscriber_id=null, $message_id=null)
	{
		if (($subscriber_id=trim($subscriber_id))==='')
			$subscriber_id = null;
        list($queue, $authItems) = $this->loadQueue($queue_name, array('nfy.message.read', 'nfy.message.create'));
		$this->verifySubscriber($queue, $subscriber_id);

		if ($queue instanceof NfyDbQueue) {
			NfyDbMessage::model()->withQueue($queue->id);
			if ($subscriber_id !== null)
				NfyDbMessage::model()->withSubscriber($subscriber_id);

			$dbMessage = NfyDbMessage::model()->findByPk($message_id);
			if ($dbMessage === null)
				throw new CHttpException(404, Yii::t("NfyModule.app", 'Message with given ID was not found.'));
			$messages = NfyDbMessage::createMessages($dbMessage);
			$message = reset($messages);
		} else {
			//! @todo should we even bother to locate a single message by id?
			$message = new NfyMessage;
			$message->setAttributes(array(
				'id' => $message_id,
				'subscriber_id' => $subscriber_id,
				'status' => NfyMessage::AVAILABLE,
			));
		}

		if (isset($_POST['delete'])) {
			$queue->delete($message->id, $message->subscriber_id);
			$this->redirect(array('messages', 'queue_name'=> $queue_name, 'subscriber_id'=>$message->subscriber_id));
		}

		$this->render('message', array(
			'queue' => $queue,
			'queue_name' => $queue_name,
			'dbMessage' => $dbMessage,
			'message' => $message,
			'authItems' => $authItems,
		));
	}

	/**
	 * Loads queue specified by id and checks authorization.
	 * @param string $name queue component name
	 * @param array $authItems
	 * @return array NfyQueueInterface object and array with authItems as keys and boolean values
	 * @throws CHttpException 403 or 404
	 */
    protected function loadQueue($name, $authItems=array())
    {
		/** @var CWebUser */
		$user = Yii::app()->user;
		/** @var NfyQueue */
		$queue = Yii::app()->getComponent($name);
		if (!($queue instanceof NfyQueueInterface))
            throw new CHttpException(404, Yii::t("NfyModule.app", 'Queue with given ID was not found.'));
        $assignedAuthItems = array();
        $allowAccess = empty($authItems);
        foreach($authItems as $authItem) {
            $assignedAuthItems[$authItem] = $user->checkAccess($authItem, array('queue'=>$queue));
            if ($assignedAuthItems[$authItem])
                $allowAccess = true;
        }
        if (!$allowAccess) {
            throw new CHttpException(403, Yii::t('yii','You are not authorized to perform this action.'));
        }
        return array($queue, $assignedAuthItems);
    }

	/**
	 * Checks if current user can read only messages from subscribed queues and is subscribed.
	 * @param NfyQueueInterface $queue
	 * @param integer $subscriber_id
	 * @throws CHttpException 403
	 */
	protected function verifySubscriber($queue, $subscriber_id)
	{
		/** @var CWebUser */
		$user = Yii::app()->user;
        $subscribedOnly = $user->checkAccess('nfy.message.read.subscribed', array(), true, false);
		if ($subscribedOnly && (!$queue->isSubscribed($user->getId()) || $subscriber_id != $user->getId()))
            throw new CHttpException(403, Yii::t('yii','You are not authorized to perform this action.'));
	}

	/**
	 * @param string $id id of the queue component
	 * @param boolean $subscribed should the queue be checked using current user's subscription
	 * @throws CHttpException 403
	 */
    public function actionPoll($id, $subscribed=true)
    {
		$userId = Yii::app()->user->getId();
		$queue = Yii::app()->getComponent($id);
		if (!($queue instanceof NfyQueueInterface))
			return array();
		if (!Yii::app()->user->checkAccess('nfy.message.read', array('queue'=>$queue)))
            throw new CHttpException(403, Yii::t('yii','You are not authorized to perform this action.'));

		Yii::app()->session->close();


		$data = array();
		$data['messages'] = $this->getMessages($queue, $subscribed ? $userId : null);

		$pollFor = $this->getModule()->longPolling;
		$maxPoll = $this->getModule()->maxPollCount;
		if ($pollFor && $maxPoll && empty($data['messages'])) {
			while(empty($data['messages']) && $maxPoll) {
				$data['messages'] = $this->getMessages($queue, $subscribed ? $userId : null);
				usleep($pollFor * 1000);
				$maxPoll--;
			}
		}

        if(empty($data['messages'])) {
            header("HTTP/1.0 304 Not Modified");
            exit();
        } else {
            header("Content-type: application/json");
            Yii::app()->getClientScript()->reset();
            echo json_encode($data);
        }
	}

	/**
	 * Fetches messages from a queue and deletes them. Messages are transformed into a json serializable array.
	 * If a sound is configured in the module, an url is added to each message.
	 *
	 * Only first 20 messages are returned but all available messages are deleted from the queue.
	 *
	 * @param NfyQueueInterface $queue
	 * @param string $userId
	 * @return array
	 */
    protected function getMessages($queue, $userId)
    {
		$messages = $queue->receive($userId);

        if (empty($messages)) {
            return array();
        }

        $messages = array_slice($messages, 0, 20);
        $soundUrl = $this->getModule()->soundUrl !== null ? $this->createAbsoluteUrl($this->getModule()->soundUrl) : null;

        $results = array();
        foreach($messages as $message) {
            $result = array(
                'title'=>$queue->label,
                'body'=>$message->body,
            );
            if ($soundUrl!==null) {
                $result['sound'] = $soundUrl;
            }
            $results[] = $result;
        }
		return $results;
	}

	public function createMessageUrl($queue_name, NfyMessage $message)
	{
		return $this->createUrl('message', array('queue_name' => $queue_name, 'subscriber_id' => $message->subscriber_id, 'message_id'=>$message->id));
	}
}
