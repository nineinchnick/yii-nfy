<?php

class NfyModule extends CWebModule
{
	/**
	 * @var string Name of user model class.
	 */
	public $userClass = 'User';
	/**
	 * @var string if not null a sound will be played along with displaying a notification
	 */
	public $soundUrl;
	/**
	 * @var integer how many milliseconds to wait for new messages on the server side;
	 * zero or null disables long polling
	 */
	public $longPolling = 1000;
	/**
	 * @var integer how many times can messages be polled in a single action call
	 */
	public $maxPollCount = 30;

	public function init()
	{
		$this->setImport(array(
			'nfy.models.*',
			'nfy.components.*',
		));
	}
}
