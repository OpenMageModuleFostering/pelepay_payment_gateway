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


class Eoi_Pelepay_Model_Source_RequestType
{
    public function toOptionArray()
    {
        return array(
            array('value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE, 'label' => Mage::helper('pelepay')->__('Preauthorization')),
            array('value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE, 'label' => Mage::helper('pelepay')->__('Authorization')),
        );
    }
}