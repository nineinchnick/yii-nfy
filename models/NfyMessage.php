<?php

/**
 * This is the model class for table "{{nfy_messages}}".
 *
 * The followings are the available columns in table '{{nfy_messages}}':
 * @property integer $id
 * @property integer $queue_id
 * @property string $created_on
 * @property integer $sender_id
 * @property integer $message_id
 * @property integer $subscription_id
 * @property integer $status
 * @property integer $timeout
 * @property string $locked_on
 * @property string $deleted_on
 * @property string $mimetype
 * @property string $body
 *
 * The followings are the available model relations:
 * @property NfyMessage $mainMessage
 * @property NfyMessage[] $subscriptionMessages
 * @property NfySubscription $subscription
 * @property Users $sender
 */
class NfyMessage extends CActiveRecord
{
	const AVAILABLE = 0;
	const LOCKED = 1;
	const DELETED = 2;

	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return NfyMessage the static model class
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
		return array(
			array('queue_id, sender_id, body', 'required', 'except'=>'search'),
			array('sender_id, subscription_id, timeout', 'numerical', 'integerOnly'=>true),
			array('message_id, subscription_id, timeout', 'numerical', 'integerOnly'=>true, 'on'=>'search'),
			array('status', 'numerical', 'integerOnly'=>true, 'on'=>'search'),
			array('mimetype', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'mainMessage' => array(self::BELONGS_TO, 'NfyMessage', 'message_id'),
			'sender' => array(self::BELONGS_TO, Yii::app()->getModule('nfy')->userClass, 'sender_id'),
			'subscription' => array(self::BELONGS_TO, 'NfySubscription', 'subscription_id'),
			'subscriptionMessages' => array(self::HAS_MANY, 'NfyMessage', 'message_id'),
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
			'created_on' => 'Created On',
			'sender_id' => 'Sender ID',
			'message_id' => 'Message ID',
			'subscription_id' => 'Subscription ID',
			'status' => 'Status',
			'timeout' => 'Timeout',
			'locked_on' => 'Locked On',
			'deleted_on' => 'Deleted On',
			'mimetype' => 'MIME Type',
			'body' => 'Message Body',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria=new CDbCriteria;
		$criteria->compare('queue_id', $this->queue_id, true);
		$criteria->compare('sender_id', $this->sender_id);
		$criteria->compare('message_id', $this->message_id);
		$criteria->compare('subscription_id', $this->subscription_id);
		$criteria->compare('status', $this->status);
		$criteria->compare('timeout', $this->timeout);
		$criteria->compare('mimetype', $this->mimetype, true);
		$criteria->compare('body', $this->body, true);

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

	public function __clone()
	{
		$this->primaryKey = null;
		$this->subscription_id = null;
		$this->isNewRecord = true;
	}

	public function scopes()
	{
        $t = $this->getTableAlias(true);
		return array(
			'deleted' => array('condition'=>"$t.status=".self::DELETED),
		);
	}

	public function available($timeout=null)
	{
		return $this->withStatus(self::AVAILABLE,$timeout);
	}

	public function locked($timeout=null)
	{
		return $this->withStatus(self::LOCKED,$timeout);
	}

	public function timedout($timeout=null)
	{
		if ($timeout === null) {
			$this->getDbCriteria()->mergeWith(array('condition'=>'1=0'));
			return $this;
		}
		$now = new DateTime("-$timeout seconds", new DateTimezone('UTC'));
        $t = $this->getTableAlias(true);
		$criteria = array(
			'condition' => "($t.status=".self::LOCKED." AND $t.locked_on <= :timeout)",
			'params' => array(':timeout'=>$now->format('Y-m-d H:i:s')),
		);
        $this->getDbCriteria()->mergeWith($criteria);
        return $this;
	}

	public function withStatus($statuses, $timeout=null)
	{
		if (!is_array($statuses))
			$statuses = array($statuses);
        $t = $this->getTableAlias(true);
		$now = new DateTime("-$timeout seconds", new DateTimezone('UTC'));
		$criteria = new CDbCriteria;
		$conditions = array();
		// test for two special cases
		if (array_diff($statuses, array(self::AVAILABLE, self::LOCKED)) === array()) {
			// only not deleted
			$conditions[] = "$t.status!=".self::DELETED;
		} elseif (array_diff($statuses, array(self::AVAILABLE, self::LOCKED, self::DELETED)) === array()) {
			// pass - don't add no conditions
		} else {
			// merge all statuses
			foreach($statuses as $status) {
				switch($status) {
					case self::AVAILABLE:
						$conditions[] = "$t.status=".$status;
						if ($timeout !== null) {
							$conditions[] = "($t.status=".self::LOCKED." AND $t.locked_on <= :timeout)";
							$criteria->params = array(':timeout'=>$now->format('Y-m-d H:i:s'));
						}
						break;
					case self::LOCKED:
						if ($timeout !== null) {
							$conditions[] = "($t.status=$status AND $t.locked_on > :timeout)";
							$criteria->params = array(':timeout'=>$now->format('Y-m-d H:i:s'));
						} else {
							$conditions[] = "$t.status=".$status;
						}
						break;
					case self::DELETED:
						$conditions[] = "$t.status=".$status;
						break;
				}
			}
		}
		if (!empty($conditions)) {
			$criteria->addCondition('('.implode(') OR (', $conditions).')', 'OR');
			$this->getDbCriteria()->mergeWith($criteria);
		}
        return $this;
	}

	public function withQueue($queue_id)
	{
        $t = $this->getTableAlias(true);
		$pk = $this->tableSchema->primaryKey;
        $this->getDbCriteria()->mergeWith(array(
            'condition' => $t.'.queue_id=:queue_id',
			'params' => array(':queue_id'=>$queue_id),
			'order' => "$t.$pk ASC",
        ));
        return $this;
	}

	public function withSubscriber($subscriber_id=null)
	{
		if ($subscriber_id === null) {
			$t = $this->getTableAlias(true);
			$criteria = array('condition'=>"$t.subscription_id IS NULL");
		} else {
			$schema = $this->getDbConnection()->getSchema();
			$criteria = array(
				'together' => true,
				'with' => array('subscription' => array(
					'condition' => $schema->quoteSimpleTableName('subscription').'.subscriber_id=:subscriber_id',
					'params' => array(':subscriber_id'=>$subscriber_id),
				)),
			);
		}
        $this->getDbCriteria()->mergeWith($criteria);
        return $this;
	}
}
