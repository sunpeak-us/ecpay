<?php

class Ecpay_Ecpaypayment_Block_Form_Ecpaypayment extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ecpaypayment/form/payment.phtml');
    }
}