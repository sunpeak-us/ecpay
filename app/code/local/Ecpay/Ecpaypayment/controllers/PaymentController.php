<?php

class Ecpay_Ecpaypayment_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function redirectAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock(
            'Mage_Core_Block_Template',
            'ecpaypayment',
            array('template' => 'ecpaypayment/redirect.phtml')
        );
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    public function responseAction()
    {
        echo Mage::helper('ecpaypayment')->getPaymentResult();
        exit;
    }
}