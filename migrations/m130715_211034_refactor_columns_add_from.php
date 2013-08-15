<?php

class m130715_211034_refactor_columns_add_from extends CDbMigration
{
	public function safeUp()
	{
		$nfy = Yii::app()->getModule('nfy');
		$user = CActiveRecord::model($nfy->userClass);
		$userTable = $user->tableName();
		$userPk = $user->primaryKey() === null ? $user->tableSchema->primaryKey : $user->primaryKey();
		$userPkType = $user->tableSchema->getColumn($userPk)->dbType;
		$driver = $this->dbConnection->getDriverName();

		if ($driver=='sqlite') {
			$this->dropTable('{{nfy_messages}}');
			$this->createTable('{{nfy_messages}}', array(
				'id'=>'pk',
				'channel_id'=>'integer not null'.($driver=='sqlite' ? ' CONSTRAINT {{nfy_messages}}_channel_id_fkey REFERENCES {{nfy_channels}}(id) ON DELETE CASCADE ON UPDATE CASCADE' : ''),
				'user_id'=>'integer not null'.($driver=='sqlite' ? ' CONSTRAINT {{nfy_messages}}_user_id_fkey REFERENCES '.$userTable.'('.$userPk.') ON DELETE CASCADE ON UPDATE CASCADE' : ''),
				'logtime'=>'timestamp',
				'message'=>'text',
			));
			$this->createIndex('{{nfy_messages}}_channel_id_idx', '{{nfy_messages}}', 'channel_id');

			$this->createTable('{{nfy_channels2}}', array(
				'id'=>'pk',
				'name'=>'varchar(128) not null',
				'levels'=>'varchar(128)',
				'categories'=>'varchar(128)',
				'message_template'=>'text',
				'enabled'=>'boolean not null default true',
				'route_class'=>'varchar(128) not null default \'NfyDbRoute\'',
			));
			$this->execute('INSERT INTO {{nfy_channels2}} (id,name,levels,categories,message_template) SELECT id,name,level,category,message_template FROM {{nfy_channels}}');
			$this->dropTable('{{nfy_channels}}');
			$this->renameTable('{{nfy_channels2}}','{{nfy_channels}}');

			$this->createTable('{{nfy_subscriptions2}}', array(
				'id'=>'pk',
				'channel_id'=>'integer not null CONSTRAINT {{nfy_subscriptions}}_channel_id_fkey REFERENCES {{nfy_channels}}(id) ON DELETE CASCADE ON UPDATE CASCADE',
				'user_id'=>$userPkType.' not null CONSTRAINT {{nfy_subscriptions}}_user_id_fkey REFERENCES '.$userTable.'('.$userPk.') ON DELETE CASCADE ON UPDATE CASCADE',
				'transports'=>'text',
				'registered_on' => 'timestamp',
			));
			$this->execute('INSERT INTO {{nfy_subscriptions2}} (id,channel_id,user_id,transports,registered_on) SELECT id,channel_id,user_id,push_transports,registered_on FROM {{nfy_subscriptions}}');
			$this->dropTable('{{nfy_subscriptions}}');
			$this->renameTable('{{nfy_subscriptions2}}','{{nfy_subscriptions}}');
		} else {
			$this->delete('{{nfy_messages}}');
			$this->addColumn('{{nfy_messages}}', 'user_id', $userPkType.' not null');
			$this->addForeignKey('{{nfy_messages}}_user_id_fkey', '{{nfy_messages}}', 'user_id', $userTable, $userPk, 'CASCADE', 'CASCADE');

			$this->renameColumn('{{nfy_channels}}', 'level', 'levels');
			$this->renameColumn('{{nfy_channels}}', 'category', 'categories');

			$this->dropColumn('{{nfy_channels}}', 'criteria_callback');
			$this->addColumn('{{nfy_channels}}', 'enabled', 'boolean not null default true');
			$this->addColumn('{{nfy_channels}}', 'route_class', "varchar(128) not null default 'NfyDbRoute'");

			$this->renameColumn('{{nfy_subscriptions}}', 'push_transports', 'transports');
		}
		$this->createIndex('{{nfy_messages}}_user_id_idx', '{{nfy_messages}}', 'user_id');

		$this->addColumn('{{nfy_queues}}', 'message', "text");
	}

	public function safeDown()
	{
		$driver = $this->dbConnection->getDriverName();

		if ($driver=='sqlite') {
			$this->createTable('{{nfy_subscriptions2}}', array(
				'id'=>'pk',
				'channel_id'=>'integer not null CONSTRAINT {{nfy_subscriptions}}_channel_id_fkey REFERENCES {{nfy_channels}}(id) ON DELETE CASCADE ON UPDATE CASCADE',
				'user_id'=>$userPkType.' not null CONSTRAINT {{nfy_subscriptions}}_user_id_fkey REFERENCES '.$userTable.'('.$userPk.') ON DELETE CASCADE ON UPDATE CASCADE',
				'push_transports'=>'text',
				'registered_on' => 'timestamp',
			));
			$this->execute('INSERT INTO {{nfy_subscriptions2}} (id,channel_id,user_id,push_transports,registered_on) SELECT id,channel_id,user_id,transports,registered_on FROM {{nfy_subscriptions}}');
			$this->dropTable('{{nfy_subscriptions}}');
			$this->renameTable('{{nfy_subscriptions2}}','{{nfy_subscriptions}}');

			$this->createTable('{{nfy_channels2}}', array(
				'id'=>'pk',
				'name'=>'varchar(128) not null',
				'level'=>'varchar(128)',
				'category'=>'varchar(128)',
				'criteria_callback'=>'text',
				'message_template'=>'text',
			));
			$this->execute('INSERT INTO {{nfy_channels2}} (id,name,level,category,message_template) SELECT id,name,levels,categories,message_template FROM {{nfy_channels}}');
			$this->dropTable('{{nfy_channels}}');
			$this->renameTable('{{nfy_channels2}}','{{nfy_channels}}');
		} else {
			$this->renameColumn('{{nfy_subscriptions}}', 'transports', 'push_transports');

			$this->dropColumn('{{nfy_channels}}', 'route_class');
			$this->dropColumn('{{nfy_channels}}', 'enabled');
			$this->addColumn('{{nfy_channels}}', 'criteria_callback', 'text');

			$this->renameColumn('{{nfy_channels}}', 'categories', 'category');
			$this->renameColumn('{{nfy_channels}}', 'levels', 'level');
		}

		$this->dropColumn('{{nfy_queues}}', 'message');

		$this->dropColumn('{{nfy_messages}}', 'user_id');
	}
}
