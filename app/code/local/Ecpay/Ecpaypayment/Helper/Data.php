<?php
use \Mage_Directory_Model_Currency as C;
use \Mage_Sales_Model_Order as O;
include_once('Library/ECPay.Payment.Integration.php');
include_once('Library/EcpayCartLibrary.php');
class Ecpay_Ecpaypayment_Helper_Data extends Mage_Core_Helper_Abstract
{
    private $paymentModel = null;
    private $prefix = 'ecpay_';
    private $updateNotify = false;
    private $resultNotify = true;
    private $obtainCodeNotify = true;

    private $errorMessages = array();

    public function __construct()
    {
        $this->paymentModel = Mage::getModel('ecpaypayment/payment');
        $this->errorMessages = array(
            'invalidPayment' => $this->__('ecpay_payment_checkout_invalid_payment'),
            'invalidOrder' => $this->__('ecpay_payment_checkout_invalid_order'),
        );
    }

    public function getPaymentGatewayUrl()
    {
        return Mage::getUrl('ecpaypayment/payment/gateway', array('_secure' => false));
    }

    public function getPostPaymentParameter($name)
    {
        $posts = Mage::app()->getRequest()->getParams();
        return $posts['payment'][$name];
    }

    public function setChoosenPayment($choosenPayment)
    {
        $this->getCheckoutSession()->setEcpayChoosenPayment($choosenPayment);
    }

    public function getChoosenPayment()
    {
        $session = $this->getCheckoutSession();
        if (empty($session->getEcpayChoosenPayment()) === true) {
            return '';
        } else {
            return $session->getEcpayChoosenPayment();
        }
    }

    public function destroyChoosenPayment()
    {
        $this->getCheckoutSession()->unsEcpayChoosenPayment();
    }

    public function isValidPayment($choosenPayment)
    {
        return $this->paymentModel->isValidPayment($choosenPayment);
    }

    public function getErrorMessage($name, $value)
    {
        $message = $this->errorMessages[$name];
        if ($value !== '') {
            return sprintf($message, $value);
        } else {
            return $message;
        }
    }

	/**
	 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * «Warning: Missing argument 1 for Ecpay_Ecpaypayment_Helper_Data::getRedirectHtml(),
	 * called in app/design/frontend/base/default/template/ecpaypayment/redirect.phtml on line 2
	 * and defined in app/code/local/Ecpay/Ecpaypayment/Helper\Data.php on line 71»:
	 * https://github.com/sunpeak-us/ecpay/issues/4
	 * @throws Mage_Core_Exception
	 */
    function getRedirectHtml()
    {
        try {
            $this->paymentModel->loadLibrary();
            $sdkHelper = $this->paymentModel->getHelper();  /** @var \EcpayCartLibrary $sdkHelper */
            // Validate choose payment
            $choosenPayment = $this->getChoosenPayment();
            if ($this->isValidPayment($choosenPayment) === false) {
                throw new Exception($this->getErrorMessage('invalidPayment', $choosenPayment));
            }

            // Validate the order id
            $orderId = $this->getOrderId();
            if (!$orderId) {
                throw new Exception($this->getErrorMessage('invalidOrder', ''));
            }

            // Update order status and comments
            $order = $this->getOrder($orderId);
            $createStatus = $this->paymentModel->getEcpayConfig('create_status');
            $pattern = $this->__('ecpay_payment_order_comment_payment_method');
            $paymentName = $this->getPaymentTranslation($choosenPayment);
            $comment = sprintf($pattern, $paymentName);
            $order->setState($createStatus, $createStatus, $comment, $this->updateNotify)->save();

            $checkoutSession = $this->getCheckoutSession();
            $checkoutSession->setEcpaypaymentQuoteId($checkoutSession->getQuoteId());
            $checkoutSession->setEcpaypaymentRealOrderId($orderId);
            $checkoutSession->getQuote()->setIsActive(false)->save();
//            $checkoutSession->clear();

            // Checkout
            $helperData = array(
                'choosePayment' => $choosenPayment,
                'hashKey' => $this->paymentModel->getEcpayConfig('hash_key'),
                'hashIv' => $this->paymentModel->getEcpayConfig('hash_iv'),
                'returnUrl' => $this->paymentModel->getModuleUrl('response'),
				/**
				 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
				 * The previous code was:
				 * 		'clientBackUrl' =>
				 * 			$this->paymentModel->getMagentoUrl('sales/order/view/order_id/' . $this->getOrderId()),
				 * 1) "The module should redirect successful customers to the «checkout success» page":
				 * https://github.com/sunpeak-us/ecpay/issues/10
				 * 2) "The module should unsuccessful customers to the cart page and restore their carts":
				 * https://github.com/sunpeak-us/ecpay/issues/11
				 */
                'clientBackUrl' => $this->paymentModel->getModuleUrl('customerReturn'),
                'orderId' => $orderId,
				// 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
				// "The module should pass payment amounts to the ECPay's API in NTD":
				// https://github.com/sunpeak-us/ecpay/issues/9
                'total' => $this->toTWD($order),
                'itemName' => $this->__('ecpay_payment_redirect_text_item_name'),
                'version' => $this->prefix . 'module_magento_2.1.0206',
            );
            $sdkHelper->checkout($helperData);
            $checkoutSession->clear();
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }
        return ;
    }

    public function getPaymentResult()
    {
        $resultMessage = '1|OK';
        $error = '';
        try {
            $this->paymentModel->loadLibrary();
            $sdkHelper = $this->paymentModel->getHelper(); /** @var \EcpayCartLibrary $sdkHelper */

            // Get valid feedback
            $helperData = array(
                'hashKey' => $this->paymentModel->getEcpayConfig('hash_key'),
                'hashIv' => $this->paymentModel->getEcpayConfig('hash_iv'),
            );
            $feedback = $sdkHelper->getValidFeedback($helperData);
            unset($helperData);

            $orderId = $sdkHelper->getOrderId($feedback['MerchantTradeNo']);
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId); /** @var O $order */
			/**
			 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
			 * 1) "The `$this->paymentModel->getMagentoConfig('use_store_currency')` code
			 * does not have a sense because the `use_store_currency` configuration option is absent":
			 * https://github.com/sunpeak-us/ecpay/issues/8
			 * 2) "The module should pass payment amounts to the ECPay's API in NTD":
			 * https://github.com/sunpeak-us/ecpay/issues/9
			 */
            // Check the amounts
            if ($sdkHelper->validAmount($feedback['TradeAmt'], $this->toTWD($order)) === false) {
                // throw new Exception($sdkHelper->getAmountError($orderId));
            }

            // Get the response status
            $orderStatus = $order->getState();
            $createStatus = $this->paymentModel->getEcpayConfig('create_status');
            $helperData = array(
                'validStatus' => ($orderStatus === $createStatus),
                'orderId' => $orderId,
            );
            $responseStatus = $sdkHelper->getResponseStatus($feedback, $helperData);
            unset($helperData);

            // Update the order status
            $patterns = array(
                2 => $this->__('ecpay_payment_order_comment_atm'),
                3 => $this->__('ecpay_payment_order_comment_cvs'),
                4 => $this->__('ecpay_payment_order_comment_barcode'),
            );
            switch($responseStatus) {
                // Paid
                case 1:
                    $status = $this->paymentModel->getEcpayConfig('success_status');
                    $pattern = $this->__('ecpay_payment_order_comment_payment_result');
                    $comment = $sdkHelper->getPaymentSuccessComment($pattern, $feedback);
                    $order->setState($status, $status, $comment, $this->resultNotify)->save();
                    unset($status, $pattern, $comment);
                    break;
                case 2:// ATM get code
                case 3:// CVS get code
                case 4:// Barcode get code
                    $status = $orderStatus;
                    $pattern = $patterns[$responseStatus];
                    $comment = $sdkHelper->getObtainingCodeComment($pattern, $feedback);
                    $order->setState($status, $status, $comment, $this->obtainCodeNotify)->save();
                    unset($status, $pattern, $comment);
                    break;
                default:
            }
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
            $this->_getCheckout()->addError($error);
        } catch (Exception $e) {
            $error = $e->getMessage();
            Mage::logException($e);
        }

        if ($error !== '') {
            if (is_null($orderId) === false) {
                $status = $this->paymentModel->getEcpayConfig('failed_status');
                $pattern = $this->__('ecpay_payment_order_comment_payment_failure');
                $comment = $sdkHelper->getFailedComment($pattern, $error);
                $order->setState($status, $status, $comment, $this->resultNotify);
                $order->save();
                unset($status, $pattern, $comment);
            }
            
            // Set the failure result
            $resultMessage = '0|' . $error;
        }
        echo $resultMessage;
        exit;
    }

    public function getPaymentTranslation($payment)
    {
        return $this->__('ecpay_payment_text_' . strtolower($payment));
    }


    private function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    private function getOrderId()
    {
        return $this->getCheckoutSession()->getLastRealOrderId();
    }

    private function getOrder($orderId)
    {
        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }

	/**
	 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * The method returns the order's total in NTD.
	 * "The module should pass payment amounts to the ECPay's API in NTD":
	 * https://github.com/sunpeak-us/ecpay/issues/9
	 * @used-by getRedirectHtml()
	 * @param \Mage_Sales_Model_Order $o
	 * @return int
	 */
    private function toTWD(O $o) {
    	$cBase = Mage::getModel('directory/currency')->load($o->getBaseCurrencyCode()); /** @var C $cBase */
		return intval($o->getBaseGrandTotal() * $cBase->getRate('TWD'));
	}
}
