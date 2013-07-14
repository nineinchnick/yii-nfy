<?php

class NfyModule extends CWebModule
{
	public function init()
	{
		$this->setImport(array(
			'nfy.models.*',
			'nfy.components.*',
		));
	}
}
