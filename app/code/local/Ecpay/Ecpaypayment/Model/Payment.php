<?php
class Ecpay_Ecpaypayment_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'ecpaypayment';
    protected $_formBlockType = 'ecpaypayment/form_ecpaypayment';
	/**
	 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * «Backend needs to note:
	 * 1) the currency rate used,
	 * 2) the actual amount charged to the customer (in TWD)
	 * 3) the base currency amount (USD)»
	 * https://github.com/sunpeak-us/ecpay/issues/14
	 */    
    protected $_infoBlockType = 'ecpaypayment/info';

    protected $_isGateway                   = true;
    protected $_canOrder                    = true;
    protected $_canAuthorize                = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = false;
    protected $_canCaptureOnce              = false;
    protected $_canRefund                   = false;
    protected $_canRefundInvoicePartial     = true;
    protected $_canVoid                     = true;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = true;
    protected $_isInitializeNeeded          = false;
    protected $_canFetchTransactionInfo     = true;
    protected $_canReviewPayment            = false;
    protected $_canCreateBillingAgreement   = false;
    protected $_canManageRecurringProfiles  = true;

    private $prefix = 'ecpay_';

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('ecpaypayment/payment/redirect', array('_secure' => false));
    }

    public function assignData($data)
    {
        $ecpayHelper = Mage::helper('ecpaypayment');
        $ecpayHelper->destroyChoosenPayment();
        $choosenPayment = $data->getEcpayChoosenPayment();
        $ecpayHelper->setChoosenPayment($choosenPayment);
        return $this;
    }

    public function getValidPayments()
    {
        $payments = $this->getEcpayConfig('payment_methods', true);
        $trimed = trim($payments);
        return explode(',', $trimed);
    }

    public function isValidPayment($choosenPayment)
    {
        $payments = $this->getValidPayments();
        return (in_array($choosenPayment, $payments));
    }

    public function getEcpayConfig($name)
    {
        return $this->getMagentoConfig($this->prefix . $name);
    }

    public function getMagentoConfig($name)
    {
        return $this->getConfigData($name);
    }

	/**
	 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * @used-by \Ecpay_Ecpaypayment_Helper_Data::getPaymentResult()
	 * @used-by \Ecpay_Ecpaypayment_Helper_Data::getRedirectHtml()
	 */
    function loadLibrary() {
		/**
		 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
		 * «Warning: include_once(EcpayCartLibrary.php): failed to open stream:
		 * No such file or directory in app/code/local/Ecpay/Ecpaypayment/Model/Payment.php on line 68»:
		 * https://github.com/sunpeak-us/ecpay/issues/5
		 */
    	Mage::helper('ecpaypayment');
    }

    public function getHelper() {
        $merchant_id = $this->getEcpayConfig('merchant_id');
        return new EcpayCartLibrary(array('merchantId' => $merchant_id));
    }

    public function getModuleUrl($action = '')
    {
        if ($action !== '') {
            $route = $this->_code . '/payment/' . $action;
        } else {
            $route = '';
        }
        return $this->getMagentoUrl($route);
    }

    public function getMagentoUrl($route)
    {
        return Mage::getUrl($route);
    }
}
