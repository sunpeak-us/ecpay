<?php
/**
 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
 * «Choose a language of the checkout interface per a store»:
 * https://github.com/sunpeak-us/ecpay/issues/13
 */
final class Ecpay_Ecpaypayment_Model_System_Config_Source_Language {
	/**
	 * 2018-11-15 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
	 * @used-by app/code/local/Ecpay/Ecpaypayment/etc/system.xml
	 * @return array(array(string => string))
	 */
	function toOptionArray() {return [
		['value' => 'CHI', 'label' => 'Chinese']
		,['value' => 'ENG', 'label' => 'English']
		,['value' => 'JPN', 'label' => 'Japanese']
		,['value' => 'KOR', 'label' => 'Korean']
	];}
}