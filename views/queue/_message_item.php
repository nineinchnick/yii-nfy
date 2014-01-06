<?php
/* @var $data NfyMessage */
?>

<div style="margin-bottom: 20px; word-break: break-all; white-space: normal;">
    <div style="<?php echo (int)$data->status !== NfyMessage::AVAILABLE ? '' : "font-weight:bold;"; ?>">
        <?php echo $data->created_on; ?>
        <?php echo CHtml::link(CHtml::encode($data->id), array('message', 'queue_id' => $data->queue_id, 'subscriber_id' => $data->subscription_id, 'message_id'=>$data->id)); ?>
        <?php echo $data->body; ?>
    </div>
</div>
