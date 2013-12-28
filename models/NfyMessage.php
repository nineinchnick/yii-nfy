<?php

/**
 * This is the model class for table "{{nfy_messages}}".
 *
 * The followings are the available columns in table '{{nfy_messages}}':
 * @property integer $id
 * @property integer $queue_name
 * @property string $created_on
 * @property integer $sender_id
 * @property integer $subscription_id
 * @property integer $status
 * @property integer $timeout
 * @property string $locked_on
 * @property string $deleted_on
 * @property string $mimetype
 * @property string $body
 *
 * The followings are the available model relations:
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
			array('queue_name, sender_id, body', 'required', 'except'=>'search'),
			array('sender_id, subscription_id, timeout', 'numerical', 'integerOnly'=>true),
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
			'subscription' => array(self::BELONGS_TO, 'NfySubscription', 'subscription_id'),
			'sender' => array(self::BELONGS_TO, Yii::app()->getModule('nfy')->userClass, 'sender_id'),
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
			'created_on' => 'Created On',
			'sender_id' => 'Sender ID',
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
		$criteria->compare('queue_name', $this->queue_name, true);
		$criteria->compare('sender_id', $this->sender_id);
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
        $t = $this->getTableAlias(true);
		$criteria = array('condition' => "($t.status=".self::AVAILABLE.")");
		if ($timeout !== null) {
			$now = new DateTime("-$timeout seconds", new DateTimezone('UTC'));
			$criteria['condition'] .= " OR ($t.status=".self::LOCKED." AND $t.locked_on <= :timeout)";
			$criteria['params'] = array(':timeout'=>$now->format('Y-m-d H:i:s'));
		}
        $this->getDbCriteria()->mergeWith($criteria);
        return $this;
	}

	public function locked($timeout=null)
	{
        $t = $this->getTableAlias(true);
		$criteria = array('condition' => "($t.status=".self::LOCKED.")");
		if ($timeout !== null) {
			$now = new DateTime("-$timeout seconds", new DateTimezone('UTC'));
			$criteria['condition'] .= " AND $t.locked_on > :timeout";
			$criteria['params'] = array(':timeout'=>$now->format('Y-m-d H:i:s'));
		}
        $this->getDbCriteria()->mergeWith($criteria);
        return $this;
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

	public function withQueue($queue_name)
	{
        $t = $this->getTableAlias(true);
		$pk = $this->tableSchema->primaryKey;
        $this->getDbCriteria()->mergeWith(array(
            'condition' => $t.'.queue_name=:queue_name',
			'params' => array(':queue_name'=>$queue_name),
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
