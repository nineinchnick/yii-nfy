<?php

/**
 * This is the model class for table "{{nfy_channels}}".
 *
 * The followings are the available columns in table '{{nfy_channels}}':
 * @property integer $id
 * @property string $name
 * @property string $level
 * @property string $category
 * @property string $criteria_callback
 * @property string $message_template
 *
 * The followings are the available model relations:
 * @property NfyMessages[] $nfyMessages
 * @property NfySubscriptions[] $nfySubscriptions
 */
class NfyChannels extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return NfyChannels the static model class
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
		return '{{nfy_channels}}';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('name', 'required'),
			array('name, level, category', 'length', 'max'=>128),
			array('criteria_callback, message_template', 'safe'),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('name, level, category, criteria_callback, message_template', 'safe', 'on'=>'search'),
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
			'messages' => array(self::HAS_MANY, 'NfyMessages', 'channel_id'),
			'subscriptions' => array(self::HAS_MANY, 'NfySubscriptions', 'channel_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'name' => 'Name',
			'level' => 'Level',
			'category' => 'Category',
			'criteria_callback' => 'Criteria Callback',
			'message_template' => 'Message Template',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria=new CDbCriteria;

		$criteria->compare('name',$this->name,true);
		$criteria->compare('level',$this->level,true);
		$criteria->compare('category',$this->category,true);
		$criteria->compare('criteria_callback',$this->criteria_callback,true);
		$criteria->compare('message_template',$this->message_template,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * @param mixed $msg if string, is treated as message to be logged, if an array, should contain 'old' and 'new' keys with CModel objects as values
	 * @param string $level level of the message (e.g. 'trace', 'warning', 'error'). It is case-insensitive.
	 * @param string $category category of the message (e.g. 'system.web'). It is case-insensitive.
	 */
	public function process($msg, $level, $category) {
		// create message using templates
		if (is_array($msg)) {
			// create tokens from old and new models' attributes
			$tokens = array();
			$values = array();
			foreach($msg['old']->getAttributes() as $attribute=>$value) {
				$tokens[] = "{old.$attribute}";
				$values[] = $value;
			}
			foreach($msg['new']->getAttributes() as $attribute=>$value) {
				$tokens[] = "{new.$attribute}";
				$values[] = $value;
			}
			if ($this->message_template === null) {
				$msg = serialize(array_combine($tokens, $values));
			} else {
				$msg = str_replace($tokens, $values, $this->message_template);
			}
		}
		// save message
		$message = new NfyMessages;
		$message->channel_id = $this->id;
		$message->logtime = date('Y-m-d H:i:s');
		$message->message = $msg;
		if (!$message->save()) {
			Yii::log(Yii::t('NfyModule.app', "Failed to save message '{msg}' for channel {channel_id}.", array('{msg}' => $msg, '{channel_id}' => $this->id)), 'error', 'nfy');
		}
		// load subscriptions for matching channels
		foreach($this->subscriptions as $subscription) {
			// send push notifications via selected transports
			foreach(explode(',',$subscription->push_transports) as $transport) {
				$transport = trim($transport, " \t\n\r\0,");
				//! @todo implement
			}
			// add message to subscription's queue
			$queue = new NfyQueues;
			$queue->subscription_id = $subscription->id;
			$queue->message_id = $message->id;
			if (!$queue->save()) {
				Yii::log(Yii::t('NfyModule.app', 'Failed to send notification {message_id} to user {user_id} via subscription {subscription_id}.', array('{message_id}' => $message->id, '{user_id}' => $subscription->user_id, '{subscription_id}' => $subscription->id)), 'error', 'nfy');
			}
		}
	}
}
