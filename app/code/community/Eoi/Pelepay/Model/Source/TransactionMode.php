<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   EOI
 * @package    EOI_WonderFaye
 * @copyright  Copyright (c) 2012 EOI (http://www.eoi.com)
 */


class Eoi_Pelepay_Model_Source_TransactionMode
{
    public function toOptionArray()
    {
        $options =  array();       ;
        foreach (Mage::getSingleton('pelepay/config')->getTransactionModes() as $code => $name) {
            $options[] = array(
            	   'value' => $code,
            	   'label' => $name
            );
        }

        return $options;
    }
}