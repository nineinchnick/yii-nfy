<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of MessageForm
 *
 * @author aguzowski
 */
class MessageForm extends CFormModel {
    public $content = "";
    
    public function rules() {
        return array(
            array('content', 'required'),
        );
    }
}
