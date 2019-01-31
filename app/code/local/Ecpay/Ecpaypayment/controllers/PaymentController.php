<?php
use Ecpay_Ecpaypayment_Redirector as R;
use Mage_Checkout_Model_Session as CheckoutSession;
use Mage_Sales_Model_Order as O;
class Ecpay_Ecpaypayment_PaymentController extends Mage_Core_Controller_Front_Action
{
	/**
	 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * 1) "The module should redirect successful customers to the «checkout success» page":
	 * https://github.com/sunpeak-us/ecpay/issues/10
	 * 2) "The module should unsuccessful customers to the cart page and restore their carts":
	 * https://github.com/sunpeak-us/ecpay/issues/11
	 * @used-by \Ecpay_Ecpaypayment_Helper_Data::getRedirectHtml()
	 */
    function customerReturnAction() {
		$ss = Mage::getSingleton('checkout/session'); /** @var CheckoutSession $ss */
		$m = Mage::getModel('ecpaypayment/payment'); /** @var \Ecpay_Ecpaypayment_Model_Payment $m */
		/** @var O|null $o */
		if (
			($o = $ss->getLastRealOrder())
			&& !$o->isCanceled()
			&& $o->getStatus() !== $m->getEcpayConfig('failed_status')
		) {
			/**
			 * 2019-01-31 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
			 * «Improve ECpay module for Magento 1: send order emails to customers»
			 * https://www.upwork.com/ab/f/contracts/21411797
			 * https://github.com/sunpeak-us/ecpay/issues/20
			 */
			$o->sendNewOrderEmail();
			$this->getResponse()->setRedirect(Mage::getUrl('checkout/onepage/success'));
		}
		else {
			R::restoreQuote();
			$this->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
		}
    }

    public function redirectAction()
    {
    	R::set();
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
		/*Mage::log(
			json_encode($_REQUEST, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			,null
			,'mage2pro.ecpay.log'
		);*/
        echo Mage::helper('ecpaypayment')->getPaymentResult();
        exit;
    }
}