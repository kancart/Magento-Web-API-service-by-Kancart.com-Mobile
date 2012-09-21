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
class Kancart_MobileApi_Model_User extends Kancart_MobileApi_Model_Abstract {

    protected function _construct() {
        
    }

    public function Register($userData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        $session->setEscapeMessages(true);
        $errors = array();
        $customer = Mage::registry('current_customer');
        if (is_null($customer)) {
            $customer = Mage::getModel('customer/customer');
        }
        if (isset($userData['isSubscribed'])) {
            $customer->setIsSubscribed(1);
        }
        $customer->getGroupId();
        try {
            $desPassword = Mage::helper('mobileapi/CryptoUtil')->Crypto($userData['pwd'], 'AES-256', KANCART_APP_SECRECT, false);
            $customer->setPassword($desPassword);
            $customer->setData('email', $userData['uname']);
            $customer->setData('firstname', $userData['firstname']);
            $customer->setData('lastname', $userData['lastname']);
            $validationResult = count($errors) == 0;
            if (true === $validationResult) {
                $customer->save();
                $session->setCustomerAsLoggedIn($customer);
                $customer->sendNewAccountEmail('registered');
                $result->setResult('0x0000', array());
            } else {
                if (is_array($errors)) {
                    $result->setResult('0x1000', null, null, implode("\n", $errors));
                } else {
                    $result->setResult('0x1010');
                }
            }
        } catch (Mage_Core_Exception $e) {
            if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_EMAIL_EXISTS) {
                $result->setResult('0x1000', null, null, 'An account with this email address already exists.');
                $session->setEscapeMessages(false);
            } else {
                $result->setResult('0x1000', null, null, $e->getMessage());
            }
            return $result->returnResult();
        } catch (Exception $e) {
            $result->setResult('0x1000', null, null, $e->getMessage());
            return $result->returnResult();
        }
        return $result->returnResult();
    }

    public function Update($userData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $customer = $this->_getSession()->getCustomer();
        $customerForm = Mage::getModel('customer/form');
        $customerForm->setFormCode('customer_account_edit')
                ->setEntity($customer);
        $errors = array();
        $customerErrors = $customerForm->validateData($userData);
        if ($customerErrors !== true) {
            $errors = array_merge($customerErrors, $errors);
        } else {
            $customerForm->compactData($userData);
            $customerErrors = $customer->validate();
            if (is_array($customerErrors)) {
                $errors = array_merge($customerErrors, $errors);
            }
        }
        if (!empty($errors)) {
            $result->setResult('0x1000', null, null, implode("\n", $errors));
            return $result->returnResult();
        }
        try {
            $customer->save();
            $this->_getSession()->setCustomer($customer);
            $result->setResult('0x0000');
            return $result->returnResult();
        } catch (Mage_Core_Exception $e) {
            $this->_message($e->getMessage(), self::MESSAGE_STATUS_ERROR);
        } catch (Exception $e) {
            $result->setResult('0x1000', null, null, $e->getMessage());
            return $result->returnResult();
        }
    }

    public function Login($userLoginData) {
        $uname = $userLoginData['uname'];
        $pwd = $userLoginData['pwd'];
        $session = $this->_getSession();
        $desPassword = Mage::helper('mobileapi/CryptoUtil')->Crypto($pwd, 'AES-256', KANCART_APP_SECRECT, false);
        $result = Mage::getModel('mobileapi/Result');
        if ($uname != null && $pwd != null) {
            try {
                if ($session->login($uname, $desPassword)) {
                    if ($session->getCustomer()->getIsJustConfirmed()) {
                        $session->getCustomer()->sendNewAccountEmail('confirmed');
                    }
                    $result->setResult('0x0000', array('sessionkey' => md5(time())));
                } else {
                    $result->setResult('0x1001');
                }
            } catch (Mage_Core_Exception $e) {
                switch ($e->getCode()) {
                    case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
                        $result->setResult('0x1000', null, null, $e->getMessage());
                        return $result->returnResult();
                        break;
                    case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
                        $result->setResult('0x1000', null, null, $e->getMessage());
                        return $result->returnResult();
                        break;
                    default:
                        $result->setResult('0x1000', null, null, $e->getMessage());
                        return $result->returnResult();
                }
            } catch (Exception $e) {
                $result->setResult('0x1000', null, null, $e->getMessage());
            }
        } else {
            $result->setResult('0x1002');
        }
        return $result->returnResult();
    }

    public function Get($userData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $fields = $userData['fields'];
        $uname = $userData['uname'];
        $session = $this->_getSession();
        $result->setResult('0x0000', $this->_toUserData($session->getCustomer()), $fields);
        return $result->returnResult();
    }

    public function IsExists($userData) {
        $info = array(
            'uname_is_exist' => true
        );
        $result = Mage::getModel('mobileapi/Result');
        try {
            $customer = Mage::getModel('customer/customer');
            $customer->setStoreId(1);
            $customer->setWebsiteId(1);
            $customer->loadByEmail($userData['uname']);
            if ($customer->getData('entity_id')) {
                $info['uname_is_exist'] = true;
            } else {
                $info['uname_is_exist'] = false;
            }
            $result->setResult('0x0000', $info);
        } catch (Exception $e) {
            $result->setResult('0x1000', null, null, $e->getMessage());
            return $result->returnResult();
        }
        return $result->returnResult();
    }

    public function Logout() {
        $result = Mage::getModel('mobileapi/Result');
        try {
            $this->_getSession()->logout();
            $result->setResult('0x0000');
        } catch (Mage_Core_Exception $e) {
            $result->setResult('0x1000', null, null, $e->getMessage());
            return $result->returnResult();
        } catch (Exception $e) {
            $result->setResult('0x1000', null, null, $e->getMessage());
            return $result->returnResult();
        }
        return $result->returnResult();
    }

    public function AddressGet($queryData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        if (isset($queryData['address_book_id'])) {
            $fields = $queryData['fields'];
            $uname = $queryData['uname'];
            $addressId = $queryData['address_book_id'];
            if (!$this->_getSession()->isLoggedIn()) {
                $result->setResult('0x1008');
                return $result->returnResult();
            }
            $customer = $this->_getSession()->getCustomer();
            $addressesData = $customer->getAddressById($addressId);
            if (!is_null($addressesData->getId())) {
                $result->setResult('0x0000', array('address' => $this->_toAddressData($this->prepareAddressData($addressesData, $defaultBillingID, $defaultShippingID), $fields)));
            } else {
                $result->setResult('0x1000', null, null, 'No matched address data.');
            }
            return $result->returnResult();
        }
        $result->setResult('0x1000', null, null, 'Parameter address_book_id missing.');
        return $result->returnResult();
    }

    public function AddressesGet($queryData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $fields = $queryData['fields'];
        $uname = $queryData['uname'];
        $customer = $this->_getSession()->getCustomer();
        $addressesData = $customer->getAddresses();
        $defaultBilling = $customer->getDefaultBillingAddress();
        $defaultShipping = $customer->getDefaultShippingAddress();
        if ($defaultBilling)
            $defaultBillingID = $defaultBilling->getId();
        if ($defaultShipping)
            $defaultShippingID = $defaultShipping->getId();
        if (count($addressesData)) {
            $addresses = array();
            foreach ($addressesData as $value) {
                array_push($addresses, $this->_toAddressData($this->prepareAddressData($value, $defaultBillingID, $defaultShippingID)));
            }
            $result->setResult('0x0000', array('addresses' => $addresses));
        } else {
            $result->setResult('0x0000');
        }
        return $result->returnResult();
    }

    public function AddressSave($addressData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        if (!is_null($addressData)) {
            $customer = $session->getCustomer();
            $address = Mage::getModel('customer/address');
            $addressId = $addressData['address_book_id'];
            if ($addressId) {
                $existsAddress = $customer->getAddressById($addressId);
                if ($existsAddress->getId() && $existsAddress->getCustomerId() == $customer->getId()) {
                    $address->setId($existsAddress->getId());
                }
            }
            $errors = array();
            try {
                $addressType = explode(',', $addressData['address_type']);
                $address->setCustomerId($customer->getId())
                        ->setIsDefaultBilling(strtolower($addressType[0]) == 'billing' || strtolower($addressType[1]) == 'billing')
                        ->setIsDefaultShipping(strtolower($addressType[0]) == 'shipping' || strtolower($addressType[1]) == 'shipping');
                $address->setLastname($addressData['lastname']);
                $address->setFirstname($addressData['firstname']);
                $address->setSuffix($addressData['suffix']);
                $address->setTelephone($addressData['telephone']);
                $address->setCompany($addressData['company']);
                $address->setFax($addressData['fax']);
                $address->setPostcode($addressData['postcode']);
                $address->setCity($addressData['city']);
                $address->setStreet(array($addressData['address1'], $addressData['address2']));
                $address->setCountry($addressData['country_name']);
                $address->setCountryId($addressData['country_id']);
                if (isset($addressData['state'])) {
                    $address->setRegion($addressData['state']);
                    $address->setRegionId(null);
                } else {
                    $address->setRegion($addressData['zone_name']);
                    $address->setRegionId($addressData['zone_id']);
                }
                $addressErrors = $address->validate();
                if ($addressErrors !== true) {
                    $errors = array_merge($errors, $addressErrors);
                }
                $addressValidation = count($errors) == 0;
                if (true === $addressValidation) {
                    $address->save();
                    $result->setResult('0x0000', new ArrayObject());
                    return $result->returnResult();
                } else {
                    if (is_array($errors)) {
                        $result->setResult('0x1000', null, null, $errors);
                    } else {
                        $result->setResult('0x1000', null, null, 'Can\'t save address');
                    }
                    return $result->returnResult();
                }
            } catch (Mage_Core_Exception $e) {
                $result->setResult('0x1000', null, null, $e->getMessage());
                return $result->returnResult();
            } catch (Exception $e) {
                $result->setResult('0x1000', null, null, $e->getMessage());
                return $result->returnResult();
            }
        } else {
            $result->setResult('ERROR_0x1011');
            return $result->returnResult();
        }
    }

    public function AddressRemove($addressData) {
        $session = $this->_getSession();
        $result = Mage::getModel('mobileapi/Result');
        if (!$session->isLoggedIn()) {
            $result->setResult('0x0002');
            return $result->returnResult();
        }
        $addressId = $addressData['address_book_id'];
        if ($addressId) {
            $address = Mage::getModel('customer/address')->load($addressId);
            if ($address->getCustomerId() != $this->_getSession()->getCustomerId()) {
                $result->setResult('0x1000', null, null, 'Address does not belong to this customer.');
                return $result->returnResult();
                ;
            }
            try {
                $address->delete();
                $result->setResult('0x0000', new ArrayObject());
                return $result->returnResult();
            } catch (Exception $e) {
                $result->setResult('0x1000', null, null, $e->getMessage());
                return $result->returnResult();
            }
        }
    }

    protected function _getSession() {
        return Mage::getSingleton('customer/session');
    }

    protected function _toUserData($customerData) {
        $userData = array(
            'uname' => $customerData->email,
            'nick' => $customerData->firstname . $customerData->lastname,
            'email' => $customerData->email,
            'fax' => null,
            'telephone' => null,
            'default_address_id' => null,
            'dob' => null,
            'lastname' => $customerData->lastname,
            'firstname' => $customerData->firstname,
            'gender' => null,
            'mobile' => null
        );
        return $userData;
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
            'country_name' => $data['country']
        );
        if ($data['region_id']) {
            $addressData['zone_id'] = $data['region_id'];
            $addressData['zone_name'] = $data['region'];
        } else {
            $addressData['state'] = $data['region'];
        }
        if ($data['is_default_billing'] && $data['is_default_shipping'])
            $addressData['address_type'] = 'billing,shipping';
        else if ($data['is_default_billing'])
            $addressData['address_type'] = 'billing';
        else if ($data['is_default_shipping'])
            $addressData['address_type'] = 'shipping';
        return $addressData;
    }

    public function prepareAddressData(Mage_Customer_Model_Address $address, $defaultBillingID = 0, $defaultShippingID = 0) {
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
