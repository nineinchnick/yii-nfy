<?php
/* @var $model MessageForm */
/* @var $form CActiveForm */
?>
<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'message-form',
	'enableAjaxValidation'=>false,
)); ?>
	<?php echo $form->errorSummary($model); ?>

	<div class="row">
        <p><?php echo $form->label($model, 'content'); ?>:</p>
		<?php echo $form->textArea($model,'content', array('style'=>'width: 600px;', 'rows'=>5)); ?>
	</div>

    <br/>
    
	<div class="row buttons">
		<?php echo CHtml::submitButton(Yii::t('NfyModule.app', 'Submit')); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->
