<?php
/* @var $data NfyQueue */
?>

<h3><?php echo CHtml::encode($data->name); ?> <small><?php echo CHtml::link(Yii::t('NfyModule.app','View messages'), array('messages', 'queue_id'=>$data->id, 'subscriber_id'=>Yii::app()->user->getId()))?></small></h3>
<div style="margin-bottom: 20px;">
</div>
