<?php

/**
 *
 */
class MessageForm extends CFormModel
{
    public $content;
    
	public function rules()
	{
        return array(
            array('content', 'filter', 'filter'=>'trim'),
            array('content', 'default', 'setOnEmpty'=>true, 'value' => null),
            array('content', 'required'),
        );
    }

	public function attributeLabels()
	{
		return array(
			'content' => Yii::t('NfyModule.app', 'Message content'),
		);
	}
}
