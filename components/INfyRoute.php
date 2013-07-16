<?php

interface INfyRoute {
	/**
	 * Determines if this route can deliver messages originating from specified user.
	 * @param mixed $user_id
	 * @param mixed $msg
	 * @return boolean
	 */
	public function canSend($user_id, $msg);
	/**
	 * Determines if message can be delivered to specified user.
	 * @param mixed $user_id
	 * @param mixed $msg
	 * @return boolean
	 */
	public function canReceive($user_id, $msg);
	/**
	 * Returns available transports as name=>label.
	 * The default db transport saves messages to a database queue
	 * so they can be polled later and displayed as web notifications.
	 * @return array
	 */
	public function getTransports();
	/**
	 * Processes and delivers the message to users subscribed to calling channel.
	 * Message can be passed as string or and array of two CModel objects under 'old' and 'new' keys,
	 * which will be transformed into a message using configured message template.
	 *
	 * @param mixed $msg a string treated as verbatim message or an array containing CModel objects under 'old' and 'new' keys
	 * @param integer $channel_id required to associate message with originating channel
	 * @param array array of NfySubscriptions models specifying recipients of message
	 */
	public function process($msg, $channel_id, array $subscriptions);
}
