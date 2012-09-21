<?php

/**
 * KanCart
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http:
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
class Kancart_MobileApi_Model_Cart extends Kancart_MobileApi_Model_Abstract {

    protected function _construct() {
        
    }

    public function ShoppingCartGet($cartMessages) {
        $result = Mage::getModel('mobileapi/Result');
        $cart = $this->_getCart();
        if ($cart->getQuote()->getItemsCount()) {
            $cart->init();
            $cart->save();
            if (!$this->_getQuote()->validateMinimumAmount()) {
                $warning = Mage::getStoreConfig('sales/minimum_order/description');
                $cartMessages[parent::MESSAGE_STATUS_WARNING] = $warning;
            }
        }
        foreach ($cart->getQuote()->getMessages() as $message) {
            if ($message) {
                $cartMessages[$message->getType()] = $message->getText();
            }
        }
        $quote = $cart->getQuote();
        $currency = $quote->getquote_currency_code();
        $cartInfo = array();
        $cartInfo['is_virtual'] = Mage::helper('checkout/cart')->getIsVirtualQuote();
        $cartInfo['summary_qty'] = (int) Mage::helper('checkout/cart')->getSummaryCount();
        if (strlen($quote->getCouponCode())) {
            $cartInfo['has_coupon_code'] = 1;
        }
        $cartItemsArr = array();
        foreach ($this->_getItems() as $item) {
            if ($item->getProductType() == 'configurable')
                $renderer = new Mage_Checkout_Block_Cart_Item_Renderer_Configurable();
            else
                $renderer = new Mage_Checkout_Block_Cart_Item_Renderer();
            $renderer->setItem($item);
            $cartItemArr = array();
            $cartItemArr['cart_item_id'] = $item->getId();
            $cartItemArr['cart_item_key'] = null;
            $cartItemArr['currency'] = $currency;
            $cartItemArr['entity_type'] = $item->getProductType();
            $cartItemArr['item_id'] = $item->getProduct()->getId();
            $cartItemArr['item_title'] = strip_tags($renderer->getProductName());
            $cartItemArr['code'] = 'cart[' . $item->getId() . '][qty]';
            $cartItemArr['qty'] = $renderer->getQty();
            $cartItemArr['quantity'] = $renderer->getQty();
            $icon = $item->getProduct()->getThumbnailUrl();
            $cartItemArr['thumbnail_pic_url'] = $icon;
            $file = Mage::helper('mobileapi')->urlToPath($icon);
            $cartItemArr['icon_modification_time'] = filemtime($file);
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
                $cartItemArr['formated_price_excluding_tax'] = $formatedExclPrice;
                $cartItemArr['formated_price_including_tax'] = $formatedInclPrice;
            } else {
                if (Mage::helper('tax')->displayCartPriceExclTax()) {
                    $cartItemArr['price_regular'] = $exclPrice;
                    $cartItemArr['formated_price_regular'] = $formatedExclPrice;
                }
                if (Mage::helper('tax')->displayCartPriceInclTax()) {
                    $cartItemArr['price_regular'] = $inclPrice;
                    $cartItemArr['formated_price_regular'] = $formatedInclPrice;
                }
            }
            $exclPrice = $inclPrice = 0.00;
            if (Mage::helper('tax')->displayCartPriceExclTax() || Mage::helper('tax')->displayCartBothPrices()) {
                if (Mage::helper('weee')->typeOfDisplay($item, array(0, 1, 4), 'sales') && $item->getWeeeTaxAppliedAmount()) {
                    $exclPrice = $item->getRowTotal() + $item->getWeeeTaxAppliedRowAmount() + $item->getWeeeTaxRowDisposition();
                } else {
                    $exclPrice = $item->getRowTotal();
                }
            }
            if (Mage::helper('tax')->displayCartPriceInclTax() || Mage::helper('tax')->displayCartBothPrices()) {
                $_incl = Mage::helper('checkout')->getSubtotalInclTax($item);
                if (Mage::helper('weee')->typeOfDisplay($item, array(0, 1, 4), 'sales') && $item->getWeeeTaxAppliedAmount()) {
                    $inclPrice = $_incl + $item->getWeeeTaxAppliedRowAmount();
                } else {
                    $inclPrice = $_incl - $item->getWeeeTaxRowDisposition();
                }
            }
            $exclPrice = Mage::helper('mobileapi')->formatPriceForXml($exclPrice);
            $formatedExclPrice = $quote->getStore()->formatPrice($exclPrice, false);
            $inclPrice = Mage::helper('mobileapi')->formatPriceForXml($inclPrice);
            $formatedInclPrice = $quote->getStore()->formatPrice($inclPrice, false);
            if (Mage::helper('tax')->displayCartBothPrices()) {
                $cartItemArr['subtotal_excluding_tax'] = $exclPrice;
                $cartItemArr['subtotal_including_tax'] = $inclPrice;
                $cartItemArr['formated_subtotal_excluding_tax'] = $formatedExclPrice;
                $cartItemArr['formated_subtotal_including_tax'] = $formatedInclPrice;
            } else {
                if (Mage::helper('tax')->displayCartPriceExclTax()) {
                    $cartItemArr['item_price'] = $exclPrice;
                    $cartItemArr['formated_subtotal_regular'] = $formatedExclPrice;
                }
                if (Mage::helper('tax')->displayCartPriceInclTax()) {
                    $cartItemArr['item_price'] = $inclPrice;
                    $cartItemArr['formated_subtotal_regular'] = $formatedInclPrice;
                }
            }
            $cartItemArr['original_price'] = null;
            $cartItemArr['remark'] = null;
            $cartItemArr['display_skus'] = null;
            if ($_options = $renderer->getOptionList()) {
                $optionsArr = '';
                foreach ($_options as $_option) {
                    $_formatedOptionValue = $renderer->getFormatedOptionValue($_option);
                    $optionsArr = $optionsArr . strip_tags($_option['label']) . ':' . strip_tags($_formatedOptionValue['value']) . ' <br /> ';
                    //*                     if (isset($_formatedOptionValue['full_view'])) {
                }
                $cartItemArr['display_skus'] = $optionsArr;
            }
            if ($messages = $renderer->getMessages()) {
                $itemMessagesArr = array();
                foreach ($messages as $message) {
                    $itemMessageArr = array();
                    $itemMessageArr['type'] = $message['type'];
                    $itemMessageArr['text'] = strip_tags($message['text']);
                    array_push($itemMessagesArr, $itemMessageArr);
                }
                $cartItemArr['err_msg'] = $itemMessagesArr;
            }
            array_push($cartItemsArr, $cartItemArr);
        }
        $cartInfo['cart_items'] = $cartItemsArr;
        $cartInfo['messages'] = array();
        foreach ($cartMessages as $key => $value) {
            array_push($cartInfo['messages'], $value);
        }
        $quote->collectTotals()->save();
        $cartInfo['cart_items_count'] = Mage::helper('checkout/cart')->getSummaryCount();
        //$cartInfo['cart_virtual_qty'] = (int) $quote->getItemVirtualQty();
        if (strlen($quote->getCouponCode())) {
            $cartInfo['cart_has_coupon_code'] = 1;
        }
        $displayInclTax = $displayBoth = false;
        $PriceInfos = array();
        $position = 10;
        foreach ($quote->getTotals() as $total) {
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
                        $title = $this->helper('xmlconnect')->__('Subtotal (Excl. Tax)');
                        $value = $total->getValueExclTax();
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = $code;
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 1;
                        array_push($PriceInfos, $PriceInfo);
                        $code = $code . '_incl_tax';
                        $title = $this->helper('xmlconnect')->__('Subtotal (Incl. Tax)');
                        $value = $total->getValueInclTax();
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
                        $title = $this->helper('xmlconnect')->__('Grand Total (Excl. Tax)');
                        $value = $grandTotalExlTax;
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = 'total';
                        $PriceInfo['price'] = $value;
                        $PriceInfo['currency'] = $currency;
                        $PriceInfo['position'] = 5;
                        array_push($PriceInfos, $PriceInfo);
                        $code = $code . '_incl_tax';
                        $title = $this->helper('xmlconnect')->__('Grand Total (Incl. Tax)');
                        $value = $total->getValue();
                        $PriceInfo = array();
                        $PriceInfo['name'] = $title;
                        $PriceInfo['type'] = 'total';
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
                $PriceInfo = array();
                $PriceInfo['name'] = $title;
                if ($code == 'grand_total') {
                    $PriceInfo['type'] = 'total';
                } else {
                    $PriceInfo['type'] = $code;
                }
                $PriceInfo['price'] = $value;
                $PriceInfo['currency'] = $currency;
                $PriceInfo['position'] = $position++;
                array_push($PriceInfos, $PriceInfo);
            }
        }
        $cartInfo['price_infos'] = $PriceInfos;
        $this->addPaymentInfo($cartInfo);
        $result->setResult('0x0000', $cartInfo);
        return $result->returnResult();
    }
    
    private function addPaymentInfo(&$cartInfo){
        if($this->isPaymentMethodAvaiable('paypal_express')){
            $cartInfo['payment_methods'][] = 'paypalec';
        }
    }
    private function isPaymentMethodAvaiable($paymentMethodCode){
        $cart = $this->_getCart();
        $quote = $cart->getQuote();
        $store = $quote ? $quote->getStoreId() : null;
        
        $paymentHelper = Mage::helper('payment');
        $methods = $paymentHelper->getStoreMethods($store, $quote); 
        $total = $quote->getBaseSubtotal() + $quote->getShippingAddress()->getBaseShippingAmount();
        
        foreach ($methods as $key => $method) {
            if ($this->_canUseMethod($method)
                    && ($total != 0
                    || $method->getCode() == 'free'
                    || ($quote->hasRecurringItems() && $method->canManageRecurringProfiles()))) {
                if($method->getCode() === $paymentMethodCode){
                    return true;
                }         
            }
            unset($methods[$key]);
        }
        return false;
    }
    
    private function getQuote(){
        return $this->_getCart()->getQuote();
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
    
    public function ShoppingCartAdd($productData) {
        $result = Mage::getModel('mobileapi/Result');
        $cart = $this->_getCart();
        $cartMessages = array();
        try {
            if (isset($productData['qty'])) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                                array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                $productData['qty'] = $filter->filter($productData['qty']);
            }
            $product = null;
            $productId = (int) $productData['item_id'];
            if ($productId) {
                $_product = Mage::getModel('catalog/product')
                        ->setStoreId(Mage::app()->getStore()->getId())
                        ->load($productId);
                if ($_product->getId()) {
                    $product = $_product;
                }
            }
            $related = $productData['related_item'];
            if (!$product) {
                $cartMessages['result'] = Kancart_MobileApi_Model_Result::ERROR_0x5002;
                return $this->ShoppingCartGet($cartMessages);
            }
            if ($product->isConfigurable()) {
                $qty = isset($productData['qty']) ? $productData['qty'] : 0;
                $requestedQty = ($qty > 1) ? $qty : 1;
                $at = array();
                $skus = array();
                if (isset($productData['skus'])) {
                    $skus = json_decode($productData['skus'], true);
                }
                foreach ($skus as $sku) {
                    $at[$sku['sku_id']] = $sku['value'];
                }
                $subProduct = $product->getTypeInstance(true)->getProductByAttributes($at, $product);
                if ($requestedQty < ($requiredQty = $subProduct->getStockItem()->getMinSaleQty())) {
                    $requestedQty = $requiredQty;
                }
                $productData['qty'] = $requestedQty;
            }
            $params = array();
            $params['qty'] = $productData['qty'];
            $at = array();
            $aqt = array();
            $skus = array();
            if (isset($productData['skus'])) {
                $skus = json_decode($productData['skus'], true);
            }
            foreach ($skus as $sku) {
                $sku_values = explode(',', $sku['value']);
                if (count($sku_values) > 1)
                    $at[$sku['sku_id']] = $sku_values;
                else if (count($sku_values) == 1) {
                    $at[$sku['sku_id']] = $sku['value'];
                    $aqt[$sku['sku_id']] = $params['qty'];
                }
            }
            $params['options'] = $at;
            $params['super_attribute'] = $at;
            $params['bundle_option'] = $at;
            $params['bundle_option_qty'] = $aqt;
            $params['super_group'] = $at;
            $cart->addProduct($product, $params);
            if (!empty($related)) {
                $cart->addProductsByIds(explode(',', $related));
            }
            $cart->save();
            $this->_getSession()->setCartWasUpdated(true);
            if ($cart->getQuote()->getHasError()) {
                $message = Mage::helper('core')->htmlEscape($product->getName() . ' has been added to your cart.');
                $message .= ' But cart has some errors.';
                $cartMessages['result'] = $message;
            }
            return $this->ShoppingCartGet($cartMessages);
        } catch (Mage_Core_Exception $e) {
            if ($this->_getSession()->getUseNotice(true)) {
                $cartMessages['result'] = $e->getMessage();
                return $this->ShoppingCartGet($cartMessages);
            } else {
                $cartMessages['result'] = implode("\n", array_unique(explode("\n", $e->getMessage())));
                return $this->ShoppingCartGet($cartMessages);
            }
        } catch (Exception $e) {
            $cartMessages['result'] = $e->getMessage();
            return $this->ShoppingCartGet($cartMessages);
        }
    }

    public function ShoppingCartRemove($productData) {
        $id = (int) $productData['cart_item_id'];
        if ($id) {
            try {
                $result = Mage::getModel('mobileapi/Result');
                $this->_getCart()->removeItem($id)->save();
                $cartMessages['result'] = $this->__('Item has been deleted from cart.');
                return $this->ShoppingCartGet($cartMessages);
            } catch (Mage_Core_Exception $e) {
                $cartMessages['result'] = $e->getMessage();
                return $this->ShoppingCartGet($cartMessages);
            } catch (Exception $e) {
                $cartMessages['result'] = $e->getMessage();
                return $this->ShoppingCartGet($cartMessages);
            }
        }
    }

    public function ShoppingCartUpdate($productsData) {
        try {
            $result = Mage::getModel('mobileapi/Result');
            if (is_array($productsData)) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                $cartData = array();
                if (isset($productsData['qty']) && isset($productsData['cart_item_id'])) {
                    $productsData['qty'] = $filter->filter($productsData['qty']);
                    $cartData[(int) $productsData['cart_item_id']]['qty'] = (int) $productsData['qty'];
                }
                $cart = $this->_getCart();
                if (!$cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                    $cart->getQuote()->setCustomerId(null);
                }
                $cart->updateItems($cartData)
                        ->save();
            }
            $this->_getSession()->setCartWasUpdated(true);
            $cartMessages['result'] = $this->__('Cart has been updated.');
            return $this->ShoppingCartGet($cartMessages);
        } catch (Mage_Core_Exception $e) {
            $cartMessages['result'] = $e->getMessage();
            return $this->ShoppingCartGet($cartMessages);
        } catch (Exception $e) {
            $cartMessages['result'] = $e->getMessage();
            return $this->ShoppingCartGet($cartMessages);
        }
    }

    protected function _getCart() {
        return Mage::getSingleton('checkout/cart');
    }

    protected function _getSession() {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getQuote() {
        return $this->_getCart()->getQuote();
    }

    public function getCheckout() {
        if (empty($this->_checkout)) {
            $this->_checkout = Mage::getSingleton('checkout/session');
        }
        return $this->_checkout;
    }

    public function getQuoteSession() {
        if (empty($this->_quote)) {
            $this->_quote = $this->getCheckout()->getQuote();
        }
        return $this->_quote;
    }

    public function _getItems() {
        return $this->_getQuote()->getAllVisibleItems();
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

    public function getOnepage() {
        return Mage::getSingleton('checkout/type_onepage');
    }

    protected function _getCustomerSession() {
        return Mage::getSingleton('customer/session');
    }

}
