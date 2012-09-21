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
class Kancart_MobileApi_Model_Country extends Kancart_MobileApi_Model_Abstract {
	public function getCountries() {
		$collection = Mage::getModel('directory/country')->getCollection();
		$result = array();
		foreach ($collection as $country) {
			$country->getName(); 
			$result[] = $country->toArray(array('country_id', 'iso2_code', 'iso3_code', 'name'));
		}
		$countries = array();
		for ($i = 0; $i < count($result); $i++) {
			$countries[$i]['country_id'] = $result[$i]['country_id'];
			$countries[$i]['country_name'] = $result[$i]['name'];
			$countries[$i]['country_iso_code_2'] = $result[$i]['iso2_code'];
			$countries[$i]['country_iso_code_3'] = $result[$i]['iso3_code'];
		}
		$result = Mage::getModel('mobileapi/Result');
		$result->setResult('0x0000', array('countries' => $countries));
		return $result->returnResult();
	}
}
