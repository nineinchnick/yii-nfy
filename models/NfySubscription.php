<?php

/**
 * This is the model class for table "{{nfy_subscriptions}}".
 *
 * The followings are the available columns in table '{{nfy_subscriptions}}':
 * @property integer $id
 * @property integer $queue_name
 * @property integer $subscriber_id
 * @property string $created_on
 * @property boolean $is_deleted
 *
 * The followings are the available model relations:
 * @property NfyMessage[] $messages
 * @property Users $subscriber
 */
class NfySubscription extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return NfySubscription the static model class
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
			array('queue_name, subscriber_id', 'required', 'except'=>'search'),
			array('subscriber_id', 'numerical', 'integerOnly'=>true),
			array('is_deleted', 'boolean'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'messages' => array(self::HAS_MANY, 'NfyMessage', 'subscription_id'),
			'subscriber' => array(self::BELONGS_TO, Yii::app()->getModule('nfy')->userClass, 'subscriber_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'queue_name' => 'Queue Name',
			'subscriber_id' => 'Subscriber ID',
			'created_on' => 'Created On',
			'is_deleted' => 'Is Deleted',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria=new CDbCriteria;

		$criteria->compare('queue_name',$this->queue_name,true);
		$criteria->compare('subscriber_id',$this->subscriber_id);
		$criteria->compare('is_deleted',$this->is_deleted);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	public function beforeSave() {
		if ($this->isNewRecord && $this->created_on === null) {
			$now = new DateTime('now', new DateTimezone('UTC'));
			$this->created_on = $now->format('Y-m-d H:i:s');
		}
		return true;
	}

	public function scopes()
	{
        $t = $this->getTableAlias(true);
		return array(
			'current' => array('condition' => "$t.is_deleted = 0"),
		);
	}

	public function withQueue($queue_name)
	{
        $t = $this->getTableAlias(true);
        $this->getDbCriteria()->mergeWith(array(
            'condition' => $t.'.queue_name=:queue_name',
			'params' => array(':queue_name'=>$queue_name),
        ));
        return $this;
	}

	public function withSubscriber($subscriber_id)
	{
        $t = $this->getTableAlias(true);
        $this->getDbCriteria()->mergeWith(array(
            'condition' => $t.'.subscriber_id=:subscriber_id',
			'params' => array(':subscriber_id'=>$subscriber_id),
        ));
        return $this;
	}

	public function matchingCategory($category)
	{
		//! @todo add a join to categories without select and a distinct flag
        return $this;
	}
}
