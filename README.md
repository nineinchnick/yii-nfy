# Notifications

This is a module for [Yii framework](http://www.yiiframework.com/) that provides:

* a generic queue component
* a Publish/Subscribe message delivery pattern
* a SQL database queue implementation
* a configurable way to send various notifications, messages and tasks to a queue
* a basic widget to read such items from queue and display them to the user as system notifications
* a basic widget to put in a navbar that displays notifications and/or messages in a popup
* a basic CRUD to manage and/or debug queues or use as a simple messanger

Messages could be passed directly as strings or created from some objects, like Active Records. This could be used to log all changes to the models, exactly like the [audittrail2](http://www.yiiframework.com/extension/audittrail2) extension.

When recipients are subscribed to a channel, message delivery can depend on category filtering, much like in logging system provided by the framework.

A simple SQL queue implementation is provided if a MQ server is not available or not necessary.

## Installation

Download and extract.

Enable module in configuration. Do it in both main and console configs, because some settings are used in migrations. See the configuration section how to specify users table name and its primary key type.

Copy migrations to your migrations folder and adjust dates in file and class names. Then apply migrations:

~~~
./yiic migrate
~~~

Define some queues as application components and optionally enable the module, see the next section.

## Configuration

### Queue components

Define each queue as an application component.

~~~php
'components' => array(
	'queue' => array(
		'class' => 'nfy.components.NfyDbQueue',
		'name' => 'Notifications',
		'timeout' => 30,
	),
	// ...
),
~~~

Then you can send and receive messages through this component:

~~~php
// send one message 'test'
Yii::app()->queue->send('test');
// receive all available messages without using subscriptions and immediately delete them from the queue
$messages = $queue->receive();
~~~

Or you could subscribe some users to it:

~~~php
Yii::app()->queue->subscribe(Yii:app()->user->getId());
// send one message 'test'
Yii::app()->queue->send('test');
// receive all available messages for current user and immediately delete them from the queue
$messages = $queue->receive(Yii:app()->user->getId());
// if there are any other users subscribed, they will receive the message independently
~~~

### Module parameters

By specifying the users model class name in the _userClass_ property proper table name and primary key column name will be used in migrations.

## Usage examples

### Broadcasting

To send a message to every user create a queue and just subscribe every user to it.

### Filtering

When subscribing a user to a queue a list of categories can be specified. Only messages with matching category will be delivered to this subscription. This system is modelled after logger from the framework.

### Notifying model changes

Monitor one model for changes and notify some users.

By extending the NfyDbQueue or NfyQueue classes you can handle messages that are not strings and create a string message body from such data.

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
		Yii::app()->queue->send(array('old'=>$old,'new'=>$this), 'logs.audit');
		return parent::afterSave($event);
	}
}

// trigger logging
$test = Test::model()->find();
$test->attribute = 'value';
$test->save();
~~~

### Display notifications

Put anywhere in your layout or views or controller.

~~~php
$this->widget('nfy.extensions.webNotifications.WebNotifications', array('url'=>$this->createUrl('/nfy/default/poll', array('id'=>'queueComponentId'))));
~~~

### Using together with Pusher

Instead of ussing NfyQueue, publish messages directly to [Pusher.com](http://pusher.com/) service, using [pusher](http://www.yiiframework.com/extension/pusher) extension:

~~~php
	$pusher = Yii::createComponent(array(
		'class' => 'Pusher',
		'key' => 'XXX',
		'secret' => 'YYY',
		'appId' => 'ZZZ',
	));
	$pusher->trigger('test_channel','newMessage',array('title'=>'nfy title', 'body'=>'test message'));
~~~

Configure the WebNotifications widget to receive messages through a web socket:

~~~php
<?php $this->widget('nfy.extensions.webNotifications.WebNotifications', array(
	'url'=>'ws://ws.pusherapp.com:80/app/XXXclient=javascript&protocol=6',
	'method'=>WebNotifications::METHOD_PUSH,
	'websocket'=>array(
		'onopen'=>'js:function(socket){return function(e) {
			socket.send(JSON.stringify({
				"event": "pusher:subscribe",
				"data": {"channel": "test_channel"}
			}));
		};}',
		'onmessage'=>'js:function(_socket){return function(e) {
			var message = JSON.parse(e.data);
			var data = JSON.parse(message.data);
			if (typeof data.title != "undefined" && typeof data.body != "undefined") {
				notificationsPoller.addMessage(data);
				notificationsPoller.display();
			}
		};}',
	),
)); ?>
~~~

The drawback is that when the page is refreshed it temporarily disconnects from Pusher. So if you send any messages while processing a request from an only opened page instance it will not receive any messages.

The example mostly shows how to use web sockets and a different delivery method than Nfy::log().

### Receiving messages

By configuring the WebNotifications widget messages could be read by:

* polling using ajax (repeating requests at fixed interval) an action that checks a queue and returns new items
* connect to a web socket and wait for new items

## Changelog

### 0.9 - 2013-12-28

__Warning!__ This version breaks backward compatibility. All database tables must be dropped and migrations has to be run again.

* Major refactoring to provide a generic queue component with improved interface.
* Rerwritten message filtering and subscription delivery.
* Support for reserving messages with a timeout.
* Basic CRUD to manage/debug queues or use as a simple messanger interface.

### 0.6.5 - 2013-08-20

* Fixed a typo in the default controller.
* Added support for web sockets in WebNotifications widget.
* More examples and updated docs.

### 0.6 - 2013-08-15

* Enabled long polling
* Fixes in migrations for SQLite

### 0.5.5 - 2013-08-12

* Specify transport in the NfyChannels::subscribe() method.
* Fill value of registered_on when saving NfySubscriptions model.

### 0.5 - 2013-07-16

Backward compatibility breaking:

* Added routes and default NfyDbRoute implementation, this required refactoring the database schema, so remember to apply migrations when upgrading. The 'criteria_callback' column has been removed and replaced by the 'canSend()' method of the NfyDbRoute class.

Other changes:

* Saving the sending user id, so it can be used to filter recipients and customize message body.
* Added userClass parameter to the module to extract user table name and its primary key column name and type.
* Updated the documentation.

### 0.3 - 2013-07-13

* Initial release

