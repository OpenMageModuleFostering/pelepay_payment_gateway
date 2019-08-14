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

class Eoi_Pelepay_ProcessingController extends Mage_Core_Controller_Front_Action
{
    protected $_successBlockType = 'pelepay/success';
    protected $_failureBlockType = 'pelepay/failure';
    protected $_cancelBlockType  = 'pelepay/cancel';

    protected $_order = NULL;
    protected $_paymentInst = NULL;

     
	 
    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * when customer selects Pele-Pay payment method
     */
    public function redirectAction()
    {
        try {
            $session = $this->_getCheckout();

            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($session->getLastRealOrderId());
            if (!$order->getId()) {
                Mage::throwException('No order for processing found');
            }
            if ($order->getState() != Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                $order->setState(
                    Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                    $this->_getPendingPaymentStatus(),
                    Mage::helper('pelepay')->__('Customer was redirected to Pele-Pay.')
                )->save();
            }

            if ($session->getQuoteId() && $session->getLastSuccessQuoteId()) {
                $session->setPelepayQuoteId($session->getQuoteId());
                $session->setPelepaySuccessQuoteId($session->getLastSuccessQuoteId());
                $session->setPelepayRealOrderId($session->getLastRealOrderId());
                $session->getQuote()->setIsActive(false)->save();
                $session->clear();
            }

            $this->loadLayout();
            $this->renderLayout();
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            $this->_debug('Pelepay error: ' . $e->getMessage());
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Pelepay returns POST variables to this action
     */
    public function responseAction()
    {
	    //print_r($_REQUEST);
        try {
            $request = $this->_checkReturnedPost();
            if ($request['Response'] == '000') { // successfull
                $this->_processSale($request);
            }elseif ($request['Response'] != '000') { // Failure;
			    $msg = '';
				/*START - THE ERROR CODES TO BE DISPLAYED*/
				switch(trim($request['Response'])) {
					case '003': 
						$msg = Mage::helper('pelepay')->__('התקשר לחברת האשראי.');
						break;
					case '004': 
						$msg = Mage::helper('pelepay')->__('סירוב של חברת האשראי.');
						break;
					case '033': 
						$msg = Mage::helper('pelepay')->__('הכרטיס אינו תקין.');
						break;
					case '001': 
						$msg = Mage::helper('pelepay')->__('כרטיס אשראי חסום.');
						break;
					case '002': 
						$msg = Mage::helper('pelepay')->__('כרטיס אשראי גנוב.');
						break;
					case '039': 
						$msg = Mage::helper('pelepay')->__('ספרת הביקורת של הכרטיס אינה תקינה.');
						break;
					case '101': 
						$msg = Mage::helper('pelepay')->__('לא מכבדים דיינרס.');
						break;
					case '061': 
						$msg = Mage::helper('pelepay')->__('לא הוזן מספר כרטיס אשראי.');
						break;
					case '157': 
						$msg = Mage::helper('pelepay')->__('כרטיס אשראי תייר.');
						break;
					case '133': 
						$msg = Mage::helper('pelepay')->__('כרטיס אשראי תייר.');
						break;
					case '036': 
						$msg = Mage::helper('pelepay')->__('פג תוקף הכרטיס.');
						break;							
				}
				/*END - THE ERROR CODES TO BE DISPLAYED */
				$session = $this->_getCheckout();
				$pelepay_response_msg     = $msg;
				$pelepay_response_msgcode = $request['Response'];
			    $session->addError(Mage::helper('pelepay')->__("The Error Response From PelePay is : ".$pelepay_response_msgcode." - ".$msg));
				Mage::getSingleton('core/session')->setPelepayResMsgCode($pelepay_response_msgcode);
                Mage::getSingleton('core/session')->setPelepayResMsg($pelepay_response_msg);
                $this->getResponse()->setBody(
                $this->getLayout()
                    ->createBlock($this->_failureBlockType)
                    ->setOrder($this->_order)
                    ->toHtml()
                );
            }
			 else {
                Mage::throwException('Transaction was not successfull.');
            }
        } catch (Mage_Core_Exception $e) {
            $this->_debug('Pelepay response error: ' . $e->getMessage());
            $this->getResponse()->setBody(
                $this->getLayout()
                    ->createBlock($this->_failureBlockType)
                    ->setOrder($this->_order)
                    ->toHtml()
            );
        }
    }

    /**
     * Pelepay return action
     */
    public function successAction()
    {
        try {
            $session = $this->_getCheckout();
            $session->unsPelepayRealOrderId();
            $session->setQuoteId($session->getPelepayQuoteId(true));
            $session->setLastSuccessQuoteId($session->getPelepaySuccessQuoteId(true));
			$request = $this->_checkReturnedPost();
            if ($request['Response'] == '000') { // successfull
                $this->_processSale($request);
            }
            $this->_redirect('checkout/onepage/success');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getCheckout()->addError($e->getMessage());
        } catch(Exception $e) {
            $this->_debug('Pelepay error: ' . $e->getMessage());
            Mage::logException($e);
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * Pelepay return action
     */
    public function cancelAction()
    {
        // set quote to active
		$session = $this->_getCheckout();
		$this->_order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
        if ($quoteId = $session->getPelepayQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                $session->setQuoteId($quoteId);
            }
        }
		$request = '';
        $session->addError(Mage::helper('pelepay')->__('The order has been canceled.'));
		$this->_processCancel($request);
    }


    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
            // check request type
        if (!$this->getRequest()->isGet())
            Mage::throwException('Wrong request type.');

        
        $helper = Mage::helper('core/http');
        // get request variables
        $request = $this->getRequest()->getParams();
		
        if (empty($request))
            Mage::throwException('Request doesn\'t contain POST elements.');

           // check order id
        if (empty($request['orderid']) || strlen($request['orderid']) > 50)
            Mage::throwException('Missing or invalid order ID');

            // load order for further validation
        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($request['orderid']);
        if (!$this->_order->getId())
            Mage::throwException('Order not found');

        $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();
        
        return $request;
    }

    /**
     * Process success response
     */
    protected function _processSale($request)
    {
        // check transaction amount and currency
        if ($this->_paymentInst->getConfigData('use_store_currency')) {
            $price      = number_format($this->_order->getGrandTotal(),2,'.','');
            $currency   = $this->_order->getOrderCurrencyCode();
        } else {
            $price      = number_format($this->_order->getBaseGrandTotal(),2,'.','');
            $currency   = $this->_order->getBaseCurrencyCode();
        }

        // check transaction amount
        if ($price != $request['amount'])
            Mage::throwException('Transaction currency doesn\'t match.');

        // save transaction information
        $this->_order->getPayment()
        	->setTransactionId($request['index'])
        	->setLastTransId($request['index']);
        	//->setCcAvsStatus($request['AVS'])
        	//->setCcType($request['cardType']);

        if ($this->_order->canInvoice()) {
            $invoice = $this->_order->prepareInvoice();
            $invoice->register()->capture();
            Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder())
            ->save();
        }
		$Ordstatus_tmp = $this->_paymentInst->getConfigData('order_status');
		if($Ordstatus_tmp == '')
		$Ordstatus_tmp = Mage_Sales_Model_Order::STATE_PROCESSING;
        $this->_order->addStatusToHistory($Ordstatus_tmp,Mage::helper('pelepay')->__('Customer returned successfully'));
        $this->_order->sendNewOrderEmail();
        $this->_order->setEmailSent(true);

        $this->_order->save();

        /*$this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_successBlockType)
                ->setOrder($this->_order)
                ->toHtml()
        );*/
    }

    /**
     * Process success response
     */
    protected function _processCancel($request)
    {
        // cancel order
        if ($this->_order->canCancel()) {
            $this->_order->cancel();
            $this->_order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, Mage::helper('pelepay')->__('Payment was canceled'));
            $this->_order->save();
        }

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_cancelBlockType)
                ->setOrder($this->_order)
                ->toHtml()
        );
    }

    protected function _getPendingPaymentStatus()
    {
        return Mage::helper('pelepay')->getPendingPaymentStatus();
    }

    /**
     * Log debug data to file
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData)
    {
        if (Mage::getStoreConfigFlag('payment/pelepay_paymentmethod/debug')) {
            Mage::log($debugData, null, 'payment_pelepay_paymentmethod.log', true);
        }
    }
}