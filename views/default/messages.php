<?php
/* @var $this DefaultController */
/* @var $dataProvider CArrayDataProvider */
/* @var $queue NfyQueueInterface */
/* @var $model MessageForm */
/* @var $authItems array */

$this->breadcrumbs=array(
	Yii::t('NfyModule.app', 'Queues')=>array('index'),
	$queue->name,
);
?>
<h1><?php echo $queue->name; ?></h1>

<?php if ($authItems['nfy.message.create']): ?>
<?php echo $this->renderPartial('_message_form', array('model'=>$model)); ?>
<?php endif; ?>

<?php if ($authItems['nfy.message.read']): ?>
<p>
<?php $this->widget('zii.widgets.CListView', array(
    'dataProvider'=>$dataProvider,
    'itemView'=>'_message_item',
    'pager' => array(
        'class' => 'CLinkPager',
        'prevPageLabel' => Yii::t('NfyModule.app', 'Newer'),
        'nextPageLabel' => Yii::t('NfyModule.app', 'Older'),
    ),
)); ?>
</p>
<?php endif; ?>
