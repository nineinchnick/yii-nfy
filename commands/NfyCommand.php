<?php

Yii::import('nfy.NfyModule');

class NfyCommand extends CConsoleCommand
{
    /**
     * nfy.channel.create
     * nfy.channel.update
     * nfy.channel.delete
     * nfy.channel.read
     *       |
     *       \-nfy.channel.read.subscribed
     * nfy.channel.subscribe
     * nfy.channel.unsubscribe
     *
     * nfy.message.read
     *       |
     *       \-nfy.message.read.subscribed
     * 
     * nfy.message.create
     *       |
     *       \-nfy.message.create.subscribed
     */
    public function getTemplateAuthItems() {
        $bizRule = 'return !isset($params["channel"]) || $params["channel"]->isSubscribed;';
        return array(
            array('name'=> 'nfy.channel.create',            'bizRule' => null, 'child' => null),
            array('name'=> 'nfy.channel.update',            'bizRule' => null, 'child' => null),
            array('name'=> 'nfy.channel.delete',            'bizRule' => null, 'child' => null),
            array('name'=> 'nfy.channel.read',              'bizRule' => null, 'child' => null),
            array('name'=> 'nfy.channel.read.subscribed',   'bizRule' => $bizRule, 'child' => 'nfy.channel.read'),
            array('name'=> 'nfy.channel.subscribe',         'bizRule' => null, 'child' => null),
            array('name'=> 'nfy.channel.unsubscribe',       'bizRule' => null, 'child' => null),
            array('name'=> 'nfy.message.read',              'bizRule' => null, 'child' => null),
            array('name'=> 'nfy.message.create',            'bizRule' => null, 'child' => null),
            array('name'=> 'nfy.message.read.subscribed',   'bizRule' => $bizRule, 'child' => 'nfy.message.read'),
            array('name'=> 'nfy.message.create.subscribed', 'bizRule' => $bizRule, 'child' => 'nfy.message.create'),
        );
    }

    public function getTemplateAuthItemDescriptions()
    {
        return array(
            'nfy.channel.create'            => Yii::t('NfyModule.auth', 'Create channel'),
            'nfy.channel.update'            => Yii::t('NfyModule.auth', 'Update any channel'),
            'nfy.channel.delete'            => Yii::t('NfyModule.auth', 'Delete any channel'),
            'nfy.channel.read'              => Yii::t('NfyModule.auth', 'Read any channel'),
            'nfy.channel.read.subscribed'   => Yii::t('NfyModule.auth', 'Read subscribed channel'),
            'nfy.channel.subscribe'         => Yii::t('NfyModule.auth', 'Subscribe to any channel'),
            'nfy.channel.unsubscribe'       => Yii::t('NfyModule.auth', 'Unsubscribe from a channel'),
            'nfy.message.read'              => Yii::t('NfyModule.auth', 'Read messages from any channel'),
            'nfy.message.create'            => Yii::t('NfyModule.auth', 'Send messages to any channel'),
            'nfy.message.read.subscribed'   => Yii::t('NfyModule.auth', 'Read messages from subscribed channel'),
            'nfy.message.create.subscribed' => Yii::t('NfyModule.auth', 'Send messages to subscribed channel'),
        );
    }

    public function actionCreateAuthItems()
    {
		$auth = Yii::app()->authManager;

        $newAuthItems = array();
        $descriptions = $this->getTemplateAuthItemDescriptions();
        foreach($this->getTemplateAuthItems() as $template) {
            $newAuthItems[$template['name']] = $template;
        }
		$existingAuthItems = $auth->getAuthItems(CAuthItem::TYPE_OPERATION);
        foreach($existingAuthItems as $name=>$existingAuthItem) {
            if (isset($newAuthItems[$name]))
                unset($newAuthItems[$name]);
        }
        foreach($newAuthItems as $template) {
            $auth->createAuthItem($template['name'], CAuthItem::TYPE_OPERATION, $descriptions[$template['name']], $template['bizRule']);
            if (isset($template['child']) && $template['child'] !== null) {
                $auth->addItemChild($template['name'], $template['child']);
            }
        }
	}

    public function actionRemoveAuthItems()
    {
		$auth = Yii::app()->authManager;

        foreach($this->getTemplateAuthItems() as $template) {
            $auth->removeAuthItem($template['name']);
        }
    }
}
