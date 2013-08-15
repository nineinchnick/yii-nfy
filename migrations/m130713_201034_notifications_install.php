<?php

class m130713_201034_notifications_install extends CDbMigration
{
	public function safeUp()
	{
		$nfy = Yii::app()->getModule('nfy');
		$user = CActiveRecord::model($nfy->userClass);
		$userTable = $user->tableName();
		$userPk = $user->primaryKey() === null ? $user->tableSchema->primaryKey : $user->primaryKey();
		$userPkType = $user->tableSchema->getColumn($userPk)->dbType;
		$driver = $this->dbConnection->getDriverName();

		$this->createTable('{{nfy_channels}}', array(
			'id'=>'pk',
			'name'=>'varchar(128) not null',
			'level'=>'varchar(128)',
			'category'=>'varchar(128)',
			'criteria_callback'=>'text',
			'message_template'=>'text',
		));
		$this->createTable('{{nfy_messages}}', array(
			'id'=>'pk',
			'channel_id'=>'integer not null'.($driver=='sqlite' ? ' CONSTRAINT {{nfy_messages}}_channel_id_fkey REFERENCES {{nfy_channels}}(id) ON DELETE CASCADE ON UPDATE CASCADE' : ''),
			'logtime'=>'timestamp',
			'message'=>'text',
		));
		$this->createTable('{{nfy_subscriptions}}', array(
			'id'=>'pk',
			'channel_id'=>'integer not null'.($driver=='sqlite' ? ' CONSTRAINT {{nfy_subscriptions}}_channel_id_fkey REFERENCES {{nfy_channels}}(id) ON DELETE CASCADE ON UPDATE CASCADE' : ''),
			'user_id'=>$userPkType.' not null'.($driver=='sqlite' ? ' CONSTRAINT {{nfy_subscriptions}}_user_id_fkey REFERENCES '.$userTable.'('.$userPk.') ON DELETE CASCADE ON UPDATE CASCADE' : ''),
			'push_transports'=>'text',
			'registered_on' => 'timestamp',
		));
		$this->createTable('{{nfy_queues}}', array(
			'id'=>'pk',
			'subscription_id'=>'integer not null'.($driver=='sqlite' ? ' CONSTRAINT {{nfy_queues}}_subscription_id_fkey REFERENCES {{nfy_subscriptions}}(id) ON DELETE CASCADE ON UPDATE CASCADE' : ''),
			'message_id'=>'integer not null'.($driver=='sqlite' ? ' CONSTRAINT {{nfy_queues}}_message_id_fkey REFERENCES {{nfy_messages}}(id) ON DELETE CASCADE ON UPDATE CASCADE' : ''),
			'is_delivered'=>'boolean not null default '.($driver=='sqlite'?'0':'false'),
			'delivered_on'=>'timestamp',
		));

		if ($this->dbConnection->getDriverName() != 'sqlite') {
			$this->addForeignKey('{{nfy_messages}}_channel_id_fkey', '{{nfy_messages}}', 'channel_id', '{{nfy_channels}}', 'id', 'CASCADE', 'CASCADE');
			$this->addForeignKey('{{nfy_subscriptions}}_channel_id_fkey', '{{nfy_subscriptions}}', 'channel_id', '{{nfy_channels}}', 'id', 'CASCADE', 'CASCADE');
			$this->addForeignKey('{{nfy_subscriptions}}_user_id_fkey', '{{nfy_subscriptions}}', 'user_id', $userTable, $userPk, 'CASCADE', 'CASCADE');
			$this->addForeignKey('{{nfy_queues}}_subscription_id_fkey', '{{nfy_queues}}', 'subscription_id', '{{nfy_subscriptions}}', 'id', 'CASCADE', 'CASCADE');
			$this->addForeignKey('{{nfy_queues}}_message_id_fkey', '{{nfy_queues}}', 'message_id', '{{nfy_messages}}', 'id', 'CASCADE', 'CASCADE');
		}

		$this->createIndex('{{nfy_messages}}_channel_id_idx', '{{nfy_messages}}', 'channel_id');
		$this->createIndex('{{nfy_subscriptions}}_channel_id_idx', '{{nfy_subscriptions}}', 'channel_id');
		$this->createIndex('{{nfy_subscriptions}}_user_id_idx', '{{nfy_subscriptions}}', 'user_id');
		$this->createIndex('{{nfy_queues}}_subscription_id_idx', '{{nfy_queues}}', 'subscription_id');
		$this->createIndex('{{nfy_queues}}_message_id_idx', '{{nfy_queues}}', 'message_id');
		$this->createIndex('{{nfy_queues}}_is_delivered_idx', '{{nfy_queues}}', 'is_delivered');
	}

	public function safeDown()
	{
		$this->dropTable('{{nfy_queues}}');
		$this->dropTable('{{nfy_subscriptions}}');
		$this->dropTable('{{nfy_messages}}');
		$this->dropTable('{{nfy_channels}}');
	}
}

