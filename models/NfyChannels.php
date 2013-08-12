<?php

/**
 * This is the model class for table "{{nfy_channels}}".
 *
 * The followings are the available columns in table '{{nfy_channels}}':
 * @property integer $id
 * @property string $name
 * @property string $levels
 * @property string $categories
 * @property string $message_template
 * @property boolean $enabled
 * @property string $route_class
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
			array('name, levels, categories, route_class', 'length', 'max'=>128),
			array('message_template, enabled', 'safe'),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('name, levels, categories, message_template, enabled, route_class', 'safe', 'on'=>'search'),
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
			'levels' => 'Levels',
			'categories' => 'Categories',
			'message_template' => 'Message Template',
			'enabled' => 'Enabled',
			'route_class' => 'Route Class',
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
		$criteria->compare('levels',$this->levels,true);
		$criteria->compare('categories',$this->categories,true);
		$criteria->compare('message_template',$this->message_template,true);
		$criteria->compare('enabled',$this->enabled);
		$criteria->compare('route_class',$this->route_class,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * Subscribes user to this channel.
	 * @param integer $user_id
	 * @param string $transports default transports
	 * @return boolean
	 */
	public function subscribe($user_id, $transports = null) {
		$subscription = new NfySubscriptions;
		$subscription->channel_id = $this->id;
		$subscription->user_id = $user_id;
		$subscription->transports = $transports;
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
