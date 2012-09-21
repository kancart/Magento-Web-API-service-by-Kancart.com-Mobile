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
class Kancart_MobileApi_Model_Item extends Kancart_MobileApi_Model_Abstract {

    public function getItem($apidata) {
        $fields = $apidata['fields'];
        $item_id = $apidata['item_id'];
        $item = array();
        $product = Mage::getModel('catalog/product')->load($item_id);
        if ($product && $product->getId()) {
            $helper = Mage::helper('catalog/image');
            $item['item_id'] = $product->getId();
            $item['item_title'] = $product->getName();
            $item['thumb_description'] = $product->getShortDescription();
            $item['detail_description'] = $product->getDescription();
            $item['item_url'] = $product->getProductUrl();
            $item['cid'] = $product->getCategoryIds();
            $item['qty'] = $product->getStockItem()->getQty();
            $itemImageStr = 'image';
            $itemData = $product->getData();
            if (isset($itemData['image'])) {
                $itemImageStr = 'image';
            } else if (isset($itemData['small_image'])) {
                $itemImageStr = 'small_image';
            } else if (isset($itemData['thumbnail'])) {
                $itemImageStr = 'thumbnail';
            }
            $item['thumbnail_pic_url'] = (string) $helper->init($product, $itemImageStr)->resize(320, 320);
            $item['main_pic_url'] = (string) $helper->init($product, $itemImageStr)->resize(640, 640);
            $prices = array();
            $productType = $product->getTypeId();
            switch ($productType) {
                case Mage_Catalog_Model_Product_Type::TYPE_SIMPLE: {
                        $item['skus'] = $this->getProductCustomOptionsOption($product);
                        $prices = $this->collectProductPrices($product);
                    } break;
                case Mage_Catalog_Model_Product_Type::TYPE_BUNDLE: {
                        if ($product->getTypeInstance(true)->hasOptions($product)) {
                            $item['skus'] = $this->getProductBundleOptions($product);
                        }
                        $prices = $this->collectBundleProductPrices($product);
                    } break;
                case Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE: {
                        if ($product->isConfigurable()) {
                            $item['skus'] = $this->getProductOptions($product);
                        }
                        $prices = $this->collectProductPrices($product);
                    } break;
                case Mage_Catalog_Model_Product_Type::TYPE_GROUPED: {
                        $item['skus'] = $this->getProductGroupedOptions($product);
                        $prices = $this->collectProductPrices($product);
                    } break;
                case Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL: {
                        $item['skus'] = array();
                        $prices = $this->collectProductPrices($product);
                    } break;
                default: {
                        $item['skus'] = array();
                        $prices = $this->collectProductPrices($product);
                    } break;
            }
            $item['prices'] = $prices;
            $item['is_virtual'] = $product->isVirtual();
            if (!$product->getRatingSummary()) {
                Mage::getModel('review/review')
                        ->getEntitySummary($product, Mage::app()->getStore()->getId());
            }
            $item['rating_score'] = round((int) $product->getRatingSummary()->getRatingSummary() / 20);
            $item['rating_count'] = $product->getRatingSummary()->getReviewsCount();
            $item['sales_type'] = ($product->isInStock()) ? 'stock' : 'distribute';
            $item['qty_min_unit'] = 1;
            $stockItem = $product->getStockItem();
            if ($stockItem) {
                if ($stockItem->getMinSaleQty() && $stockItem->getMinSaleQty() > 0) {
                    $item['qty_min_unit'] = ($stockItem->getMinSaleQty() * 1);
                }
            }
            $item['item_imgs'] = array();
            if ($product->getMediaGalleryImages()) {
                $images = $product->getMediaGalleryImages();
                $i = 0;
                foreach ($images as $image) {
                    $itemImg = array();
                    $itemImg['img_id'] = $image->getId();
                    $itemImg['img_url'] = (string) $helper->init($product, $itemImageStr, $image->getFile())->resize(640, 640);
                    $itemImg['position'] = $i;
                    array_push($item['item_imgs'], $itemImg);
                    $i++;
                }
                if ($i == 0) {
                    $itemImg = array();
                    $itemImg['img_id'] = 0;
                    $itemImg['img_url'] = (string) $helper->init($product, $itemImageStr)->resize(640, 640);
                    $itemImg['position'] = 0;
                    array_push($item['item_imgs'], $itemImg);
                }
            } else {
                $itemImg = array();
                $itemImg['img_id'] = 0;
                $itemImg['img_url'] = (string) $helper->init($product, $itemImageStr)->resize(640, 640);
                $itemImg['position'] = 0;
                array_push($item['item_imgs'], $itemImg);
            }
        }

        $result = Mage::getModel('mobileapi/Result');
        $result->setResult('0x0000', array('item' => $item));
        return $result->returnResult();
    }

    public function getItems($apidata) {
        $fields = $apidata['fields'];
        $cid = $apidata['cid'];
        $query = $apidata['query'];
        $pageNo = isset($apidata['page_no']) ? $apidata['page_no'] : 1;
        $pageSize = isset($apidata['page_size']) ? $apidata['page_size'] : 20;
        $orderBy = isset($apidata['order_by']) ? $apidata['order_by'] : 'relevance';
        $searchResult = $this->items($apidata, $query, $cid, $pageNo, $pageSize, $orderBy);
        $result = Mage::getModel('mobileapi/Result');
        $result->setResult('0x0000', $searchResult);
        return $result->returnResult();
    }

    public function items($params, $query=false, $cid=false, $pageNo = 1, $pageSize = 2, $orderBy = 'relevance', $filters = null, $store = null) {
        $helper = Mage::helper('catalogsearch');
        $query = $helper->getQuery();
        $query->setStoreId(Mage::app()->getStore()->getId());
        if ($query->getQueryText()) {
            if ($helper->isMinQueryLength()) {
                $query->setId(0)
                        ->setIsActive(1)
                        ->setIsProcessed(1);
            } else {
                if ($query->getId()) {
                    $query->setPopularity($query->getPopularity() + 1);
                } else {
                    $query->setPopularity(1);
                }
                if (false && $query->getRedirect()) {
                    $query->save();
                    $this->getResponse()->setRedirect($query->getRedirect());
                    return;
                } else {
                    $query->prepare();
                }
            }
            $helper->checkNotes();
            if (!Mage::helper('catalogsearch')->isMinQueryLength()) {
                $query->save();
            }
        }
        $collection = $helper->getEngine()->getResultCollection();
        $collection->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
                ->setStore(Mage::app()->getStore())
                ->addAttributeToSelect('image')
                ->addMinimalPrice()
                ->addFinalPrice()
                ->addTaxPercents()
                ->addStoreFilter(Mage::app()->getStore()->getId());
        $category = Mage::getModel('catalog/category');
        if (isset($cid) && $category->checkId($cid)) {
            $category->setStoreId(Mage::app()->getStore()->getId())
                    ->load($cid);
            $collection->addCategoryFilter($category);
        }
        if (isset($params[$helper->getQueryParamName()]) && strlen($params[$helper->getQueryParamName()]) > 0) {
            $collection->addSearchFilter($query->getQueryText());
        }
        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($collection);
        $orderKey = 'relevance';
        $orderValue = 'desc';
        switch ($orderBy) {
            case 'relevance': {
                    $orderKey = 'relevance';
                    $orderValue = 'desc';
                }break;
            case 'name': {
                    $orderKey = 'name';
                    $orderValue = 'desc';
                }break;
            case 'price:asc': {
                    $orderKey = 'price';
                    $orderValue = 'asc';
                }break;
            case 'price:desc': {
                    $orderKey = 'price';
                    $orderValue = 'desc';
                }break;
            default: {
                    $orderKey = 'relevance';
                    $orderValue = 'asc';
                }break;
        }
        $collection->addAttributeToSort($orderKey, $orderValue);
        $collection->setPage($pageNo, $pageSize);
        $collection->load();
        $size = $collection->getSize();
        $productList = $collection->getItems();
        $items = array();
        $imageHelper = Mage::helper('catalog/image');
        foreach ($productList as $product) {
            $item = array();
            if ($product && $product->getId()) {
                $item['item_id'] = $product->getId();
                $item['item_title'] = $product->getName();
                $item['thumb_description'] = $product->getShortDescription();
                $item['detail_description'] = $product->getDescription();
                $item['item_url'] = $product->getProductUrl();
                $item['cid'] = $product->getCategoryIds();
                $item['qty'] = $product->getStockItem()->getQty();

                $itemData = $product->getData();
                $itemImageStr = 'image';
                if (isset($itemData['image'])) {
                    $itemImageStr = 'image';
                } else if (isset($itemData['small_image'])) {
                    $itemImageStr = 'small_image';
                } else if (isset($itemData['thumbnail'])) {
                    $itemImageStr = 'thumbnail';
                }

                $item['thumbnail_pic_url'] = (string) $imageHelper->init($product, $itemImageStr)->resize(320, 320);
                $item['main_pic_url'] = (string) $imageHelper->init($product, $itemImageStr)->resize(640, 640);
                $prices = array();
                $prices['currency'] = Mage::getModel('core/store')->load(Mage::app()->getStore()->getId())->getCurrentCurrencyCode();
                $productType = $product->getTypeId();
                if ($productType == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                    $prices = $this->collectBundleProductPrices($product);
                } else {
                    $prices = $this->collectProductPrices($product);
                }
                $item['prices'] = $prices;
                $item['item_imgs'] = array();
                $item['is_virtual'] = $product->isVirtual();
                if (!$product->getRatingSummary()) {
                    Mage::getModel('review/review')
                            ->getEntitySummary($product, Mage::app()->getStore()->getId());
                }
                $item['rating_score'] = round((int) $product->getRatingSummary()->getRatingSummary() / 20);
                $item['rating_count'] = $product->getRatingSummary()->getReviewsCount();
                $item['sales_type'] = ($product->isInStock()) ? 'stock' : 'distribute';
                $item['qty_min_unit'] = 1;
                $stockItem = $product->getStockItem();
                if ($stockItem) {
                    if ($stockItem->getMinSaleQty() && $stockItem->getMinSaleQty() > 0) {
                        $item['qty_min_unit'] = ($stockItem->getMinSaleQty() * 1);
                    }
                }
            }
            array_push($items, $item);
        }
        $searchResult = array();
        $searchResult['items'] = $items;
        $searchResult['total_results'] = $size;
        return $searchResult;
    }

    public function info($productId, $store = null, $attributes = null, $identifierType = null) {
        $item = array();
        if ($product && $product->getId()) {
            $item['item_id'] = $product->getId();
            $item['item_title'] = $product->getName();
            $item['thumb_description'] = $product->getShortDescription();
            $item['description'] = $product->getDescription();
            $item['item_url'] = $product->getProductUrl();
            $item['cid'] = $product->getCategoryIds();
            $item['qty'] = $product->getStockItem()->getQty();
            $item['thumbnail_pic_url'] = $product->getThumbnailUrl();
            $item['main_pic_url'] = $product->getImageUrl();
            $item['original_price'] = $product->getPrice();
            $item['price'] = ($product->getSpecialPrice()) == null ? ($product->getPrice()) : ($product->getSpecialPrice());
            $item['currency'] = Mage::getModel('core/store')->load(Mage::app()->getStore()->getId())->getCurrentCurrencyCode();
            $item['is_virtual'] = $product->isVirtual();
            $item['skus'] = $this->getProductOptions($product);
            if (!$product->getRatingSummary()) {
                Mage::getModel('review/review')
                        ->getEntitySummary($product, Mage::app()->getStore()->getId());
            }
            $item['rating_score'] = round((int) $product->getRatingSummary()->getRatingSummary() / 20);
            $item['rating_count'] = $product->getRatingSummary()->getReviewsCount();
            $item['sales_type'] = ($product->isInStock()) ? 'stock' : 'distribute';
            $item['qty_min_unit'] = 1;
            $stockItem = $product->getStockItem();
            if ($stockItem) {
                if ($stockItem->getMinSaleQty() && $stockItem->getMinSaleQty() > 0) {
                    $item['qty_min_unit'] = ($stockItem->getMinSaleQty() * 1);
                }
            }
        }
        return $item;
    }

    public function getItemImgs($_product) {
        $result = array();
        $imgs = Mage::getModel('Catalog/Product_Attribute_Media_Api')->items($_product->getId());
        $i = 0;
        foreach ($imgs as $img) {
            $result[$i]['img_id'] = null;
            $result[$i]['img_url'] = $img['url'];
            $result[$i]['position'] = $img['position'];
            $i++;
        }
        return $result;
    }

    public function getItemTierPrice($_product) {
        $result = array();
        $tiers = Mage::getModel('Catalog/Product_Attribute_Tierprice_Api')->info($_product->getId());
        if (count($tiers) == 0) {
//            $result[0]['min_qty'] = $_product->getStockItem()->getMinSaleQty();
//            $result[0]['max_qty'] = $_product->getStockItem()->getMaxSaleQty();
//            $result[0]['price'] = $_product->getPrice();
//            $result[0]['currency'] = Mage::getModel('core/store')->load(Mage::app()->getStore()->getId())->getCurrentCurrencyCode();
//            $result[0]['leading_time'] = null;
            return $result;
        }
        for ($i = 0; $i < count($tiers); $i++) {
            $result[$i]['min_qty'] = $tiers[$i]['qty'];
            if (($i + 1) < count($tiers)) {
                $result[$i]['max_qty'] = $tiers[$i + 1]['qty'] - 1;
            } else {
                $result[$i]['max_qty'] = ($_product->getStockItem()->getMaxSaleQty()) == 0 ? $_product->getStockItem()->getQty() : ($_product->getStockItem()->getMaxSaleQty());
            }
            $result[$i]['price'] = $tiers[$i]['price'];
            $result[$i]['leading_time'] = null;
        }
        return $result;
    }

    public function getItemPriceRange($_product) {
        $tiers = Mage::getModel('Catalog/Product_Attribute_Tierprice_Api')->info($_product->getId());
        if ($tiers == null) {
            return $_product->getPrice();
        }
        $minprice = $tiers[0]['price'];
        foreach ($tiers as $tier) {
            if ($tier['price'] < $minprice) {
                $minprice = $tier['price'];
            }
        }
        return $minprice . '~' . $_product->getPrice();
    }

    public function isUserLoggedIn() {
        return Mage::getSingleton('customer/session')->isLoggedIn();
    }

    public function getSearchSuggest() {
        
    }

    public function getProductGroupedOptions(Mage_Catalog_Model_Product $product) {
        if (!$product->getId()) {
            return array();
        }
        if (!$product->isSaleable()) {
            return array();
        }
        /**
         * Grouped (associated) products
         */
        $_associatedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
        if (!sizeof($_associatedProducts)) {
            return array();
        }
        $skus = array();
        foreach ($_associatedProducts as $_item) {
            if (!$_item->isSaleable()) {
                continue;
            }
            $sku = array();

            $sku['sku_id'] = $_item->getId();
            $sku['mode'] = 'product';
            $sku['name'] = strip_tags($_item->getName());
            $sku['qty'] = $_item->getQty() * 1;
            $sku['is_editable'] = 1;

            /**
             * Process product price
             */
            if ($_item->getPrice() != $_item->getFinalPrice()) {
                $productPrice = $_item->getFinalPrice();
            } else {
                $productPrice = $_item->getPrice();
            }
            if ($productPrice > 0.00) {
                $sku['price'] = $productPrice;
            }
            $sku['values'] = array();
            array_push($skus, $sku);
        }
        return $skus;
    }

    public function getProductBundleOptions(Mage_Catalog_Model_Product $product) {
        
        if ($product->getTypeInstance(true)->hasOptions($product)) {
            $product->getTypeInstance(true)->setStoreFilter($product->getStoreId(), $product);
            $optionCollection = $product->getTypeInstance(true)->getOptionsCollection($product);
            $selectionCollection = $product->getTypeInstance(true)->getSelectionsCollection(
                    $product->getTypeInstance(true)->getOptionsIds($product), $product
            );
            $bundleOptions = $optionCollection->appendSelections($selectionCollection, false, false);
            if (!sizeof($bundleOptions)) {
                return array();
            }
            $skus = array();
            foreach ($bundleOptions as $_option) {
                $selections = $_option->getSelections();
                if (empty($selections)) {
                    continue;
                }
                $type = OPTION_TYPE_SELECT;
                if ($_option->isMultiSelection()) {
                    $type = OPTION_TYPE_MULTIPLE_SELECT;
                }
                $code = $_option->getId();
                if ($type == OPTION_TYPE_MULTIPLE_SELECT) {
                    $code .= '';
                }
                $sku = array();
                $sku['sku_id'] = $code;
                $sku['parent_id'] = $_option->getParentId();
                $sku['position'] = $_option->getPosition();
                $sku['allow_blank'] = $_option->getRequired() == '1' ? 'false' : 'true';
                $sku['mode'] = $this->_getOptionTypeForKanCartByRealType($_option->getType());
                $sku['name'] = $_option->getTitle();
                $sku['values'] = array();
                if (!$_option->getRequired() && $sku['mode'] != self::OPTION_TYPE_MULTIPLE_SELECT) {
                    $none = array(
                        'sku_id' => 'none',
                        'value_id' => '',
                        'name' => 'None',
                    );
                    array_push($sku['values'], $none);
                }
                $selections = $_option->getSelections();
                if (empty($selections)) {
                    array_push($skus, $sku);
                    continue;
                }
                foreach ($selections as $_selection) {
                    if (!$_selection->isSaleable()) {
                        continue;
                    }
                    $finalValue = array();
                    $finalValue['sku_id'] = $_option->getOptionId();
                    $finalValue['value_id'] = $_selection->getSelectionId();
                    $finalValue['name'] = $_selection->getName();
                    $finalValue['qty'] = !($_selection->getSelectionQty() * 1) ? '1' : $_selection->getSelectionQty() * 1;
                    $finalValue['is_default'] = $_selection->getIsDefault();
                    if (!$_option->isMultiSelection()) {
                        if ($_selection->getSelectionCanChangeQty()) {
                            $finalValue['is_qty_editable'] = 1;
                        }
                    }
                    $price = $product->getPriceModel()->getSelectionPreFinalPrice($product, $_selection);
                    if ((float) $price != 0.00) {
                        $finalValue['price'] = $price;
                    } else {
                        $finalValue['price'] = null;
                    }
                    array_push($sku['values'], $finalValue);
                }
                array_push($skus, $sku);
            }
            return $skus;
        }
        return false;
    }

    public function getProductOptions(Mage_Catalog_Model_Product $product) {
        $orgiOptions = $this->getProductCustomOptionsOption($product);
        $finalSkus = array();
        $options = array();
        if (!$product->isSaleable()) {
            return $orgiOptions;
        }
        $_attributes = $product->getTypeInstance(true)->getConfigurableAttributes($product);
        if (!sizeof($_attributes)) {
            return $orgiOptions;
        }
        $_allowProducts = array();
        $_allProducts = $product->getTypeInstance(true)->getUsedProducts(null, $product);
        foreach ($_allProducts as $_product) {
            if ($_product->isSaleable()) {
                $_allowProducts[] = $_product;
            }
        }
        foreach ($_allowProducts as $_item) {
            $_productId = $_item->getId();
            foreach ($_attributes as $attribute) {
                $productAttribute = $attribute->getProductAttribute();
                $attributeValue = $_item->getData($productAttribute->getAttributeCode());
                if (!isset($options[$productAttribute->getId()])) {
                    $options[$productAttribute->getId()] = array();
                }
                if (!isset($options[$productAttribute->getId()][$attributeValue])) {
                    $options[$productAttribute->getId()][$attributeValue] = array();
                }
                $options[$productAttribute->getId()][$attributeValue][] = $_productId;
            }
        }
        foreach ($_attributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            $attributeId = $productAttribute->getId();
            $info = array(
                'id' => $productAttribute->getId(),
                'label' => $attribute->getLabel(),
                'is_required' => $productAttribute->getIsRequired(),
                'options' => array()
            );
            $prices = $attribute->getPrices();
            if (is_array($prices)) {
                foreach ($prices as $value) {
                    if (!isset($options[$attributeId][$value['value_index']])) {
                        continue;
                    }
                    $price = $this->_preparePrice($product, $value['pricing_value'], $value['is_percent']);
                    $optionProducts = array();
                    if (isset($options[$attributeId][$value['value_index']])) {
                        $optionProducts = $options[$attributeId][$value['value_index']];
                    }
                    $info['options'][] = array(
                        'id' => $value['value_index'],
                        'label' => $value['label'],
                        'price' => $price,
                        'products' => $optionProducts,
                    );
                }
            }
            if (sizeof($info['options']) > 0) {
                $attributes[$attributeId] = $info;
            }
        }
        $isFirst = true;
        $_attributes = $attributes;
        reset($_attributes);
        foreach ($attributes as $id => $attribute) {
            $finalSku = array();
            $finalSku['sku_id'] = $id;
            $finalSku['allow_blank'] = $attribute['is_required'] == '1' ? 'false' : 'true';
            $finalSku['mode'] = 'select';
            $finalSku['name'] = $attribute['label'];
            $finalSku['values'] = array();
            if (!$attribute['is_required']) {
                $none = array(
                    'sku_id' => 'none',
                    'value_id' => '',
                    'name' => 'None',
                );
                array_push($finalSku['values'], $none);
            }
            if ($isFirst) {
                foreach ($attribute['options'] as $option) {
                    $finalValue = array();
                    $finalValue['sku_id'] = $id;
                    $finalValue['value_id'] = $option['id'];
                    $finalValue['name'] = $option['label'];
                    if ((float) $option['price'] != 0.00) {
                        $finalValue['price'] = $option['price'];
                    } else {
                        $finalValue['price'] = null;
                    }
                    if (sizeof($_attributes) > 1) {
                        $this->_prepareRecursivelyRelatedValues($finalValue, $_attributes, $option['products'], 1);
                    }
                    array_push($finalSku['values'], $finalValue);
                }
                $isFirst = false;
            }
            array_push($finalSkus, $finalSku);
        }    
        for ($i = 0; $i < count($finalSkus); $i++) {
            if (isset($finalSkus[$i]['values']) && count($finalSkus[$i]['values']) > 0) {
                for ($j = 0; $j < count($finalSkus[$i]['values']); $j++) {
                    if (isset($finalSkus[$i]['values'][$j]['values']) && count($finalSkus[$i]['values'][$j]['values']) > 0) {
                        for ($k = 0; $k < count($finalSkus[$i]['values'][$j]['values']); $k++) {
                            for ($g = 0; $g < count($finalSkus); $g++) {
                                if ($finalSkus[$i]['values'][$j]['values'][$k]['sku_id'] == $finalSkus[$g]['sku_id']) {
                                    array_push($finalSkus[$g]['values'], $finalSkus[$i]['values'][$j]['values'][$k]);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $finalSkus;
    }

    public function getProductCustomOptionsOption(Mage_Catalog_Model_Product $product) {
        $options = array();
        if (!$product->getId()) {
            return $options;
        }
        if (!$product->isSaleable() || !sizeof($product->getOptions())) {
            return $options;
        }
        foreach ($product->getOptions() as $option) {
            $optionObj = array();
            $type = $this->_getOptionTypeForKanCartByRealType($option->getType());
            $code = $option->getId();
            $optionObj['sku_id'] = $code;
            $optionObj['allow_blank'] = $option->getIsRequire() == '1' ? 'false' : 'true';
            $optionObj['mode'] = $type;
            $optionObj['name'] = $option->getTitle();
            $price = $option->getPrice();
            if ($price) {
                $optionObj['price'] = $price;
            } else {
                $optionObj['price'] = null;
            }
            $optionObj['values'] = array();
            if (!$option->getIsRequire() && $optionObj['mode'] != self::OPTION_TYPE_MULTIPLE_SELECT) {
                $none = array(
                    'sku_id' => 'none',
                    'value_id' => '',
                    'name' => 'None',
                );
                array_push($optionObj['values'], $none);
            }
            if ($type == self::OPTION_TYPE_SELECT) {
                foreach ($option->getValues() as $value) {
                    $optionValueObj = array();
                    $optionValueObj['sku_id'] = $code;
                    $optionValueObj['value_id'] = $value->getId();
                    $optionValueObj['name'] = $value->getTitle();
                    $price = $value->getPrice();
                    if ($price) {
                        $optionValueObj['price'] = $price;
                    } else {
                        $optionValueObj['price'] = null;
                    }
                    array_push($optionObj['values'], $optionValueObj);
                }
            }
            array_push($options, $optionObj);
        }
        return $options;
    }

    protected function _prepareRecursivelyRelatedValues(&$skuValue, $attributes, $productIds, $cycle = 1) {
        $relatedValue = null;
        for ($i = 0; $i < $cycle; $i++) {
            next($attributes);
        }
        $attribute = current($attributes);
        $attrId = key($attributes);
        $skuValue['values'] = array();
        foreach ($attribute['options'] as $option) {
            $intersect = array_intersect($productIds, $option['products']);
            if (empty($intersect)) {
                continue;
            }
            $finalValue = array();
            $finalValue['sku_id'] = $attrId;
            $finalValue['value_id'] = $option['id'];
            $finalValue['name'] = $option['label'];
            if ((float) $option['price'] != 0.00) {
                $finalValue['price'] = $option['price'];
            } else {
                $finalValue['price'] = null;
            }
            array_push($skuValue['values'], $finalValue);
            $_attrClone = $attributes;
            if (next($_attrClone) != false) {
                reset($_attrClone);
                $this->_prepareRecursivelyRelatedValues($finalValue, $_attrClone, $intersect, $cycle + 1);
            }
        }
    }

    const OPTION_TYPE_SELECT = 'select';
    const OPTION_TYPE_CHECKBOX = 'select';
    const OPTION_TYPE_MULTIPLE_SELECT = 'multiple_select';
    const OPTION_TYPE_TEXT = 'input';

    protected function _getOptionTypeForKanCartByRealType($realType) {
        switch ($realType) {
            case Mage_Catalog_Model_Product_Option::OPTION_TYPE_DROP_DOWN:
            case Mage_Catalog_Model_Product_Option::OPTION_TYPE_RADIO:
            case Mage_Catalog_Model_Product_Option::OPTION_GROUP_SELECT:
                $type = self::OPTION_TYPE_SELECT;
                break;
            case Mage_Catalog_Model_Product_Option::OPTION_TYPE_MULTIPLE:
            case 'multi':
            case Mage_Catalog_Model_Product_Option::OPTION_TYPE_CHECKBOX:
                $type = self::OPTION_TYPE_MULTIPLE_SELECT;
                break;
            case Mage_Catalog_Model_Product_Option::OPTION_TYPE_FIELD:
            case Mage_Catalog_Model_Product_Option::OPTION_TYPE_AREA:
            default:
                $type = self::OPTION_TYPE_TEXT;
                break;
        }
        return $type;
    }

    protected function _preparePrice($product, $price, $isPercent = false) {
        if ($isPercent && !empty($price)) {
            $price = $product->getFinalPrice() * $price / 100;
        }
        $price = Mage::app()->getStore()->convertPrice($price);
        $price = Mage::app()->getStore()->roundPrice($price);
        return $price;
    }

    public function collectProductPrices($product) {
        $DisplayMinimalPrice = true;
        $UseLinkForAsLowAs = false;
        $prices = array();
        $prices['currency'] = Mage::getModel('core/store')->load(Mage::app()->getStore()->getId())->getCurrentCurrencyCode();
        $base_price = array();
        $display_prices = array();
        $tier_prices = array();
        $tier_prices = $this->getItemTierPrice($product);
        $prices['tier_prices'] = $tier_prices;

        $_coreHelper = Mage::helper('core');
        /* @var $_coreHelper Mage_Core_Helper_Data */
        /* @var $_weeeHelper Mage_Weee_Helper_Data */
        /* @var $_taxHelper Mage_Tax_Helper_Data */
        $_minimalPriceValue = $product->getMinimalPrice();

        if (!$product->isGrouped()) {
            $_price = $product->getPrice();
            $_finalPrice = $product->getFinalPrice();
            if ($DisplayMinimalPrice && $_minimalPriceValue && $_minimalPriceValue < $_finalPrice) {
                if (!$UseLinkForAsLowAs) {
                    $display_prices = $this->addtoDisplayPrices($display_prices, $this->__('As low as:'), $_coreHelper->currency($_minimalPriceValue, false, false), '');
                }
                $base_price['price'] = $_finalPrice;
            } else {
                if ($_finalPrice == $_price) {
                    $display_prices = $this->addtoDisplayPrices($display_prices, 'Price: ', $_coreHelper->currency($_price, false, false), '');
                    $base_price['price'] = $_price;
                } else {
                    $display_prices = $this->addtoDisplayPrices($display_prices, $this->__('Regular Price:'), $_coreHelper->currency($_price, false, false), 'line-through');
                    $display_prices = $this->addtoDisplayPrices($display_prices, $this->__('Special Price:'), $_coreHelper->currency($_finalPrice, false, false), '');
                    $base_price['price'] = $_finalPrice;
                }
            }
        } else {
            if ($DisplayMinimalPrice && $_minimalPriceValue) {
                $display_prices = $this->addtoDisplayPrices($display_prices, $this->__('Starting at:'), $_coreHelper->currency($_minimalPriceValue, false, false), '');
            }
            $base_price['price'] = $_finalPrice;
        }
        $prices['base_price'] = $base_price;
        $prices['display_prices'] = $display_prices;
        return $prices;
    }

    public function collectBundleProductPrices($product) {
        $DisplayMinimalPrice = true;
        $UseLinkForAsLowAs = false;
        $prices = array();
        $prices['currency'] = Mage::getModel('core/store')->load(Mage::app()->getStore()->getId())->getCurrentCurrencyCode();
        $base_price = array();
        $display_prices = array();
        $tier_prices = array();
        $tier_prices = $this->getItemTierPrice($product);
        $prices['tier_prices'] = $tier_prices;

        $_coreHelper = Mage::helper('core');
        /* @var $_coreHelper Mage_Core_Helper_Data */
        /* @var $_weeeHelper Mage_Weee_Helper_Data */
        /* @var $_taxHelper Mage_Tax_Helper_Data */
        list($_minimalPrice, $_maximalPrice) = $product->getPriceModel()->getPrices($product);
        $_finalPrice = $product->getFinalPrice();
        if ($product->getPriceView()) {
            $display_prices = $this->addtoDisplayPrices($display_prices, $this->__('As low as:'), $_coreHelper->currency($_minimalPrice, false, false));
            $base_price['price'] = $_finalPrice;
        } else {
            if ($_minimalPrice <> $_maximalPrice) {
                $display_prices = $this->addtoDisplayPrices($display_prices, $this->__('From:'), $_coreHelper->currency($_minimalPrice, false, false), 'from');
                $display_prices = $this->addtoDisplayPrices($display_prices, $this->__('To:'), $_coreHelper->currency($_maximalPrice, false, false), 'to');
            } else {
                $display_prices = $this->addtoDisplayPrices($display_prices, '', $_coreHelper->currency($_minimalPrice, false, false));
            }
            $base_price['price'] = $_finalPrice;
        }
        $prices['base_price'] = $base_price;
        $prices['display_prices'] = $display_prices;
        return $prices;
    }

    public function addtoDisplayPrices($display_prices, $title, $value, $style) {
        $display_price = array();
        $display_price['title'] = $title;
        $display_price['price'] = $value;
        $display_price['style'] = $style;
        array_push($display_prices, $display_price);
        return $display_prices;
    }

    public function addtoWeees($weees, $name, $amount) {
        $weee = array();
        $weee['name'] = $name;
        $weee['amount'] = $amount;
        array_push($weees, $weee);
        return $weees;
    }

}