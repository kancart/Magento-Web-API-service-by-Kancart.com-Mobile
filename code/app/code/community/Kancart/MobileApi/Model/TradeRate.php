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
class Kancart_MobileApi_Model_TradeRate extends Kancart_MobileApi_Model_Abstract {
	public function getTradeRates($apidata) {
		$fields = $apidata['fields'];
		$item_id = $apidata['item_id'];
		$rates = $this->getRates($item_id);
		$result = Mage::getModel('mobileapi/Result');
		$result->setResult('0x0000', $rates);
		return $result->returnResult();
	}
        
	public function addTradeRate($apidata) {
		$result = Mage::getModel('mobileapi/Result');
		$data = array();
		$data['ratings'] = array();
		$r = (int)$apidata['rating'];
		$data['ratings']['1'] = (string)$r;
		$data['ratings']['2'] = (string)($r+5);
		$data['ratings']['3'] = (string)($r+10);
		$data['nickname'] = Mage::getSingleton('customer/session')->getCustomer()->lastname;
		$data['title'] = $apidata['title'];
		$data['detail'] = $apidata['content'];
		$rating = $data['ratings'];
		if (($product = $this->_loadProduct($apidata['item_id'])) && !empty($data)) {
			$review     = Mage::getModel('review/review')->setData($data);
			$validate = $review->validate();
			if ($validate === true) {
				try {
					$review->setEntityId($review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
					->setEntityPkValue($product->getId())
					->setStatusId(Mage_Review_Model_Review::STATUS_PENDING)
					->setCustomerId(Mage::getSingleton('customer/session')->getCustomerId())
					->setStoreId(Mage::app()->getStore()->getId())
					->setStores(array(Mage::app()->getStore()->getId()))
					->save();
					foreach ($rating as $ratingId => $optionId) {
						Mage::getModel('rating/rating')
						->setRatingId($ratingId)
						->setReviewId($review->getId())
						->setCustomerId(Mage::getSingleton('customer/session')->getCustomerId())
						->addOptionVote($optionId, $product->getId());
					}
					$review->aggregate();
					$result->setResult('0x0000', 'Your review has been accepted for moderation.');
				}
				catch (Exception $e) {
					$result->setResult('0x8000', null, null, $e->getMessage());
				}
			}
			else {
				if (is_array($validate)) {
					$errors = array();
					foreach ($validate as $errorMessage) {
						array_push($errors,$errorMessage);
					}
					$result->setResult('0x8000', null, null, $errors);
				}
				else {
					$result->setResult('0x8000', null, null, 'Unable to post the review.');
				}
			}
		}
		return $result->returnResult();
	}
	public function getRates($productId, $storeId=0) {
		$collection = Mage::getResourceModel('review/review_collection')
		->addEntityFilter('product', $productId)
		->addStoreFilter(Mage::app()->getStore()->getId())
		->addStatusFilter('approved')
		->setDateOrder();
		if (!$collection) {
			$result = array();
			$result['total_results'] = 0;
			$result['trade_rates'] = array();
			return $result;
		}
		$tradeRates = array();
		foreach ($collection->getItems() as $review) {
			$tradeRate = array();
			$tradeRate['item_id'] = $productId;
			$tradeRate['uname'] = $review->getNickname();
			$tradeRate['rate_title'] = $review->getTitle();
			$tradeRate['rate_content'] = $review->getDetail();
			$tradeRate['rate_date'] = $review->getCreatedAt();
			$tradeRate['rate_score'] = '0';
			$summary = Mage::getModel('rating/rating')->getReviewSummary($review->getId());
			if ($summary->getCount() > 0) {
				$rating = round($summary->getSum() / $summary->getCount() / 20);
			}
			if ($rating) {
				$tradeRate['rate_score'] = (string)$rating;
			}
			array_push($tradeRates, $tradeRate);
		}
		$result = array();
		$result['total_results'] = $collection->getSize();
		$result['trade_rates'] = $tradeRates;
		return $result;
	}
	protected function _loadProduct($productId)
	{
		if (!$productId) {
			return false;
		}
		$product = Mage::getModel('catalog/product')
		->setStoreId(Mage::app()->getStore()->getId())
		->load($productId);
		if (!$product->getId() || !$product->isVisibleInCatalog() || !$product->isVisibleInSiteVisibility()) {
			return false;
		}
		return $product;
	}
}