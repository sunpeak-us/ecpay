<?php
use \Mage_Checkout_Model_Session as CheckoutSession;
use \Mage_Core_Model_Session as CoreSession;
use \Mage_Sales_Model_Order as O;
use \Mage_Sales_Model_Quote as Q;
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
		/** @var O|null $o */
		if (($o = $ss->getLastRealOrder()) && !$o->isCanceled()) {
			$this->getResponse()->setRedirect(Mage::getUrl('checkout/onepage/success'));
		}
		else {
			$coreSession = Mage::getSingleton('core/session'); /** @var CoreSession $coreSession */
			$coreSession->addError(nl2br(Mage::app()->getRequest()->getParam('RtnMsg')));
			if ($o && $o->canCancel()) {
				$o->cancel()->save();
			}
			if ($qid = $ss->getData('last_success_quote_id')) {  /** @var int|null $qid */
				$q = Mage::getModel('sales/quote');	/** @var Q $q */
				$q->load($qid);
				$q->setIsActive(true);
				$q->save();
			}
			$this->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
		}
    }

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