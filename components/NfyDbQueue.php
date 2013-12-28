<?php

/**
 * Saves sent messages and tracks subscriptions in a database.
 */
class NfyDbQueue extends NfyQueue {
	/**
	 * Creates an instance of NfyMessage model. The passed message body may be modified, @see formatMessage().
	 * This method may be overriden in extending classes.
	 * @param string $body message body
	 * @return NfyMessage
	 */
	protected function createMessage($body)
	{
		$message = new NfyMessage;
		$message->setAttributes(array(
			'queue_name'	=> $this->name,
			'timeout'		=> $this->timeout,
			'sender_id'		=> Yii::app()->user->getId(),
			'status'		=> NfyMessage::AVAILABLE,
			'body'			=> $body,
		), false);
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
			Yii::log(Yii::t('NfyModule.app', "Not sending message '{msg}' to queue {queue_name}.", array('{msg}' => $queueMessage->body, '{queue_name}' => $this->name)), CLogger::LEVEL_INFO, 'nfy');
            return;
        }

		$success = true;

		$subscriptions = NfySubscription::model()->current()->withQueue($this->name)->matchingCategory($category)->findAll();
        
        $trx = $queueMessage->getDbConnection()->getCurrentTransaction() !== null ? null : $queueMessage->getDbConnection()->beginTransaction();
        
        if (empty($subscriptions) && !$queueMessage->save()) {
			Yii::log(Yii::t('NfyModule.app', "Failed to save message '{msg}' in queue {queue_name}.", array('{msg}' => $queueMessage->body, '{queue_name}' => $this->name)), CLogger::LEVEL_ERROR, 'nfy');
            return false;
        }

		foreach($subscriptions as $subscription) {
			$subscriptionMessage = clone $queueMessage;
			$subscriptionMessage->subscription_id = $subscription->id;
            if ($this->beforeSendSubscription($subscriptionMessage, $subscription->subscriber_id) !== true) {
                continue;
            }

			if (!$subscriptionMessage->save()) {
				Yii::log(Yii::t('NfyModule.app', 'Failed to save message {msg} in queue {queue_name} for the subscription {subscription_id}.', array(
					'{msg}' => $queueMessage->body,
					'{queue_name}' => $this->name,
					'{subscription_id}' => $subscription->id,
				)), CLogger::LEVEL_ERROR, 'nfy');
				$success = false;
			}
            
            $this->afterSendSubscription($subscriptionMessage, $subscription->subscriber_id);
		}

        $this->afterSend($queueMessage);

		if ($trx !== null) {
			$trx->commit();
		}

		Yii::log(Yii::t('NfyModule.app', "Sent message '{msg}' to queue {queue_name}.", array('{msg}' => $queueMessage->body, '{queue_name}' => $this->name)), CLogger::LEVEL_INFO, 'nfy');

		return $success;
	}

	/**
	 * @inheritdoc
	 */
	public function recv($subscriber_id=null, $limit=-1, $mode=self::GET_LOCK)
	{
		$pk = NfyMessage::model()->tableSchema->primaryKey;
		$trx = NfyMessage::model()->getDbConnection()->getCurrentTransaction() !== null || $mode === self::GET_PEEK ? null : NfyMessage::model()->getDbConnection()->beginTransaction();
		$messages = NfyMessage::model()->withQueue($this->name)->withSubscriber($subscriber_id)->available($this->timeout)->findAll(array('index'=>$pk, 'limit'=>$limit));
		if (!empty($messages)) {
			if ($mode === self::GET_DELETE) {
				$now = new DateTime('now', new DateTimezone('UTC'));
				NfyMessage::model()->updateByPk(array_keys($messages), array('status'=>NfyMessage::DELETED, 'deleted_on'=>$now->format('Y-m-d H:i:s')));
			} elseif ($mode === self::GET_LOCK) {
				$now = new DateTime('now', new DateTimezone('UTC'));
				NfyMessage::model()->updateByPk(array_keys($messages), array('status'=>NfyMessage::LOCKED, 'locked_on'=>$now->format('Y-m-d H:i:s')));
			}
		}
		if ($trx !== null) {
			$trx->commit();
		}
		return $messages;
	}

	/**
	 * @inheritdoc
	 */
	public function delete($message_id, $subscriber_id=null)
	{
        $trx = NfyMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyMessage::model()->getDbConnection()->beginTransaction();
		$pk = NfyMessage::model()->tableSchema->primaryKey;
		$messages = NfyMessage::model()->withQueue($this->name)->withSubscriber($subscriber_id)->locked($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		$now = new DateTime('now', new DateTimezone('UTC'));
		NfyMessage::model()->updateByPk($message_ids, array('status'=>NfyMessage::DELETED, 'deleted_on'=>$now->format('Y-m-d H:i:s')));
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}

	/**
	 * @inheritdoc
	 */
	public function unlock($message_id, $subscriber_id=null)
	{
        $trx = NfyMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyMessage::model()->getDbConnection()->beginTransaction();
		$pk = NfyMessage::model()->tableSchema->primaryKey;
		$messages = NfyMessage::model()->withQueue($this->name)->withSubscriber($subscriber_id)->locked($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		NfyMessage::model()->updateByPk($message_ids, array('status'=>NfyMessage::AVAILABLE));
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}

	/**
	 * @inheritdoc
	 */
	public function subscribe($subscriber_id, $categories=null, $exceptions=null)
	{
		$trx = NfySubscription::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfySubscription::model()->getDbConnection()->beginTransaction();
        $subscription = NfySubscription::model()->withQueue($this->name)->withSubscriber($subscriber_id)->find();
		if ($subscription === null) {
			$subscription = new NfySubscription;
			$subscription->setAttributes(array(
				'queue_name' => $this->name,
				'subscriber_id' => $subscriber_id,
			));
		} else {
			$subscription->is_deleted = 0;
			NfySubscriptionCategory::model()->deleteAllByAttributes(array('subscription_id'=>$subscription->primaryKey));
		}
		if (!$subscription->save())
			throw new CException(Yii::t('NfyModule.app', 'Failed to subscribe {subscriber_id} to {queue_name}', array('{subscriber_id}'=>$subscriber_id, '{queue_name}'=>$this->name)));
		$this->saveSubscriptionCategories($categories, $subscription->primaryKey, false);
		$this->saveSubscriptionCategories($exceptions, $subscription->primaryKey, true);
		if ($trx !== null) {
			$trx->commit();
		}
		return true;
	}

	protected function saveSubscriptionCategories($categories, $subscription_id, $are_exceptions=false)
	{
		if ($categories === null)
			return true;
		if (!is_array($categories))
			$categories = array($categories);
		foreach($categories as $category) {
			$subscriptionCategory = new NfySubscriptionCategory;
			$subscriptionCategory->setAttributes(array(
				'subscription_id'	=> $subscription_id,
				'category'			=> $category,
				'is_exception'		=> $are_exceptions ? 1 : 0,
			));
			if (!$subscriptionCategory->save())
				throw new CException(Yii::t('NfyModule.app', 'Failed to save category {category} for subscription {subscription_id}', array('{category}'=>$category, '{subscription_id}'=>$subscription_id)));
		}
		return true;
	}

	/**
	 * @inheritdoc
	 * @param boolean @permanent if false, the subscription will only be marked as removed and the messages will remain in the storage; if true, everything is removed permanently
	 */
	public function unsubscribe($subscriber_id, $permanent=true)
	{
		$trx = NfySubscription::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfySubscription::model()->getDbConnection()->beginTransaction();
        $subscription = NfySubscription::model()->withQueue($this->name)->withSubscriber($subscriber_id)->find();
		if ($subscription !== null) {
			if ($permanent)
				$subscription->delete();
			else
				$subscription->saveAttributes(array('is_deleted'=>1));
		}
		if ($trx !== null) {
			$trx->commit();
		}
	}

	/**
	 * @inheritdoc
	 */
	public function isSubscribed($subscriber_id)
	{
        $subscription = NfySubscription::model()->current()->withQueue($this->name)->withSubscriber($subscriber_id)->find();
        return $subscription !== null;
	}

	/**
	 * Unlocks timed-out messages.
	 * @return array of unlocked message ids
	 */
	public function unlockTimedout()
	{
        $trx = NfyMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyMessage::model()->getDbConnection()->beginTransaction();
		$pk = NfyMessage::model()->tableSchema->primaryKey;
		$messages = NfyMessage::model()->withQueue($this->name)->timedout($this->timeout)->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		NfyMessage::model()->updateByPk($message_ids, array('status'=>NfyMessage::AVAILABLE));
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}

	/**
	 * Removes deleted messages from the storage.
	 * @return array of removed message ids
	 */
	public function removeDeleted()
	{
        $trx = NfyMessage::model()->getDbConnection()->getCurrentTransaction() !== null ? null : NfyMessage::model()->getDbConnection()->beginTransaction();
		$pk = NfyMessage::model()->tableSchema->primaryKey;
		$messages = NfyMessage::model()->withQueue($this->name)->deleted()->findAllByPk($message_id, array('select'=>$pk, 'index'=>$pk));
		$message_ids = array_keys($messages);
		NfyMessage::model()->deleteByPk($message_ids);
		if ($trx !== null) {
			$trx->commit();
		}
		return $message_ids;
	}
}
