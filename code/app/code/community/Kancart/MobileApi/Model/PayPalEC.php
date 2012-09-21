<?php

/**
 * KanCart
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@kancart.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade KanCart to newer
 * versions in the future. If you wish to customize KanCart for your
 * needs please refer to http://www.kancart.com for more information.
 *
 * @copyright  Copyright (c) 2011 kancart.com (http://www.kancart.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Kancart_MobileApi_Model_PayPalEC extends Kancart_MobileApi_Model_Abstract {
    const PAYMENT_INFO_TRANSPORT_TOKEN = 'paypal_express_checkout_token';
    const PAYMENT_INFO_TRANSPORT_PAYER_ID = 'paypal_express_checkout_payer_id';

    protected $_methodType = 'paypal_express';

    /**
     * @var Mage_Paypal_Model_Express_Checkout
     */
    protected $_paypalExpressCheckout = null;

    protected function _getSession() {
        return Mage::getSingleton('customer/session');
    }

    protected function _initPaypalExpressCheckout() {
        $quote = $this->getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            Mage::throwException(Mage::helper('paypal')->__('Unable to initialize Express Checkout.'));
        }
        if (is_null($this->_config)) {
            $this->_config = Mage::getModel('paypal/config', array('paypal_express'));
        }
        $this->_paypalExpressCheckout = Mage::getSingleton('paypal/express_checkout', array(
                    'config' => $this->_config,
                    'quote' => $quote,
                ));
    }

    public function Start($requestData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        try {
            $this->getQuote()->reserveOrderId()->save();
            $this->getApi();
            $this->_api->setAmount($this->getQuote()->getBaseGrandTotal())
                    ->setCurrencyCode($this->getQuote()->getBaseCurrencyCode())
                    //->setInvNum($this->getQuote()->getReservedOrderId())
                    ->setReturnUrl(urldecode($requestData['return_url']))
                    ->setCancelUrl(urldecode($requestData['cancel_url']))
                    ->setSolutionType($this->_config->solutionType)
                    ->setPaymentAction($this->_config->paymentAction)
            ;
            $paypalCart = Mage::getModel('paypal/cart', array($this->getQuote()));
            $this->_api->setPaypalCart($paypalCart)
                    ->setIsLineItemsEnabled($this->_config->lineItemsEnabled)
            ;
            $this->_api->callSetExpressCheckout();
            $token = $this->_api->getToken();
            $paypalSite = '';
            if (PAYPAL_ENVIRONMENT == 'sandbox') {
                $paypalSite .= "https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout-mobile";
            } else {
                $paypalSite .= "https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout-mobile";
            }
            $paypalSite .= "&useraction=continue&token=" . $token;
            $this->_initToken($token);
            $result->setResult('0x0000', array('token' => $token, 'paypal_redirect_url' => $paypalSite));
            return $result->returnResult();
        } catch (Mage_Core_Exception $e) {
            $result->setResult('0x9000', null, null, $e->getMessage());
            return $result->returnResult();
        } catch (Exception $e) {
            $result->setResult('0x9000', null, null, $e->getMessage());
            return $result->returnResult();
        }
    }

    private function getPaypalSession() {
        return Mage::getSingleton('paypal/session');
    }

    /**
     * Search for proper checkout token in request or session or (un)set specified one
     * Combined getter/setter
     *
     * @param string $setToken
     * @return Mage_Paypal_ExpressController|string
     */
    protected function _initToken($setToken = null) {
        if (null !== $setToken) {
            if (false === $setToken) {
                // security measure for avoid unsetting token twice
                if (!$this->getPaypalSession()->getExpressCheckoutToken()) {
                    Mage::throwException($this->__('PayPal Express Checkout Token does not exist.'));
                }
                $this->getPaypalSession()->unsExpressCheckoutToken();
            } else {
                $this->getPaypalSession()->setExpressCheckoutToken($setToken);
            }
            return $this;
        }
    }

    protected function _getPaypalCheckoutToken($requestData) {
        if (isset($requestData['token'])) {
            if ($requestData['token'] !== $this->getPaypalSession()->getExpressCheckoutToken()) {
                Mage::throwException($this->__('Wrong PayPal Express Checkout Token specified.'));
            }
        } else {
            return $this->getPaypalSession()->getExpressCheckoutToken();
        }
        return $requestData['token'];
    }

    public function Detail($requestData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        try {
            $this->_initPaypalExpressCheckout();
            $this->_paypalExpressCheckout->returnFromPaypal($this->_getPaypalCheckoutToken($requestData));
            $result->setResult('0x0000', Mage::getModel('mobileapi/Checkout')->kancart_shoppingcart_checkout_detail());
            return $result->returnResult();
        } catch (Mage_Core_Exception $e) {
            $result->setResult('0x9000', null, null, $e->getMessage());
            return $result->returnResult();
        } catch (Exception $e) {
            $result->setResult('0x9000', null, null, $e->getMessage());
            return $result->returnResult();
        }
    }

    /**
     * Fix for the plugin,some fields of billng address may be null,
     * so we keep them same with the shipping address
     */
    private function makeBillingSameAsShipping() {
        $shippingAddress = $this->getQuote()->getShippingAddress();
        //Only the followng fields need to be set
        $billingData = array();
        $billingData['city'] = $shippingAddress->getCity();
        $billingData['street'] = $shippingAddress->getStreet();
        $billingData['region'] = $shippingAddress->getRegion();
        $billingData['region_id'] = $shippingAddress->getRegionId();
        $billingData['postcode'] = $shippingAddress->getPostcode();
        $billingData['country_id'] = $shippingAddress->getCountryId();
        $billingData['telephone'] = $shippingAddress->getTelephone();
        $billingData['fax'] = $shippingAddress->getFax();

        $this->getQuote()
             ->getBillingAddress()
             ->addData($billingData)
             ->save();
    }

    public function Pay($requestData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        try {
            $this->makeBillingSameAsShipping();
            $payment = $this->getQuote()->getPayment();

            $payment->setMethod($this->_methodType);
            Mage::getSingleton('paypal/info')->importToPayment($this->_api, $payment);
            $payment->setAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_PAYER_ID, $requestData['payer_id'])
                    ->setAdditionalInformation(self::PAYMENT_INFO_TRANSPORT_TOKEN, $requestData['token'])
            ;
            $this->getQuote()->collectTotals()->save();
            $this->getOnepage()->saveOrder();
            $this->getOnepage()->getQuote()->save();
            $order = $this->_initOrder($this->getOnepage()->getLastOrderID());
            $this->getOnepage()->getCheckout()->clear();
            $result->setResult('0x0000', array('transaction_id' => $order->getPayment()->getLastTransId(),
                'payment_total' => $order->getPayment()->getAmountAuthorized(),
                'currency' => $order->getOrderCurrencyCode(),
                'order_id' => $order->getIncrementId(),
                'messages' => array($this->__('You will receive an order confirmation email with details of your order and a link to track its progress.'))));
            return $result->returnResult();
        } catch (Mage_Core_Exception $e) {
            $result->setResult('0x0000', array('redirect_to_page' => 'checkout_review', 'messages' => array($e->getMessage())));
            return $result->returnResult();
        } catch (Exception $e) {
            $result->setResult('0x5000', null, null, $e->getMessage());
            return $result->returnResult();
        }
        $this->getOnepage()->getQuote()->save();
    }

    protected function _initOrder($orderIncrementId) {
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            $this->_fault('Invalid OrderID (Order)');
        }
        return $order;
    }

    protected $_checkout;

    public function getCheckout() {
        if (empty($this->_checkout)) {
            $this->_checkout = Mage::getSingleton('checkout/session');
        }
        return $this->_checkout;
    }

    protected $_api = null;
    protected $_config = null;

    protected function getApi() {
        if (null === $this->_api) {
            $this->_config = Mage::getModel('paypal/config', array('paypal_express'));
            $this->_api = Mage::getModel('paypal/api_nvp')->setConfigObject($this->_config);
        }
        return $this->_api;
    }

    protected $_quote = null;

    public function getQuote() {
        if (empty($this->_quote)) {
            $this->_quote = $this->getCheckout()->getQuote();
        }
        return $this->_quote;
    }

    public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

}