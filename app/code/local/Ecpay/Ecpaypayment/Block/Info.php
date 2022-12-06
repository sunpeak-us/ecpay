<?php
use Mage_Directory_Model_Currency as C;
use Mage_Payment_Model_Info as II;
use Mage_Sales_Model_Order as O;
use Mage_Sales_Model_Order_Payment as P;
/**
 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
 * «Backend needs to note:
 * 1) the currency rate used,
 * 2) the actual amount charged to the customer (in TWD)
 * 3) the base currency amount (USD)»
 * https://github.com/sunpeak-us/ecpay/issues/14
 */
final class Ecpay_Ecpaypayment_Block_Info extends Mage_Payment_Block_Info {
	/**
	 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * @override
	 * @see Mage_Payment_Block_Info::getSpecificInformation()
	 * @used-by app/design/adminhtml/default/default/template/payment/info/default.phtml
	 * @return array(string => string|string[])
	 */
	function getSpecificInformation() {
		/** @var array(string => string|string[]) $r */	/** @var P $p */
		if (!($p = $this->getInfo()) instanceof P) {
			$r = [];
		}
		else {
			$o = $p->getOrder(); /** @var O $o */
			$b = $o->getBaseCurrency(); /** @var C $b */
			$bc = $b->getCode(); /** @var string $bc */
			$a = $p->getAdditionalInformation(self::TOTAL_TWD); /** @var int|null $a */
			$r = $this->getIsSecureMode() || !$a ? [] : array_filter([
				'Amount in TWD:' => $a
				# $cb->formatPrecision($o->getBaseGrandTotal(), 2, [], false)
				,"Amount in $bc:" => number_format($o->getBaseGrandTotal(), 2)
				,"$bc / TWD rate:" => number_format($p->getAdditionalInformation(self::RATE), 2)
			]);
		}
		return $r;
	}

	/**
	 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * @used-by getSpecificInformation()
	 * @used-by \Ecpay_Ecpaypayment_Helper_Data::getRedirectHtml()
	 */
	const RATE = 'ecpay_rate';

	/**
	 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * @used-by getSpecificInformation()
	 * @used-by \Ecpay_Ecpaypayment_Helper_Data::getRedirectHtml()
	 */
	const TOTAL_TWD = 'ecpay_total_twd';
}