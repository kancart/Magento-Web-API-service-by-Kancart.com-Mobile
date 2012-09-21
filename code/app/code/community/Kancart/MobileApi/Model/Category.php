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
class Kancart_MobileApi_Model_Category extends Mage_Catalog_Model_Category_Api {

    public function getCategories($apidata) {
        $categories = array();
        $fields = $apidata['fields'];
        $parentid = $apidata['parent_cid'];
        $isallcat = false;
        if (isset($apidata['all_cat'])) {
            $isallcat = $apidata['all_cat'];
        }
        $result = Mage::getModel('mobileapi/Result');
        if ($isallcat) {
            try {
                $categories = $this->getSubCategories(Mage::app()->getStore()->getRootCategoryId(), 0);
                //$categories = extractSubCategories(Mage::app()->getStore()->getRootCategoryId(), 0);
            } catch (Exception $e) {
                $result->setResult('0x2002', null, null, $e->getMessage());
            }
        } else {
            if ($parentid == -1) {
                $parentid = Mage::app()->getStore()->getRootCategoryId();
            }
            try {
                $categories = $this->getSubCategories($parentid, 1);
            } catch (Exception $e) {
                $result->setResult('0x2002', null, null, $e->getMessage());
            }
        }
        $result->setResult('0x0000', array('item_cats' => $categories));
        return $result->returnResult();
    }
    
    public function clearResult(){
        $this->result = array();
    }
    
    public function getSubCategories($parentCid = null, $level = 0) {
        $this->extractSubCategories($parentCid, $level);
        $ret = $this->result;
        $this->clearResult();
        return $ret;
    }

    public function extractSubCategories($parentCategoryId, $level) {
        $this->extractSubCategoriesRecursively($parentCategoryId, $level);
        return $arrCategoryInfo;
    }
    
    /**
     * 递归地获取子类别信息，特别地，包括所有子类别下的商品数量
     * @param type $parentCategoryId
     * @param type $level
     */
    private function extractSubCategoriesRecursively($parentCategoryId, $level) {
        $layer = Mage::getSingleton('catalog/layer');
        $category = Mage::getModel('catalog/category')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load((int) $parentCategoryId);
        $categories = $category->getChildrenCategories();
        $productCollection = Mage::getResourceModel('catalog/product_collection');
        $layer->prepareProductCollection($productCollection);
        $productCollection->addCountToCategories($categories);
        foreach ($categories as $category) {
            if ($category->hasChildren()) {
                //$this->printCategoryInfo($category,$level);
                $this->addCategoryInfo($category);
                $this->extractSubCategoriesRecursively($category->getId(), $level + 1);
            } else {
                //$this->printCategoryInfo($category,$level);
                $this->addCategoryInfo($category);
            }
        }
    }

    
    private $result = array();
    
    private function addCategoryInfo($category) {
       
        $cty = array();
        if ($category->hasChildren()) {
            $cty['is_parent'] = true;
        } else {
            $cty['is_parent'] = false;
        }
        $cty['cid'] = strval($category->getId());
        $cty['parent_cid'] = strval($category->getParentId());
        $cty['sort_order'] = $category->getPosition();
        $cty['name'] = $category->getName();

        if ($cty['cid'] == Mage::app()->getStore()->getRootCategoryId()) {
            $cty['cid'] = '-1';
        }
        if ($cty['parent_cid'] == Mage::app()->getStore()->getRootCategoryId()) {
            $cty['parent_cid'] = '-1';
        }
        //$cty['data'] = $category->getData();

        $cty['count'] = $category->getProductCount();
        $this->result[] = $cty;

    }

    public function assignProductCount($parentCid, $allCategoreis) {
        $layer = Mage::getSingleton('catalog/layer');
        $category = Mage::getModel('catalog/category')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load((int) $parentCid);

        $collection = $category->getProductCollection();
        $collection
                ->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
                ->addMinimalPrice()
                ->addFinalPrice()
                ->addTaxPercents()
                //->addStoreFilter()
                ->addUrlRewrite($category->getId());
        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($collection);
        $layer->prepareProductCollection($productCollection);
        $collection->addCountToCategories($allCategoreis);

        return $categories;
    }

}