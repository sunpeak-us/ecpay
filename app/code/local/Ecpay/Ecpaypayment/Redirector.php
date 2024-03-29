<?php
use Mage_Checkout_Model_Session as Session;
use Mage_Sales_Model_Order as O;
use Mage_Sales_Model_Quote as Q;
# 2018-11-19 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
# "Handle the browser's «Back» button on the ECPay payment form":
# https://github.com/sunpeak-us/ecpay/issues/18
final class Ecpay_Ecpaypayment_Redirector {
	/**
	 * 2018-11-19
	 * @used-by \Ecpay_Ecpaypayment_O::controller_action_predispatch_checkout()
	 * @return bool
	 */
	static function is() {return !!self::sess()->getData(self::$K);}

	/**
	 * 2018-11-19
	 * @used-by \Ecpay_Ecpaypayment_PaymentController::customerReturnAction()
	 * @used-by \Ecpay_Ecpaypayment_O::controller_action_predispatch_checkout()
	 */
	static function restoreQuote() {
		$o = self::sess()->getLastRealOrder(); /** @var O $o */
		if ($o && $o->canCancel()) {
			$o->cancel()->save();
		}
		if ($qid = self::sess()->getData('last_success_quote_id')) {  /** @var int|null $qid */
			$q = Mage::getModel('sales/quote');	/** @var Q $q */
			$q->load($qid);
			$q->setIsActive(true);
			$q->save();
			# 2019-02-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
			# «When a customer checkouts as a guest, but cancels at ECPay checkout,
			# the contents do not stay in cart».
			# https://github.com/sunpeak-us/ecpay/issues/21
			self::sess()->replaceQuote($q);
		}
		self::unset();
	}

	/**
	 * 2018-11-19
	 * @used-by \Ecpay_Ecpaypayment_PaymentController::redirectAction()
	 */
	static function set() {self::sess()->setData(self::$K, true);}

	/**
	 * 2018-11-19
     * @used-by self::restoreQuote()
     * @used-by \Ecpay_Ecpaypayment_O::controller_action_predispatch_checkout()
	 */
	static function unset() {self::sess()->unsetData(self::$K);}

	/**
	 * 2018-11-19
	 * @used-by self::is()
	 * @used-by self::set()
	 * @used-by self::unset()
	 * @return Session
	 */
	static private function sess() {return Mage::getSingleton('checkout/session');}

	/**
	 * 2018-11-19
	 * @used-by self::is()
	 * @used-by self::set()
	 * @used-by self::unset()
	 * @var string
	 */
	private static $K = 'mage2pro_ecpay_redirected';
}