<?php

/**
 * Saves sent messages and tracks subscriptions using a redis component.
 *
 * When in non-blocking (default) mode, subscriptions are tracked in one hash
 * and their message delivery is handled by creating an extra list for every subscription.
 *
 * In blocking mode, subscriptions are handled by using SUBSCRIBE/UNSUBSCRIBE commands and message are sent
 * using PUBLISH command instead of using separate lists.
 * Peeking and locking is disabled and the receive() method becomes blocking.
 * Before/after send subscription events are not raised.
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

		if ($this->blocking) {
			$this->redis->publish($category, serialize($queueMessage));
		} else {
			$this->sendToList($queueMessage, $category);
		}

        $this->afterSend($queueMessage);

		Yii::log(Yii::t('NfyModule.app', "Sent message '{msg}' to queue {queue_label}.", array('{msg}' => $queueMessage->body, '{queue_label}' => $this->label)), CLogger::LEVEL_INFO, 'nfy');
	}

	private function sendToList($queueMessage, $category)
	{
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

		$this->redis->exec();
	}

	/**
	 * @inheritdoc
	 */
	public function peek($subscriber_id=null, $limit=-1, $status=NfyMessage::AVAILABLE)
	{
		if ($this->blocking) {
			throw new CException('Not supported. When in blocking mode peeking is not available. Use the receive() method.');
		}
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
		if ($this->blocking) {
			throw new CException('Not supported. When in blocking mode peeking is not available. Use the receive() method.');
		}
		throw new CException('Not implemented. Redis queues does not support reserving messages. Use the receive() method.');
	}

	/**
	 * @inheritdoc
	 */
	public function receive($subscriber_id=null, $limit=-1)
	{
		$messages = array();
		$count = 0;
		if ($this->blocking) {
			$response = $this->redis->parseResponse('', true);
			if (is_array($response)) {
				$type = array_shift($reponse);
				if ($type == 'message') {
					$channel = array_shift($response);
					$message = array_shift($response);
				} elseif ($type == 'pmessage') {
					$pattern = array_shift($response);
					$channel = array_shift($response);
					$message = array_shift($response);
				}
				if (isset($message)) {
					$messages[] = $message;
				}
			}
			return $messages;
		}
		$list_id = $this->id.($subscriber_id === null ? '' : self::SUBSCRIPTION_LIST_PREFIX.$subscriber_id);
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
		if ($this->blocking) {
			if ($exceptions !== null) {
				throw new CException('Not supported. Redis queues does not support pattern exceptions in blocking (pubsub) mode.');
			}
			foreach($categories as $category) {
				if (($c=rtrim($category,'*'))!==$category) {
					$this->redis->psubscribe($category);
				} else {
					$this->redis->subscribe($category);
				}
			}
			return;
		}
		$now = new DateTime('now', new DateTimezone('UTC'));
		$subscription = new NfySubscription;
		$subscription->setAttributes(array(
			'subscriber_id'=>$subscriber_id,
			'label'=>$label,
			'categories'=>$categories,
			'exceptions'=>$exceptions !== null ? $exceptions : array(),
			'created_on'=>$now->format('Y-m-d H:i:s'),
		));
		$this->redis->hset($this->id.self::SUBSCRIPTIONS_HASH, $subscriber_id, serialize($subscription));
	}

	/**
	 * @inheritdoc
	 */
	public function unsubscribe($subscriber_id, $permanent=true)
	{
		if ($this->blocking) {
			$this->redis->punsubscribe();
			$this->redis->unsubscribe();
			return;
		}
		$this->redis->hdel($this->id.self::SUBSCRIPTIONS_HASH, $subscriber_id);
	}

	/**
	 * @inheritdoc
	 */
	public function isSubscribed($subscriber_id)
	{
		if ($this->blocking) {
			throw new CException('Not supported. In blocking mode it is not possible to track subscribers.');
			return;
		}
		return $this->redis->hexists($this->id.self::SUBSCRIPTIONS_HASH, $subscriber_id);
	}

	/**
	 * @inheritdoc
	 */
	public function getSubscriptions($subscriber_id=null)
	{
		$subscriptions = array();
		foreach($this->redis->hvals($this->id.self::SUBSCRIPTIONS_HASH) as $rawSubscription) {
			$subscriptions[] = unserialize($rawSubscription);
		}
		return $subscriptions;
	}
}
