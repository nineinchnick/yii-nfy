<?php

/**
 * This is the model class for table "{{nfy_subscription_categories}}".
 *
 * The followings are the available columns in table '{{nfy_subscription_categories}}':
 * @property integer $id
 * @property integer $subscription_id
 * @property string $category
 * @property boolean $is_exception
 *
 * The followings are the available model relations:
 * @property NfyDbSubscription $subscription
 */
class NfyDbSubscriptionCategory extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return NfyDbSubscriptionCategory the static model class
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
		return '{{nfy_subscription_categories}}';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		return array(
			array('subscription_id, category, is_exception', 'required', 'except'=>'search'),
			array('subscription_id', 'numerical', 'integerOnly'=>true),
			array('is_exception', 'boolean'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		return array(
			'subscription' => array(self::BELONGS_TO, 'NfyDbSubscription', 'subscription_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'subscription_id' => 'Subscription ID',
			'category' => 'Category',
			'is_exception' => 'Is Exception',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		$criteria=new CDbCriteria;

		$criteria->compare('subscription_id',$this->subscription_id);
		$criteria->compare('category',$this->category,true);
		$criteria->compare('is_exception',$this->is_exception);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}
}
