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
class Kancart_MobileApi_Model_Currency extends Kancart_MobileApi_Model_Abstract {
	public function getCurrencies() {
		$currency = Mage::getModel('core/store')->load(Mage::app()->getStore()->getId())->getCurrentCurrencyCode();
		$result = Mage::getModel('mobileapi/Result');
		$result->setResult('0x0000', array('currencies'=>array($this->formatCurrency($currency))));
		return $result->returnResult();
	}
	public function formatCurrency($currency) {
		$data = array();
		$data['currency_code'] = $currency;
		$data['currency_symbol'] = "US$";
		$data['currency_symbol_right'] = FALSE;
		$data['decimal_symbol'] = ".";
		$data['group_symbol'] = ",";
		$data['decimal_places'] = 2;
		$data['description'] = "United States Dollar";
		return $data;
	}
	public function getConfigCurrencies($path) {
		$read = $this->_getReadAdapter();
		$select = $read->select()
		->from($this->getTable('core/config_data'))
		->where($read->quoteInto(' path = ? ', $path))
		->order(' value ASC ');
		$data = $read->fetchAll($select);
		$tmp_array = array();
		foreach ($data as $configRecord) {
			$tmp_array = array_merge($tmp_array, explode(',', $configRecord['value']));
		}
		$data = array_unique($tmp_array);
		return $data;
	}
}