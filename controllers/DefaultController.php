<?php

class DefaultController extends Controller
{
	public function actionIndex()
	{
		$this->render('index');
	}

	public function actionPoll() {
		Yii::app()->session->close();

		$data = array();
		$data['messages'] = $this->getMessages();

		$pollFor = $this->getModule()->longPolling;
		$maxPoll = $this->getModule()->maxPollCount;
		if ($pollFor && $maxPoll && empty($data['messages'])) {
			while(empty($data['messages']) && $maxPoll) {
				$data['messages'] = $this->getMessages();
				usleep($pollFor * 1000);
				$maxPoll--;
			}
		}

		header("Content-type: application/json");
		Yii::app()->getClientScript()->reset();
		echo json_encode($data);
	}

	protected function getMessages() {
		$result = array();
		$with = array(
			'queues' => array(
				'together'=>true,
				'on'=>'queues.is_delivered=:is_delivered',
				'with'=>'defaultMessage',
				'params'=>array(':is_delivered'=>false),
			),
			'channel'=>array('together'=>true),
		);
		$subscriptions = NfySubscriptions::model()->with($with)->findAll('t.user_id=:user_id', array(':user_id'=>Yii::app()->user->getId()));
		foreach($subscriptions as $subscription) foreach($subscription->queues as $queue) {
			$queue->delivered_on = date('Y-m-d H:i:s');
			$queue->is_delivered = true;
			if ($queue->save()) {
				$notification = array(
					'title'=>$subscription->channel->name,
					'body'=>$queue->message !== null ? $queue->message : $queue->defaultMessage->message,
				);
				if ($this->getModule()->soundUrl!==null) {
					$notification['sound'] = $this->createAbsoluteUrl($this->getModule()->soundUrl);
				}
				$result[] = $notification;
			}
		}
		return $result;
	}
}
