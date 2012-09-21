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
class Kancart_MobileApi_Model_PayPalWPS extends Kancart_MobileApi_Model_Abstract {

    protected $_methodType = 'paypal_standard';

    
    protected function _initOrder($orderIncrementId) {
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            $this->_fault('Invalid OrderID (Order)');
        }
        return $order;
    }
    
    public function Done($requestData) {
        $result = Mage::getModel('mobileapi/Result');
        $customerSession = Mage::getSingleton('customer/session');
        if (!$customerSession->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        } else {
            try {
                $session = Mage::getSingleton('checkout/session');
                $session->setQuoteId($session->getPaypalStandardQuoteId(true));
                $session->getQuote()->setIsActive(false)->save();
                
                $order = $this->_initOrder(Mage::getSingleton('checkout/type_onepage')->getLastOrderID());
                $result->setResult('0x0000', array('transaction_id' => $order->getPayment()->getLastTransId(),
                    'payment_total' => $order->getGrandTotal(),
                    'currency' => $order->getOrderCurrencyCode(),
                    'order_id' => $order->getIncrementId(),
                    'messages' => array($this->__('You will receive an order confirmation email with details of your order and a link to track its progress.'))));
                $session->clear();
                $cart = Mage::getSingleton('checkout/cart');
                $cart->getQuote()->setItemsCount(0);
                
            } catch (Mage_Core_Exception $e) {
                $result->setResult('0x9000', null, null, $e->getMessage());
                return $result->returnResult();
            } catch (Exception $e) {
                $result->setResult('0x9000', null, null, $e->getMessage());
                return $result->returnResult();
            }
        }
        return $result->returnResult();
    }

}