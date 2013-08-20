<?php

/**
 * Adds extra transports to the NfyDbRoute.
 * Requires two components: mailer (EMailer extension) and jabberSender (EJabberSender extension).
 * Also assumes that a User model exists and it has the following attributes:
 * - email
 * - firstname and lastname.
 */
class NfyOtherRoute extends NfyDbRoute {
	protected function deliver($transport, $message, $subscription, $msg) {
		switch($transport) {
			default: return parent::delivery($transport, $message, $subscription, $msg);
			case 'email':
				$message = $this->formatLogMessage($msg, $message->user_id, $subscription->user_id);
				$user = User::model()->findByPk($subscription->user_id);
				$mail = Yii::app()->mailer;
				$mail->ClearAddresses();
				$mail->AddAddress($user->email, $user->firstname.' '.$user->lastname);
				$mail->Subject = 'Notification from '.Yii::app()->name;
				$mail->MsgHTML($message);
				if (!$mail->Send()) {
					Yii::log(Yii::t('NfyModule.app', 'Failed to send notification {message_id} to user {user_id} via email.', array('{message_id}' => $message->id, '{user_id}' => $subscription->user_id)), 'error', 'nfy');
				}
				break;
			case 'xmpp':
				$message = $this->formatLogMessage($msg, $message->user_id, $subscription->user_id);
				$user = User::model()->findByPk($subscription->user_id);
				Yii::app()->jabberSender->sendMessage($user->jabber, $message);
				//! @todo too bad there's no way to stop sendMessage from dying on errors
				break;
		}
	}
}
