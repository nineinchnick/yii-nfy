<?php
/* @var $data NfyQueue */
/* @var $subscriptions array */
$subscriptions = $data->getSubscriptions();
?>

<div style="margin-bottom: 20px;">
    <div>
        <?php echo CHtml::link(CHtml::encode($data->name), array('messages', 'queue_id'=>$data->id)); ?>
<?php if (!empty($subscriptions)): ?>
		<ul>
<?php foreach($subscriptions as $subscription): ?>
		<li><?php echo CHtml::link(CHtml::encode($subscription->label), array('messages', 'queue_id'=>$data->id, 'subscriber_id'=>$subscription->subscriber_id)); ?></li>
<?php endforeach; ?>
		</ul>
<?php endif; ?>
    </div>
</div>
