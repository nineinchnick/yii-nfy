<?php
/* @var $model Message */
/* @var $form CActiveForm */
?>
<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'message-form',
	'enableAjaxValidation'=>false,
)); ?>
	<?php echo $form->errorSummary($model); ?>

	<div class="row">
        <p><?php echo Yii::t('NfyModule.app', 'Message content'); ?>:</p>
		<?php echo $form->textArea($model,'content', array('style'=>'width: 600px;', 'rows'=>5)); ?>
	</div>

    <br/>
    
	<div class="row buttons">
		<?php echo CHtml::submitButton('Wyślij'); ?>
	</div>

<?php $this->endWidget(); ?>

</div><!-- form -->
