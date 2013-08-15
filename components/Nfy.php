<?php

Yii::import('nfy.NfyModule');

/**
 * The Nfy class acts like the CLogger class. Instead of collecting the messages,
 * it instantly passes them to each channel for processing, similar to CLogRouter
 * calling collectLogs on each route on log flush event.
 */
class Nfy {

	/**
	 * Sends passed message to all enabled channels matching specified level and category.
	 *
	 * @param mixed $msg if string, is treated as message to be logged, if an array, should contain 'old' and 'new' keys with CModel objects as values
	 * @param string $level level of the message (e.g. 'trace', 'warning', 'error'). It is case-insensitive.
	 * @param string $category category of the message (e.g. 'system.web'). It is case-insensitive.
	 */
	public static function log($msg,$level=CLogger::LEVEL_INFO,$category='application') {
		$cacheDuration=3600;
		$channels = NfyChannels::model()->cache($cacheDuration)->findAll('t.enabled=:enabled', array(':enabled'=>true));
		foreach($channels as $key=>$channel) {
			if ($channel->levels !== null) {
				$levels=preg_split('/[\s,]+/',strtolower($channel->levels),-1,PREG_SPLIT_NO_EMPTY);
				if (!in_array($level, $levels)) continue;
			}
			if ($channel->categories !== null) {
				$categories=preg_split('/[\s,]+/',strtolower($channel->categories),-1,PREG_SPLIT_NO_EMPTY);
				if (!in_array($category, $categories)) continue;
			}

			$route = Yii::createComponent(array(
				'class'=>$channel->route_class,
				'message_template'=>$channel->message_template,
			));
			if (!($route instanceof INfyRoute)) {
				$error = Yii::t('NfyModule.app', 'Route class {route} specified for channel {channel_name} must implement the INfyRoute interface.', array(
					'{route}' => $channel->route_class,
					'{channel_name}' => $channel->name,
				));
				// fail silently so the normal app workflow won't be disrupted by invalid notification configuration
				Yii::log($error, 'error', 'nfy');
				continue;
			}
			$route->process($msg, $channel->id, $channel->subscriptions);
		}
	}
}
