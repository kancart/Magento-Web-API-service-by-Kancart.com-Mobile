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
class Kancart_MobileApi_Model_Result extends Kancart_MobileApi_Model_Abstract {
	const STATUS_FAIL = 'fail';
	const STATUS_SUCCESS = 'success';
	const ERROR_0x0001 = 'Invalid API (System)';     
	const ERROR_0x0002 = 'Invalid SessionKey (System)';   
	const ERROR_0x0003 = 'Time error over 10min (System)';    
	const ERROR_0x0004 = 'Invalid response format (System)';    
	const ERROR_0x0005 = 'Invalid API version (System)';   
	const ERROR_0x0006 = 'Invalid encryption method (System)'; 
	const ERROR_0x0007 = 'Language is not supported (System)'; 
	const ERROR_0x0008 = 'Currency is not supported (System)'; 
	const ERROR_0x0009 = 'Authentication failed (System)';    
	const ERROR_0x0010 = 'Time out (System)';     
	const ERROR_0x0011 = 'Data error (System)';     
	const ERROR_0x0012 = 'DataBase error (System)';    
	const ERROR_0x0013 = 'Server error (System)';    
	const ERROR_0x0014 = 'Permission denied (System)';   
	const ERROR_0x0015 = 'Service unavailable (System)';   
	const ERROR_0x0016 = 'Invalid signature (System)';   
	const ERROR_0x0017 = 'Invalid session ID (System)';   
	const ERROR_0x0018 = 'Invalid method (System)';   
	const ERROR_0x1001 = 'Invalid login or password (User)';   
	const ERROR_0x1002 = 'Login and password are required (User)'; 
	const ERROR_0x1003 = 'Verification code error (User)';    
	const ERROR_0x1004 = 'Invalid AddressID (User)';    
	const ERROR_0x1005 = 'Invalid return fields (User)';    
	const ERROR_0x1006 = 'No information in the region (User)';    
	const ERROR_0x1007 = 'User authentication problem (User)';    
	const ERROR_0x1008 = 'User not logged in (User)';
	const ERROR_0x1009 = 'You are already logged in (User)';
	const ERROR_0x1010 = 'Invalid user data (User)';
	const ERROR_0x1011 = 'Input parameter error (User)';    
	const ERROR_0x2001 = 'Invalid return fields (Category)';    
	const ERROR_0x2002 = 'Input parameter error (Category)';    
	const ERROR_0x2003 = 'No subcategory in it. (Category)';    
	const ERROR_0x3001 = 'Invalid return fields (Item)';    
	const ERROR_0x3002 = 'Input parameter error (Item)';    
	const ERROR_0x4001 = 'Invalid return fields (Postage)';    
	const ERROR_0x4002 = 'Input parameter error (Postage)';    
	const ERROR_0x5001 = 'Invalid ItemID (Cart)';    
	const ERROR_0x5002 = 'Product is unavailable (Cart)';
	const ERROR_0x5003 = 'Input parameter error (Cart)';    
	const ERROR_0x6001 = 'Invalid return fields (Order)';    
	const ERROR_0x6002 = 'Invalid OrderID (Order)';    
	const ERROR_0x6003 = 'Input parameter error (Order)';    
	const ERROR_0x7001 = 'User does not exist (Favorites)';    
	const ERROR_0x7002 = 'Invalid ItemID (Favorites)';    
	const ERROR_0x8001 = 'Invalid return fields (Rating)';    
	const ERROR_0x8002 = 'Invalid ItemID (Rating)';    
	const ERROR_0x8003 = 'Input parameter error (Rating)';    
	const ERROR_0x8004 = 'User does not exist (Rating)';    
	protected $result;
	protected $code;
	protected $info;
	protected $fields;
	public function setResult($code, $info=null, $fields=null, $exMsg=null) {
		if ($code == '0x0000') {
			$this->result = self::STATUS_SUCCESS;
			$this->code = $code;
			$this->info = $info;
			$this->fields = $fields;
		} else {
			$this->result = self::STATUS_FAIL;
			$this->code = $code;
			$this->info = array();
			if (is_null($exMsg)) {
				$this->info['err_msg'] = constant('self::ERROR_' . $code);
			} else {
				$this->info['err_msg'] = $exMsg;
			}
			if (is_null($this->info['err_msg'])) {
				$this->info['err_msg'] = 'Undefied error';
			}
		}
	}
	public function returnResult() {
		if (empty($this->fields))
		return array('result' => $this->result, 'code' => $this->code, 'info' => $this->info);
		else
		return array('result' => $this->result, 'code' => $this->code, 'info' => $this->arrayFromFields($this->fields, $this->objectToArray($this->info)));
	}
	function objectToArray($object) {
		return Zend_Json::decode(Zend_Json::encode($object), true);
	}
	function arrayFromFields($fieldstr, $arrayitem) {
		if (is_array($arrayitem[0])) {
			$results = array();
			foreach ($arrayitem as $item) {
				$result = array();
				$fields = explode(',', $fieldstr);
				foreach ($item as $key => $value) {
					for ($i = 0; $i < count($fields); $i++) {
						if ($fields[$i] == $key) {
							$result[$key] = $value;
						}
					}
				}
				array_push($results, $result);
			}
			return $results;
		} else {
			$result = array();
			$fields = explode(',', $fieldstr);
			foreach ($arrayitem as $key => $value) {
				for ($i = 0; $i < count($fields); $i++) {
					if ($fields[$i] == $key) {
						$result[$key] = $value;
					}
				}
			}
			return $result;
		}
	}
}