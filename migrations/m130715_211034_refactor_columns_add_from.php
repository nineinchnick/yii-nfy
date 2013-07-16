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

		$this->delete('{{nfy_messages}}');
		$this->addColumn('{{nfy_messages}}', 'user_id', $userPkType.' not null');
		$this->addForeignKey('{{nfy_messages}}_user_id_fkey', '{{nfy_messages}}', 'user_id', $userTable, $userPk, 'CASCADE', 'CASCADE');

		$this->renameColumn('{{nfy_channels}}', 'level', 'levels');
		$this->renameColumn('{{nfy_channels}}', 'category', 'categories');
		$this->dropColumn('{{nfy_channels}}', 'criteria_callback');
		$this->addColumn('{{nfy_channels}}', 'enabled', 'boolean not null default true');
		$this->addColumn('{{nfy_channels}}', 'route_class', "varchar(128) not null default 'NfyDbRoute'");
		$this->addColumn('{{nfy_queues}}', 'message', "text");

		$this->renameColumn('{{nfy_subscriptions}}', 'push_transports', 'transports');
	}

	public function safeDown()
	{
		$this->renameColumn('{{nfy_subscriptions}}', 'transports', 'push_transports');

		$this->dropColumn('{{nfy_queues}}', 'message');
		$this->dropColumn('{{nfy_channels}}', 'route_class');
		$this->dropColumn('{{nfy_channels}}', 'enabled');
		$this->addColumn('{{nfy_channels}}', 'criteria_callback', 'text');

		$this->renameColumn('{{nfy_channels}}', 'categories', 'category');
		$this->renameColumn('{{nfy_channels}}', 'levels', 'level');

		$this->dropColumn('{{nfy_messages}}', 'user_id');
	}
}
