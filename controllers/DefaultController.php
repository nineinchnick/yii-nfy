<?php

class DefaultController extends Controller
{
    public function filters() {
        return array(
            'accessControl',
        );
    }

    public function accessRules() {
        return array(
            array('allow', 'actions' => array('index'), 'users' => array('@'), 'roles' => array('nfy.queue.read')),
            array('allow', 'actions' => array('messages', 'subscribe', 'unsubscribe'), 'users' => array('@')),
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

	public function actionSubscribe($queue_id)
	{
        list($queue, $authItems) = $this->loadQueue($queue_id, array('nfy.queue.subscribe'));

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

	public function actionUnsubscribe($queue_id)
	{
        list($queue, $authItems) = $this->loadQueue($queue_id, array('nfy.queue.unsubscribe'));
		$queue->unsubscribe(Yii::app()->user->getId());
		$this->redirect(array('index'));
	}

	/**
	 * Displays messages in the specified queue.
	 * @param string $queue_id
	 * @param string $subscriber_id
	 */
	public function actionMessages($queue_id, $subscriber_id=null)
	{
        list($queue, $authItems) = $this->loadQueue($queue_id, array('nfy.message.read', 'nfy.message.create'));

		$formModel = new MessageForm('create');
        if ($authItems['nfy.message.create'] && isset($_POST['MessageForm'])) {
			$formModel->attributes=$_POST['MessageForm'];
			if($formModel->validate()) {
				//! @todo use first selected category for current subscription - if there's only one the list should be hidden from user
				//$queue->getSubscription()->categories(array('limit'=>1,'condition'=>'exception=0'))
				$queue->send($formModel->content);
				$this->redirect(array('messages', 'queue_id'=>$queue_id, 'subscriber_id'=>$subscriber_id));
			}
        }

        $dataProvider = null;
        if ($authItems['nfy.message.read']) {
			$dataProvider = new CArrayDataProvider(
				$queue->peek($subscriber_id, 200, array(NfyMessage::AVAILABLE, NfyMessage::LOCKED, NfyMessage::DELETED)),
				array('sort'=>array('attributes'=>array('id'), 'defaultOrder' => array('id' => CSort::SORT_DESC)))
			);
            // reverse display order
            $dataProvider->setData(array_reverse($dataProvider->getData()));
        }
        $this->render('messages', array('queue' => $queue, 'dataProvider' => $dataProvider, 'model' => $formModel, 'authItems' => $authItems));
	}

    protected function loadQueue($id, $authItems=array())
    {
		/** @var CWebUser */
		$user = Yii::app()->user;
		/** @var NfyQueue */
		$queue = Yii::app()->getComponent($id);
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

    protected function sendMessage($queue)
    {
        $model = new MessageForm('create');
        
        if(isset($_POST['MessageForm'])) {
            $model->attributes=$_POST['MessageForm'];
            if($model->validate()) {
                $queue->send($model->content);
            }
        }
        return $model;
    }

	/**
	 * @param string $id id of the queue component
	 * @param boolean $subscribed should the queue be checked using current user's subscription
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
                'title'=>$queue->name,
                'body'=>$message->body,
            );
            if ($soundUrl!==null) {
                $result['sound'] = $soundUrl;
            }
            $results[] = $result;
        }
		return $results;
	}
}
