# Notifications

This is a module for [Yii framework](http://www.yiiframework.com/) that provides:

* a configurable way to send various notifications, messages and tasks to a queue
* a basic widget to read such items from queue and display them to the user.

Messages could be passed directly as strings or created from two versions of a model, before and after updating its attributes. The created message could depend on:

* Model class
* Attributes modified
* Values before and after modification

As a side effect, this could be used to log all changes to the models, exactly like the [audittrail2](http://www.yiiframework.com/extension/audittrail2) extension.

Message filtering and creation logic is grouped into _channels_. Message recipients are selected by subscribing them to those channels.

A simple SQL queue implementation is provided if a MQ server is not available or not necessary.

## Components

### Logger

Delivers messages to queues from subscriptions to channels matching criteria.

Subscriptions specify which transport should be used for push notifications.

A simple database based queue implementation is provided, but could be replaced by a MQ backend.

### Web interface

A widget displaying (html5) notifications from queues using polling or push.

## Installation

Download and extract.

Enable module in configuration. See the configuration section how to specify users table name and its primary key type.

Import module classes from:
	'application.modules.nfy.components.Nfy',
	'application.modules.nfy.models.*',

Apply migrations:

~~~
./yiic migrate --migrationPath=nfy.migrations
~~~

Create some channels and subscribe users to them.

## Configuration

Users table name and primary key type.

Define extra push transports.

## Usage examples

### Minimal setup

~~~php
// initialize module's configuration, this should be done via migrations or CRUD
// create a channel with a basic filter and template
$channel = new NfyChannels;
$channel->name = 'test';
$channel->criteria_callback = 'Test::match';
$channel->message_template = 'Attribute changed from {old.attribute} to {new.attribute}';
$channel->save();
$subscription = new NfySubscriptions;
$subscription->user_id = Yii::app()->user->getId();
$subscription->channel_id = $channel->id;
$subscription->save();
~~~

### Criteria callback

~~~php
// this could be your CActiveRecord model
class Test extends CActiveRecord {
	... // CActiveRecord requires some methods, this is skipped for keeping this short
	public static function match($data) {
		return $data['new']->attribute == 'value';
	}
	public function afterFind($event){
		$this->_old($this->getOwner()->getAttributes());
		return parent::afterFind($event);
	}
	public function afterSave($event) {
		$old = clone $this;
		$old->setAttributes($this->_old);
		Nfy::log(array('old'=>$old,'new'=>$this));
		return parent::afterSave($event);
	}
}

// trigger logging
$test = Test::model()->find();
$test->attribute = 'value';
$test->save();
// now the table nfy_queues contains a logged message
~~~

### Push notifications

## Todo

* Configure (CLogger,) CLogRouter and CLogRoute(s) and use them as keys for push_transport column, including the db logger, rename that column
* Add from_user_id to queue table
* Create a CRUD to manage channels and subscriptions
* Add an example of a broadcast message to all users
* Document how to display a translated message and a full changes summary using serialized msg
* Document why one MQ backend is better than a DB with other (mail, jabber) backends (faster, delivery time is constant and shouldn't block user action) - checkout vNotifier extension
* Provide a behavior similar to audit trail extension to plug in CActiveRecord
* Implement restrictions which channels are available for users to subscribe to.
