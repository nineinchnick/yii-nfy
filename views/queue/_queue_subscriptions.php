<?php
/* @var $data NfyQueue */
/* @var $subscriptions array */
$subscriptions = $data->getSubscriptions();
?>

<h3><?php echo CHtml::encode($data->name); ?> <small><?php echo CHtml::link(Yii::t('NfyModule.app','View all messages'), array('messages', 'queue_id'=>$data->id))?></small></h3>
<p>
	<?php echo CHtml::link(Yii::t('NfyModule.app', 'Subscribe'), array('subscribe', 'queue_id'=>$data->id)); ?> /
	<?php echo CHtml::link(Yii::t('NfyModule.app', 'Unsubscribe'), array('unsubscribe', 'queue_id'=>$data->id)); ?>
</p>
<?php if (!empty($subscriptions)): ?>
	<p>
		<?php echo Yii::t('NfyModule.app', 'Subscriptions'); ?>:
	</p>
	<ul>
<?php foreach($subscriptions as $subscription): ?>
		<li>
			<?php echo CHtml::link(
				CHtml::encode($subscription->label),
				array('messages', 'queue_id'=>$data->id, 'subscriber_id'=>$subscription->subscriber_id),
				array('title'=>implode("\n",array_map(function($c){return $c->category;}, $subscription->categories)))
			); ?>
		</li>
<?php endforeach; ?>
	</ul>
<?php endif; ?>
