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
class Kancart_MobileApi_Model_OrderStatus extends Kancart_MobileApi_Model_Abstract {
	public function getOrderStatuses() {
		$result = Mage::getModel('mobileapi/Result');
		$statuses = array();
		$statuses = Mage::getSingleton('sales/order_config')->getStatuses();
		$orderStatuses = array();
		$orderStatus = null;
		foreach ($statuses as $key => $value) {
			$orderStatus = array();
			$orderStatus['status_id'] = $key;
			$orderStatus['status_name'] = $value;
			$orderStatus['display_test'] = $value;
			array_push($orderStatuses, $orderStatus);
		}
		$result->setResult('0x0000', array('order_statuses' => $orderStatuses));
		return $result->returnResult();
	}
}