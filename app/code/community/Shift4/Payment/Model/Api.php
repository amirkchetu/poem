<?php

/**
 * Wrapper that performs i4GO and Shift4 API communication
 * 
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Api {
    /* constant value of the processing mode to be displayed in configuration option */

    const PROCESSING_MODE_LIVE = 'live';
    const PROCESSING_MODE_DEMO = 'demo';

    private $_shift4EndPoint;

    /* Array holds the card holder data */
    private $_cardHolderData = array();

    /* End point for the Authorize Client Request in demo mode */
    private $_authorizeClientEndpointDemo = 'https://access.shift4test.com';

    /* End point for the Authorize Client Request in live mode */
    //private $_authorizeClientEndpointLive = 'https://access.shift4.com'; // TBD
    private $_authorizeClientEndpointLive = 'https://access.shift4test.com';

    /* String holds the mode of the API */
    private $_mode;

    /* Server to Server flag */
    public $_isServerToServerCall = false;

    /* Hard coded access token for the demo mode */
    private $_accessTokenForDemoMode = '780E2894-9BD5-4984-BD04-8B34A8DD451B';

    /* Hard coded Client GUID */
    private $_clientGuid = 'FD9B748A-A7A9-0F61-990CE157AE1FD886';

    /* Hard coded Auth Token for demo mode */
    private $_authTokenForDemo = 'DEA3D860-ED5A-3BDA-B9B317C03F6F43E5';
    private $_serverToServerDemoUrl = 'https://dotn.shift4test.com/api/S4Tran_Action.cfm';
    /* custom logs will be saved in this file in var/log folder */
    private $_logFileName = 'api.log';
    /* Shif4 error codes */
    private $_errorCodeFile = 'shift4ErrorCodes.xml';
    /* Global timers for timeout */
    private $_globalTimerUtg=65;
    private $_globalTimerLive=125;
    private $_timeout;
    
    /* HardCoded Vendor Name */
    private $_vendorName='Chetu:S4PM_Magento:2.0';
    
    
    /**
     * Prepare the class variables that are necessary for many API requests
     *
     * @param NULL
     */
    public function __construct() {
        // Server switch based on the configuration, UTG/Server To Server
        $this->_mode = Mage::getStoreConfig('payment/shift4_payment/processing_mode');
        $serverAddresses = Mage::getStoreConfig('payment/shift4_payment/server_addresses');
        
        if ($this->_mode == self::PROCESSING_MODE_DEMO) {
            //$array_server_Addresses = explode(",", $serverAddresses);
            //$this->_shift4EndPoint = $array_server_Addresses[0];
            $this->_shift4EndPoint = $this->_serverToServerDemoUrl;
            $this->_timeout = $this->_globalTimerLive;
        } else {
            $this->_shift4EndPoint = $serverAddresses;
            $this->_timeout = $this->_globalTimerUtg;
            if (!$is_utg) {
                // If the Universal Transaction Gateway® (UTG®) is in use, 
                //the Server Addresses field will be populated with a comma-separated list of UTG IP addresses and port numbers.
                $this->_isServerToServerCall = true;
                $this->_timeout = $this->_globalTimerLive;
            }
        }
    }

    /**
     * Log the all communication, problems only or off
     *
     * @param string $message
     * @param String $logType (problems, all)
     */
    public function logAPICommunications($message, $logType = 'all') {
        $logging = Mage::getStoreConfig('payment/shift4_payment/logging');

        switch ($logging) {
            case 'problems':
                // log all the issue only not request and response
                if ($logType == $logging) {
                    Mage::log($message, null, $this->_logFileName);
                }
                break;

            case 'all':
                // log all the communication
                Mage::log($message, null, $this->_logFileName);
                break;

            case 'off':
            // logging is off
        }
    }

    /**
     * Get the access token for the Shift4 API requests
     *
     * @param String $authToken
     * @param String $serverAddresses
     *
     * @return Varien_Object/Exception
     */
    public function getShift4AccessToken($authToken, $serverAddresses) {
        $array_server_Addresses = explode(",", $serverAddresses);
        $endPoint = $array_server_Addresses[0];
        //If admin has not supplied the endpoint, grab from config node/or default value
        if (empty($endPoint)) {
            $endPoint = $this->_shift4EndPoint;
        }
        $requestBody = $this->_prepareTokenExchangeRequestBody($authToken);
        $response = $this->_executeRequest($requestBody, $endPoint);

        if ($response) {
            $result = $this->_readShift4ApiResponse($response);
            $message = 'The access token exchange response = ' . print_r($result, true);
            $this->logAPICommunications($message);

            return $result;
        } else {
            $errorMsg = Mage::helper('shift4')->__('There has been error processing the token exchange request. Please try again.');
            Mage::throwException($errorMsg);
        }
    }

    /**
     * Tokenize the Card Holder Data via i4Go API request
     *
     * @param Object $observer
     *
     * @return Object 
     */
    public function saveI4GoTokenWithPayment(Varien_Event_Observer $observer) {
        $this->_cardHolderData = $observer->getEvent()->getInput()->getData();
        $event = $observer->getEvent();
        /** @var $helper Shift4_Payment_Helper_Data */
        $helper = Mage::helper('shift4');

        if ($this->_cardHolderData['method'] == 'shift4_payment') {
            // Save the token with the payment object for the further process          
            if (empty($this->_cardHolderData['card_token'])) {
                $errorMsg = $helper->__('Could not retirve the card token. Please enter the card information and click the secure my payment information button.');
                Mage::throwException($errorMsg);
            } else {
                // Save the unique_id with customer account and associate with order	
                $message = "The True Token returned from i4Go is : " . $this->_cardHolderData['card_token'];
                $this->logAPICommunications($message);
                $event->getPayment()->setI4goTrueToken($this->_cardHolderData['card_token']);
            }
        } else if ($helper->isCustomerstoredPaymentMethod($this->_cardHolderData['method'])) {
            // Associate token with the payment
            $customerstoredModel = Mage::getModel('shift4/customerstored');
            $payment = $event->getPayment();
            if ($payment->hasAdditionalInformation('stored_card_id')) {
                // Reference payment using existing customer's stored card
                $storedCardId = $payment->getAdditionalInformation('stored_card_id');
                $customerstoredModel->load($storedCardId);

                if ($customerstoredModel->getId()) {
                    $message = "The user used the already added card. The True Token saved with card id : " . $customerstoredModel->getCardToken();
                    $this->logAPICommunications($message);
                    $payment->setI4goTrueToken($customerstoredModel->getCardToken());
                }
            }
        }
    }

    /**
     * Tokenize the Card Holder Data via i4Go API request and save to table / My account
     *
     * @param Object $data
     *
     * @return Array 
     */
    public function saveI4GoTokenizedData($data) {
        $this->_cardHolderData = $data;

        if ($this->_cardHolderData['method'] == 'shift4_payment') {
            // Tokenize the CHD and get the true token       
            $savedCardModel = Mage::getModel('shift4/customerstored');
            // Save the unique_id with customer account and associate with order	
            $message = "There True Token returned : " . $data['card_token'];
            $this->logAPICommunications($message);

            // Save the card holder data to table
            $customer_id = Mage::getSingleton('customer/session')->getCustomer()->getId();
            $savedCardModel->setCustomerId($customer_id);
            if (!isset($data['card_token'])) {
                Mage::throwException("Could not retrieve the card token value.");
            }
            $savedCardModel->setCcType($this->_cardHolderData['cc_type'])
                    ->setCcLast4(substr($this->_cardHolderData['card_token'], 0, 4))
                    ->setCcExpMonth($this->_cardHolderData['cc_exp_month'])
                    ->setCcExpYear($this->_cardHolderData['cc_exp_year'])
                    ->setPaymentMethod($this->_cardHolderData['method'])
                    ->setCardToken($data['card_token']);

            $saved = $savedCardModel->save();

            return $saved;
        }
    }

    /**
     * Authorize the Client IP address using i4Go API
     *
     * @param none
     *
     * @return Object 
     */
    public function _authorizeClientRequest() {
        /* Authorize Client request fields array */
        if ($this->_mode == 'demo') {
            $accessToken = $this->_accessTokenForDemoMode;
            $endPoint = $this->_authorizeClientEndpointDemo;
        } else {
            $accessToken = Mage::getStoreConfig('payment/shift4_payment/access_token');
            $endPoint = $this->_authorizeClientEndpointLive;
        }
        $authorizeClientFields = array(
            'fuseaction' => 'account.authorizeClient',
            'i4go_clientip' => $_SERVER['SERVER_ADDR'],
            'i4go_accesstoken' => $accessToken
        );

        $message = 'The authorize client request fields =' . print_r($authorizeClientFields, true);
        $this->logAPICommunications($message);
        $data = str_replace('+', '%20', http_build_query($authorizeClientFields, '', '&'));
        
        $json_response = $this->_executeRequest($data, $endPoint);
        if ($json_response) {
            $response = $this->_readI4GoAuthorizeClientResponse($json_response);

            if ($response['error']) {
                $this->logAPICommunications('Authorize client fields=' . print_r($authorizeClientFields, true), 'problems');
            }
            $message = 'The authorize client response= ' . print_r($response, true);
            $this->logAPICommunications($message);

            return $response;
        } else {
            $errorMsg = Mage::helper('shift4')->__('There has been error in authorizing client. Please try again.');
            Mage::throwException($errorMsg);
        }
    }

    /**
     * Read the json response returned from i4Go for authorize client request
     *
     * @param String $json
     *
     * @return Array 
     */
    private function _readI4GoAuthorizeClientResponse($json) {
        $result = json_decode($json, true);
        $data = array();
        $data['error'] = false;

        if ($result['i4go_responsecode'] == 1 && $result['i4go_response'] == 'SUCCESS') {
            // Authorize client request complete 
            $data['response'] = $result;
        } else {
            // There is an error in communication
            $message = "There is error in authorize client request: " . $result['i4go_response'];
            $this->logAPICommunications($message, 'problems');
            $this->logAPICommunications("The Authorize client response == " . print_r($result, true), 'problems');

            $data['error'] = true;
            $data['response_code'] = (string) $result['i4go_responsecode'];
            $data['error_text'] = (string) $result['i4go_response'];
        }

        return $data;
    }

    /**
     * Read the XML response returned from Shift4 API
     *
     * @param String $xml
     * @param String $type
     *
     * @return Array 
     */
    private function _readShift4ApiResponse($xml) {
        $result = new SimpleXMLElement($xml);
        $data = array();

        $data['error'] = false;
        $data['error_to_show_user'] = '';
        $data['response'] = $result;

        $shift4Response = json_decode(json_encode((array) $result), true);

        $varienObj = new Varien_Object();
        $data['response'] = $varienObj->setData($shift4Response);

        if ($data['response']->getErrorindicator() == 'Y' || $data['response']->getPrimaryerrorcode() > 0 || ($data['response']->getLongerror() != '') || ($data['response']->getShorterror() != '')
        ) {
            // There is an error in communication
            $message = "There is error in communication with Shift4: 
						Primary Error code:- " . $result->primaryerrorcode . "
						Secondary Error code:- " . $result->secondaryerrorcode . "
						Short Error:- " . $result->shorterror . ", 
						Long Error:- " . $result->longerror;

            $this->logAPICommunications($message, 'problems');
            $this->logAPICommunications("Response === " . print_r($result, true), 'problems');

            $messageForUser = $this->getUserFriendlyErrorForShift4($result);
            $this->logAPICommunications($messageForUser, 'problems');

            $data['error'] = true;
            $data['error_to_show_user'] = $messageForUser;
        }

        $response = new Varien_Object();
        $response->setData($data);
        // Return the response as a varien_object

        return $response;
    }

    /**
     * Create the request body for the Token Exchange Request
     *
     * @param String $authToken
     *
     * @return String 
     */
    protected function _prepareTokenExchangeRequestBody($authToken) {
        if ($this->_mode == self::PROCESSING_MODE_DEMO) {
            $authToken = $this->_authTokenForDemo; // Hard coded for demo mode, change this as per need
        }

        $fields = array(
            'STX' => 'Yes',
            'Verbose' => 'Yes',
            'FunctionRequestCode' => 'CE',
            'APIFormat' => 0,
            'APISignature' => '$',
            'AuthToken' => $authToken,
            'ClientGUID' => $this->_clientGuid,
            'CONTENTTYPE' => 'XML',
            'Date' => date('mdy'),
            'Time' => gmdate('his', time()),
            'APIOptions' => 'ALLDATA',
            'RequestorReference=' => mt_rand(100000, 99999999),
            'Vendor' => $this->_vendorName,
            'ETX' => 'Yes',
        );

        $message = 'The access token exchange request fields== ' . print_r($fields, true);
        $this->logAPICommunications($message);
        
        $data = str_replace('+', '%20', http_build_query($fields, '', '&'));
      
        return $data;
    }

    /**
     * Call the auth/sale/void/refund request of the Shift4 API 
     *
     * @param Object $payment
     * @param numeric $amount
     * @param String $type
     *
     * @return Array 
     */
    public function callShift4TransactionApi($payment) {
        $requestFields = $this->_prepareTransactionRequestBody($payment);

        $response = $this->_postRequest($requestFields);

        return $response;
    }

    /**
     * Cancel/Void the current payment request of the Shift4 API 
     *
     * @param Object $payment
     *
     * @return Array 
     */
    public function cancelShift4Transaction($payment) {
        $invoiceId = $payment->getShift4InvoiceId();
        $trueToken = $payment->getI4GoTrueToken();

        if (empty($invoiceId)) {
            Mage::throwException(Mage::helper('shift4')->__('Payment cancelling error'));
        }
        $requestFields = $this->_getCommonFieldsForShift4Request();

        $note = 'Processing cancel request for Invoice #' . $invoiceId;

        $requestFields['FunctionRequestCode'] = '08';
        $requestFields['APIOptions'] = 'ALLDATA';
        $requestFields['Notes'] = '<p>' . $note . '</p>';
        $requestFields['SaleFlag'] = 'S';
        $requestFields['UniqueID'] = $trueToken;
        $requestFields['CardEntryMode'] = 'M';
        $requestFields['Vendor'] = $this->_vendorName;
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            // Load the customer's data
            $_customer = Mage::getSingleton('customer/session')->getCustomer();

            $requestFields['CustomerName'] = $_customer->getFirstname() . " " . $_customer->getLastname();
            $requestFields['CustomerReference'] = $_customer->getEntityId();
        }
        // The Magento order id will reflect as a invoice id in Shift4 transaction

        $requestFields['Invoice'] = $invoiceId; // Check the last Invoice id		

        $message = 'Request field for Cancel== ' . print_r($requestFields, true);
        $this->logAPICommunications($message);

        $response = $this->_postRequest($requestFields);

        return $response;
    }

    /**
     * Cancel the current payment request of the Shift4 API 
     *
     * @param Object $payment
     *
     * @return Array 
     */
    public function processRefundRequest($payment) {
        $transactionId = $payment->getTransId();
        if (empty($transactionId)) {
            Mage::throwException(Mage::helper('shift4')->__('Payment cancelling error'));
        }
        $requestFields = $this->_getCommonFieldsForShift4Request();

        $note = 'Processing refund request for transaction #' . $transactionId . ' and amount ' . $payment->getAmount();

        $requestFields['FunctionRequestCode'] = '1D';
        $requestFields['APIOptions'] = 'ALLDATA';
        $requestFields['Notes'] = '<p>' . $note . '<p>';
        $requestFields['SaleFlag'] = 'C';
        $requestFields['UniqueID'] = $payment->getI4GoTrueToken();
        $requestFields['TranID'] = $transactionId;
        $requestFields['CardEntryMode'] = 'M';
        $requestFields['PrimaryAmount'] = $payment->getAmount();

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            // Load the customer's data
            $_customer = Mage::getSingleton('customer/session')->getCustomer();

            $requestFields['CustomerName'] = $_customer->getFirstname() . " " . $_customer->getLastname();
            $requestFields['CustomerReference'] = $_customer->getEntityId();
        }

        $message = 'Request field for Refund  == ' . print_r($requestFields, true);
        $this->logAPICommunications($message);

        $response = $this->_postRequest($requestFields);

        return $response;
    }

    /**
     * Cancel the current payment request of the Shift4 API 
     *
     * @param Object $payment
     *
     * @return Array 
     */
    public function getShift4InvoiceStatus($payment) {
        $invoiceId = $payment->getLastShift4InvoiceId();
        if (empty($invoiceId)) {
            Mage::throwException(Mage::helper('shift4')->__('There was an error processing the request. Please try again.'));
        }
        $requestFields = $this->_getCommonFieldsForShift4Request();

        $requestFields['FunctionRequestCode'] = '07';
        $requestFields['APIOptions'] = 'ALLDATA';
        $requestFields['SaleFlag'] = 'S';
        $requestFields['UniqueID'] = $payment->getI4GoTrueToken();
        $requestFields['Invoice'] = $invoiceId;
        $requestFields['Vendor'] = $this->_vendorName;

        $message = 'Request field for get invoice info == ' . print_r($requestFields, true);
        $this->logAPICommunications($message);
        $response = $this->_postRequest($requestFields);

        return $response;
    }

    /**
     * Make the Shift4 API Server To Server call
     *
     * @param Array $data
     *
     * @return String $xml; 
     */
    private function _makeServerToServerApiCall($data) {
        if ($this->_shift4EndPoint != '') {
            $array_server_Addresses = explode(",", $this->_shift4EndPoint);
            if (count($array_server_Addresses)) {
                for ($count = 0; $count < count($array_server_Addresses); $count++) {
                    $endPoint = (strpos($array_server_Addresses[$count],'http')>-1)?$array_server_Addresses[$count]:'https://'.$array_server_Addresses[$count];
                    $endPoint .= '/api/S4Tran_Action.cfm';
                    $xml_response = $this->_executeRequest($data, $endPoint);
                    //echo $endPoint.' :: ';
                    if ($xml_response) {
                        break;
                    }
                }
                return $xml_response;
            } else {
                // Error, no server address configured
                $message = 'No server address string configured';
                $this->logAPICommunications($message, 'problems');

                return false;
            }
        }
    }

    /**
     * Create the request body for the Shift4 API Transaction 
     *
     * @param Object $payment
     * @param numeric $amount
     * @param string $type
     *
     * @return String 
     */
    private function _prepareTransactionRequestBody($payment) {
        $_customer = Mage::getModel('customer/customer')->load($payment->getOrder()->getCustomerId());
        $_customerAddresses = $this->getCustomerUsedAddressInOrder($payment);

        $_customerShippingAddress = $_customerAddresses['shipping_address'];
        $_customerBillingAddress = $_customerAddresses['billing_address'];
        // Get the true token saved with order
        $totals = Mage::getSingleton('checkout/session')->getQuote()->getTotals();

        $_transactionId = NULL;
        $note = '';
        $type = $payment->getAnetTransType();
        $amount = $payment->getAmount();

        // Switch the transaction type and prepare the function request code and request notes
        switch ($type) {
            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_AUTH_ONLY:
                $note .= 'Auth Request for amount ' . $amount . '';
                $functionRequestCode = '1B';
                $_trueToken = $payment->getOrder()->getPayment()->getI4goTrueToken();
                $_saleFlag = 'S';
                break;

            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_AUTH_CAPTURE:
                $note .= 'Sales Request for amount ' . $amount . '';
                $_trueToken = $payment->getOrder()->getPayment()->getI4goTrueToken();
                $_saleFlag = 'S';
                $functionRequestCode = '1D';
                break;

            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
                $note .= 'Preauth capture Request for amount ' . $amount . '';
                $_trueToken = $payment->getI4GoTrueToken();
                $_saleFlag = 'S';
                $functionRequestCode = '1D';
                break;

            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_VOID:
                $_transactionId = $payment->getLastTransId();
                $_trueToken = $payment->getI4GoTrueToken();
                $note .= 'Processing void request for transaction ' . $_transactionId;
                $_saleFlag = 'S';
                $functionRequestCode = '08';
                break;

            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_VOID_RETRY:
                $_transactionId = $payment->getLastTransId();
                $_trueToken = $payment->getI4GoTrueToken();
                $note .= 'Processing void request for transaction ' . $_transactionId;
                $_saleFlag = 'C';
                $functionRequestCode = '1D';
                break;

            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_CREDIT:
                $note .= 'Processing refund request for amount ' . $amount;
                $_trueToken = $payment->getI4GoTrueToken();
                $_transactionId = $payment->getLastTransId();
                $functionRequestCode = '1D';
                $_saleFlag = 'C';
                break;
        }

        $message = $note . ' True Token in order is ==  ' . $_trueToken;
        $this->logAPICommunications($message);

        // Get the common fields for the Shift4 API request
        $requestFields = $this->_getCommonFieldsForShift4Request();

        $requestFields['FunctionRequestCode'] = $functionRequestCode;
        $requestFields['Notes'] = '<p>' . $note . '</p>';

        if (isset($_saleFlag)) {
            $requestFields['SaleFlag'] = $_saleFlag;
        }

        $requestFields['UniqueID'] = $_trueToken;
        $requestFields['CardPresent'] = 'N';
        $requestFields['CardEntryMode'] = 'M';
        $requestFields['Vendor'] = $this->_vendorName;
        $requestFields['CustomerName'] = $_customer->getData('firstname') . " " . $_customer->getData('lastname');
        $requestFields['CustomerReference'] = $_customer->getId();
        if ($_customerShippingAddress->getData('postcode')) {
            $requestFields['DestinationZipCode'] = $_customerShippingAddress->getData('postcode');
        }
        /* added for AVS response */
        if ($_customerBillingAddress->getData('street')) {
            $requestFields['StreetAddress'] = $_customerBillingAddress->getData('street');
        }
        if ($_customerBillingAddress->getData('postcode')) {
            $requestFields['ZipCode'] = $_customerBillingAddress->getData('postcode');
        }
        if ($type != Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_VOID && $type != Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_VOID_RETRY) {
            $requestFields['PrimaryAmount'] = $amount;
        }
        // Exclude the tranID for void transaction request
        if (isset($_transactionId) && $type != Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_VOID && $type != Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_VOID_RETRY) {
            $requestFields['TranID'] = $_transactionId;
        }
        // The Magento order id will reflect as a invoice id in Shift4 transaction
        if ($payment->getOrder()->getIncrementId()) {
            $paymentId = $payment->getId();
            $paymentIdLast3 = substr($paymentId, -3);

            if ($type == Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_PRIOR_AUTH_CAPTURE) {
                $requestFields['Invoice'] = $payment->getShift4InvoiceId();
            } else {
                $requestFields['Invoice'] = substr($payment->getOrder()->getIncrementId(), -5) . $paymentIdLast3 . $payment->getShift4TransCount();
            }
            $payment->setLastShift4InvoiceId($requestFields['Invoice']);
        }
        $cart = Mage::getModel('checkout/cart')->getQuote();
        $cartItemCount = Mage::helper('checkout/cart')->getItemsCount();
        if ($cartItemCount && ($type == Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_AUTH_ONLY || $type == Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_AUTH_CAPTURE)) {
            $i = 1;
            foreach ($cart->getAllItems() as $item) {
                $requestFields['ProductDescriptor' . $i] = $item->getProduct()->getName();
                ;
                if ($i == 4)
                    break;
                $i++;
            }
            if (isset($totals['tax']) && $totals['tax']->getValue()) {
                $requestFields['TaxIndicator'] = 'Y';
                $requestFields['TaxAmount'] = $totals['tax']->getValue();
            } else {
                $requestFields['TaxIndicator'] = 'N';
            }
        }

        $message = 'Request field for ' . $type . '== ' . print_r($requestFields, true);
        $this->logAPICommunications($message);

        return $requestFields;
    }

    /**
     * Get the fields that are common for Shift4 API request 
     *
     * @param none
     *
     * @return Array 
     */
    private function _getCommonFieldsForShift4Request() {
        if ($this->_mode == 'demo') {
            $accessToken = $this->_accessTokenForDemoMode;
        } else {
            $accessToken = Mage::getStoreConfig('payment/shift4_payment/access_token');
        }

        $apiOptions = 'ALLDATA, ALLOWPARTIALAUTH';
        if ($this->_isMultishipping()) {
            $apiOptions = 'ALLDATA';
        }
        $commonFields = array(
            'AccessToken' => $accessToken,
            'APIFormat' => 0,
            'APIOptions' => $apiOptions,
            'APISignature' => '$',
            'CONTENTTYPE' => 'XML',
            'Date' => date('mdy'),
            'RequestorReference' => mt_rand(100000, 99999999),
            'STX' => 'Yes',
            'ETX' => 'Yes',
            'Time' => gmdate('his', time()),
            'ReceiptTextColumns' => 3,
        );

        return $commonFields;
    }

    /**
     * Make request based on the configuration
     *
     * @param array $requestFields
     *
     * @return Array 
     * @throws Mage_Core_Exception
     */
    protected function _postRequest($requestFields) {
        $data = str_replace('+', '%20', http_build_query($requestFields, '', '&'));
        if ($this->_isServerToServerCall) {
            $xml_response = $this->_makeServerToServerApiCall($data);
            // API call is server to server
        } else {
            // Call is via UTG
            $array_server_Addresses = explode(",", $this->_shift4EndPoint);
            $endPoint = $array_server_Addresses[0];
            $message = 'Calling Shift4 API via UTG mode. End point= ' . $endPoint;
            $this->logAPICommunications($message);

            $xml_response = $this->_executeRequest($data, $endPoint);
        }

        if ($xml_response) {
            $response = $this->_readShift4ApiResponse($xml_response);

            return $response;
        } else {
            $errorMsg = Mage::helper('shift4')->__('There has been error processing the request. Please try again.');
            Mage::throwException($errorMsg);
        }
    }

    /**
     * Check if the checkout type is multishipping
     *
     * @return bool true/false
     */
    public function _isMultishipping() {
        return Mage::getSingleton('checkout/session')->getQuote()->getIsMultiShipping();
    }

    /**
     * Get the customer shipping address and billing address in the current order
     * 
     * @param $_ordIncrementId
     * @return Mage_Sales_Model_Order
     */
    public function getCustomerUsedAddressInOrder($payment) {
        $shippingAddress = $payment->getOrder()->getShippingAddress();
        $billingAddress = $payment->getOrder()->getBillingAddress();

        $_address = array();
        $_address['shipping_address'] = new Varien_Object;
        $_address['billing_address'] = new Varien_Object;

        if ($shippingAddress) {
            $shippingAddressId = $shippingAddress->getData('customer_address_id');
            if ((int) $shippingAddressId) {
                $_address['shipping_address'] = Mage::getModel('customer/address')->load($shippingAddressId);
            } else {
                $_address['shipping_address'] = $shippingAddress;
            }
        }
        if ($billingAddress) {
            $billingingAddressId = $billingAddress->getData('customer_address_id');
            if ((int) $billingingAddressId) {
                $_address['billing_address'] = Mage::getModel('customer/address')->load($billingingAddressId);
            } else {
                $_address['billing_address'] = $billingAddress;
            }
        }

        return $_address;
    }

    /**
     * Display the API error like timeout, server unavailable etc.
     *
     * @param Int $errorCode
     *
     * @return string
     */
    public function getUserFriendlyErrorForShift4($result) {
        // Load the local error file from error folder 
        $primaryErrorCode = $result->primaryerrorcode;
        $shift4ErrorCodeFile = Mage::getBaseDir() . DS . 'errors' . DS . $this->_errorCodeFile;

        if (file_exists($shift4ErrorCodeFile)) {
            $xml = simplexml_load_file($shift4ErrorCodeFile);
            // convert the xml object to array 
            $xmlStringToArray = json_decode(json_encode((array) $xml), true);
            foreach ($xmlStringToArray['error-code'] as $errorContainer) {
                $codes = explode(",", $errorContainer['values']);
                // If error code exists, fetch the error detail
                if (in_array($primaryErrorCode, $codes)) {
                    $errorMessage = (!empty($errorContainer['detail'])) ? $errorContainer['detail'] : $errorContainer['message'];
                    break;
                }
            }
            if (empty($errorMessage)) {
                $longError = (string) $result->longerror;
                $shortError = (string) $result->shorterror;
                // Display the error message that returned from Shift4
                $errorMessage = (!empty($longError)) ? $longError : $shortError;
            }
        } else {
            // Error code file does not exists
            $this->logAPICommunications('The error code file does not exists', 'problems');
            $errorMessage = 'There has been error processing your request. Please try later';
        }

        return urldecode($errorMessage);
    }

    /**
     * Execute the API Request
     *
     * @param String $requestBody
     * @param string $endpoint
     *
     * @return json/xml string
     */
    private function _executeRequest($requestBody, $endpoint) {       
        $handler = curl_init();
        curl_setopt($handler, CURLOPT_URL, $endpoint);
        curl_setopt($handler, CURLOPT_POSTFIELDS, $requestBody);
        curl_setopt($handler, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($handler, CURLOPT_TIMEOUT, $this->_timeout); // Set global timer for operation

        if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1') {
            curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, false);
        }

        $response = curl_exec($handler);

        if (curl_errno($handler)) {
            $error_num = curl_errno($handler);
            $error_desc = 'cURL ERROR -> ' . curl_errno($handler) . ': ' . curl_error($handler);
            $message = "Error to execute a curl request :" . $error_num . "-" . $error_desc;
            $this->logAPICommunications($message, 'problems');

            return false;
        } else {
            $returnCode = (int) curl_getinfo($handler, CURLINFO_HTTP_CODE);
            $error_num = $returnCode;
            switch ($returnCode) {
                case 200:
                    $error_num = 0;
                    break;

                case 400:
                    $error_desc = 'ERROR -> 400 Bad Request';
                    break;

                case 404:
                    $error_desc = 'ERROR -> 404 Not Found';
                    break;

                case 500:
                    $error_desc = 'ERROR -> 500 Internal Server Error';
                    break;

                case 503:
                    $error_desc = 'ERROR -> 503 Service Unavailable';
                    break;

                default:
                    $error_desc = 'HTTP ERROR -> ' . $returnCode;
                    break;
            }
            if (isset($error_desc)) {
                $message = "Error to execute a curl request :" . $error_num . "-" . $error_desc;
                $this->logAPICommunications($message, 'problems');
            }
        }

        curl_close($handler);
        if ($returnCode == 200) {

            return $response;
        }
    }

}
