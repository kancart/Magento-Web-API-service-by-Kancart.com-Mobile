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
class Kancart_MobileApi_Model_Checkout extends Kancart_MobileApi_Model_Abstract {

    protected $_customer;
    protected $_checkout;
    protected $_quote;
    protected $_address;
    protected $_countryCollection;
    protected $_regionCollection;
    protected $_addressesCollection;

    protected function _construct() {
        
    }

    public function AddressUpdate($addressData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        
        $customerSession = Mage::getSingleton('customer/session');
        $primaryBillingAddress = $customerSession->getCustomer()->getPrimaryBillingAddress();

        $info = array();
        $exceptions = array();
        if ((isset($addressData['shipping_address_book_id']) && !empty($addressData['shipping_address_book_id'])) || (isset($addressData['shipping_address']) && !empty($addressData['shipping_address']))) {
            if (!empty($addressData['shipping_address']))
                $sdata = $addressData['shipping_address'];
            if (!empty($addressData['shipping_address_book_id']))
                $scustomerAddressId = $addressData['shipping_address_book_id'];
            $sadata = array();
            $sadata = json_decode($sdata, true);
            $sadata['street'] = array($sadata['address1'], $sadata['address2']);
            $sadata['save_in_address_book'] = '1';
            if (isset($sadata['state'])) {
                $sadata['region'] = $sadata['state'];
                $sadata['region_id'] = null;
            } else {
                $sadata['region_id'] = $sadata['zone_id'];
                $sadata['region'] = $sadata['zone_name'];
            }
            if (!empty($addressData['shipping_address']))
                $addressResult = $this->getOnepage()->saveShipping($sadata, null);
            else {
                $addressResult = $this->getOnepage()->saveShipping($sadata, $scustomerAddressId);
                /* Some of the fields of billing may be empty,
                 * so wo fill them with shipping address
                 */
                $addressResult = $this->getOnepage()->saveBilling($sdata,$customerAddressId);
            }
            
            if (!($primaryBillingAddress instanceof Varien_Object)) {
                $sadata['save_in_address_book'] = '1';
                $addressResult = $this->getOnepage()->saveBilling($sadata, null);
            }
            
            if(isset($addressResult['error'])){
                if (!is_array($addressResult['message'])) {
                    $addressResult['message'] = array($addressResult['message']);
                }
                $exceptions['shipping_address'] = implode('. ', $addressResult['message']);
            }
        }
        if ((isset($addressData['billing_address_book_id']) && !empty($addressData['billing_address_book_id'])) || (isset($addressData['billing_address']) && !empty($addressData['billing_address']))) {
            if (!empty($addressData['billing_address']))
                $sdata = $addressData['billing_address'];
            if (!empty($addressData['billing_address_book_id']))
                $scustomerAddressId = $addressData['billing_address_book_id'];
            $sadata = array();
            $sadata = json_decode($sdata, true);
            $sadata['street'] = array($sadata['address1'], $sadata['address2']);
            $sadata['save_in_address_book'] = '1';
            if (isset($sadata['state'])) {
                $sadata['region'] = $sadata['state'];
                $sadata['region_id'] = null;
            } else {
                $sadata['region_id'] = $sadata['zone_id'];
                $sadata['region'] = $sadata['zone_name'];
            }
            $sadata['save_in_address_book'] = '1';
            if (!empty($addressData['billing_address']))
                $addressResult = $this->getOnepage()->l($sadata, null);
            else {
                $addressResult = $this->getOnepage()->saveBilling($sadata, $scustomerAddressId);
            }
            if (!isset($addressResult['error'])) {
                
            } else {
                if (!is_array($addressResult['message'])) {
                    $addressResult['message'] = array($addressResult['message']);
                }
                $exceptions['billing_address'] = implode('. ', $addressResult['message']);
            }
        }
        $result->setResult('0x0000', $this->kancart_shoppingcart_checkout_detail());
        return $result->returnResult();
    }

    public function ShippingMethodsUpdate($shippingMethodData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$shippingMethodData) {
            $result->setResult('0x5000', null, null, $this->__('Specified invalid data.'));
            return $result->returnResult();
        }
        $ShippingRateCode = $shippingMethodData['shipping_method_id'];
        $shippingMethodResult = $this->getOnepage()->saveShippingMethod($ShippingRateCode);
        if (!isset($shippingMethodResult['error'])) {
            $result->setResult('0x0000', $this->kancart_shoppingcart_checkout_detail());
            return $result->returnResult();
        } else {
            $shippingMethodResult['message'] = $shippingMethodResult['message'];
            $result->setResult('0x5000', null, null, implode('. ', $shippingMethodResult['message']));
            return $result->returnResult();
        }
    }

    public function ShoppingCartCheckoutDetail($PaymentMethodData) {
        $session = $this->_getSession(); 
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        
        $result->setResult('0x0000', $this->kancart_shoppingcart_checkout_detail());
        return $result->returnResult();
    }

    public function kancart_shoppingcart_checkout_detail() {
        $checkoutDetailArr = array();
        $billing_address = array();
        $baddress = $this->getBillingAddress();
        if ($baddress->getData('country_id')) {
            $billing_address = $this->prepareQuoteAddressData($baddress);
        } else {
            $baddress = $this->getCustomer()->getPrimaryBillingAddress();
            if ($baddress) {
                $this->getQuote()->setBillingAddress($baddress);
            } else {
                $baddress = $this->getCustomer()->getPrimaryShippingAddress();
                if ($baddress) {
                    $this->getQuote()->setBillingAddress($baddress);
                }
            }
        }
        $shipping_address = array();
        $address = $this->getShippingAddress();
        if ($address->getData('country_id')) {
            $shipping_address = $this->prepareQuoteAddressData($address);
        } else {
            $address = $this->getCustomer()->getPrimaryShippingAddress();
            if ($address) { 
                $this->getQuote()->setShippingAddress($address);
                $shipping_address = $this->prepareAddressData($address, null, $address->getId());
            }
        }
        $checkoutDetailArr['shipping_address'] = $this->_toAddressData($shipping_address);
        if (isset($checkoutDetailArr['shipping_address']['address_book_id']))
            $checkoutDetailArr['need_shipping_address'] = false;
        else
            $checkoutDetailArr['need_shipping_address'] = true;
        $checkoutDetailArr['review_orders'] = array($this->getOrderReview());
        $checkoutDetailArr['price_infos'] = $this->getPriceInfos();
        
        $checkoutDetailArr['is_virtual'] = $checkoutDetailArr['review_orders'][0]['is_virtual'];
        if ($checkoutDetailArr['is_virtual']) {
            if ($baddress) {
                $checkoutDetailArr['need_billing_address'] = false;
                $billing_address = $this->prepareAddressData($baddress, null, $baddress->getId());
                $checkoutDetailArr['billing_address'] = $this->_toAddressData($billing_address);
            } else {
                $checkoutDetailArr['need_billing_address'] = true;
            }
            $checkoutDetailArr['need_billing_address'] = false;
            $checkoutDetailArr['shipping_address'] = null;
        }
        //Get PaymentMethod
        $quote = $this->getQuote();
        $store = $quote ? $quote->getStoreId() : null;
        
        $paymentHelper = Mage::helper('payment');
        $methods = $paymentHelper->getStoreMethods($store, $quote);
        $total = $quote->getBaseSubtotal() + $quote->getShippingAddress()->getBaseShippingAmount();
        foreach ($methods as $key => $method) {
            if ($this->_canUseMethod($method)
                    && ($total != 0
                    || $method->getCode() == 'free'
                    || ($quote->hasRecurringItems() && $method->canManageRecurringProfiles()))) {
                $code = '';
                if($method->getCode() == 'paypal_standard'){
                    $code = 'paypal';
                }else if($method->getCode() == 'paypal_express'){
                    $code = 'paypalwpp';
                }
                if($code == 'paypal' || $code == 'paypalwpp'){
                    $checkoutDetailArr['payment_methods'][] =
                        array('pm_id' => $code,
                            'pm_title' => $method->getTitle(),
                            'pm_code' => $method->getCode(),
                            'description' => $method->getDescription(),
                            'img_url' => '');
                }
            }
            unset($methods[$key]);
        }

        return $checkoutDetailArr;
    }

    protected function _canUseMethod($method) {
        if (!$method->canUseForCountry($this->getQuote()->getBillingAddress()->getCountry())) {
            return false;
        }

        if (!$method->canUseForCurrency(Mage::app()->getStore()->getBaseCurrencyCode())) {
            return false;
        }

        /**
         * Checking for min/max order total for assigned payment method
         */
        $total = $this->getQuote()->getBaseGrandTotal();
        $minTotal = $method->getConfigData('min_order_total');
        $maxTotal = $method->getConfigData('max_order_total');

        if ((!empty($minTotal) && ($total < $minTotal)) || (!empty($maxTotal) && ($total > $maxTotal))) {
            return false;
        }
        return true;
    }

    public function getPriceInfos() {
        $displayInclTax = $displayBoth = false;
        $PriceInfos = array();
        $currency = $this->getQuote()->getquote_currency_code();
        $position = 10;
        $quote = $this->getQuote();
        foreach ($this->getQuote()->getTotals() as $total) {
            $code = $total->getCode();
            if ($code == 'giftcardaccount') {
                continue;
            }
            $title = '';
            $value = null;
            switch ($code) {
                case 'subtotal':
                    if ($this->_getTaxConfig()->displayCartSubtotalBoth($this->_getStore())) {
                        $code = $code . '_excl_tax';
                        $title = $this->__('Subtotal (Excl. Tax)');
                        $value = $total->getValueExclTax();
                        $value = Mage::helper('mobileapi')->formatPriceForXml($value);
                        $formatedValue = $quote->getStore()->formatPrice($value, false);
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = $code;
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 1;
                        array_push($PriceInfos, $PriceInfo);
                        $code = $code . '_incl_tax';
                        $title = $this->__('Subtotal (Incl. Tax)');
                        $value = $total->getValueInclTax();
                        $value = Mage::helper('mobileapi')->formatPriceForXml($value);
                        $formatedValue = $quote->getStore()->formatPrice($value, false);
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = $code;
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 2;
                        array_push($PriceInfos, $PriceInfo);
                    }
                    break;
                case 'shipping':
                    if ($this->_getTaxConfig()->displayCartShippingBoth($this->_getStore())) {
                        $code = $code . '_excl_tax';
                        $title = Mage::helper('tax')->__('Shipping Excl. Tax (%s)', $total->getAddress()->getShippingDescription());
                        $value = $total->getAddress()->getShippingAmount();
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = $code;
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 3;
                        array_push($PriceInfos, $PriceInfo);
                        $code = $code . '_incl_tax';
                        $title = Mage::helper('tax')->__('Shipping Incl. Tax (%s)', $total->getAddress()->getShippingDescription());
                        $value = $total->getAddress()->getShippingInclTax();
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = $code;
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 4;
                        array_push($PriceInfos, $PriceInfo);
                    } else if ($this->_getTaxConfig()->displayCartShippingInclTax($this->_getStore())) {
                        $code = $code . '_incl_tax';
                        $title = Mage::helper('tax')->__('Shipping Incl. Tax (%s)', $total->getAddress()->getShippingDescription());
                        $value = $total->getAddress()->getShippingInclTax();
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = $code;
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 4;
                        array_push($PriceInfos, $PriceInfo);
                    } else {
                        $code = $code . '_excl_tax';
                        $title = Mage::helper('tax')->__('Shipping Excl. Tax (%s)', $total->getAddress()->getShippingDescription());
                        $value = $total->getAddress()->getShippingAmount();
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = $code;
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 3;
                        array_push($PriceInfos, $PriceInfo);
                    }
                    break;
                case 'grand_total':
                    $excl = $total->getAddress()->getGrandTotal() - $total->getAddress()->getTaxAmount();
                    $excl = max($excl, 0);
                    $grandTotalExlTax = $excl;
                    $displayBoth = $this->_grandTotal_includeTax($total) && $grandTotalExlTax >= 0;
                    if ($displayBoth) {
                        $code = $code . '_excl_tax';
                        $title = $this->__('Grand Total (Excl. Tax)');
                        $value = $grandTotalExlTax;
                        $value = Mage::helper('mobileapi')->formatPriceForXml($value);
                        $formatedValue = $quote->getStore()->formatPrice($value, false);
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = 'total';
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 5;
                        array_push($PriceInfos, $PriceInfo);
                        $code = $code . '_incl_tax';
                        $title = $this->__('Grand Total (Incl. Tax)');
                        $value = $total->getValue();
                        $value = Mage::helper('mobileapi')->formatPriceForXml($value);
                        $formatedValue = $quote->getStore()->formatPrice($value, false);
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = $code;
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 6;
                        array_push($PriceInfos, $PriceInfo);
                    }
                    break;
                default:
                    break;
            }
            if ($title == '' || is_null($value)) {
                $title = $total->getTitle();
                $value = $total->getValue();
                $value = Mage::helper('mobileapi')->formatPriceForXml($value);
                $formatedValue = $quote->getStore()->formatPrice($value, false);
                $PriceInfo = array();
                $PriceInfo['name'] = $title;
                $PriceInfo['type'] = $code;
                $PriceInfo['price'] = $value;
                $PriceInfo['currency'] = $currency;
                $PriceInfo['position'] = $position++;
                array_push($PriceInfos, $PriceInfo);
            }
        }
        return $PriceInfos;
    }

    public function getOrderReview() {
        $orderReviewArr = array();
        $quote = $this->getQuote();
        $cartItemsArr = array();
        $virtual_flag = false;
        foreach ($this->getQuote()->getAllVisibleItems() as $item) {
            $renderer = new Mage_Checkout_Block_Cart_Item_Renderer();
            $renderer->setItem($item);
            $cartItemArr = array();
            $cartItemArr['item_id'] = $item->getProduct()->getId();
            $cartItemArr['cart_item_id'] = $item->getId();
            $cartItemArr['item_title'] = strip_tags($renderer->getProductName());
            $cartItemArr['qty'] = $renderer->getQty();
            $icon = $item->getProduct()->getThumbnailUrl();
            $virtual_flag = $item->getProduct()->isVirtual();
            $cartItemArr['thumbnail_pic_url'] = $icon;
            $file = Mage::helper('mobileapi')->urlToPath($icon);
            $exclPrice = $inclPrice = 0.00;
            if (Mage::helper('tax')->displayCartPriceExclTax() || Mage::helper('tax')->displayCartBothPrices()) {
                if (Mage::helper('weee')->typeOfDisplay($item, array(0, 1, 4), 'sales') && $item->getWeeeTaxAppliedAmount()) {
                    $exclPrice = $item->getCalculationPrice() + $item->getWeeeTaxAppliedAmount() + $item->getWeeeTaxDisposition();
                } else {
                    $exclPrice = $item->getCalculationPrice();
                }
            }
            if (Mage::helper('tax')->displayCartPriceInclTax() || Mage::helper('tax')->displayCartBothPrices()) {
                $_incl = Mage::helper('checkout')->getPriceInclTax($item);
                if (Mage::helper('weee')->typeOfDisplay($item, array(0, 1, 4), 'sales') && $item->getWeeeTaxAppliedAmount()) {
                    $inclPrice = $_incl + $item->getWeeeTaxAppliedAmount();
                } else {
                    $inclPrice = $_incl - $item->getWeeeTaxDisposition();
                }
            }
            $exclPrice = Mage::helper('mobileapi')->formatPriceForXml($exclPrice);
            $formatedExclPrice = $quote->getStore()->formatPrice($exclPrice, false);
            $inclPrice = Mage::helper('mobileapi')->formatPriceForXml($inclPrice);
            $formatedInclPrice = $quote->getStore()->formatPrice($inclPrice, false);
            if (Mage::helper('tax')->displayCartBothPrices()) {
                $cartItemArr['price_excluding_tax'] = $exclPrice;
                $cartItemArr['price_including_tax'] = $inclPrice;
            } else {
                if (Mage::helper('tax')->displayCartPriceExclTax()) {
                    $cartItemArr['item_price'] = $exclPrice;
                }
                if (Mage::helper('tax')->displayCartPriceInclTax()) {
                    $cartItemArr['item_price'] = $inclPrice;
                }
            }
            if ($_options = $renderer->getOptionList()) {
                $optionsArr = array();
                foreach ($_options as $_option) {
                    $optionArr = array();
                    $_formatedOptionValue = $renderer->getFormatedOptionValue($_option);
                    $optionsArr = $optionsArr . strip_tags($_option['label']) . ':' . strip_tags($_formatedOptionValue['value']) . ',';
                }
                $optionsArr = substr($optionsArr, 0, strlen($optionsArr) - 1);
                $cartItemArr['skus'] = $optionsArr;
            }
            if ($messages = $renderer->getMessages()) {
                $itemMessagesArr = array();
                foreach ($messages as $message) {
                    $itemMessageArr = array();
                    $itemMessageArr['type'] = $message['type'];
                    $itemMessageArr['text'] = strip_tags($message['text']);
                    array_push($itemMessagesArr, $itemMessageArr);
                }
                $cartItemArr['item_messages'] = $itemMessagesArr;
            }
            array_push($cartItemsArr, $cartItemArr);
        }

        $orderReviewArr['cart_items'] = $cartItemsArr;
        $orderReviewArr['order_id'] = $this->getOnepage()->getLastOrderId();
        $orderReviewArr['selected_coupon_id'] = $this->getQuote()->getCouponCode();
        $orderReviewArr['shipping_methods'] = $this->getShippingMethods();
        $orderReviewArr['selected_shipping_method_id'] = $this->getQuote()->getShippingAddress()->getShippingMethod();
        $orderReviewArr['is_virtual'] = $virtual_flag;
        return $orderReviewArr;
    }

    public function getShippingMethods() {
        try {
            $result = array('error' => $this->__('Error.'));
            $this->getOnepage()->getQuote()->getShippingAddress()->setCollectShippingRates(true);
            $this->getOnepage()->getQuote()->collectTotals()->save();
            $_shippingRateGroups = $this->getShippingRates();
            if (!$this->getQuote()->getShippingAddress()->getShippingMethod())
                $setDefaultSM = true;
            if ($_shippingRateGroups) {
                $store = $this->getQuote()->getStore();
                $_sole = count($_shippingRateGroups) == 1;
                $shippingMethodsArr = array();
                foreach ($_shippingRateGroups as $code => $_rates) {
                    $_sole = $_sole && count($_rates) == 1;
                    foreach ($_rates as $_rate) {
                        $shippingMethodArr = array();
                        $shippingMethodArr['sm_id'] = $_rate->getCode();
                        $shippingMethodArr['title'] = strip_tags($_rate->getMethodTitle());
                        $shippingMethodArr['description'] = strip_tags($_rate->getMethodDescription());
                        $shippingMethodArr['sm_code'] = $code;
                        if ($_rate->getErrorMessage()) {
                            $rateArr['error_message'] = strip_tags($_rate->getErrorMessage());
                        } else {
                            $price = Mage::helper('tax')->getShippingPrice($_rate->getPrice(), Mage::helper('tax')->displayShippingPriceIncludingTax(), $this->getAddress());
                            $shippingMethodArr['price'] = Mage::helper('mobileapi')->formatPriceForXml($store->convertPrice($price, false, false));
                        }
                        $shippingMethodArr['currency'] = Mage::getModel('core/store')->load(Mage::app()->getStore()->getId())->getCurrentCurrencyCode();
                        array_push($shippingMethodsArr, $shippingMethodArr);
                    }
                }
                if ($setDefaultSM) {
                    $this->ShippingMethodsUpdate(array('shipping_method_id' => $shippingMethodsArr[0]['sm_id']));
                }
                return $shippingMethodsArr;
            } else {
                return $this->__('Sorry, no quotes are available for this order at this time.');
            }
        } catch (Mage_Core_Exception $e) {
            return $e->getMessage();
        }
    }

    
    public function ShoppingCartCheckout($requestData) {
        $session = $this->_getSession();    
        $result = Mage::getModel('mobileapi/Result');
        
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        // PayPal WPS
        // if payment_method_id == paypal
        // PayPal Express Checkout
        // if payment_method_id == paypalwpp
        if ($requestData['payment_method_id'] === 'paypal')
            $requestData['payment_method_id'] = 'paypal_standard';
        else if ($requestData['payment_method_id'] === 'paypalwpp')
            $requestData['payment_method_id'] = 'paypal_express';
        $payment = array();
        $payment['method'] = $requestData['payment_method_id'];
        try {
            $this->getOnepage()->savePayment($payment);
            $paypalParams = array();
            if ($requestData['payment_method_id'] === 'paypal_standard') {
                
                if (!$this->saveOrder()) {
                }
                $checkOutSession = Mage::getSingleton('checkout/session');
                $checkOutSession->setPaypalStandardQuoteId($checkOutSession->getQuoteId());
                $standard = Mage::getModel('paypal/standard');
                foreach ($standard->getStandardCheckoutFormFields() as $field => $value) {
                    $paypalParams[$field] = $value;
                }
                $paypalParams['shopping_url'] = $requestData['shoppingcart_url'];
                $paypalParams['return'] = $requestData['return_url'];;
                $paypalParams['cancel_return'] = $requestData['cancel_url'];;

                $paypalRedirectUrl = $standard->getConfig()->getPaypalUrl();
                $result->setResult('0x0000', array('paypal_redirect_url' => $paypalRedirectUrl, 'paypal_params' => $paypalParams));
                return $result->returnResult();
            } else {
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
                $paypalSite .= "&useraction=commit&token=" . $token;
                $result->setResult('0x0000', array('token' => $token, 'paypal_redirect_url' => $paypalSite));
                return $result->returnResult();
            }
        } catch (Mage_Core_Exception $e) {
            $result->setResult('0x9000', null, null, $e->getMessage());
            return $result->returnResult();
        } catch (Exception $e) {
            $result->setResult('0x9000', null, null, $e->getMessage());
            return $result->returnResult();
        }
    }

    public function getRequest() {
        return Mage::app()->getRequest();
    }

    /**
     * 保存订单 2012-05-24
     * @return bool 
     */
    private function saveOrder() {
        $result = array();
        $success = true;
        try {
            if ($requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds()) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                if ($diff = array_diff($requiredAgreements, $postedAgreements)) {
                    $result['success'] = false;
                    $result['error'] = true;
                    $result['error_messages'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    return;
                }
            }
            $this->getOnepage()->getQuote()->getPayment()->importData(array('method' => 'paypal_standard'));
            $this->getOnepage()->saveOrder();

            $redirectUrl = $this->getOnepage()->getCheckout()->getRedirectUrl();
            $result['success'] = true;
            $result['error'] = false;
        } catch (Mage_Payment_Model_Info_Exception $e) {
            $message = $e->getMessage();
            if (!empty($message)) {
                $result['error_messages'] = $message;
            }
            $result['goto_section'] = 'payment';
            $result['update_section'] = array(
                'name' => 'payment-method',
                'html' => $this->_getPaymentMethodsHtml()
            );
            $success = false;
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success'] = false;
            $result['error'] = true;
            $result['error_messages'] = $e->getMessage();

            if ($gotoSection = $this->getOnepage()->getCheckout()->getGotoSection()) {
                $result['goto_section'] = $gotoSection;
                $this->getOnepage()->getCheckout()->setGotoSection(null);
            }

            if ($updateSection = $this->getOnepage()->getCheckout()->getUpdateSection()) {
                if (isset($this->_sectionUpdateFunctions[$updateSection])) {
                    $updateSectionFunction = $this->_sectionUpdateFunctions[$updateSection];
                    $result['update_section'] = array(
                        'name' => $updateSection,
                        'html' => $this->$updateSectionFunction()
                    );
                }
                $this->getOnepage()->getCheckout()->setUpdateSection(null);
            }
            $success = false;
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success'] = false;
            $result['error'] = true;
            $result['error_messages'] = $this->__('There was an error processing your order. Please contact us or try again later.');
            $success = false;
        }
        $this->getOnepage()->getQuote()->save();
        return $success;
    }

    public function getCustomer() {
        if (empty($this->_customer)) {
            $this->_customer = Mage::getSingleton('customer/session')->getCustomer();
        }
        return $this->_customer;
    }

    public function getBillingAddress() {
        if (is_null($this->_address)) {
            $this->_address = Mage::getModel('sales/quote_address');
        }
        $this->_address = $this->getQuote()->getBillingAddress();
        return $this->_address;
    }

    public function getShippingAddress() {
        if (is_null($this->_address)) {
            $this->_address = Mage::getModel('sales/quote_address');
        }
        $this->_address = $this->getQuote()->getShippingAddress();
        return $this->_address;
    }

    public function getCheckout() {
        if (empty($this->_checkout)) {
            $this->_checkout = Mage::getSingleton('checkout/session');
        }
        return $this->_checkout;
    }

    public function getQuote() {
        if (empty($this->_quote)) {
            $this->_quote = $this->getCheckout()->getQuote();
        }
        return $this->_quote;
    }

    public function getTotals() {
        return $this->getQuote()->getTotals();
    }

    protected function _getSession() {
        return Mage::getSingleton('customer/session');
    }
    
    protected $_api = null;
    protected $_config = null;

    protected function getApi() {
        if (null === $this->_api) {
            $this->_config = Mage::getModel('paypal/config', array('paypal_express'));
            $tShoppingCartCheckouthis->_api = Mage::getModel('paypal/api_nvp')->setConfigObject($this->_config);
        }
        return $this->_api;
    }

    public function isCustomerLoggedIn() {
        return Mage::getSingleton('customer/session')->isLoggedIn();
    }

    public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

    public function getShippingRates() {
        if (empty($this->_rates)) {
            $this->getAddress()->collectShippingRates()->save();
            $groups = $this->getAddress()->getGroupedAllShippingRates();
            return $this->_rates = $groups;
        }
        return $this->_rates;
    }

    public function getAddress() {
        if (empty($this->_address)) {
            $this->_address = $this->getQuote()->getShippingAddress();
        }
        return $this->_address;
    }

    public function getCarrierName($carrierCode) {
        if ($name = Mage::getStoreConfig('carriers/' . $carrierCode . '/title')) {
            return $name;
        }
        return $carrierCode;
    }

    public function getAddressShippingMethod() {
        return $this->getAddress()->getShippingMethod();
    }

    public function getShippingPrice($price, $flag) {
        return $this->getQuote()->getStore()->convertPrice(Mage::helper('tax')->getShippingPrice($price, $flag, $this->getAddress()), true);
    }

    public function _grandTotal_includeTax($total) {
        if ($total->getAddress()->getGrandTotal()) {
            return $this->_getTaxConfig()->displayCartTaxWithGrandTotal($this->_getStore());
        }
        return false;
    }

    public function _getStore() {
        return Mage::app()->getStore();
    }

    public function _getTaxConfig() {
        return Mage::getSingleton('tax/config');
    }

    public function prepareQuoteAddressData($address, $defaultBillingID = 0, $defaultShippingID = 0) {
        if (!$address) {
            return array();
        }
        $attributes = $this->getAttributes();
        $data = array(
            'entity_id' => $address->getId()
        );
        $data['is_default_billing'] = $defaultBillingID == $data['entity_id'];
        $data['is_default_shipping'] = $defaultShippingID == $data['entity_id'];
        foreach ($attributes as $attribute) {
            if ($attribute->getAttributeCode() == 'country_id') {
                $data['country'] = $address->getCountryModel()->getName();
                $data['country_id'] = $address->getCountryId();
            } else if ($attribute->getAttributeCode() == 'region') {
                $data['region'] = $address->getRegion();
            } else {
                $dataModel = Mage_Customer_Model_Attribute_Data::factory($attribute, $address);
                $value = $dataModel->outputValue(Mage_Customer_Model_Attribute_Data::OUTPUT_FORMAT_ONELINE);
                if ($attribute->getFrontendInput() == 'multiline') {
                    $values = $dataModel->outputValue(Mage_Customer_Model_Attribute_Data::OUTPUT_FORMAT_ARRAY);
                    foreach ($values as $k => $v) {
                        $key = sprintf('%s%d', $attribute->getAttributeCode(), $k + 1);
                        $data[$key] = $v;
                    }
                }
                $data[$attribute->getAttributeCode()] = $value;
            }
        }
        return $data;
    }

    protected $_attributes;

    public function getAttributes() {
        if (is_null($this->_attributes)) {
            $this->_attributes = array();
            $config = Mage::getSingleton('eav/config');
            foreach ($config->getEntityAttributeCodes('customer_address') as $attributeCode) {
                $this->_attributes[$attributeCode] = $config->getAttribute('customer_address', $attributeCode);
            }
        }
        return $this->_attributes;
    }

}