<?php

class EcpayCartLibrary
{
    private $merchantId = '';
    private $stageMids = ['2000132', '2000214']; // Stage merchant id
    private $isTest = false; // Test mode
    private $provider = 'ECPay'; // Service provider
    private $tradeTime = ''; // Trade time
    private $orderPrefix = ''; // MerchantTradeNo prefix
    private $encryptType = ''; // Encrypt type
    private $productUrl = 'https://payment.ecpay.com.tw';
    private $stageUrl = 'https://payment-stage.ecpay.com.tw';
    private $functionPath = [
        'checkOut' => '/Cashier/AioCheckOut/V5',
        'queryTrade' => '/Cashier/QueryTradeInfo/V5',
    ]; // API function path
    private $successCodes = [
        'payment' => 1,
        'atmGetCode' => 2,
        'cvsGetCode' => 10100073,
        'barcodeGetCode' => 10100073,
    ]; // API success return code

    public function __construct($data)
    {
        $this->loadSdk();
        $this->merchantId = $data['merchantId'];
        $this->isTest = $this->isTestMode();
        $this->tradeTime = $this->getDateTime('Y/m/d H:i:s', '');
        $this->orderPrefix = $this->getDateTime('ymdHi', $this->tradeTime);
        $this->encryptType = ECPay_EncryptType::ENC_SHA256;
    }
    
    /**
     * Check test mode by merchant id
     * @return boolean
     */
    public function isTestMode()
    {
        return in_array($this->merchantId, $this->stageMids);
    }

    /**
     * Load AIO SDK
     * @return void
     */
    public function loadSdk()
    {
		// 2018-11-01 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
		// «Warning: include(ECPay\AllInOne.php): failed to open stream: No such file or directory».
		// https://github.com/sunpeak-us/ecpay/issues/1
        if (!@class_exists('ECPay_AllInOne', false)) {
            include('ECPay.Payment.Integration.php');
        }
    }

    /**
	 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
     * @used-by \Ecpay_Ecpaypayment_Helper_Data::getRedirectHtml()
     * @param array $data The data for checkout
     * @return void
     */
    function checkout($data)
    {
        $paymentType = $data['choosePayment'];

        // Set SDK parameters
        $aio = $this->getAio(); // Get AIO object
        $aio->MerchantID = $this->merchantId;
        $aio->HashKey = $data['hashKey'];
        $aio->HashIV = $data['hashIv'];
        $aio->ServiceURL = $this->getUrl('checkOut'); // Get Checkout URL
        $aio->EncryptType = $this->encryptType;
        $aio->Send['ReturnURL'] = $data['returnUrl'];
        $aio->Send['ClientBackURL'] = $this->filterUrl($data['clientBackUrl']);
        $aio->Send['MerchantTradeNo'] = $this->getMerchantTradeNo($data['orderId']);
        $aio->Send['MerchantTradeDate'] = $this->tradeTime;
        $aio->Send['TradeDesc'] = $data['version'];
		/**
		 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
		 * The previous (incorrect) code:
		 * 		$aio->Send['TotalAmount'] = $this->_convertAmounttoUSD($this->getAmount($data['total']));
		 * "The sunpeak.us modification of the official ECPay module
		 * incorrectly sends payment amounts in USD to the ECPay's API.
		 * It is forbidden because the ECPay's API accepts only TWD
		 * which is exactly stated in the API's specification.":
		 * https://github.com/sunpeak-us/ecpay/issues/7
		 */
        $aio->Send['TotalAmount'] = $this->getAmount($data['total']);
        $aio->Send['ChoosePayment'] = $this->getPaymentMethod($paymentType);
		// 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
		// «Bind bank cards to the customers»: https://github.com/sunpeak-us/ecpay/issues/12
		$cs = Mage::getSingleton('customer/session'); /** @var \Mage_Customer_Model_Session $cs */
		if ($cs->isLoggedIn()) {
			/**
			 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
			 * «The function of this parameter is used to save a credit card number.
			 * If merchants need to bind the card: please set 1 as the value of this parameter.
			 * If merchants do not need to bind the card: please set 0 as the value of this parameter.»
			 * https://mage2.pro/t/5728
			 */
			$aio->Send['BindingCard'] = 1;
			/**
			 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
			 * «Merchant 1. [Saved credit card number on platform PlatformID]
			 * 1.1) The naming rules are set by the platform provider
			 * (applicable to platform providers with membership systems):
			 * when transmitting parameter MerchantMemberID,
			 * the first 7 digits must be the PlatformID
			 * (PlatformID+ member ID. The length must not exceed 30 characters)
			 * Ex: 3002599Test1234
			 *
			 * 1.2) The naming rules are set by the merchants under the platform
			 * (applicable to merchants with membership systems):
			 * when transmitting parameter MerchantMemberID,
			 * the first 7 digits must be the MerchantID (MerchantID + member ID.
			 * The length must not exceed 30 characters)
			 * Ex: 2000132Test1234
			 *
			 * 2) [Saved credit card number is by merchant MerchantID:
			 * The naming rules are set by the merchant (applicable to merchants with membership systems):
			 * when transmitting parameter MerchantMemberID, the first 7 digits must be the MerchantID
			 * (MerchantID + member ID. The length must not exceed 30 characters)
			 * Ex: 2000132Test1234»
			 * 
			 * https://mage2.pro/t/5728
			 */
			$aio->Send['MerchantMemberID'] = "{$this->merchantId}_{$cs->getId()}";
		}
        // Set the product info
        $aio->Send['Items'][] = [
            'Name' => $data['itemName'],
            'Price' => $aio->Send['TotalAmount'],
			/**
			 * 2018-11-06 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
			 * The previous (incorrect) code:
			 * 		'Currency'  => $data['currency'],
			 * It led to the error:
			 * «Notice: Undefined index: currency
			 * in app/code/local/Ecpay/Ecpaypayment/Helper/Library/EcpayCartLibrary.php on line 87»:
			 * https://github.com/sunpeak-us/ecpay/issues/6
			 * The `Currency` parameter is not set anywhere, is not used anywhere,
			 * and does not have any sense because ECPay supports only a single currency: NTD.
			 * https://github.com/sunpeak-us/ecpay/issues/7
			 */
            'Quantity' => 1,
            'URL' => '',
        ];
        
        // Set the extend information
        switch ($aio->Send['ChoosePayment']) {
            case ECPay_PaymentMethod::Credit:
                // Do not support UnionPay
                $aio->SendExtend['UnionPay'] = false;
				/**
				 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
				 * 1) «Choose a language of the checkout interface per a store»:
				 * https://github.com/sunpeak-us/ecpay/issues/13
				 * 2) This option is available only if `ChoosePayment` is `Credit`.
				 */
                $aio->SendExtend['Language'] = Mage::getStoreConfig('payment/ecpaypayment/language');
                // Credit installment parameters
                $installments = $this->getInstallment($paymentType);
                if ($installments > 0) {
                    $aio->SendExtend['CreditInstallment'] = $installments;
                    $aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
                    $aio->SendExtend['Redeem'] = false;
                }
                break;
            case ECPay_PaymentMethod::ATM:
                $aio->SendExtend['ExpireDate'] = 3;
                $aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
                break;
            case ECPay_PaymentMethod::CVS:
            case ECPay_PaymentMethod::BARCODE:
                $aio->SendExtend['Desc_1'] = '';
                $aio->SendExtend['Desc_2'] = '';
                $aio->SendExtend['Desc_3'] = '';
                $aio->SendExtend['Desc_4'] = '';
                $aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
                break;
            case ECPay_PaymentMethod::WebATM:
            default:
        }
        $aio->CheckOut();
        exit;
    }

    /**
     * Create AIO Object
     * @return object
     */
    public function getAio()
    {
        return new ECPay_AllInOne(); 
    }

    /**
     * Get AIO URL
     * @param  string $type URL type
     * @return string
     */
    public function getUrl($type)
    {
        if ($this->isTest === true) {
            $url = $this->stageUrl;
        } else {
            $url = $this->productUrl;
        }
        return $url . $this->functionPath[$type];
    }

    /**
     * Filter the specific character
     * @param  string $url URL
     * @return string
     */
    public function filterUrl($url)
    {
        return str_replace('&amp;', '&', $url);
    }

    /**
     * Get AIO merchant trade number
     * @param  mix $orderId Order id
     * @return string
     */
    public function getMerchantTradeNo($orderId)
    {
        if ($this->isTest === true) {
            return $this->orderPrefix . $orderId;
        } else {
            return strval($orderId);
        }
    }

    /**
     * Get date time
     * @param  string $pattern    Date time pattern
     * @param  string $dateString Date string
     * @return string
     */
    public function getDateTime($pattern, $dateString = '')
    {
        if ($dateString !== '') {
            return date($pattern, $this->getUnixTime($dateString));
        } else {
            return date($pattern);
        }
    }
    
    /**
     * Get the payment method from the payment type
     * @param  string $paymentType Payment type
     * @return string
     */
    public function getPaymentMethod($paymentType)
    {
        $pieces = explode('_', $paymentType);
        return $this->getSdkPaymentMethod($pieces[0]);
    }

    public function getSdkPaymentMethod($payment)
    {
        $sdkPaymentClass = 'ECPay_PaymentMethod';
        $lower = strtolower($payment);
        $sdkPayment = '';
        switch ($lower) {
            case 'all':
                $sdkPayment = $sdkPaymentClass::ALL;
                break;
            case 'credit':
                $sdkPayment = $sdkPaymentClass::Credit;
                break;
            case 'webatm':
                $sdkPayment = $sdkPaymentClass::WebATM;
                break;
            case 'atm':
                $sdkPayment = $sdkPaymentClass::ATM;
                break;
            case 'cvs':
                $sdkPayment = $sdkPaymentClass::CVS;
                break;
            case 'barcode':
                $sdkPayment = $sdkPaymentClass::BARCODE;
                break;
            default:
                $sdkPayment = '';
        }
        return $sdkPayment;
    }

    /**
     * Get AIO feedback
     * @param  array $data The data for getting aio feedback
     * @return array
     */
    public function getFeedback($data)
    {
        $aio = $this->getAio();  /** @var \ECPay_AllInOne $aio */
        $aio->MerchantID = $this->merchantId;
        $aio->HashKey = $data['hashKey'];
        $aio->HashIV = $data['hashIv'];
        $aio->EncryptType = $this->encryptType;
        $feedback = $aio->CheckOutFeedback(); /** @var array(string => mixed) */
        if (count($feedback) < 1) {
            throw new Exception($this->provider . ' feedback is empty.');
        }
        return $feedback;
    }

    /**
     * Get AIO trade info
     * @param  array $feedback AIO feedback
     * @param  array $data     The data for querying aio trade info
     * @return array
     */
    public function getTradeInfo($feedback, $data)
    {
        $aio = $this->getAio();
        $aio->MerchantID = $this->merchantId;
        $aio->HashKey = $data['hashKey'];
        $aio->HashIV = $data['hashIv'];
        $aio->ServiceURL = $this->getUrl('queryTrade');
        $aio->EncryptType = $this->encryptType;
        $aio->Query['MerchantTradeNo'] = $feedback['MerchantTradeNo'];
        $info = $aio->QueryTradeInfo();
        if (count($info) < 1) {
            throw new Exception($this->provider . ' trade info is empty.');
        }
        return $info;
    }

    /**
     * Get AIO feedback and validate
	 * @used-by \Ecpay_Ecpaypayment_Helper_Data::getPaymentResult()
     * @param  array $data The data for getting AIO feedback
     * @return array
     */
    public function getValidFeedback($data)
    {
        $feedback = $this->getFeedback($data); // AIO feedback
        $info = $this->getTradeInfo($feedback, $data); // Trade info

        // Check the amount
        if (!$this->validAmount($feedback['TradeAmt'], $info['TradeAmt'])) {
            throw new Exception('Invalid ' . $this->provider . ' feedback.(1)');
        }

        // Check the status when in product
        if ($this->isTest === false) {
            if ($this->isSuccess($feedback, 'payment') === true) {
                if ($this->toInt($info['TradeStatus']) !== 1) {
                     throw new Exception('Invalid ' . $this->provider . ' feedback.(2)');
                }
            }
        }
        return $feedback;
    }

    /**
     * Validate the amounts
     * @param  mix $source Source amount
     * @param  mix $target Target amount
     * @return boolean
     */
    public function validAmount($source, $target)
    {
//	return ($source===$source);
        return ($this->getAmount($source) === $this->getAmount($target));
    }
    
    /**
     * Get the amount
     * @param  mix $amount Amount
     * @return integer
     */
    public function getAmount($amount)
    {
        return round($amount, 0);
    }

    /**
     * Get the order id from AIO merchant trade number
     * @param  string $merchantTradeNo AIO merchant trade number
     * @return integer
     */
    public function getOrderId($merchantTradeNo)
    {
        if ($this->isTest === true) {
            $start = strlen($this->orderPrefix);
            $orderId = substr($merchantTradeNo, $start);
        } else {
            $orderId = $merchantTradeNo;
        }
        return $this->toInt($orderId);
    }

    /**
     * Get AIO response status
     * @param  array $feedback  AIO feedback
     * @param  array $orderInfo Order info
     * @return integer
     */
    public function getResponseStatus($feedback, $orderInfo)
    {
        $orderId = $orderInfo['orderId'];
        $validStatus = $orderInfo['validStatus'];
        $paymentMethod = $this->getPaymentMethod($feedback['PaymentType']);
        $paymentFailed = $this->getPaymentFailed($orderId, $feedback);
        $statusError = $this->getStatusError($orderId);

        // Check the response status
        //   0:failed
        //   1:Paid
        //   2:ATM get code
        //   3:CVS get code
        //   4:BARCODE get code
        $responseStatus = 0;
        switch($paymentMethod) {
            case ECPay_PaymentMethod::Credit:
            case ECPay_PaymentMethod::WebATM:
                if ($this->isSuccess($feedback, 'payment') === true) {
                    if ($validStatus === true) {
                        $responseStatus = 1; // Paid
                    } else {
                        throw new Exception($statusError);
                    }
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            case ECPay_PaymentMethod::ATM:
                if ($this->isSuccess($feedback, 'payment') === true) {
                    if ($validStatus === true) {
                        $responseStatus = 1; // Paid
                    } else {
                        throw new Exception($statusError);
                    }
                } elseif ($this->isSuccess($feedback, 'atmGetCode') === true) {
                    $responseStatus = 2; // ATM get code
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            case ECPay_PaymentMethod::CVS:
                if ($this->isSuccess($feedback, 'payment') === true) {
                    if ($validStatus === true) {
                        $responseStatus = 1; // Paid
                    } else {
                        throw new Exception($statusError);
                    }
                } elseif ($this->isSuccess($feedback, 'cvsGetCode') === true) {
                    $responseStatus = 3; // CVS get code
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            case ECPay_PaymentMethod::BARCODE:
                if ($this->isSuccess($feedback, 'payment') === true) {
                    if ($validStatus === true) {
                        $responseStatus = 1; // Paid
                    } else {
                        throw new Exception($statusError);
                    }
                } elseif ($this->isSuccess($feedback, 'barcodeGetCode') === true) {
                    $responseStatus = 4; // Barcode get code
                } else {
                    throw new Exception($paymentFailed);
                }
                break;
            default:
                throw new Exception($this->getInvalidPayment($orderId));
        }
        return $responseStatus;
    }

    /**
     * Get payment failed message
     * @param  mix   $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string
     */
    public function getPaymentFailed($orderId, $feedback)
    {
        return sprintf('Order %s Exception.(%s: %s)', $orderId, $feedback['RtnCode'], $feedback['RtnMsg']);
    }

    /**
     * Get invalid payment message
     * @param  mix   $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string
     */
    public function getInvalidPayment($orderId)
    {
        return sprintf('Order %s, payment method is invalid.', $orderId);
    }

    /**
     * Get order status error message
     * @param  mix   $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string
     */
    public function getStatusError($orderId)
    {
        return sprintf('Order %s status error.', $orderId);
    }

    /**
     * Get amount error message
     * @param  mix   $orderId  Order id
     * @param  array $feedback AIO feedback
     * @return string
     */
    public function getAmountError($orderId)
    {
        return sprintf('Order %s amount are not identical.', $orderId);
    }

    /**
     * Check AIO feedback status
     * @param  array   $feedback AIO feedback
     * @param  string  $type     Feedback type
     * @return boolean
     */
    public function isSuccess($feedback, $type)
    {
        return ($this->toInt($feedback['RtnCode']) === $this->toInt($this->successCodes[$type]));
    }

    /**
     * Get the installment
     * @param  string $paymentType Payment type
     * @return integer
     */
    public function getInstallment($paymentType)
    {
        $pieces = explode('_', $paymentType);
        if (isset($pieces[1]) === true) {
            return $this->getAmount($pieces[1]);
        } else {
            return 0;
        }
    }

    /**
     * Get payment success message
     * @param  string $partten  Message pattern
     * @param  array  $feedback AIO feedback
     * @return string
     */
    public function getPaymentSuccessComment($partten, $feedback)
    {
        return sprintf($partten, $feedback['RtnCode'], $feedback['RtnMsg']);
    }

    /**
     * Get obtaining code comment
     * @param  string $partten  Message pattern
     * @param  array  $feedback AIO feedback
     * @return string
     */
    public function getObtainingCodeComment($partten, $feedback)
    {
        $type = $this->getPaymentMethod($feedback['PaymentType']);
        switch($type) {
            case 'ATM':
                return sprintf(
                    $partten,
                    $feedback['RtnCode'],
                    $feedback['RtnMsg'],
                    $feedback['BankCode'],
                    $feedback['vAccount'],
                    $feedback['ExpireDate']
                );
                break;
            case 'CVS':
                return sprintf(
                    $partten,
                    $feedback['RtnCode'],
                    $feedback['RtnMsg'],
                    $feedback['PaymentNo'],
                    $feedback['ExpireDate']
                );
                break;
            case 'BARCODE':
                return sprintf(
                    $partten,
                    $feedback['RtnCode'],
                    $feedback['RtnMsg'],
                    $feedback['ExpireDate'],
                    $feedback['Barcode1'],
                    $feedback['Barcode2'],
                    $feedback['Barcode3']
                );
                break;
            default:
        }
        return 'undefine';
    }

    /**
     * Get obtaining code comment
     * @param  string $partten  Message pattern
     * @param  array  $error    Error message
     * @return string
     */
    public function getFailedComment($partten, $error)
    {
        return sprintf($partten, $error);
    }

    /**
     * Chang the value to integer
     * @param  mix $value Value
     * @return integer
     */
    public function toInt($value)
    {
        return intval($value);
    }

    /**
     * Get the unixtime
     * @param  string $dateString Date string
     * @return integer
     */
    public function getUnixTime($dateString) {
        return strtotime($dateString);
    }
}
