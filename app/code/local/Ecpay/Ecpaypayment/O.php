<?php
use Ecpay_Ecpaypayment_Redirector as R;
use Mage_Core_Controller_Varien_Action as C;
use Varien_Event_Observer as Ob;
// 2018-11-19 Dmitry Fedyuk https://www.upwork.com/fl/mage2pro
// "Handle the browser's «Back» button on the ECPay payment form":
// https://github.com/sunpeak-us/ecpay/issues/18
final class Ecpay_Ecpaypayment_O {
	/**
	 * 2018-11-19
	 * @used-by R::restoreQuote()
	 * @param Ob $ob
	 * @return void
	 */
	function controller_action_predispatch_checkout(Ob $ob) {
		/**
		 * Если покупатель перешёл сюда с сайта платёжной системы,
		 * а не со страницы нашего магазина,
		 * то нужно не перенаправлять покупателя назад на сайт платёжной системы,
		 * а позволить покупателю оплатить заказ другим способом.
		 *
		 * Покупатель мог перейти сюда с сайта платёжной системы,
		 * нажав кнопку «Назад» в браузере,
		 * или же нажав специализированную кнопку отмены операции на сайте платёжной системы
		 * (например, на платёжной странице LiqPay кнопка «В магазин»
		 * работает как javascript:history.back()).
		 *
		 * Обратите внимание, что последние версии браузеров Firefox и Chrome
		 * при нажатии посетителем браузерной кнопки «Назад»
		 * перенаправляют посетилеля не на страницу df_payment/redirect,
		 * а сразу на страницу checkout/onepage.
		 *
		 * Впервые заметил такое поведение 17 сентября 2013 года в Forefox 23.0.1 и Chrome 29,
		 * причём Internet Explorer 10 в тот же день вёл себя по-прежнему.
		 *
		 * Видимо, Firefox и Chrome так делают по той причине,
		 * что посетитель со страницы checkout/onepage
		 * перенаправляется через страницу df_payment/redirect на страницу платёжной системы
		 * автоматически, скриптом, без участия покупателя.
		 *
		 * Поэтому мы делаем обработку в двух точках:
		 * @see Df_Payment_RedirectController::indexAction
		 * @see Df_Checkout_Model_Dispatcher::controller_action_predispatch_checkout
		 */
		if (R::is()) {
			$c = $ob->getData('controller_action'); /** @var C $c */
			if ('checkout_onepage_success' === $c->getFullActionName()) {
				/**
				 * В отличие от метода
				 * @see Df_Payment_Model_Action_Confirm::process()
				 * здесь необходимость вызова unsetRedirected() не вызывает сомнений,
				 * потому что Df_Checkout_Model_Dispatcher:controller_action_predispatch_checkout()
				 * обрабатывает именно сессию покупателя, а не запрос платёжной системы
				 */
				R::unset();
			}
			else {
				R::restoreQuote();
			}
		}
	}
}