<?php

/**
 * This is the model class for table "{{nfy_subscriptions}}".
 *
 * The followings are the available columns in table '{{nfy_subscriptions}}':
 * @property integer $id
 * @property integer $channel_id
 * @property integer $user_id
 * @property string $transports
 * @property string $registered_on
 *
 * The followings are the available model relations:
 * @property NfyQueues[] $nfyQueues
 * @property NfyChannels $channel
 * @property Users $user
 */
class NfySubscriptions extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return NfySubscriptions the static model class
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
		return '{{nfy_subscriptions}}';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array('channel_id, user_id', 'required'),
			array('channel_id, user_id', 'numerical', 'integerOnly'=>true),
			array('transports, registered_on', 'safe'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'queues' => array(self::HAS_MANY, 'NfyQueues', 'subscription_id'),
			'channel' => array(self::BELONGS_TO, 'NfyChannels', 'channel_id'),
			'user' => array(self::BELONGS_TO, 'Users', 'user_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'channel_id' => 'Channel',
			'user_id' => 'User',
			'transports' => 'Transports',
			'registered_on' => 'Registered On',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria=new CDbCriteria;

		$criteria->compare('transports',$this->transports,true);
		$criteria->compare('registered_on',$this->registered_on,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	public function beforeSave() {
		if ($this->isNewRecord && $this->registered_on === null)
			$this->registered_on = date('Y-m-d H:i:s');
		return true;
	}
}
