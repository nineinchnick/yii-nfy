<?php

/**
 * This is the model class for table "{{nfy_messages}}".
 *
 * The followings are the available columns in table '{{nfy_messages}}':
 * @property integer $id
 * @property integer $channel_id
 * @property string $logtime
 * @property string $message
 * @property integer $user_id
 *
 * The followings are the available model relations:
 * @property NfyQueues[] $nfyQueues
 * @property NfyChannels $channel
 * @property Users $user
 */
class NfyMessages extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return NfyMessages the static model class
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
		return '{{nfy_messages}}';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('channel_id, user_id', 'required'),
			array('channel_id, user_id', 'numerical', 'integerOnly'=>true),
			array('logtime, message', 'safe'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'queues' => array(self::HAS_MANY, 'NfyQueues', 'message_id'),
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
			'logtime' => 'Logtime',
			'message' => 'Message',
			'user_id' => 'User',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria=new CDbCriteria;
		$criteria->compare('logtime',$this->logtime,true);
		$criteria->compare('message',$this->message,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
}
