<?php

/**
 * This is the model class for table "{{nfy_subscriptions}}".
 *
 * The followings are the available columns in table '{{nfy_subscriptions}}':
 * @property integer $id
 * @property integer $queue_id
 * @property string $label
 * @property integer $subscriber_id
 * @property string $created_on
 * @property boolean $is_deleted
 *
 * The followings are the available model relations:
 * @property NfyDbMessage[] $messages
 * @property Users $subscriber
 * @property NfyDbSubscriptionCategory[] $categories
 */
class NfyDbSubscription extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return NfyDbSubscription the static model class
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
			array('queue_id, subscriber_id', 'required', 'except'=>'search'),
			array('subscriber_id', 'numerical', 'integerOnly'=>true),
			array('is_deleted', 'boolean'),
			array('label', 'safe'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'messages' => array(self::HAS_MANY, 'NfyDbMessage', 'subscription_id'),
			'subscriber' => array(self::BELONGS_TO, Yii::app()->getModule('nfy')->userClass, 'subscriber_id'),
			'categories' => array(self::HAS_MANY, 'NfyDbSubscriptionCategory', 'subscription_id'),
			'messagesCount' => array(self::STAT, 'NfyDbMessage', 'subscription_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'queue_id' => 'Queue ID',
			'label' => 'Label',
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

		$criteria->compare('queue_id',$this->queue_id,true);
		$criteria->compare('label',$this->label,true);
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
			'current' => array(
                'condition' => "$t.is_deleted = :false",
                'params' => array(':false'=>0),
            ),
		);
	}

	public function withQueue($queue_id)
	{
        $t = $this->getTableAlias(true);
        $this->getDbCriteria()->mergeWith(array(
            'condition' => $t.'.queue_id=:queue_id',
			'params' => array(':queue_id'=>$queue_id),
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

	public function matchingCategory($categories)
	{
        if ($categories===null)
            return $this;
        $t = $this->getTableAlias(true);
		$r = $this->dbConnection->schema->quoteTableName('categories');

        if (!is_array($categories))
            $categories = array($categories);

        $criteria = new CDbCriteria;
		$criteria->with = array('categories'=>array(
			'together'=>true,
			'select'=>null,
			'distinct'=>true,
		));

        $i = 0;
        foreach($categories as $category) {
			$criteria->addCondition("($r.is_exception = :false AND :category$i LIKE $r.category) OR ($r.is_exception = :true AND :category$i NOT LIKE $r.category)");
			$criteria->params[':false'] = 0;
			$criteria->params[':true'] = true;
			$criteria->params[':category'.$i++] = $category;
        }
        
        $this->getDbCriteria()->mergeWith($criteria);
        return $this;
	}

	public static function createSubscriptions($dbSubscriptions)
	{
		if (!is_array($dbSubscriptions)) {
			$dbSubscriptions = array($dbSubscriptions);
		}
		$result = array();
		foreach($dbSubscriptions as $dbSubscription) {
			$attributes = $dbSubscription->getAttributes();
			unset($attributes['id']);
			unset($attributes['queue_id']);
			unset($attributes['is_deleted']);
			$subscription = new NfySubscription;
			$subscription->setAttributes($attributes);
			foreach($dbSubscription->categories as $category) {
				if ($category->is_exception) {
					$subscription->categories[] = $category->category;
				} else {
					$subscription->exceptions[] = $category->category;
				}
			}
			$result[] = $subscription;
		}
		return $result;
	}
}
