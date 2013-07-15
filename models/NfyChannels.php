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
	 * Subscribes user to this channel.
	 * @param integer $user_id
	 * @return boolean
	 */
	public function subscribe($user_id) {
		$subscription = new NfySubscriptions;
		$subscription->channel_id = $this->id;
		$subscription->user_id = $user_id;
		return $subscription->save();
	}

	/**
	 * Unsubscribes user from this channel.
	 * @param integer $user_id
	 * @return boolean
	 */
	public function unsubscribe($user_id) {
		$subscriptions = NfySubscriptions::model()->findAllByAttributes(array('channel_id'=>$this->id,'user_id'=>$user_id));
		foreach($subscriptions as $subscription) {
			$subscription->delete();
		}
		return true;
	}
}
