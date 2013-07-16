<?php

/**
 * A default message route saving messages to queues for each subscription of a channel.
 * Messages and queues are tables in a database.
 * Queues can be polled later to display messages using web notifications.
 *
 * This class could be extended adding more optional transports, such as XMPP or email delivery.
 */
class NfyDbRoute extends CComponent implements INfyRoute {
	/**
	 * @var string Template containing strings like {old.attribute} and {new.attribute}.
	 * If message passed to this route is an array holding two model version, this template
	 * will be used to create the text message.
	 */
	public $message_template;

	/**
	 * @var string A cache for processed message body.
	 */
	protected $_message;

	/**
	 * Determines if this route can deliver messages originating from specified user.
	 * @param mixed $user_id
	 * @param mixed $msg
	 * @return boolean
	 */
	public function canSend($user_id, $msg) {
		return true;
	}

	/**
	 * Determines if message can be delivered to specified user.
	 * @param mixed $user_id
	 * @param mixed $msg
	 * @return boolean
	 */
	public function canReceive($user_id, $msg) {
		return true;
	}

	/**
	 * Returns available transports as name=>label.
	 * The default db transport saves messages to a database queue
	 * so they can be polled later and displayed as web notifications.
	 * @return array
	 */
	public function getTransports() {
		return array(
			'db' => Yii::t('NfyModule.app', 'Web notification'),
		);
	}

	/**
	 * @param string $transport name of user selected transport
	 * @param NfyMessages $message provides the default message body
	 * @param NfySubscription $subscription provides the receiving user
	 * @param mixed $msg used create a customized message for the receiving user
	 */
	protected function deliver($transport, $message, $subscription, $msg) {
		switch($transport) {
			default:
			case 'db':
				$queue = new NfyQueues;
				$queue->subscription_id = $subscription->id;
				$queue->message_id = $message->id;
				$queue->message = $this->formatLogMessage($msg, $message->user_id, $subscription->user_id);
				if (!$queue->save()) {
					Yii::log(Yii::t('NfyModule.app', 'Failed to send notification {message_id} to user {user_id} via subscription {subscription_id}.', array('{message_id}' => $message->id, '{user_id}' => $subscription->user_id, '{subscription_id}' => $subscription->id)), 'error', 'nfy');
				}
				break;
		}
	}

	/**
	 * Processes and delivers the message to users subscribed to calling channel.
	 * Message can be passed as string or and array of two CModel objects under 'old' and 'new' keys,
	 * which will be transformed into a message using configured message template.
	 *
	 * @param mixed $msg a string treated as verbatim message or an array containing CModel objects under 'old' and 'new' keys
	 * @param integer $channel_id required to associate message with originating channel
	 * @param array array of NfySubscriptions models specifying recipients of message
	 */
	public function process($msg, $channel_id, array $subscriptions) {
		if (!$this->canSend(Yii::app()->user->getId(), $msg))
			return;

		$message = new NfyMessages;
		$message->channel_id = $channel_id;
		$message->user_id = Yii::app()->user->getId();
		$message->logtime = date('Y-m-d H:i:s');
		$message->message = $this->formatLogMessage($msg, $message->user_id);
		if (!$message->save()) {
			Yii::log(Yii::t('NfyModule.app', "Failed to save message '{msg}' for channel {channel_id}.", array('{msg}' => $message->message, '{channel_id}' => $channel_id)), 'error', 'nfy');
		}

		$availableTransports = $this->getTransports();
		$availableTransports = array_keys($availableTransports);
		foreach($subscriptions as $subscription) {
			if (!$this->canReceive($subscription->user_id, $msg))
				continue;

			$transports=preg_split('/[\s,]+/',strtolower($subscription->transports),-1,PREG_SPLIT_NO_EMPTY);
			foreach($transports as $transport) {
				if (!in_array($transport, $availableTransports)) {
					Yii::log(Yii::t('NfyModule.app', "Unknown transport {transport} in channel {channel_id}.", array('{transport}' => $transport, '{channel_id}' => $channel_id)), 'warning', 'nfy');
					continue;
				}

				$this->deliver($transport, $message, $subscription, $msg);
			}
		}
	}

	/**
	 * If $msg is an array, a string message will be created using the $message_template property.
	 * Message body could depend on sending or receiving user. If receiving user is not specified, 
	 * a general message to all users should be prepared. If it is not null, either return null indicating
	 * that all users gets same message or return a customized message for that user.
	 *
	 * @param string $msg
	 * @param mixed $sending_user_id
	 * @param mixed $receiving_user_id if null, a default message will be returned, otherwise a customized version could be created
	 * @return string if null that means receiving_user_id was not null but there is no customized message, use the default one
	 */
	protected function formatLogMessage($msg, $sending_user_id, $receiving_user_id = null) {
		if ($receiving_user_id !== null)
			return null;
		// this is not necessary in this class, but is provided as an example for extending classes
		if ($this->_message !== null)
			return $this->_message;
		// create message using templates
		if (is_array($msg)) {
			// create tokens from old and new models' attributes
			$tokens = array();
			$values = array();
			foreach($msg['old']->getAttributes() as $attribute=>$value) {
				$tokens[] = "{old.$attribute}";
				$values[] = $value;
			}
			foreach($msg['new']->getAttributes() as $attribute=>$value) {
				$tokens[] = "{new.$attribute}";
				$values[] = $value;
			}
			if ($this->message_template === null) {
				$msg = serialize(array_combine($tokens, $values));
			} else {
				$msg = str_replace($tokens, $values, $this->message_template);
			}
		}
		$this->_message = $msg;
		return $msg;
	}
}
