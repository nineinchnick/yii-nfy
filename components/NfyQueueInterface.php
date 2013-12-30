<?php

interface NfyQueueInterface
{
	const GET_PEEK = 0;
	const GET_LOCK = 1;
	const GET_DELETE = 2;

	/**
	 * Determines if message can be sent.
	 * The default implementation should raise the {@link onBeforeSend} event.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @param mixed $message the actual format depends on the implementation
	 * @return boolean
	 */
    public function beforeSend($message);
	/**
	 * Called after sending the message. 
	 * The default implementation should raise the {@link onAfterSend} event.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @param mixed $message the actual format depends on the implementation
	 */
    public function afterSend($message);
	/**
	 * Determines if message can be sent to specified subscription.
	 * The default implementation should raise the {@link onBeforeSendSubscription} event.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @param mixed $message the actual format depends on the implementation
	 * @param mixed $subscriber_id the actual format depends on the implementation
	 * @return boolean
	 */
    public function beforeSendSubscription($message, $subscriber_id);
	/**
	 * Called after sending the message to a subscription. 
	 * The default implementation should raise the {@link onAfterSendSubscription} event.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @param mixed $message the actual format depends on the implementation
	 * @param mixed $subscriber_id the actual format depends on the implementation
	 */
    public function afterSendSubscription($message, $subscriber_id);
	/**
	 * Sends message to the queue. If there are any subscriptions, it will be delivered to those matching specified category.
	 *
	 * @param mixed $message the actual format depends on the implementation
	 * @param string $category category of the message (e.g. 'system.web'). It is case-insensitive.
	 */
	public function send($message, $category=null);
	/**
	 * Gets messages from the queue, but neither lockes or removes them.
	 * Messages are sorted by date and time of creation.
	 * @param mixed $subscriber_id the actual format depends on the implementation
	 * @param integer $limit if null, all available messages are fetched from the queue
	 * @param integer|array $status allows peeking at locked or removed messages (not yet permanently)
	 * @return array of NfyMessage objects
	 */
	public function peek($subscriber_id=null, $limit=null, $status=NfyMessage::AVAILABLE);
	/**
	 * Gets available messages from the queue and lockes them. Unless they are deleted, they will unlock after a specific amount of time.
	 * @param mixed $subscriber_id the actual format depends on the implementation
	 * @param integer $limit if null, all available messages are fetched from the queue
	 * @return array of NfyMessage objects
	 */
	public function reserve($subscriber_id=null, $limit=null);
	/**
	 * Gets available messages from the queue and removes them from the queue.
	 * @param mixed $subscriber_id the actual format depends on the implementation
	 * @param integer $limit if null, all available messages are fetched from the queue
	 * @return array of NfyMessage objects
	 */
	public function receive($subscriber_id=null, $limit=null);
	/**
	 * Deletes locked messages from the queue.
	 * @param integer|array $message_id one or many message ids
	 * @param mixed $subscriber_id if not null, only this subscriber's messages will be affected, the actual format depends on the implementation
	 * @return integer|array one or more ids of deleted message, some could have timed out and had been unlocked automatically
	 */
	public function delete($message_id, $subscriber_id=null);
	/**
	 * Unlocks locked messages.
	 * @param integer|array $message_id one or many message ids
	 * @param mixed $subscriber_id if not null, only this subscriber's messages will be affected, the actual format depends on the implementation
	 * @return integer|array one or more ids of unlocked message, some could have timed out and had been unlocked automatically
	 */
	public function unlock($message_id, $subscriber_id=null);
	/**
	 * Subscribes a recipient to this queue. If categories are specified,
	 * only matching messages will be delivered.
	 * @param mixed $subscriber_id the actual format depends on the implementation
	 * @param array $categories list of categories of messages (e.g. 'system.web') that should be delivered to this subscription
	 * @param array $exceptions list of categories of messages (e.g. 'system.web') that should NOT be delivered to this subscription
	 */
	public function subscribe($subscriber_id, $categories=null, $exceptions=null);
	/**
	 * Unsubscribes a recipient from this queue.
	 * @param mixed $subscriber_id the actual format depends on the implementation
	 */
	public function unsubscribe($subscriber_id);
	/**
	 * Checkes if recipient is subscribed to this queue.
	 * @param mixed $subscriber_id the actual format depends on the implementation
	 * @return boolean
	 */
	public function isSubscribed($subscriber_id);
}
