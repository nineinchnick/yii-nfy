<?php

/**
 * Poll messages and display them as notifications, optionally playing a sound.
 *
 * If native web notifications are not available, the WNF (http://wnf.brunoscopelliti.com/) plugin is used.
 *
 * @author Jan Was <janek.jan@gmail.com>
 */
class WebNotifications extends CWidget {
	public $url;

	public static function initClientScript() {
        $bu = Yii::app()->assetManager->publish(dirname(__FILE__) . '/assets/');
        $cs = Yii::app()->clientScript;
        $cs->registerCssFile($bu . '/css/webnotification.min.css');
        $cs->registerScriptFile($bu . '/js/jquery.webnotification'.(YII_DEBUG ? '' : '.min').'.js');
        $cs->registerScriptFile($bu . '/js/main.js');
	}

    public function run()
    {
		$options = array(
			'url' => $this->url,
		);
        $options = CJavaScript::encode($options);

		self::initClientScript();
        $cs = Yii::app()->clientScript;
		$script = "notificationsPoller.init({$options});";
        $cs->registerScript(__CLASS__ . '#' . $this->id, $script, CClientScript::POS_END);
	}
}
