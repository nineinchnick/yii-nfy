<?php

/**
 * The Nfy class acts like a CLogger and CLogRoute, it processes the message
 * right after calling the log() method.
 */
class Nfy {

	/**
	 * Logs provided message or creates one from passes models.
	 * @param mixed $msg if string, is treated as message to be logged, if an array, should contain 'old' and 'new' keys with CModel objects as values
	 * @param string $level level of the message (e.g. 'trace', 'warning', 'error'). It is case-insensitive.
	 * @param string $category category of the message (e.g. 'system.web'). It is case-insensitive.
	 */
	public static function log($msg,$level=CLogger::LEVEL_INFO,$category='application') {
		$cacheDuration=3600;
		// load (and cache) channels and match criteria to the level, category and optionally objects
		$channels = NfyChannels::model()->cache($cacheDuration)->findAll('(t.level IS NULL OR t.level=:level) AND (t.category IS NULL OR t.category=:category)', array(':level'=>$level, ':category'=>$category));
		foreach($channels as $key=>$channel) {
			if ($channel->criteria_callback!==null && !call_user_func($channel->criteria_callback, $msg, $level, $category))
				continue;
			$channel->process($msg, $level, $category);
		}
	}
}
