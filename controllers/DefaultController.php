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
            array('allow', 'actions' => array('index'), 'users' => array('@')),
            array('allow', 'actions' => array('poll'), 'users' => array('*')),
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
        $subscribedOnly = $user->checkAccess('nfy.channel.read.subscribed', array(), true, false);
		$queues = array();
		foreach($this->module->queues as $queueId) {
			$queue = Yii::app()->getComponent($queueId);
			if (!($queue instanceof NfyQueueInterface) || ($subscribedOnly && !$queue->isSubscribed($user->getId()))) continue;
			$queues[$queueId] = $queue;
		}
		$this->render('index', array('queues'=>$queues, 'subscribedOnly' => $subscribedOnly));
	}

	/**
	 * @param string $id id of the queue component
	 * @param boolean $subscribed should the queue be checked using current user's subscription
	 */
    public function actionPoll($id, $subscribed=true)
    {
		Yii::app()->session->close();

		$data = array();
		$data['messages'] = $this->getMessages($id, $subscribed);

		$pollFor = $this->getModule()->longPolling;
		$maxPoll = $this->getModule()->maxPollCount;
		if ($pollFor && $maxPoll && empty($data['messages'])) {
			while(empty($data['messages']) && $maxPoll) {
				$data['messages'] = $this->getMessages();
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

    protected function getMessages($id, $subscribed=true)
    {
		$queue = Yii::app()->getComponent($id);
		if (!($queue instanceof NfyQueueInterface))
			return array();

		$messages = $queue->recv($subscribed ? Yii::app()->user->getId() : null, -1, NfyQueue::GET_DELETE);

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
