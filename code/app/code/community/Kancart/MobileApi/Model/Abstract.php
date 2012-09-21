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
abstract class Kancart_MobileApi_Model_Abstract extends Mage_Core_Model_Abstract {

    public function __() {
        $args = func_get_args();
        return Mage::app()->getTranslator()->translate($args);
    }

    public function isEmptyString($string) {
        if (!is_string($string)) {
            return true;
        }
        if ($string == '0') {
            return false;
        }
        if (empty($string)) {
            return true;
        }
        return false;
    }

    public function _toAddressData($data) {
        $addressData = array(
            'address_book_id' => $data['entity_id'],
            'firstname' => $data['firstname'],
            'lastname' => $data['lastname'],
            'gender' => null,
            'suffix' => $data['suffix'],
            'mobile' => $data['telephone'],
            'company' => $data['company'],
            'fax' => $data['fax'],
            'telephone' => $data['telephone'],
            'tax_code' => null,
            'postcode' => $data['postcode'],
            'city' => $data['city'],
            'address1' => $data['street1'],
            'address2' => $data['street2'],
            'country_id' => $data['country_id'],
            'country_code' => $data['iso2_code'],
            'country_name' => $data['country'],
            'zone_id' => $data['region_id'],
            'zone_code' => $data['code'],
            'zone_name' => $data['region'],
            'state' => $data['region']
        );
        if ($data['is_default_billing'] && $data['is_default_shipping'])
            $addressData['address_type'] = 'bill,ship';
        else if ($data['is_default_billing'])
            $addressData['address_type'] = 'bill';
        else if ($data['is_default_shipping'])
            $addressData['address_type'] = 'ship';
        return $addressData;
    }

    public function prepareAddressData(Mage_Customer_Model_Address $address, $defaultBillingID=0, $defaultShippingID=0) {
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