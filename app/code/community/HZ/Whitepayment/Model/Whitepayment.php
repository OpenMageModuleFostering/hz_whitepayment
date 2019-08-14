<?php
/**
 * NOTICE OF LICENSE
 *
 * @category	Whitepayment
 * @package		HZ_Whitepayment
 * @author		Whitepayment.
 * @license		http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class HZ_Whitepayment_Model_Whitepayment extends Mage_Payment_Model_Method_Cc {

	protected $_code						=	'whitepayment';	//unique internal payment method identifier

	protected $_isGateway					=	true;
    protected $_canAuthorize				=	true;
    protected $_canCapture					=	true;
    protected $_canCapturePartial			=	false;
    protected $_canRefund					=	false;
	protected $_canRefundInvoicePartial		=	false;
    protected $_canVoid						=	false;
    protected $_canUseInternal				=	true;
    protected $_canUseCheckout				=	true;
    protected $_canUseForMultishipping		=	false;
    protected $_isInitializeNeeded			=	false;
    protected $_canFetchTransactionInfo		=	false;
    protected $_canReviewPayment			=	false;
    protected $_canCreateBillingAgreement	=	false;
    protected $_canManageRecurringProfiles	=	false;
	protected $_canSaveCc					=	false;

	/**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = array('x_login', 'x_tran_key', 'x_card_num', 'x_exp_date', 'x_card_code');

	/**
     * Validate payment method information object
     */
	public function validate() {
		$info = $this->getInfoInstance();
		$order_amount=0;
		if ($info instanceof Mage_Sales_Model_Quote_Payment) {
            $order_amount=(double)$info->getQuote()->getBaseGrandTotal();
        } elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
            $order_amount=(double)$info->getOrder()->getQuoteBaseGrandTotal();
        }

		$order_min=$this->getConfigData('min_order_total');
		$order_max=$this->getConfigData('max_order_total');
		if(!empty($order_max) && (double)$order_max<$order_amount) {
			Mage::throwException("Order amount greater than permissible Maximum order amount.");
		}
		if(!empty($order_min) && (double)$order_min>$order_amount) {
			Mage::throwException("Order amount less than required Minimum order amount.");
		}
		/*
        * calling parent validate function
        */
        parent::validate();
	}

	/**
     * Send capture request to gateway
     */
	public function capture(Varien_Object $payment, $amount) {
		if ($amount <= 0) {
			Mage::throwException("Invalid amount for transaction.");
        }

		$payment->setAmount($amount);
		$data = $this->_prepareData();
		$order = $payment->getOrder();
        $szOrderID = $payment->getOrder()->increment_id;

		// Transaction Info
		$data['amount']            = $payment->getAmount();
		$data['currency']          = $order->getBaseCurrencyCode();

		// Test Card
        $data['card']              = array();
		$month = ($payment->getCcExpMonth() < 9 ? '0' . $payment->getCcExpMonth() : $payment->getCcExpMonth());
		$data['card']['number']          = $payment->getCcNumber();
		$data['card']['exp_month']       = $month;
		$data['card']['exp_year']        = $payment->getCcExpYear();
		$data['card']['cvc']             = $payment->getCcCid();

		// Order Info
        $data['email']       = 'test@example.com';
        $data['description']       = 'OrderID:' . $szOrderID;

		$result = $this->_postRequest($data);

		if ($result['http_code'] >= 200 && $result['http_code'] < 404){
			$data = $result['body'];

			if($data['is_error'] == 0){
                $payment->setStatus(self::STATUS_APPROVED);
				$payment->setLastTransId((string)$data['tag']);
				if (!$payment->getParentTransactionId() || (string)$data['tag'] != $payment->getParentTransactionId()) {
					$payment->setTransactionId((string)$data['tag']);
				}

				return $this;
			}
            else{
				$error = $this->_error_status();
				$payment->setStatus(self::STATUS_ERROR);
				Mage::throwException((string)$error[$data['error']['code']]);
			}
		}
        else{
			Mage::throwException("An error occurred during processing. Please try again in 5 minutes.");
		}
	}

	/**
     * process using cURL
     */
	protected function _postRequest($data) {
		$debugData = array('request' => $data);

        $url = 'https://api.whitepayments.com/v1/charges';
        $caInfoPath = Mage::getBaseDir('base') . DS . 'lib' . DS . 'White' . DS . 'ca-certificates.crt';
        $apiKey = $data['api_key'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CAINFO, $caInfoPath);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if($data['test_request'] == TRUE){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $result = json_decode(curl_exec($ch), true);
		$info = curl_getinfo($ch);
        $errno = curl_errno($ch);

        $result['is_error'] = 0;

        if(isset($result['error']['code'])){
            $result['is_error'] = 1;
        }
        if( $result === false || $errno != 0 ) {
            $result['is_error'] = 1;
        }

		curl_close($ch);

    	$debugData['response'] = $result;
		if($this->getConfigData('debug') == 1) {
			$this->_debug($debugData);
		}

		return array('http_code' => $info['http_code'], 'body' => $result);
	}

	protected function _prepareData() {
		$data = array(
			'api_key'			=>	$this->getConfigData('api_key'),
			'test_request'	=>	($this->getConfigData('test') == 1 ? 'TRUE' : 'FALSE')
		);

		if(($data['test_request'] == FALSE) && empty($data['api_key'])){
            Mage::throwException("Gateway Parameters Missing. Contact administrator!");
        }
        else{
            if(empty($data['api_key'])){
                $data['api_key'] = 'sk_test_1234567890abcdefghijklmnopq';
            }
        }

		return $data;
	}

	function _error_status() {
        $error['invalid_number'] 	= 'The credit card number is invalid.';
        $error['invalid_expiry_month'] 	= 'The credit card expiration date is invalid.';
        $error['invalid_expiry_year'] 	= 'The credit card expiration date is invalid.';
        $error['invalid_cvc'] 	= 'The security code is invalid.';
        $error['expired_card'] 	=  'The credit card has expired.';
        $error['card_declined'] 	= 'This transaction has been declined.';
        $error['processing_error'] 	= 'An error occurred during processing. Please try again in 5 minutes.';

		return $error;
	}
}
?>