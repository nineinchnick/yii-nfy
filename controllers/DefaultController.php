<?php

class DefaultController extends Controller
{
	public function actionIndex()
	{
		$this->render('index');
	}

	public function actionPoll() {
		$data = array('messages'=>array());
		$with = array(
			'queues' => array(
				'together'=>true,
				'on'=>'queues.is_delivered=FALSE',
				'with'=>'message',
			),
			'channel'=>array('together'=>true),
		);
		$subscriptions = NfySubscriptions::model()->with($with)->findAll('t.user_id=:user_id', array(':user_id'=>Yii::app()->user->getId()));
		foreach($subscriptions as $subscription) foreach($subscription->queues as $queue) {
			$queue->delivered_on = date('Y-m-d H:i:s');
			$queue->is_delivered = true;
			$message = $queue->message;
			if ($queue->save()) {
				$data['messages'][] = array(
					'title'=>$subscription->channel->name,
					'body'=>$message->message,
					'sound'=>Yii::app()->baseUrl.'/sounds/Door Bell-SoundBible.com-1986366504.wav',
				);
			}
		}
		header("Content-type: application/json");
		Yii::app()->getClientScript()->reset();
		echo json_encode($data);
	}
}
