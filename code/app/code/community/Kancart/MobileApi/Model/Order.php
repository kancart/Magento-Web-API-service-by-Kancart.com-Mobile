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
class Kancart_MobileApi_Model_Order extends Kancart_MobileApi_Model_Abstract {

    public function getOrders($apidata) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $uname = $apidata['uname'];
        $status = $apidata['status'];
        $fields = $apidata['fields'];
        
        try {
            if (is_null($status)) {
                $orders = Mage::getResourceModel('sales/order_collection')
                        ->addFieldToSelect('*')
                        ->addFieldToFilter('customer_id', Mage::getSingleton('customer/session')->getCustomer()->getId())
                        ->addFieldToFilter('state', array('in' => Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates()))
                        ->setOrder('created_at', 'desc');
                $orders->load();
            } else {
                $orders = Mage::getResourceModel('sales/order_collection')
                        ->addFieldToSelect('*')
                        ->addFieldToFilter('customer_id', Mage::getSingleton('customer/session')->getCustomer()->getId())
                        ->addFieldToFilter('state', array('in' => $status))
                        ->setOrder('created_at', 'desc');
                $orders->load();
            }
        } catch (Exception $e) {
            $result->setResult('0x6003', null, null, $e->getMessage());
            return $result->returnResult();
        }
        $ordersResult = array();
        foreach ($orders as $order) {
            $toorder = $this->_toOrder($order['increment_id']);
            $ordersResult[] = $toorder;
        }
        $finalResult = array();
        $finalResult['orders'] = $ordersResult;
        $finalResult['total_results'] = count($orders);
        $result->setResult('0x0000', $finalResult);
        return $result->returnResult();
    }

    public function getOrder($apidata) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $orderid = $apidata['order_id'];
        try {
            $oderResult = $this->_toOrder($orderid);
            $finalResult = array();
            $finalResult['order'] = $oderResult;
            $result->setResult('0x0000', $finalResult);
        } catch (Exception $e) {
            $result->setResult('0x6002', null, null, $e->getMessage());
        }
        return $result->returnResult();
    }

    public function Count($apidata) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $orders = Mage::getResourceModel('sales/order_collection')
                ->addFieldToSelect('*')
                ->addFieldToFilter('customer_id', Mage::getSingleton('customer/session')->getCustomer()->getId())
                ->addFieldToFilter('state', array('in' => Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates()))
                ->setOrder('created_at', 'desc');
        $orders->load();
        $ordersCounts = array();
        $ordersCount = array('status_name' => 'My Orders', 'count' => count($orders));
        array_push($ordersCounts, $ordersCount);
        $result->setResult('0x0000', array("order_counts" => $ordersCounts));
        return $result->returnResult();
    }

    public function Cancel($apidata) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $uname = $apidata['uname'];
        $order_id = $apidata['order_id'];
        $order = $this->_initOrder($order_id);
        try {
            $order->cancel();
            $order->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('order_not_canceled');
            $result->setResult('0x6002', null, null, $e->getMessage());
        }
        $result->setResult('0x0000', 'true');
        return $result->returnResult();
    }

    public function Complete($apidata) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $uname = $apidata['uname'];
        $order_id = $apidata['order_id'];
        $order = $this->_initOrder($order_id);
        try {
            $order->_checkState();
            $order->save();
        } catch (Mage_Core_Exception $e) {
            $this->_fault('order_not_complete');
            $result->setResult('0x6002', null, null, $e->getMessage());
        }
        $result->setResult('0x0000', 'true');
        return $result->returnResult();
    }

    public function items($filters = null) {
        $billingAliasName = 'billing_o_a';
        $shippingAliasName = 'shipping_o_a';
        $collection = Mage::getModel("sales/order")->getCollection()
                ->addAttributeToSelect('*')
                ->addAddressFields()
                ->addExpressionFieldToSelect(
                        'billing_firstname', "{{billing_firstname}}", array('billing_firstname' => "$billingAliasName.firstname")
                )
                ->addExpressionFieldToSelect(
                        'billing_lastname', "{{billing_lastname}}", array('billing_lastname' => "$billingAliasName.lastname")
                )
                ->addExpressionFieldToSelect(
                        'shipping_firstname', "{{shipping_firstname}}", array('shipping_firstname' => "$shippingAliasName.firstname")
                )
                ->addExpressionFieldToSelect(
                        'shipping_lastname', "{{shipping_lastname}}", array('shipping_lastname' => "$shippingAliasName.lastname")
                )
                ->addExpressionFieldToSelect(
                        'billing_name', "CONCAT({{billing_firstname}}, ' ', {{billing_lastname}})", array('billing_firstname' => "$billingAliasName.firstname", 'billing_lastname' => "$billingAliasName.lastname")
                )
                ->addExpressionFieldToSelect(
                'shipping_name', 'CONCAT({{shipping_firstname}}, " ", {{shipping_lastname}})', array('shipping_firstname' => "$shippingAliasName.firstname", 'shipping_lastname' => "$shippingAliasName.lastname")
        );
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if (isset($this->_attributesMap['order'][$field])) {
                        $field = $this->_attributesMap['order'][$field];
                    }
                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }
        $result = array();
        foreach ($collection as $order) {
            $result[] = $this->_getAttributes($order, 'order');
        }
        return $result;
    }

    public function info($orderIncrementId) {
        $order = $this->_initOrder($orderIncrementId);
        if ($order->getGiftMessageId() > 0) {
            $order->setGiftMessage(
                    Mage::getSingleton('giftmessage/message')->load($order->getGiftMessageId())->getMessage()
            );
        }
        $result = $this->_getAttributes($order, 'order');
        $result['shipping_address'] = $this->_getAttributes($order->getShippingAddress(), 'order_address');
        $result['billing_address'] = $this->_getAttributes($order->getBillingAddress(), 'order_address');
        $result['items'] = array();
        foreach ($order->getAllVisibleItems() as $item) {
            if ($item->getGiftMessageId() > 0) {
                $item->setGiftMessage(
                        Mage::getSingleton('giftmessage/message')->load($item->getGiftMessageId())->getMessage()
                );
            }
            $result['items'][] = $this->_getAttributes($item, 'order_item');
        }
        $result['payment'] = $this->_getAttributes($order->getPayment(), 'order_payment');
        $result['status_history'] = array();
        foreach ($order->getAllStatusHistory() as $history) {
            $result['status_history'][] = $this->_getAttributes($history, 'order_status_history');
        }
        $result['status'] = $order->getStatusLabel();
        return $result;
    }

    protected function _initOrder($orderIncrementId) {
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($orderIncrementId);
        if (!$order->getId()) {
            $this->_fault('Invalid OrderID (Order)');
        }
        return $order;
    }

    public function lists($setId) {
        $attributes = Mage::getModel('catalog/product')->getResource()
                ->loadAllAttributes()
                ->getSortedAttributes($setId);
        $result = array();
        foreach ($attributes as $attribute) {
            if ((!$attribute->getId() || $attribute->isInSet($setId))
                    && $this->_isAllowedAttribute($attribute)) {
                if (!$attribute->getId() || $attribute->isScopeGlobal()) {
                    $scope = 'global';
                } elseif ($attribute->isScopeWebsite()) {
                    $scope = 'website';
                } else {
                    $scope = 'store';
                }
                $result[] = array(
                    'attribute_id' => $attribute->getId(),
                );
            }
        }
        return $result;
    }

    public function getOrderItemSku($options) {
        $i = 0;
        $jsonoption = '[';
        foreach ($options as $option) {
            if ($i == 0) {
                $jsonoption.='{"sku_id":"' . $option['label'] . '","value":"' . $option['value'] . '"}';
            } else {
                $jsonoption.=',{"sku_id":"' . $option['label'] . '","value":"' . $option['value'] . '"}';
            }
            $i++;
        }
        $jsonoption.=']';
    }

    public function _toOrder($orderid) {
        $order = $this->info($orderid);
        $billing_add = explode("\n", $order['billing_address']['street']);
        $billing_country = Mage::getModel('directory/country')->loadByCode($order['billing_address']['country_id']);
        $billing_country->getName();
        $billing_zone = Mage::getModel('directory/region')->loadByName($order['billing_address']['region'], $order['billing_address']['country_id']);
        $shipping_add = explode("\n", $order['shipping_address']['street']);
        $shipping_country = Mage::getModel('directory/country')->loadByCode($order['shipping_address']['country_id']);
        $shipping_country->getName();
        $shipping_zone = Mage::getModel('directory/region')->loadByName($order['shipping_address']['region'], $order['shipping_address']['country_id']);
        $result = array();
        $result['order_id'] = $order['increment_id'];
        $result['display_id'] = $order['increment_id'];
        $result['uname'] = $order['customer_email'];
        $result['currency'] = $order['order_currency_code'];
        $result['shipping_address'] = array(
            'address_book_id' => $order['shipping_address']['parent_id'],
            'address_type' => $order['shipping_address']['address_type'],
            'lastname' => $order['shipping_address']['lastname'],
            'firstname' => $order['shipping_address']['firstname'],
            'gender' => null,
            'mobile' => null,
            'company' => $order['shipping_address']['company'],
            'fax' => $order['shipping_address']['fax'],
            'telephone' => $order['shipping_address']['telephone'],
            'tax_code' => null,
            'postcode' => $order['shipping_address']['postcode'],
            'city' => $order['shipping_address']['city'],
            'address1' => $shipping_add[0],
            'address2' => $shipping_add[1],
            'country_id' => $shipping_country->getData('country_id'),
            'country_code' => $shipping_country->getData('iso2_code'),
            'country_name' => $shipping_country->getData('name'),
            'zone_id' => $shipping_zone->getData('region_id'),
            'zone_code' => $shipping_zone->getData('code'),
            'zone_name' => $order['shipping_address']['region'],
            'state' => null
        );
        $result['billing_address'] = array(
            'address_book_id' => $order['billing_address']['parent_id'],
            'address_type' => $order['billing_address']['address_type'],
            'lastname' => $order['billing_address']['lastname'],
            'firstname' => $order['billing_address']['firstname'],
            'gender' => null,
            'mobile' => null,
            'company' => $order['billing_address']['company'],
            'fax' => $order['billing_address']['fax'],
            'telephone' => $order['billing_address']['telephone'],
            'tax_code' => null,
            'postcode' => $order['billing_address']['postcode'],
            'city' => $order['billing_address']['city'],
            'address1' => $billing_add[0],
            'address2' => $billing_add[1],
            'country_id' => $billing_country->getData('country_id'),
            'country_code' => $billing_country->getData('iso2_code'),
            'country_name' => $billing_country->getData('name'),
            'zone_id' => $billing_zone->getData('region_id'),
            'zone_code' => $billing_zone->getData('code'),
            'zone_name' => $order['billing_address']['region'],
            'state' => null
        );
        $result['uname'] = $order['customer_email'];
        $result['payment_method'] = array(
            'pm_id' => null,
            'pm_title' => $order['payment']['method'],
            'pm_description' => null,
            'pm_img_url' => null
        );
        $result['shipping_method'] = array(
            'sm_id' => null,
            'sm_code' => $order['shipping_method'],
            'title' => $order['shipping_description'],
            'description' => $order['shipping_description'],
            'price' => $order['shipping_incl_tax'],
            'currency' => $order['order_currency_code']
        );
        $result['shipping_insurance'] = null;
        $result['coupon'] = array(
            'coupon_id' => $order['coupon_code'],
            'min_price' => null,
            'max_price' => null,
            'description' => $order['discount_description'],
            'price' => null,
            'currency' => $order['order_currency_code']
        );
        $result['price_infos'] = array();
        $result['price_infos'][] = array(
            'name' => $this->__('Subtotal'),
            'type' => 'subtotal',
            'price' => $order['subtotal'],
            'currency' => $order['order_currency_code'],
            'position' => null
        );
        if ($order['shipping_amount'] != 0) {
            $result['price_infos'][] = array(
                'name' => $this->__('Shipping & Handling'),
                'type' => 'shipping',
                'price' => $order['shipping_amount'],
                'currency' => $order['order_currency_code'],
                'position' => null
            );
        }
        if ($order['discount_amount'] != 0) {
            $result['price_infos'][] = array(
                'name' => $this->__('Discount'),
                'type' => 'discount',
                'price' => $order['discount_amount'],
                'currency' => $order['order_currency_code'],
                'position' => null
            );
        }
        if ($order['tax_amount'] != 0) {
            $result['price_infos'][] = array(
                'name' => $this->____('Tax'),
                'type' => 'tax',
                'price' => $order['tax_amount'],
                'currency' => $order['order_currency_code'],
                'position' => null
            );
        }
//		array(
//                'name' => 'shipping_tax_amount',
//                'type' => 'shipping_tax',
//                'price' => $order['shipping_tax_amount'],
//                'currency' => $order['order_currency_code'],
//                'position' => null
//		)
        $result['price_infos'][] = array(
            'name' => $this->__('Grand Total'),
            'type' => 'total',
            'price' => $order['grand_total'],
            'currency' => $order['order_currency_code'],
            'position' => null
        );


        foreach ($order['items'] as $item) {
            $_product = Mage::getModel('catalog/product')->load($item['product_id']);
            $options = unserialize($item['product_options']);
            $jsonoption = $this->getOrderItemSku($options['attributes_info']);
            $result['order_items'][] = array(
                'order_item_id' => $item['item_id'],
                'item_id' => $item['product_id'],
                'item_skus' => $item['sku'],
                'skus' => $jsonoption,
                'item_title' => $item['name'],
                'thumbnail_pic_url' => $_product->getThumbnailUrl(),
                'qty' => round($item['qty_ordered'], 0),
                'price' => $item['price'],
                'final_price' => $item['row_total_incl_tax'],
                'item_tax' => $item['tax_amount'],
                'shipping_method' => $order['shipping_method'],
                'post_free' => $item['free_shipping'] == 1,
                'virtual_flag' => $item['is_virtual'] == 1
            );
        }
        foreach ($order['status_history'] as $status) {
            $result['order_status'][] = array(
                'status_id' => null,
                'status_name' => $status['status'],
                'date_added' => $status['created_at'],
                'allow_payment' => null,
                'display_text' => $status['status'],
                'comments' => $status['comment']
            );
        }
        $result['last_status_id'] = $order['status'];
        $result['order_tax'] = $order['tax_amount'];
        $result['order_date_start'] = $order['created_at'];
        if ($order['status'] == "complete") {
            $result['order_date_finish'] = $order['update_at'];
        }
        if ($order['status'] == "pending_payment") {
            $result['order_date_purchased'] = $order['update_at'];
        }
        return $result;
    }

    protected function _getSession() {
        return Mage::getSingleton('customer/session');
    }

    protected $_ignoredAttributeCodes = array(
        'global' => array('entity_id', 'attribute_set_id', 'entity_type_id')
    );
    protected $_attributesMap = array(
        'global' => array()
    );

    protected function _updateAttributes($data, $object, $type, array $attributes = null) {
        foreach ($data as $attribute => $value) {
            if ($this->_isAllowedAttribute($attribute, $type, $attributes)) {
                $object->setData($attribute, $value);
            }
        }
        return $this;
    }

    protected function _getAttributes($object, $type, array $attributes = null) {
        $result = array();
        if (!is_object($object)) {
            return $result;
        }
        foreach ($object->getData() as $attribute => $value) {
            if ($this->_isAllowedAttribute($attribute, $type, $attributes)) {
                $result[$attribute] = $value;
            }
        }
        foreach ($this->_attributesMap['global'] as $alias => $attributeCode) {
            $result[$alias] = $object->getData($attributeCode);
        }
        if (isset($this->_attributesMap[$type])) {
            foreach ($this->_attributesMap[$type] as $alias => $attributeCode) {
                $result[$alias] = $object->getData($attributeCode);
            }
        }
        return $result;
    }

    protected function _isAllowedAttribute($attributeCode, $type, array $attributes = null) {
        if (!empty($attributes)
                && !(in_array($attributeCode, $attributes))) {
            return false;
        }
        if (in_array($attributeCode, $this->_ignoredAttributeCodes['global'])) {
            return false;
        }
        if (isset($this->_ignoredAttributeCodes[$type])
                && in_array($attributeCode, $this->_ignoredAttributeCodes[$type])) {
            return false;
        }
        return true;
    }

}