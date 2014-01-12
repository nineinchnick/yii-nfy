<?php

/**
 * Saves sent messages and tracks subscriptions using a redis component.
 */
class NfyRedisQueue extends NfyQueue
{
	const SUBSCRIPTIONS_HASH = ':subscriptions';
	const SUBSCRIPTION_LIST_PREFIX = ':subscription:';
	/**
	 * @var string|NfyRedisConnection Name or a redis connection component to use as storage.
	 */
	public $redis = 'redis';
	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
		if (is_string($this->redis)) {
			$this->redis = Yii::app()->getComponent($this->redis);
		} elseif (is_array($this->redis)) {
		}
		if (!($this->redis instanceof NfyRedisConnection)) {
			throw new CException('The redis property must contain a name or a valid NfyRedisConnection component.');
		}
	}
	/**
	 * Creates an instance of NfyMessage model. The passed message body may be modified, @see formatMessage().
	 * This method may be overriden in extending classes.
	 * @param string $body message body
	 * @return NfyMessage
	 */
	protected function createMessage($body)
	{
		$now = new DateTime('now', new DateTimezone('UTC'));
		$message = new NfyMessage;
		$message->setAttributes(array(
			'created_on'	=> $now->format('Y-m-d H:i:s'),
			'sender_id'		=> Yii::app()->hasComponent('user') ? Yii::app()->user->getId() : null,
			'body'			=> $body,
		));
		return $this->formatMessage($message);
	}

	/**
	 * Formats the body of a queue message. This method may be overriden in extending classes.
	 * @param NfyMessage $message
	 * @return NfyMessage $message
	 */
	protected function formatMessage($message)
	{
		return $message;
	}

	/**
	 * @inheritdoc
	 */
	public function send($message, $category=null) {
		$queueMessage = $this->createMessage($message);

        if ($this->beforeSend($queueMessage) !== true) {
			Yii::log(Yii::t('NfyModule.app', "Not sending message '{msg}' to queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_INFO, 'nfy');
            return;
        }

		$subscriptions = $this->redis->hvals($this->id.self::SUBSCRIPTIONS_HASH);

		$this->redis->multi();

		$this->redis->rpush($this->id, serialize($queueMessage));

		foreach($subscriptions as $rawSubscription) {
			$subscription = unserialize($rawSubscription);
			if ($category !== null && !$subscription->matchCategory($category)) {
				continue;
			}
			$subscriptionMessage = clone $queueMessage;
			$subscriptionMessage->message_id = $queueMessage->id;
            if ($this->beforeSendSubscription($subscriptionMessage, $subscription->subscriber_id) !== true) {
                continue;
            }

			$this->redis->rpush($this->id.self::SUBSCRIPTION_LIST_PREFIX.$subscription->subscriber_id, serialize($subscriptionMessage));
            
            $this->afterSendSubscription($subscriptionMessage, $subscription->subscriber_id);
		}

        $this->afterSend($queueMessage);

		Yii::log(Yii::t('NfyModule.app', "Sent message '{msg}' to queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_INFO, 'nfy');
	}

	/**
	 * @inheritdoc
	 */
	public function peek($subscriber_id=null, $limit=-1, $status=NfyMessage::AVAILABLE)
	{
		$list_id = $this->id.($subscriber_id === null ? '' : self::SUBSCRIPTION_LIST_PREFIX.$subscriber_id);
		$messages = array();
		foreach($this->redis->lrange($list_id, 0, $limit) as $rawMessage) {
			$messages[] = unserialize($rawMessage);
		}
		return $messages;
	}

	/**
	 * @inheritdoc
	 */
	public function reserve($subscriber_id=null, $limit=-1)
	{
		throw new CException('Not implemented. Redis queues does not support reserving messages. Use the receive() method.');
	}

	/**
	 * @inheritdoc
	 */
	public function receive($subscriber_id=null, $limit=-1)
	{
		$list_id = $this->id.($subscriber_id === null ? '' : self::SUBSCRIPTION_LIST_PREFIX.$subscriber_id);
		$messages = array();
		$count = 0;
		while (($limit == -1 || $count < $limit) && ($message=$this->redis->lpop($list_id)) !== null) {
			$messages[] = unserialize($message);
			$count++;
		}

		return $messages;
	}

	/**
	 * @inheritdoc
	 */
	public function delete($message_id, $subscriber_id=null)
	{
		throw new CException('Not implemented. Redis queues does not support reserving messages.');
	}

	/**
	 * @inheritdoc
	 */
	public function release($message_id, $subscriber_id=null)
	{
		throw new CException('Not implemented. Redis queues does not support reserving messages.');
	}

	/**
	 * @inheritdoc
	 */
	public function subscribe($subscriber_id, $label=null, $categories=null, $exceptions=null)
	{
		$now = new DateTime('now', new DateTimezone('UTC'));
		$subscription = new NfySubscription;
		$subscription->setAttributes(array(
			'subscriber_id'=>$subscriber_id,
			'label'=>$label,
			'categories'=>$categories,
			'exceptions'=>$exceptions,
			'created_on'=>$now->format('Y-m-d H:i:s'),
		));
		$this->redis->hset($this->id.self::SUBSCRIPTIONS_HASH, $subscriber_id, serialize($subscription));
	}

	/**
	 * @inheritdoc
	 */
	public function unsubscribe($subscriber_id, $permanent=true)
	{
		$this->redis->hdel($this->id.self::SUBSCRIPTIONS_HASH, $subscriber_id);
	}

	/**
	 * @inheritdoc
	 */
	public function isSubscribed($subscriber_id)
	{
		return $this->redis->hexists($this->id.self::SUBSCRIPTIONS_HASH, $subscriber_id);
	}
}
