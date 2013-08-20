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

A message is sent by calling Nfy::log method. Then, for each enabled channel:

* Channel's route class (NfyDbRoute) determines if message can be sent
* The message is filtered, creating the final string
* For each of channel's subscriptions, route determines if message can be delivered to subscribing user
* For each transport enabled for that subscription the message is delivered
* If the NfyDbRoute saved a messaged in a database queue, it could be displayed as a web notification by polling that queue

### Nfy

A simple helper class loading enabled channels, filtering message using levels and categories and handling processing to the channel's route.

This is an equivalent of a CLogger.

### NfyDbRoute

This class defines message delivery criteria, message formatting and implements transports. By default, it saves messages to a database queue.

It can be extended to implement other delivery methods, such as a Message Queue.

### WebNotifications

A widget displaying (html5) notifications from queues using ajax polling or reading from a web socket.

## Installation

Download and extract.

Enable module in configuration. Do it in both main and console configs, because some settings are used in migrations. See the configuration section how to specify users table name and its primary key type.

Import module classes from:
	'application.modules.nfy.components.Nfy',
	'application.modules.nfy.models.*',

Apply migrations:

~~~
./yiic migrate --migrationPath=nfy.migrations
~~~

Create some channels and subscribe users to them.

## Configuration

### Module parameters

By specifying the users model class name in the _userClass_ property proper table name and primary key column name will be used in migrations.

### Minimal setup

Provide an extended version of NfyDbRoute (called MyDbRoute in the example below) to implement custom message filtering/formatting.

~~~php
// initialize module's configuration, this should be done via migrations or CRUD
// create a channel with a basic filter and template
$channel = new NfyChannels;
$channel->name = 'test';
$channel->route_class = 'MyDbRoute';
$channel->message_template = 'Attribute changed from {old.attribute} to {new.attribute}';
$channel->save();
$subscription = new NfySubscriptions;
$subscription->user_id = Yii::app()->user->getId();
$subscription->channel_id = $channel->id;
$subscription->save();
~~~

## Usage examples

### Broadcasting

Send a message to every user.

First create a default channel and subscribe every user to it. Then just call Nfy::log().

### Notifying model changes

Monitor one model for changes and notify some users.

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

### Display notifications

Put anywhere in your layout or views or controller.

~~~
[php]
$this->widget('nfy.extensions.webNotifications.WebNotifications', array('url'=>$this->createUrl('/nfy/default/poll')));
~~~

### Using together with Pusher

Instead of ussing Nfy::log, publish messages directly to [Pusher.com](http://pusher.com/) service, using [pusher](http://www.yiiframework.com/extension/pusher) extension:

~~~
[php]
	$pusher = Yii::createComponent(array(
		'class' => 'Pusher',
		'key' => 'XXX',
		'secret' => 'YYY',
		'appId' => 'ZZZ',
	));
	$pusher->trigger('test_channel','newMessage',array('title'=>'nfy title', 'body'=>'test message'));
~~~

Configure the WebNotifications widget to receive messages through a web socket:

~~~
[php]
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
			if (typeof data.title != 'undefined' && typeof data.body != 'undefined') {
				notificationsPoller.addMessage(data);
				notificationsPoller.display();
			}
		};}',
	),
)); ?>
~~~

The drawback is that when the page is refreshed it temporarily disconnects from Pusher. So if you send any messages while processing a request from an only opened page instance it will not receive any messages.

The example mostly shows how to use web sockets and a different delivery method than Nfy::log().

## Usage scenarios

### Delivering messages

Remember, that when a message is being published that process should be fast and shouldn't distrupt the normal HTTP request. If it requires contacting a remote host, it is prefferable to deliver a message to a local queue or service and let some other process handle it further.

#### Simple database queue

Implementing queues in a RDBMS could be considered an anti-pattern but if its for a low volume traffic it's an acceptable solution because it doesn't require any external components or services.

By using the Nfy component and NfyDbRoute class

* Publish a message by calling Nfy::log()
* Conditions for a channel (channels table entry and corresponding route class) are checked
* The final text message is prepared based on logic contained in the NfyDbRoute class
* Message gets delivered (INSERTed) to queues (tables) for each user subscription (subscriptions table entry)
* When checking for new messages, they are dequeued (UPDATEd)

#### Emailing

Same as above, the NfyDbRoute class could be used for message creation and filtering. The only difference is that is it sent as an email instead of put into a database queue.

#### Message queue

By using an external service, like a message queue server

* Publish a message
* The service takes care of delivering it to recipients who subscribed to a specific channel

#### Proxy

When using slow and/or unreliable transports, like sending to a remove host that could be offline, to avoid interrupting the processing of a normal HTTP request you could do the following:

* Publish the message to either the database or message queue
* Have another process in the background read from the queue and deliver it further

### Receiving messages

By configuring the WebNotifications widget messages could be read by:

* polling using ajax (repeating requests at fixed interval) an action that checks a queue and returns new items
* connect to a web socket and wait for new items

## Changelog

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

## Todo

* Create a CRUD to manage channels and subscriptions and implement restrictions which channels are available for users to subscribe to.
* Provide a behavior similar to audit trail extension to plug in CActiveRecord
* Add two more transports to the NfyDbRoute: XMPP (jabber) and SMTP (email)
