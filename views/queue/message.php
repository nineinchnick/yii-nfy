<?php
/* @var $this QueueController */
/* @var $queue NfyQueueInterface */
/* @var $message NfyMessage */
/* @var $authItems array */

$this->breadcrumbs=array(
	Yii::t('NfyModule.app', 'Queues')=>array('index'),
	$queue->name=>array('messages', 'queue_id'=>$message->queue_id, 'subscriber_id'=>$message->subscription_id),
	$message->id,
);
?>
<h1><?php echo Yii::t('NfyModule.app', 'Message {id}', array('{id}'=>$message->id)); ?> <small><?php echo $message->created_on; ?></small></h1>

<div style="margin-bottom: 10px; word-break: break-all; white-space: normal;">
    <div>
        <?php echo $message->body; ?>
    </div>
</div>
<div>
<?php if ((int)$message->status === NfyMessage::AVAILABLE): ?>
	<form method="post" action="<?php echo $this->createUrl('message', array('queue_id'=>$message->queue_id, 'subscriber_id'=>$message->subscription_id, 'message_id'=>$message->id)); ?>">
		<?php echo CHtml::submitButton(Yii::t('NfyModule.app', 'Mark as read'), array('name'=>'delete')); ?>
	</form>
<?php endif; ?>
    <?php echo CHtml::link(CHtml::encode(Yii::t('NfyModule.app', 'Back to messages list')), array('messages', 'queue_id'=>$message->queue_id, 'subscriber_id'=>$message->subscription_id)); ?>
</div>

<?php if (!Yii::app()->user->checkAccess('nfy.message.read.subscribed', array(), true, false) && ($otherMessages=$message->subscriptionMessages(array(
    'with'=>'subscription.subscriber',
    'order'=>$message->getDbConnection()->getSchema()->quoteSimpleTableName('subscriptionMessages').'.deleted_on, '.$message->getDbConnection()->getSchema()->quoteSimpleTableName('subscriber').'.username',
))) != array()): ?>
<h3><?php echo Yii::t('NfyModule.app', 'Other recipients'); ?>:</h3>
<ul>
<?php foreach($otherMessages as $otherMessage): ?>
    <li><?php echo $otherMessage->deleted_on.' '.$otherMessage->subscription->subscriber; ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
