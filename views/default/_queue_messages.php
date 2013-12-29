<?php
/* @var $data NfyQueue */
?>

<div style="margin-bottom: 20px;">
    <div>
        <?php echo CHtml::link(CHtml::encode($data->name), array('messages', 'queue_id'=>$data->id, 'subscriber_id'=>Yii::app()->user->getId())); ?>
        <?php //echo $data->categories; ?>
    </div>
</div>
