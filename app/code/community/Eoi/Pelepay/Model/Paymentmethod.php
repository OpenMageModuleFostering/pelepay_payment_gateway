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


class Eoi_Pelepay_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract
{
    const SIGNATURE_TYPE_STATIC  = 1;
    const SIGNATURE_TYPE_DYNAMIC = 2;

	/**
	* unique internal payment method identifier
	*
	* @var string [a-z0-9_]
	**/
	protected $_code = 'pelepay_paymentmethod';

    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;

    protected $_paymentMethod			= 'paymentmethod';
    protected $_defaultLocale			= 'en';
	
    protected $_testUrl	= '';//'https://www.pelepay.co.il/pay/custompaypage.aspx';
    protected $_liveUrl	= '';//'https://www.pelepay.co.il/pay/custompaypage.aspx';

    protected $_testAdminUrl	= '';
    protected $_liveAdminUrl	= '';

    protected $_formBlockType = 'pelepay/form';
    protected $_infoBlockType = 'pelepay/info';

    protected $_order;
    
	/**
     * Set Gateway Url
     *
     * @return Gateway Url
     */
	    
	function __construct()
	{
	  $this->_testUrl     = $this->getConfigData('submit_url');
	  $this->_liveUrl     = $this->getConfigData('submit_url');
	  $pelepay_button_url = $this->getConfigData('pelepay_button_url');
      Mage::getSingleton('core/session')->setPelepayBtnUrl($pelepay_button_url);
	}
	 
   /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
		if (!$this->_order) {
			$this->_order = $this->getInfoInstance()->getOrder();
		}
		return $this->_order;
    }

    public function getOrderPlaceRedirectUrl()
    {
          return Mage::getUrl('pelepay/processing/redirect');
    }

    /**
     * Return payment method type string
     *
     * @return string
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethod;
    }

    public function getUrl()
    {
    	if ($this->getConfigData('transaction_mode') == 'live')
    		return $this->_liveUrl;
    	return $this->_testUrl;
    }

    public function getAdminUrl()
    {
    	if ($this->getConfigData('transaction_mode') == 'live')
    		return $this->_liveAdminUrl;
    	return $this->_testAdminUrl;
    }																																	


    /**
     * prepare params array to send it to gateway page via POST
     *
     * @return array
     */
    public function getFormFields()
    {
	   $items     = $this->getOrder()->getAllItems();
	   $prod_desc = '';
	   foreach ($items as $itemId => $item)
		{
		   $name[] = $item->getName();
     	}
		if(@is_array($name)) {
			if(count($name) > 0) {
				foreach($name as $k => $prd)
				{
					if($prd != '') {
						 if($prod_desc == '')
						 $prod_desc = $prd;
						 else
						 $prod_desc .= " | ".$prd;
				   }
				}
			}
		}
	    	// get transaction amount and currency
        if ($this->getConfigData('use_store_currency')) {
        	$price      = number_format($this->getOrder()->getGrandTotal(),2,'.','');
        	$currency   = $this->getOrder()->getOrderCurrencyCode();
    	} else {
        	$price      = number_format($this->getOrder()->getBaseGrandTotal(),2,'.','');
        	$currency   = $this->getOrder()->getBaseCurrencyCode();
    	}

		$billing	= $this->getOrder()->getBillingAddress();

 		$locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
		if (is_array($locale) && !empty($locale))
			$locale = $locale[0];
		else
			$locale = $this->getDefaultLocale();

    	$params = 	array(
	    				'business'		=>	$this->getConfigData('business_name'),
    					'orderid'		=>	$this->getOrder()->getRealOrderId(),
	    				'testMode'		=>	($this->getConfigData('transaction_mode') == 'test') ? '100' : '0',
	    				'amount'		=>	$price,
    					'description'   =>	$prod_desc,
						'Max_payments'	=>	$this->getConfigData('max_payments'),
						'address'		=>	$billing->getStreet(-1).'&#10;'.$billing->getCity(),
						'postcode'		=>	$billing->getPostcode() ,
						'country'		=>	$billing->getCountry(),
						'phone'			=>	$billing->getTelephone(),
						'email'			=>	$this->getOrder()->getCustomerEmail(),
						'firstname'		=>	$billing->getFirstname(),
						'lastname'		=>	$billing->getLastname(),
						'cancel_return' =>  Mage::getUrl($this->getConfigData('cancel_return_url')),
						'fail_return'   =>  Mage::getUrl($this->getConfigData('fail_return_url')),
						'success_return' => Mage::getUrl($this->getConfigData('success_return_url')) //success_return_url
    				);

          return $params;
    }

    /**
     * Refund money
     *
     * @param   Varien_Object $invoicePayment
     * @return  Mage_GoogleCheckout_Model_Payment
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $transactionId = $payment->getLastTransId();
        $params = $this->_prepareAdminRequestParams();

        $params['cartId']   = 'Refund';
        $params['op']       = 'refund-partial';
        $params['index']  = $transactionId;
        $params['amount']   = $amount;
        $params['currency'] = $payment->getOrder()->getBaseCurrencyCode();

        /*$responseBody = $this->processAdminRequest($params);
        $response = explode(',', $responseBody);
        if (count($response) <= 0 || $response[0] != 'A' || $response[1] != $transactionId) {
            $message = $this->_getHelper()->__('Error during refunding online. Server response: %s', $responseBody);
            $this->_debug($message);
            Mage::throwException($message);
        }*/
        return $this;
    }

    /**
     * Capture preatutharized amount
     * @param Varien_Object $payment
     * @param <type> $amount
     */
	public function capture(Varien_Object $payment, $amount)
	{
        if (!$this->canCapture()) {
            return $this;
        }

        if (Mage::app()->getRequest()->getParam('transId')) {
            // Capture is called from response action
            $payment->setStatus(self::STATUS_APPROVED);
            return $this;
        }
        $transactionId = $payment->getLastTransId();
        $params = $this->_prepareAdminRequestParams();
        $params['index']  = $transactionId;
        /*$responseBody = $this->processAdminRequest($params);
        $response = explode(',', $responseBody);

        if (count($response) <= 0 || $response[0] != 'A' || $response[1] != $transactionId) {
            $message = $this->_getHelper()->__('Error during capture online. Server response: %s', $responseBody);
            $this->_debug($message);
            Mage::throwException($message);
        } else {
            $payment->getOrder()->addStatusToHistory($payment->getOrder()->getStatus(), $this->_getHelper()->__('WonderFaye transaction has been captured.'));
        }*/
    }


    /**
     * Check refund availability
     *
     * @return bool
     */
    public function canRefund ()
    {
        return $this->getConfigData('enable_online_operations');
    }

    public function canRefundInvoicePartial()
    {
        return $this->getConfigData('enable_online_operations');
    }

    public function canRefundPartialPerInvoice()
    {
        return $this->canRefundInvoicePartial();
    }

    public function canCapturePartial()
    {
        if (Mage::app()->getFrontController()->getAction()->getFullActionName() != 'adminhtml_sales_order_creditmemo_new'){
            return false;
        }
        return $this->getConfigData('enable_online_operations');
    }

	protected function processAdminRequest($params, $requestTimeout = 60)
	{
		try {
			$client = new Varien_Http_Client();
			$client->setUri($this->getAdminUrl())
				->setConfig(array('timeout'=>$requestTimeout,))
				->setParameterPost($params)
				->setMethod(Zend_Http_Client::POST);

			$response = $client->request();
			$responseBody = $response->getBody();

			if (empty($responseBody))
				Mage::throwException($this->_getHelper()->__('Pele-Pay API failure. The request has not been processed.'));
			// create array out of response

		} catch (Exception $e) {
            $this->_debug('Pele-Pay API connection error: '.$e->getMessage());
			Mage::throwException($this->_getHelper()->__('Pele-Pay API connection error. The request has not been processed.'));
		}

		return $responseBody;
	}

    protected function _prepareAdminRequestParams()
    {
        $params = array (
            'index'   => @$this->getConfigData('admin_index_id'),
        );
        if ($this->getConfigData('transaction_mode') == 'test') {
            $params['testMode'] = 100;
        }
        return $params;
    }

    /**
     * Log debug data to file
     *
     * Prior Magento 1.4.1 this method doesn't exists. So it is mainly to provide
     * BC.
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData)
    {
        if (method_exists($this, 'getDebugFlag')) {
            return parent::_debug($debugData);
        }

        if ($this->getConfigData('debug')) {
            Mage::log($debugData, null, 'payment_' . $this->getCode() . '.log', true);
        }
    }
}