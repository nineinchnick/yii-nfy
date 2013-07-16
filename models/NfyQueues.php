<?php

/**
 * This is the model class for table "{{nfy_queues}}".
 *
 * The followings are the available columns in table '{{nfy_queues}}':
 * @property integer $id
 * @property integer $subscription_id
 * @property integer $message_id
 * @property boolean $is_delivered
 * @property string $delivered_on
 * @property string $message
 *
 * The followings are the available model relations:
 * @property NfySubscriptions $subscription
 * @property NfyMessages $message0
 */
class NfyQueues extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return NfyQueues the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return '{{nfy_queues}}';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('subscription_id, message_id', 'required'),
			array('subscription_id, message_id', 'numerical', 'integerOnly'=>true),
			array('is_delivered, delivered_on, message', 'safe'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'subscription' => array(self::BELONGS_TO, 'NfySubscriptions', 'subscription_id'),
			'defaultMessage' => array(self::BELONGS_TO, 'NfyMessages', 'message_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'subscription_id' => 'Subscription',
			'message_id' => 'Message',
			'is_delivered' => 'Is Delivered',
			'delivered_on' => 'Delivered On',
			'defaultMessage' => 'Message',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria=new CDbCriteria;

		$criteria->compare('is_delivered',$this->is_delivered);
		$criteria->compare('delivered_on',$this->delivered_on,true);
		$criteria->compare('message',$this->message,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
}
