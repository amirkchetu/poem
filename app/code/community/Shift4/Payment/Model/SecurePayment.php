<?php

/**
 * Shift4 payment method.
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */
class Shift4_Payment_Model_SecurePayment extends Mage_Payment_Model_Method_Cc {
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */

    const METHOD_CODE = 'shift4_payment';

    protected $_code = self::METHOD_CODE;
    protected $_formBlockType = 'shift4/payment_form_cc';
    protected $_infoBlockType = 'shift4/payment_info_cc';

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial = false;

    /**
     * Can refund online?
     */
    protected $_canRefund = true;

    /**
     * Can void transactions online?
     */
    protected $_canVoid = true;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = false;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping = true;

    /**
     * Is this payment method suitable for multi-shipping checkout with partial payment?
     */
    protected $_canUseForMultishippingPartialPayment = false;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;
    protected $_canOrder = true;

    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';
    const REQUEST_TYPE_CREDIT = 'CREDIT';
    const REQUEST_TYPE_VOID = 'VOID';
    const REQUEST_TYPE_VOID_RETRY = 'VOID_RETRY';
    const REQUEST_TYPE_PRIOR_AUTH_CAPTURE = 'PRIOR_AUTH_CAPTURE';
    const RESPONSE_CODE_APPROVED = 'A';
    const RESPONSE_CODE_APPROVED_WITHOUT_AUTH = 'C';
    const RESPONSE_CODE_REFERAL_ERROR = 'R';
    const RESPONSE_CODE_DECLINED = 'D';
    const RESPONSE_CODE_CARD_EXPIRED = 'X';
    const RESPONSE_CODE_NETWORK_TIMEOUT = 'O';
    const RESPONSE_CODE_AVS_CVV2_FAILURE = 'f';
    const RESPONSE_CODE_ERROR_E = 'e';

    /* Internally handeled response codes */
    const RESPONSE_CODE_HELD = 'H';
    const RESPONSE_CODE_AVS_FAILURE = 'S';
    const RESPONSE_CODE_CVV2_FAILURE = 'V';
    const RESPONSE_REASON_CODE_PARTIAL_APPROVE = 'P';
    const RESPONSE_REASON_CODE_PENDING_REVIEW_AUTHORIZED = 252;
    const RESPONSE_REASON_CODE_PENDING_REVIEW = 253;
    const RESPONSE_REASON_CODE_PENDING_REVIEW_DECLINED = 254;
    const RESPONSE_REASON_CODE_INVOICE_NOT_FOUND = 9815;
    const PARTIAL_AUTH_CARDS_LIMIT = 5;
    const PARTIAL_AUTH_LAST_SUCCESS = 'last_success';
    const PARTIAL_AUTH_LAST_DECLINED = 'last_declined';
    const PARTIAL_AUTH_ALL_CANCELED = 'all_canceled';
    const PARTIAL_AUTH_CARDS_LIMIT_EXCEEDED = 'card_limit_exceeded';
    const PARTIAL_AUTH_DATA_CHANGED = 'data_changed';
    const TRANSACTION_STATUS_EXPIRED = 'expired';

    /**
     * Key for storing partial authorization last action state in session
     * @var string
     */
    protected $_partialAuthorizationLastActionStateSessionKey = 'shift4_payment_last_action_state';

    /**
     * Key for storing fraud transaction flag in additional information of payment model
     * @var string
     */
    protected $_isTransactionFraud = 'is_transaction_fraud';

    /**
     * Key for storing transaction id in additional information of payment model
     * @var string
     */
    protected $_realTransactionIdKey = 'real_transaction_id';

    /**
     * Key for storing split tender id in additional information of payment model
     * @var string
     */
    protected $_splitTenderIdKey = 'shift4_tender_id';

    /**
     * Array holding the timeout error codes
     * @var Array
     */
    // Add more error codes
    protected $_timeOutErrorCode = array(1001, 4003, 9033, 9951, 9960, 9961, 9964, 9012, 9018, 9020, 9023, 9489, 9901, 9902, 9957, 9962, 9978);

    /**
     * Can use the Shift4 payment on the multishipping for the partial payment
     *
     * @param   mixed $data
     * 
     * @return  Mage_Payment_Model_Info
     */
    protected function canUseForPartialPaymentWithMultishipping() {
        return $this->_canUseForMultishippingPartialPayment;
    }

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * 
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();
        $info->setCcType($data->getCcType())
                ->setCcOwner($data->getCcOwner())
                ->setCcLast4(substr($data->getCardToken(), 0, 4))
                ->setCcNumber(null)
                ->setCcExpMonth($data->getCcExpMonth())
                ->setCcExpYear($data->getCcExpYear())
                ->setCcSsIssue($data->getCcSsIssue())
                ->setCcSsStartMonth($data->getCcSsStartMonth())
                ->setCcSsStartYear($data->getCcSsStartYear());

        return $this;
    }

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * 
     * @return  Shift4_Payment_Model_SecurePayment
     */
    public function validate() {
        return $this;
    }

    /**
     * Validate AVS is enabled or not
     * 
     * @return boolean true/false 
     */
    public function isAVSDisabled() {
        $avsEnabled = Mage::getStoreConfig('payment/shift4_payment/support_avs', Mage::app()->getStore());
        if ($avsEnabled == 1) {
            return false;
        }

        return true;
    }

    /**
     * Authorize payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function authorize(Varien_Object $payment, $amount) {

        if (!$this->canAuthorize()) {
            Mage::throwException(Mage::helper('payment')->__('Authorize action is not available.'));
        }

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('shift4')->__('Invalid amount for authorization.'));
        }

        $this->_initCardsStorage($payment);

        if ($this->isPartialAuthorization($payment)) {
            $this->_partialAuthorization($payment, $amount, self::REQUEST_TYPE_AUTH_ONLY);
            $payment->setSkipTransactionCreation(true);
            return $this;
        }

        $this->_startPlace($payment, $amount, self::REQUEST_TYPE_AUTH_ONLY);
        $payment->setSkipTransactionCreation(true);

        return $this;
    }

    /**
     * Capture payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount) {
        if (!$this->canCapture()) {
            Mage::throwException(Mage::helper('payment')->__('Capture action is not available.'));
        }
        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for capture.'));
        }
        $this->_initCardsStorage($payment);

        if ($this->_isPreauthorizeCapture($payment)) {
            $this->_preauthorizeCapture($payment, $amount);
        } else if ($this->isPartialAuthorization($payment)) {
            $this->_partialAuthorization($payment, $amount, self::REQUEST_TYPE_AUTH_CAPTURE);
        } else {
            $this->_startPlace($payment, $amount, self::REQUEST_TYPE_AUTH_CAPTURE);
        }
        $payment->setSkipTransactionCreation(true);

        return $this;
    }

    /**
     * Refund the amount with transaction id
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal $amount
     * 
     * @return Shift4_Payment_Model_SecurePayment
     * 
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $requestedAmount) {
        $baseGrandTotal = $payment->getOrder()->getBaseGrandTotal();
        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }
        $payment->setAnetTransType(self::REQUEST_TYPE_CREDIT);

        $creditmemo = Mage::app()->getRequest()->getParam('creditmemo');
        $cardId = isset($creditmemo['used_card_id']) ? $creditmemo['used_card_id'] : null;
        $cardIdKey = $creditmemo['card_id_key'];

        $allowPartialRefund = isset($creditmemo['allow_partial_refund']) ? true : false;

        $cardsStorage = $this->getCardsStorage($payment);

        if ($this->_formatAmount($requestedAmount) < $this->_formatAmount($baseGrandTotal) && empty($allowPartialRefund) && empty($cardId)
        ) {
            Mage::throwException(Mage::helper('shift4')->__('Please check the "Allow Partial Refund" checkbox and select card number for partial refund.'));
        }

        if ($this->_formatAmount($requestedAmount) == $this->_formatAmount($baseGrandTotal) && !empty($allowPartialRefund) && !empty($cardId)
        ) {
            Mage::throwException(Mage::helper('shift4')->__('Invalid amount for the partial refund. The selected amount must be less that order grand total.'));
        }

        if ($this->_formatAmount(
                        $cardsStorage->getCapturedAmount() - $cardsStorage->getRefundedAmount()
                ) < $requestedAmount
        ) {
            Mage::throwException(Mage::helper('shift4')->__('Invalid amount for refund.'));
        }

        $messages = array();
        $isSuccessful = false;
        $isFiled = false;

        $paymentCards = $cardsStorage->getCards();
        $cards = array();

        if ($allowPartialRefund) {
            // Refund to the selected card
            foreach ($paymentCards as $card) {
                if ($card->getData($cardIdKey) == $cardId) {
                    // Selected cards exists in the payment cards array
                    $cards[$cardId] = $paymentCards[$cardId];
                    break;
                }
            }
        } else {
            $cards = $paymentCards;
        }

        foreach ($cards as $card) {
            if ($requestedAmount > 0) {
                $cardAmountForRefund = $this->_formatAmount($card->getCapturedAmount() - $card->getRefundedAmount());
                if ($cardAmountForRefund <= 0) {
                    continue;
                }
                /* if ($allowPartialRefund) {
                  // If refund amount grater than card capture amount, throw error
                  if ($this->_formatAmount($requestedAmount) > $this->_formatAmount($card->getCapturedAmount())) {
                  Mage::throwException(Mage::helper('shift4')->__('Max. amount available for refund for card xxxx-' . $card->getCcLast4() . ' is ' . $card->getCapturedAmount() . ". You can not refund more than captured amount."));
                  }
                  } */
                if ($cardAmountForRefund > $requestedAmount) {
                    $cardAmountForRefund = $requestedAmount;
                }

                if (!$allowPartialRefund) {
                    // First call the void, if the invoice is not found, call the refund for the complete refund
                    try {
                        $voidTransaction = $this->_voidCardTransaction($payment, $card);
                        // Check if the invoice was not found, if so, call refund
                        if ($voidTransaction === -1) {
                            try {
                                $newTransaction = $this->_refundCardTransaction($payment, $cardAmountForRefund, $card);
                                $messages[] = $newTransaction->getMessage();
                                $isSuccessful = true;
                            } catch (Exception $e) {
                                $messages[] = $e->getMessage();
                                $isFiled = true;
                                continue;
                            }
                        } else {
                            $messages[] = $voidTransaction->getMessage();
                            $card->setTransactionType("Refund");
                            $isSuccessful = true;
                        }
                    } catch (Exception $e) {
                        $messages[] = $e->getMessage();
                        $isFiled = true;
                        continue;
                    }
                } else {
                    $cardAmountForRefund = $requestedAmount;
                    try {
                        // Call 07 get invoice information request if transaction timeout
                        $newTransaction = $this->_refundCardTransaction($payment, $cardAmountForRefund, $card);
                        $messages[] = $newTransaction->getMessage();
                        $isSuccessful = true;
                    } catch (Exception $e) {
                        $messages[] = $e->getMessage();
                        $isFiled = true;
                        continue;
                    }
                }
                $card->setRefundedAmount($this->_formatAmount($card->getRefundedAmount() + $cardAmountForRefund));
                $card->setTransactionType('Refund');
                $cardsStorage->updateCard($card);
                $requestedAmount = $this->_formatAmount($requestedAmount - $cardAmountForRefund);
            } else {
                $payment->setSkipTransactionCreation(true);
                return $this;
            }
        }

        if ($isFiled) {
            $this->_processFailureMultitransactionAction($payment, $messages, $isSuccessful);
        }

        $payment->setSkipTransactionCreation(true);

        return $this;
    }

    /**
     * Void the payment through gateway
     *
     * @param  Mage_Payment_Model_Info $payment
     * 
     * @return Shift4_Payment_Model_SecurePayment
     */
    public function void(Varien_Object $payment) {
        if (!$this->canVoid($payment)) {
            Mage::throwException(Mage::helper('payment')->__('Void action is not available.'));
        }
        $payment->setAnetTransType(self::REQUEST_TYPE_VOID);

        $cardsStorage = $this->getCardsStorage($payment);
        $messages = array();
        $isSuccessful = false;
        $isFiled = false;
        // Cancel each card transaction
        foreach ($cardsStorage->getCards() as $card) {
            try {
                $newTransaction = $this->_voidCardTransaction($payment, $card);
                $messages[] = $newTransaction->getMessage();
                $card->setTransactionType("Void");
                $isSuccessful = true;
            } catch (Exception $e) {
                $messages[] = $e->getMessage();
                $isFiled = true;
                continue;
            }
            $cardsStorage->updateCard($card);
        }

        if ($isFiled) {
            $this->_processFailureMultitransactionAction($payment, $messages, $isSuccessful);
        }

        $payment->setSkipTransactionCreation(true);

        return $this;
    }

    /**
     * Cancel the payment through gateway
     *
     * @param  Mage_Payment_Model_Info $payment
     * 
     * @return Shift4_Payment_Model_SecurePayment
     */
    public function cancel(Varien_Object $payment) {
        return $this->void($payment);
    }

    /**
     * Cancel partial authorizations and flush current split_tender_id record
     *
     * @param Mage_Payment_Model_Info $payment
     * 
     * @throws Mage_Core_Exception
     */
    public function cancelPartialAuthorization(Mage_Payment_Model_Info $payment) {
        // To Do
        if (!$payment->getAdditionalInformation($this->_splitTenderIdKey)) {
            Mage::throwException(Mage::helper('shift4')->__('Invalid shift4 partial authorization.'));
        }
        // Process void request for each payment done by the user
        $cardsData = $this->getCardsStorage($payment)->getCards();

        if (is_array($cardsData) && count($cardsData)) {
            foreach ($cardsData as $cardInfo) {
                if ($cardInfo->getProcessedAmount() && $cardInfo->getShift4InvoiceId()) {
                    // Process the void request
                    $payment->setShift4InvoiceId($cardInfo->getShift4InvoiceId());
                    $payment->setI4GoTrueToken($cardInfo->getI4GoTrueToken());
                    $result = Mage::getModel('shift4/api')->cancelShift4Transaction($payment);

                    $message = 'Payment cancelling for ' . $cardInfo->getShift4InvoiceId() . ' response ' . print_r($result, true);
                    Mage::getModel('shift4/api')->logAPICommunications($message, 'problems');

                    if ($result->getError() == 1) {
                        Mage::throwException($result->getErrorToShowUser());
                    } else {
                        $responseCode = $this->_getResponseCode($result->getResponse());
                        switch ($responseCode) {
                            case self::RESPONSE_CODE_APPROVED:
                                $payment->setAdditionalInformation($this->_splitTenderIdKey, null);
                                $this->getCardsStorage($payment)->flushCards();
                                $this->setPartialAuthorizationLastActionState(self::PARTIAL_AUTH_ALL_CANCELED);
                                return;
                            default:
                                Mage::throwException(Mage::helper('shift4')->__('Payment cancelling error for invoice #' . $cardInfo->getShift4InvoiceId() . '.'));
                        }
                    }
                } else {
                    Mage::throwException(Mage::helper('shift4')->__('Payment cancelling error'));
                }
            }
        } else {
            Mage::throwException(Mage::helper('shift4')->__('Payment data not found'));
        }
    }

    /**
     * Send request with new payment to gateway
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal $amount
     * @param string $requestType
     * 
     * @return Shift4_Payment_Model_SecurePayment
     * 
     * @throws Mage_Core_Exception
     */
    protected function _startPlace($payment, $amount, $requestType) {
        $payment->setAnetTransType($requestType);
        $payment->setAmount($amount);

        $transCount = ($this->getCardsStorage($payment)->getCardsCount() + 1);
        if ($transCount < 10) {
            $transCount = '0' . $transCount;
        }
        $payment->setShift4TransCount($transCount);

        $result = Mage::getModel('shift4/api')->callShift4TransactionApi($payment);

        $message = 'The response @' . $requestType . ' == ' . print_r($result, true);
        Mage::getModel('shift4/api')->logAPICommunications($message);

        if ($result->getError() == 1) {
            // Check if timeout occured          
            if ($this->_isTransactionTimeout($result->getResponse())) {
                sleep(5);
                $this->_doTransactionTimeout($payment, $result->getResponse(), '_place');
            } else {
                // Void the non-timeout error
                $this->_voidNotApprovedTransactions($payment, $result->getResponse(), $requestType);
                Mage::throwException($result->getErrorToShowUser());
            }
        } else {
            // Check for the validavs and cvv2valid and other response codes
            $this->_place($payment, $result->getResponse());
        }

        return $this;
    }

    /**
     * Place the order after authorization
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal $amount
     * @param string $requestType
     * 
     * @return Shift4_Payment_Model_SecurePayment
     * 
     * @throws Mage_Core_Exception
     */
    protected function _place($payment, $result) {
        $requestType = $payment->getAnetTransType();
        $amount = $payment->getAmount();

        switch ($requestType) {
            case self::REQUEST_TYPE_AUTH_ONLY:
                $newTransactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
                $defaultExceptionMessage = Mage::helper('shift4')->__('Payment authorization error.');
                break;

            case self::REQUEST_TYPE_AUTH_CAPTURE:
                $newTransactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
                $defaultExceptionMessage = Mage::helper('shift4')->__('Payment capturing error.');
                break;
        }

        $this->setShift4ResponseIfPartialApproveRequired($payment, $result);
        $responseCode = $this->_getResponseCode($result);

        switch ($responseCode) {
            case self::RESPONSE_CODE_APPROVED:
                $this->getCardsStorage($payment)->flushCards();
                $card = $this->_registerCard($result, $payment, $requestType);
                $this->_addTransaction(
                        $payment, $card->getLastTransId(), $newTransactionType, array('is_transaction_closed' => 0), array($this->_realTransactionIdKey => $card->getLastTransId()), Mage::helper('shift4')->getTransactionMessage(
                                $payment, $requestType, $card->getLastTransId(), $card, $amount
                        )
                );
                if ($requestType == self::REQUEST_TYPE_AUTH_CAPTURE) {
                    $card->setCapturedAmount($card->getProcessedAmount());
                    $card->setTransactionType($requestType);
                    $this->getCardsStorage($payment)->updateCard($card);
                }
                return $this;
            case self::RESPONSE_CODE_HELD:
                if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_PENDING_REVIEW_AUTHORIZED || $result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_PENDING_REVIEW
                ) {
                    $card = $this->_registerCard($result, $payment, $requestType);
                    $this->_addTransaction(
                            $payment, $card->getLastTransId(), $newTransactionType, array('is_transaction_closed' => 0), array(
                        $this->_realTransactionIdKey => $card->getLastTransId(),
                        $this->_isTransactionFraud => true
                            ), Mage::helper('shift4')->getTransactionMessage(
                                    $payment, $requestType, $card->getLastTransId(), $card, $amount
                            )
                    );
                    if ($requestType == self::REQUEST_TYPE_AUTH_CAPTURE) {
                        $card->setCapturedAmount($card->getProcessedAmount());
                        $card->setTransactionType($requestType);
                        $this->getCardsStorage()->updateCard($card);
                    }
                    $payment
                            ->setIsTransactionPending(true)
                            ->setIsFraudDetected(true);
                    return $this;
                }
                if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_PARTIAL_APPROVE) {
                    if ($this->_processPartialAuthorizationResponse($result, $payment, $requestType)) {
                        return $this;
                    }
                }
                Mage::throwException($defaultExceptionMessage);
            case self::RESPONSE_CODE_ERROR_E:
                // check if the authorization code is a timeout error
                if ($this->_isTransactionTimeout($result)) {
                    // Do not void the transaction, log the response data
                    Mage::log('The Invoice #' . $result->getInvoice() . ' has the complete timeout error. Response Data is:-' . print_r($result, true), null, 'timeout.log');
                    Mage::throwException($this->_wrapGatewayError($responseCode));
                }

                break;
            case self::RESPONSE_CODE_DECLINED:
            case self::RESPONSE_CODE_REFERAL_ERROR:
            case self::RESPONSE_CODE_AVS_CVV2_FAILURE:
            case self::RESPONSE_CODE_AVS_FAILURE:
            case self::RESPONSE_CODE_CVV2_FAILURE:
            case self::RESPONSE_CODE_CARD_EXPIRED:
            case self::RESPONSE_CODE_NETWORK_TIMEOUT: // Please check with Jerry
                // Do the auto void 
                $this->_voidNotApprovedTransactions($payment, $result, $newTransactionType);
                Mage::throwException($this->_wrapGatewayError($responseCode));
                break;

            default:
                $this->_voidNotApprovedTransactions($payment, $result, $newTransactionType);
                Mage::throwException($defaultExceptionMessage);
        }
    }

    /**
     * Void the transaction if AVS failure or referal 
     *
     * @param Mage_Payment_Model_Info $payment
     * @param varien_object $result
     * 
     * @return Shift4_Payment_Model_SecurePayment
     * 
     * @throws Mage_Core_Exception
     */
    protected function _voidNotApprovedTransactions($payment, $result) {
        $requestType = $payment->getAnetTransType();
        $card = $this->_registerCard($result, $payment, $requestType);

        $payment->setShift4InvoiceId($card->getShift4InvoiceId());
        $payment->setI4GoTrueToken($card->getI4GoTrueToken());

        $result = Mage::getModel('shift4/api')->cancelShift4Transaction($payment);
        Mage::getModel('shift4/api')->logAPICommunications("The void response == " . print_r($result, true));
        if ($result->getError() == 1) {
            // Check if Invoice not found error exists  ... Check with Jerry    
            Mage::getModel('shift4/api')->logAPICommunications("The invoice # " . $result->getInvoice() . " could not be voided. Resposne is: " . print_r($result, true), 'problems');
            $errorMsg = Mage::helper('shift4')->__($result->getErrorToShowUser());
            Mage::throwException($errorMsg);
        }
    }

    /**
     * Check if there is error in communication/ timeout
     *
     * @param Mage_Payment_Model_Info $payment
     * @param varien_object $result
     * @param string $callback
     * 
     * @return Shift4_Payment_Model_SecurePayment
     * 
     * @throws Mage_Core_Exception
     */
    protected function _doTransactionTimeout($payment, $result, $callback) {
        $payment->setI4GoTrueToken($result->getUniqueid());
        $invoice = Mage::getModel('shift4/api')->getShift4InvoiceStatus($payment);
        Mage::getModel('shift4/api')->logAPICommunications("The get invoice response ==" . print_r($invoice, true));
        $responseCode = $this->_getResponseCode($invoice->getResponse());
        // Check if again timeout        
        if ($invoice->getError() == 1) {
            // Log the timeout error
            Mage::log('The Invoice #' . $invoice->getInvoice() . ' has timeout response. The data is:' . print_r($invoice, true), null, 'timeout.log');
            switch ($responseCode) {
                case self::RESPONSE_CODE_ERROR_E:
                case self::RESPONSE_CODE_NETWORK_TIMEOUT:
                    Mage::throwException('Client socket time-out. Please check the internet connection.');
                    break;
                default:
                    Mage::throwException('The was an error processing the request. Please try again.');
            }
        } else if (empty($responseCode)) {
            // Check the blank response
            Mage::log('The response was blank in the timeout response. Data is:-' . print_r($invoice, true), null, 'timeout.log');
            Mage::throwException('Unknown error during communication.');
        } else {
            // No error in the response, check if the transaction was approved
            $this->$callback($payment, $invoice->getResponse());
        }
    }

    /**
     * Check if transaction timeout
     *
     * @param Varien_Object $response
     *
     * @return Shift4_Payment_Model_SecurePayment
     */
    protected function _isTransactionTimeout($response) {
        // check if transaction is a timeout and call the get invoice information request
        $errorCode = $response->getPrimaryerrorcode();
        $authCode = $response->getAuthorization();
        // Test the other timeout error for 2XXX -> 2000 -> 2999
        $timeOutErrorCodes = array();
        for ($error = 2000; $error <= 2999; $error++) {
            $timeOutErrorCodes[] = $error;
        }
        if (in_array($errorCode, $this->_timeOutErrorCode)) {
            return true;
        } else if (in_array($errorCode, $timeOutErrorCodes)) {
            return true;
        } else if (in_array((int) $authCode, $this->_timeOutErrorCode)) {
            return true;
        }

        return false;
    }

    /**
     * Check if partial approval required
     *
     * @param Mage_Payment_Model_Info $payment
     * @param Varien_Object $result
     *
     * @return Shift4_Payment_Model_SecurePayment
     */
    protected function setShift4ResponseIfPartialApproveRequired($payment, $result) {
        $requestedAmount = $payment->getAmount();
        $authorizedAmount = $result->getPreauthorizedamount();

        $responseCode = $this->_getResponseCode($result);

        if ($responseCode == self::RESPONSE_CODE_APPROVED) {
            /* Check if AVS in not Enabled */
            if ($this->isAVSDisabled()) {
                $result->setData('validavs', 'Y');
            }
            /* Check if CVV is not available */
            if (!$result->getCvv2valid()) {
                $result->setData('cvv2valid', 'Y');
            }
            /* Used for the partial payment */
            if ($authorizedAmount < $requestedAmount && ($result->getValidavs() == 'Y') && ($result->getCvv2valid()) == 'Y') {
                $responseCode = self::RESPONSE_CODE_HELD;
                $result->setData('response_reason_code', self::RESPONSE_REASON_CODE_PARTIAL_APPROVE); // Set for the partial approval		
            }
            // If the AVS is not valid but the response is A, change the respone as 
            if ($result->getValidavs() == 'N' && $result->getCvv2valid() == 'N') {
                $responseCode = self::RESPONSE_CODE_AVS_CVV2_FAILURE;
            } elseif ($result->getValidavs() == 'N') {
                $responseCode = self::RESPONSE_CODE_AVS_FAILURE;
            } elseif ($result->getCvv2valid() == 'N') {
                $responseCode = self::RESPONSE_CODE_CVV2_FAILURE;
            }


            /* Change the responsecode */
            if ($result->getResponse()) {
                $result->setData('response', $responseCode);
            } else {
                $result->setData('responsecode', $responseCode);
            }
        }
    }

    /**
     * Send request with new payment to gateway during partial authorization process
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal $amount
     * @param string $requestType
     * 
     * @return Shift4_Payment_Model_SecurePayment
     */
    protected function _partialAuthorization($payment, $amount, $requestType) {
        $payment->setAnetTransType($requestType);

        /*
         * Try to build checksum of first request and compare with current checksum
         */
        $amount = $amount - $this->getCardsStorage()->getProcessedAmount();

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('shift4')->__('Invalid amount for partial authorization.'));
        }

        $transCount = ($this->getCardsStorage($payment)->getCardsCount() + 1);
        if ($transCount < 10) {
            $transCount = '0' . $transCount;
        }
        $payment->setShift4TransCount($transCount);

        $payment->setAmount($amount);
        $result = Mage::getModel('shift4/api')->callShift4TransactionApi($payment);

        $message = 'The response @' . $requestType . ' == ' . print_r($result, true);
        Mage::getModel('shift4/api')->logAPICommunications($message);

        $this->setShift4ResponseIfPartialApproveRequired($payment, $result->getResponse());
        $this->_processPartialAuthorizationResponse($result->getResponse(), $payment, $requestType);

        switch ($requestType) {
            case self::REQUEST_TYPE_AUTH_ONLY:
                $newTransactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH;
                break;
            case self::REQUEST_TYPE_AUTH_CAPTURE:
                $newTransactionType = Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE;
                break;
        }

        foreach ($this->getCardsStorage()->getCards() as $card) {
            $this->_addTransaction(
                    $payment, $card->getLastTransId(), $newTransactionType, array('is_transaction_closed' => 0), array($this->_realTransactionIdKey => $card->getLastTransId()), Mage::helper('shift4')->getTransactionMessage(
                            $payment, $requestType, $card->getLastTransId(), $card, $card->getProcessedAmount()
                    )
            );
            if ($requestType == self::REQUEST_TYPE_AUTH_CAPTURE) {
                $card->setTransactionType($requestType);
                $card->setCapturedAmount($card->getProcessedAmount());
                $this->getCardsStorage()->updateCard($card);
            }
        }

        return $this;
    }

    /**
     * Return true if there are authorized transactions
     *
     * @param Mage_Payment_Model_Info $payment
     * 
     * @return bool
     */
    protected function _isPreauthorizeCapture($payment) {
        if ($this->getCardsStorage()->getCardsCount() <= 0) {
            return false;
        }
        foreach ($this->getCardsStorage()->getCards() as $card) {
            $lastTransaction = $payment->getTransaction($card->getLastTransId());

            if (!$lastTransaction || $lastTransaction->getTxnType() != Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Send capture request to gateway for capture authorized transactions
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal $amount
     * 
     * @return Shift4_Payment_Model_SecurePayment
     */
    protected function _preauthorizeCapture($payment, $requestedAmount) {
        $cardsStorage = $this->getCardsStorage($payment);

        if ($this->_formatAmount(
                        $cardsStorage->getProcessedAmount() - $cardsStorage->getCapturedAmount()
                ) < $requestedAmount
        ) {
            Mage::throwException(Mage::helper('shift4')->__('Invalid amount for capture.'));
        }

        $messages = array();
        $isSuccessful = false;
        $isFiled = false;
        foreach ($cardsStorage->getCards() as $card) {
            if ($requestedAmount > 0) {
                $cardAmountForCapture = $card->getProcessedAmount();
                if ($cardAmountForCapture > $requestedAmount) {
                    $cardAmountForCapture = $requestedAmount;
                }
                try {
                    $newTransaction = $this->_preauthorizeCaptureCardTransaction(
                            $payment, $cardAmountForCapture, $card
                    );
                    $messages[] = $newTransaction->getMessage();
                    $isSuccessful = true;
                } catch (Exception $e) {
                    $messages[] = $e->getMessage();
                    $isFiled = true;
                    continue;
                }
                $card->setCapturedAmount($cardAmountForCapture);
                $card->setTransactionType('Sale');
                $cardsStorage->updateCard($card);
                $requestedAmount = $this->_formatAmount($requestedAmount - $cardAmountForCapture);
            } else {
                /**
                 * This functional is commented because partial capture is disable. See self::_canCapturePartial.
                 */
                //$this->_voidCardTransaction($payment, $card);
            }
        }

        if ($isFiled) {
            $this->_processFailureMultitransactionAction($payment, $messages, $isSuccessful);
        }

        return $this;
    }

    /**
     * Send capture request to gateway for capture authorized transactions of card
     *
     * @param Mage_Payment_Model_Info $payment
     * @param decimal $amount
     * @param Varien_Object $card
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     * @throws Mage_Core_Exception
     */
    protected function _preauthorizeCaptureCardTransaction($payment, $amount, $card) {
        $authTransactionId = $card->getLastTransId();
        $authTransaction = $payment->getTransaction($authTransactionId);
        $realAuthTransactionId = $authTransaction->getAdditionalInformation($this->_realTransactionIdKey);

        $payment->setAnetTransType(self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE);
        $payment->setI4GoTrueToken($card->getI4GoTrueToken());
        $payment->setShift4InvoiceId($card->getShift4InvoiceId());
        //$payment->setXTransId($realAuthTransactionId); // Need to check later
        $payment->setAmount($amount);

        $result = Mage::getModel('shift4/api')->callShift4TransactionApi($payment);

        $message = 'The response preauth capture == ' . print_r($result, true);
        Mage::getModel('shift4/api')->logAPICommunications($message);

        if ($result->getError() == 1) {
            Mage::throwException($result->getErrorToShowUser());
        } else {
            $result = $result->getResponse();
            $responseCode = $this->_getResponseCode($result);

            switch ($responseCode) {
                case self::RESPONSE_CODE_APPROVED:
                    //if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_APPROVED) {
                    $captureTransactionId = $result->getTranid() . '-capture';
                    $card->setLastTransId($captureTransactionId);
                    return $this->_addTransaction(
                                    $payment, $captureTransactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, array(
                                'is_transaction_closed' => 0,
                                'parent_transaction_id' => $authTransactionId
                                    ), array($this->_realTransactionIdKey => $result->getTranid()), Mage::helper('shift4')->getTransactionMessage(
                                            $payment, self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE, $result->getTranid(), $card, $amount
                                    )
                    );
                    //}                   
                    $exceptionMessage = Mage::helper('shift4')->__('Payment can not not be captured at this time.');
                    break;
                case self::RESPONSE_CODE_HELD:
                case self::RESPONSE_CODE_DECLINED:
                case self::RESPONSE_CODE_REFERAL_ERROR:
                    $exceptionMessage = $this->_wrapGatewayError($responseCode);
                    break;
                default:
                    $exceptionMessage = Mage::helper('shift4')->__('Payment capturing error.');
                    break;
            }
        }

        $exceptionMessage = Mage::helper('shift4')->getTransactionMessage(
                $payment, self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE, $realAuthTransactionId, $card, $amount, $exceptionMessage
        );
        Mage::throwException($exceptionMessage);
    }

    /**
     * Void the card transaction through gateway
     *
     * @param Mage_Payment_Model_Info $payment
     * @param Varien_Object $card
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     * @throws Mage_Core_Exception
     */
    protected function _voidCardTransaction($payment, $card) {
        if (!$this->canVoid($payment)) {
            Mage::throwException(Mage::helper('payment')->__('Void action is not available.'));
        }

        $authTransactionId = $card->getLastTransId();

        $authTransaction = $payment->getTransaction($authTransactionId);
        $realAuthTransactionId = $authTransaction->getAdditionalInformation($this->_realTransactionIdKey);

        $payment->setAnetTransType(self::REQUEST_TYPE_VOID);

        if ($card->getProcessedAmount() && $card->getShift4InvoiceId()) {
            $invoiceId = $card->getShift4InvoiceId();
            $trueToken = $card->getI4GoTrueToken();

            $payment->setShift4InvoiceId($invoiceId);
            $payment->setI4GoTrueToken($trueToken);

            $result = Mage::getModel('shift4/api')->cancelShift4Transaction($payment);

            $message = 'Payment cancelling for ' . $invoiceId . ' response ==' . print_r($result, true);
            Mage::getModel('shift4/api')->logAPICommunications($message, 'problems');

            if ($result->getError() == 1) {
                // Check if Invoice not found error exists            
                if ($result->getResponse()->getPrimaryerrorcode() == self::RESPONSE_REASON_CODE_INVOICE_NOT_FOUND) {
                    return -1;
                } else {
                    $errorMsg = Mage::helper('shift4')->__($result->getErrorToShowUser());
                    Mage::throwException($errorMsg);
                }
            } else {
                $result = $result->getResponse();
                $responseCode = $this->_getResponseCode($result);

                switch ($responseCode) {
                    case self::RESPONSE_CODE_APPROVED:
                        //if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_APPROVED) { //TBD
                        $voidTransactionId = $result->getTranid() . '-void';
                        $card->setLastTransId($voidTransactionId);
                        return $this->_addTransaction(
                                        $payment, $voidTransactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, array(
                                    'is_transaction_closed' => 1,
                                    'should_close_parent_transaction' => 1,
                                    'parent_transaction_id' => $authTransactionId
                                        ), array($this->_realTransactionIdKey => $result->getTranid()), Mage::helper('shift4')->getTransactionMessage(
                                                $payment, self::REQUEST_TYPE_VOID, $result->getTranid(), $card
                                        )
                        );
                        //}
                        $exceptionMessage = Mage::helper('shift4')->__('Payment cancelling error.');
                        break;
                    case self::RESPONSE_CODE_DECLINED:
                    case self::RESPONSE_CODE_REFERAL_ERROR:
                        $exceptionMessage = $this->_wrapGatewayError($responseCode);
                        break;
                    default:
                        $exceptionMessage = Mage::helper('shift4')->__('Payment voiding error.');
                        break;
                }
            }
        } else {
            Mage::throwException(Mage::helper('shift4')->__('Payment cancelling error'));
        }

        $exceptionMessage = Mage::helper('shift4')->getTransactionMessage(
                $payment, self::REQUEST_TYPE_VOID, $realAuthTransactionId, $card, false, $exceptionMessage
        );
        Mage::throwException($exceptionMessage);
    }

    /**
     * Check if transaction is expired
     *
     * @param  string $realAuthTransactionId
     * 
     * @return bool
     */
    protected function _isTransactionExpired($realAuthTransactionId) {
        //Testing.....under progress
        return self::TRANSACTION_STATUS_EXPIRED;
    }

    /**
     * Set split_tender_id to quote payment if needed
     *
     * @param Varien_Object $response
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return bool
     */
    protected function _processPartialAuthorizationResponse(Varien_Object $response, $orderPayment, $requestType) {
        $quotePayment = $orderPayment->getOrder()->getQuote()->getPayment();
        //$this->setPartialAuthorizationLastActionState(self::PARTIAL_AUTH_LAST_DECLINED);
        $exceptionMessage = null;

        try {
            $responseCode = $this->_getResponseCode($response);
            switch ($responseCode) {
                case self::RESPONSE_CODE_APPROVED:
                    $this->_registerCard($response, $orderPayment, $requestType);
                    $this->_clearAssignedData($quotePayment);
                    $this->setPartialAuthorizationLastActionState(self::PARTIAL_AUTH_LAST_SUCCESS);
                    return true;
                case self::RESPONSE_CODE_HELD:
                    if ($response->getResponseReasonCode() != self::RESPONSE_REASON_CODE_PARTIAL_APPROVE) {
                        return false;
                    }
                    if ($this->getCardsStorage($orderPayment)->getCardsCount() + 1 >= self::PARTIAL_AUTH_CARDS_LIMIT) {
                        $this->cancelPartialAuthorization($orderPayment);
                        $this->_clearAssignedData($quotePayment);
                        $this->setPartialAuthorizationLastActionState(self::PARTIAL_AUTH_CARDS_LIMIT_EXCEEDED);
                        $quotePayment->setAdditionalInformation($orderPayment->getAdditionalInformation());
                        $exceptionMessage = Mage::helper('shift4')->__('You have reached the maximum number of credit card allowed to be used for the payment.');
                        break;
                    }
                    $orderPayment->setAdditionalInformation($this->_splitTenderIdKey, 'shift4_partial_auth_key');
                    $this->_registerCard($response, $orderPayment, $requestType);
                    $this->_clearAssignedData($quotePayment);
                    $this->setPartialAuthorizationLastActionState(self::PARTIAL_AUTH_LAST_SUCCESS);
                    $quotePayment->setAdditionalInformation($orderPayment->getAdditionalInformation());
                    $exceptionMessage = null;
                    break;

                case self::RESPONSE_CODE_DECLINED:
                    $this->setPartialAuthorizationLastActionState(self::PARTIAL_AUTH_LAST_DECLINED);
                    $this->_voidNotApprovedTransactions($orderPayment, $response, $requestType);
                    Mage::throwException($this->_wrapGatewayError($responseCode));
                    break;
                case self::RESPONSE_CODE_REFERAL_ERROR:
                    $quotePayment->setAdditionalInformation($orderPayment->getAdditionalInformation());
                    $this->_voidNotApprovedTransactions($orderPayment, $response, $requestType);
                    $exceptionMessage = $this->_wrapGatewayError($responseCode);
                    break;
                case self::RESPONSE_CODE_AVS_CVV2_FAILURE:
                    // Need to void the transaction
                    // Do auto void 
                    $this->_voidNotApprovedTransactions($orderPayment, $response, $requestType);
                    Mage::throwException($this->_wrapGatewayError($responseCode));
                    break;
                default:
                    $quotePayment->setAdditionalInformation($orderPayment->getAdditionalInformation());
                    $this->_voidNotApprovedTransactions($orderPayment, $response, $requestType);
                    $exceptionMessage = $this->_wrapGatewayError(
                            Mage::helper('shift4')->__('Payment partial authorization error.')
                    );
            }
        } catch (Exception $e) {
            $exceptionMessage = $e->getMessage();
        }

        throw new Mage_Payment_Model_Info_Exception($exceptionMessage);
    }

    /**
     * It sets card`s data into additional information of payment model
     *
     * @param Shift4_Payment_Model_Shift4_Result $response
     * @param Mage_Sales_Model_Order_Payment $payment
     * 
     * @return Varien_Object
     */
    protected function _registerCard(Varien_Object $response, Mage_Sales_Model_Order_Payment $payment, $transactionType) {
        $paymentId = $payment->getId();
        $paymentIdLast3 = substr($paymentId, -3);

        $shift4InvoiceId = substr($payment->getOrder()->getIncrementId(), -5) . $paymentIdLast3 . $payment->getShift4TransCount();
        $cardsStorage = $this->getCardsStorage($payment);
        $card = $cardsStorage->registerCard();
        $payment->setShift4InvoiceId($shift4InvoiceId);

        switch ($transactionType) {
            case self::REQUEST_TYPE_AUTH_ONLY:
                $newTransactionType = "Auth";
                break;
            case self::REQUEST_TYPE_AUTH_CAPTURE:
                $newTransactionType = "Sale";
                break;
            case self::REQUEST_TYPE_CAPTURE_ONLY:
                $newTransactionType = "Sale";
                break;
            case self::REQUEST_TYPE_CREDIT:
                $newTransactionType = "Refund";
                break;
            case self::REQUEST_TYPE_VOID:
                $newTransactionType = "Void";
                break;
            case self::REQUEST_TYPE_VOID_RETRY:
                $newTransactionType = "Void";
                break;
            case self::REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
                $newTransactionType = "Sale";
                break;
            default:
                $newTransactionType = $transactionType;
        }

        $card
                ->setRequestedAmount($response->getPrimaryamount())
                ->setI4GoTrueToken($response->getUniqueid())
                ->setLastTransId($response->getTranid())
                ->setTransactionType($newTransactionType)
                ->setShift4InvoiceId($shift4InvoiceId)
                ->setAuthorizationCode($response->getAuthorization())
                ->setReceipttext($response->getReceipttext())
                ->setProcessedAmount($response->getPreauthorizedamount())
                ->setCcType($payment->getCcType())
                ->setCcOwner($payment->getCcOwner())
                ->setCcLast4($payment->getCcLast4())
                ->setCcExpMonth($payment->getCcExpMonth())
                ->setCcExpYear($payment->getCcExpYear())
                ->setCcSsIssue($payment->getCcSsIssue())
                ->setCcSsStartMonth($payment->getCcSsStartMonth())
                ->setCcSsStartYear($payment->getCcSsStartYear());
        if (
                $payment->hasAdditionalInformation('cc_save_future') &&
                $payment->getAdditionalInformation('cc_save_future') == 'Y'
        ) {
            $card->setSaveForFutureUse(true);
        } else if ($payment->hasAdditionalInformation('stored_card_id')) {
            // User used the stored card method
            $card->setShift4StoredCardId($payment->getAdditionalInformation('stored_card_id'));
        }

        $cardsStorage->updateCard($card);
        $this->_clearAssignedData($payment);

        return $card;
    }

    /**
     * Reset assigned data in payment info model
     *
     * @param Mage_Payment_Model_Info
     * 
     * @return Shift4_Payment_Model_SecurePayment
     */
    protected function _clearAssignedData($payment) {
        $payment->setCcType(null)
                ->setCcOwner(null)
                ->setCcLast4(null)
                ->setCcNumber(null)
                ->setCcCid(null)
                ->setCcExpMonth(null)
                ->setCcExpYear(null)
                ->setCcSsIssue(null)
                ->setCcSsStartMonth(null)
                ->setCcSsStartYear(null);

        return $this;
    }

    /**
     * No need for the credit card verification, set it to false by default
     *
     * @return boolean false
     */
    public function hasVerification() {
        return false;
    }

    /**
     * Gateway response wrapper
     *
     * @param string $text
     * 
     * @return string
     */
    protected function _wrapGatewayError($responseCode) {
        switch ($responseCode) {
            case self::RESPONSE_CODE_DECLINED:
                $message = 'Your card was declined as it has insufficient fund available.';
                break;
            case self::RESPONSE_CODE_ERROR_E:
                $message = 'Client socket time-out. Please check the internet connection.';
                break;
            case self::RESPONSE_CODE_NETWORK_TIMEOUT:
                $message = 'Client socket time-out. Please check the internet connection.';
                break;
            case self::RESPONSE_CODE_REFERAL_ERROR:
                $message = 'Insufficient fund available in your card. Please use another card.';
                break;
            case self::RESPONSE_CODE_AVS_CVV2_FAILURE:
                $message = 'Address Verification System (AVS)/ CVV failure.';
                break;
            case self::RESPONSE_CODE_AVS_FAILURE:
                $message = 'Address Verification System (AVS) failure.';
                break;
            case self::RESPONSE_CODE_CVV2_FAILURE:
                $message = 'Card security number is not valid.';
                break;
            case self::RESPONSE_CODE_CARD_EXPIRED:
                $message = 'Your card has expired.';
                break;
            default:
                $message = 'There has been error processing the request.';
        }

        return Mage::helper('shift4')->__('Shift4 Gateway Error: %s', $message);
    }

    /**
     * Init cards storage model
     *
     * @param Mage_Payment_Model_Info $payment
     */
    protected function _initCardsStorage($payment) {
        $this->_cardsStorage = Mage::getModel('shift4/shift4_cards')->setPayment($payment);
    }

    /**
     * Refund the card transaction through gateway
     *
     * @param Mage_Payment_Model_Info $payment
     * @param Varien_Object $card
     * 
     * @return Mage_Sales_Model_Order_Payment_Transaction
     * @throws Mage_Core_Exception
     */
    protected function _refundCardTransaction($payment, $amount, $card) {
        /**
         * Card has last transaction with type "refund" when all captured amount is refunded.
         * Until this moment card has last transaction with type "capture".
         */
        $api = Mage::getModel('shift4/api');
        $captureTransactionId = $card->getLastTransId();
        $captureTransaction = $payment->getTransaction($captureTransactionId);
        $realCaptureTransactionId = $captureTransaction->getAdditionalInformation($this->_realTransactionIdKey);

        $payment->setAnetTransType(self::REQUEST_TYPE_CREDIT);
        // Check it later
        $payment->setTransId($realCaptureTransactionId);
        $payment->setI4GoTrueToken($card->getI4GoTrueToken());
        $payment->setAmount($amount);

        if ($card->getCapturedAmount() && $realCaptureTransactionId && $card->getI4GoTrueToken()) {
            $result = $api->processRefundRequest($payment);

            $message = 'Refunding amount ' . $amount . ' Transaction #' . $realCaptureTransactionId . ' response ==' . print_r($result, true);
            $api->logAPICommunications($message, 'problems');

            if ($result->getError() == 1) {
                // check if transaction timeout
                if ($this->_isTransactionTimeout($result->getResponse())) {
                    sleep(5);
                    $this->_doTransactionTimeout($payment, $result->getResponse(), '_processRefundRequest');
                } else {
                    // do something
                    Mage::throwException($result->getErrorToShowUser());
                }
            } else {
                return ($this->_processRefundRequest($payment, $result->getResponse(), $card));
            }
        }
    }

    /**
     * Process refund request if no timeout
     *
     * @param Mage_Payment_Model_Info $payment
     * @param varien_object $result
     * @param Mage_Sales_Order_Payment $card
     * 
     * @return Shift4_Payment_Model_SecurePayment
     * 
     * @throws Mage_Core_Exception
     */
    protected function _processRefundRequest($payment, $result, $card) {
        $responseCode = $this->_getResponseCode($result);
        $amount = $payment->getAmount();
        $captureTransactionId = $card->getLastTransId();
        $realCaptureTransactionId = $payment->getTransId();

        switch ($responseCode) {
            case self::RESPONSE_CODE_APPROVED:
                //if ($result->getResponseReasonCode() == self::RESPONSE_REASON_CODE_APPROVED) {
                $refundTransactionId = $result->getTranid() . '-refund';
                $shouldCloseCaptureTransaction = 0;
                /**
                 * If it is last amount for refund, transaction with type "capture" will be closed
                 * and card will has last transaction with type "refund"
                 */
                if ($this->_formatAmount($card->getCapturedAmount() - $card->getRefundedAmount()) == $amount) {
                    $card->setLastTransId($refundTransactionId);
                    $shouldCloseCaptureTransaction = 1;
                }
                return $this->_addTransaction(
                                $payment, $refundTransactionId, Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND, array(
                            'is_transaction_closed' => 1,
                            'should_close_parent_transaction' => $shouldCloseCaptureTransaction,
                            'parent_transaction_id' => $captureTransactionId
                                ), array($this->_realTransactionIdKey => $result->getTranid()), Mage::helper('shift4')->getTransactionMessage(
                                        $payment, self::REQUEST_TYPE_CREDIT, $result->getTranid(), $card, $amount
                                )
                );
                //}
                // Wrap real error code
                $exceptionMessage = 'There has been error in refunding process';
                break;
            case self::RESPONSE_CODE_DECLINED:
            case self::RESPONSE_CODE_REFERAL_ERROR:
                $exceptionMessage = $this->_wrapGatewayError($responseCode);
                break;
            default:
                $exceptionMessage = Mage::helper('shift4')->__('Payment refunding error.');
                break;
        }

        $exceptionMessage = Mage::helper('shift4')->getTransactionMessage(
                $payment, self::REQUEST_TYPE_CREDIT, $realCaptureTransactionId, $card, $amount, $exceptionMessage
        );
        Mage::throwException($exceptionMessage);
    }

    /**
     * Return cards storage model
     *
     * @param Mage_Payment_Model_Info $payment
     * @return Shift4_Payment_Model_Shift4_Cards
     */
    public function getCardsStorage($payment = null) {
        if (is_null($payment)) {
            $payment = $this->getInfoInstance();
        }
        if (is_null($this->_cardsStorage)) {
            $this->_initCardsStorage($payment);
        }

        return $this->_cardsStorage;
    }

    /**
     * Add payment transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string $transactionId
     * @param string $transactionType
     * @param array $transactionDetails
     * @param array $transactionAdditionalInfo
     * 
     * @return null|Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _addTransaction(Mage_Sales_Model_Order_Payment $payment, $transactionId, $transactionType, array $transactionDetails = array(), array $transactionAdditionalInfo = array(), $message = false
    ) {
        $payment->setTransactionId($transactionId);
        $payment->resetTransactionAdditionalInfo();

        foreach ($transactionDetails as $key => $value) {
            $payment->setData($key, $value);
        }
        foreach ($transactionAdditionalInfo as $key => $value) {
            $payment->setTransactionAdditionalInfo($key, $value);
        }
        $transaction = $payment->addTransaction($transactionType, null, false, $message);
        foreach ($transactionDetails as $key => $value) {
            $payment->unsetData($key);
        }
        $payment->unsLastTransId();

        /**
         * It for self using
         */
        $transaction->setMessage($message);

        return $transaction;
    }

    /**
     * Process exceptions for gateway action with a lot of transactions
     *
     * @param  Mage_Payment_Model_Info $payment
     * @param  string $messages
     * @param  bool $isSuccessfulTransactions
     * 
     * @throw Mage_Core_Exception
     */
    protected function _processFailureMultitransactionAction($payment, $messages, $isSuccessfulTransactions) {
        if ($isSuccessfulTransactions) {
            $messages[] = Mage::helper('shift4')->__('Gateway actions are locked because the gateway cannot complete one or more of the transactions. ');
            /**
             * If there is successful transactions we can not to cancel order but
             * have to save information about processed transactions in order`s comments and disable
             * opportunity to voiding\capturing\refunding in future. Current order and payment will not be saved because we have to
             * load new order object and set information into this object.
             */
            $currentOrderId = $payment->getOrder()->getId();
            $copyOrder = Mage::getModel('sales/order')->load($currentOrderId);
            $copyOrder->getPayment()->setAdditionalInformation($this->_isGatewayActionsLockedKey, 1);
            foreach ($messages as $message) {
                $copyOrder->addStatusHistoryComment($message);
            }
            $copyOrder->save();
        }
        Mage::throwException(Mage::helper('shift4')->convertMessagesToMessage($messages));
    }

    /**
     * Round up and cast specified amount to float or string
     *
     * @param string|float $amount
     * @param bool $asFloat
     * 
     * @return string|float
     */
    protected function _formatAmount($amount, $asFloat = false) {
        $amount = sprintf('%.2F', $amount); // "f" depends on locale, "F" doesn't
        return $asFloat ? (float) $amount : $amount;
    }

    /**
     * If partial authorization is started method will return true
     *
     * @param Mage_Payment_Model_Info $payment
     * 
     * @return bool
     */
    public function isPartialAuthorization($payment = null) {
        if (is_null($payment)) {
            $payment = $this->getInfoInstance();
        }

        return $payment->getAdditionalInformation($this->_splitTenderIdKey);
    }

    /**
     * Fetch the response string from Shift4 response
     *
     * @param Varien_Object $result
     * @return String
     */
    protected function _getResponseCode($result) {
        if ($result->getResponse()) {
            return $result->getResponse();
        } else {
            return $result->getResponsecode();
        }
    }

    /**
     * Mock capture transaction id in invoice
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param Mage_Sales_Model_Order_Payment $payment
     * 
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function processInvoice($invoice, $payment) {
        $invoice->setTransactionId(1);
        return $this;
    }

    /**
     * Set transaction ID into creditmemo for informational purposes
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param Mage_Sales_Model_Order_Payment $payment
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function processCreditmemo($creditmemo, $payment) {
        $creditmemo->setTransactionId(1);
        return $this;
    }

    /**
     * Return partial authorization last action state from session
     *
     * @return string
     */
    public function getPartialAuthorizationLastActionState() {
        return $this->_getSession()->getData($this->_partialAuthorizationLastActionStateSessionKey);
    }

    /**
     * Set partial authorization last action state into session
     *
     * @param string $message
     * @return Shift4_Payment_Model_SecurePayment
     */
    public function setPartialAuthorizationLastActionState($state) {
        $this->_getSession()->setData($this->_partialAuthorizationLastActionStateSessionKey, $state);
        return $this;
    }

    /**
     * Unset partial authorization last action state in session
     *
     * @return Shift4_Payment_Model_SecurePayment
     */
    public function unsetPartialAuthorizationLastActionState() {
        $this->_getSession()->setData($this->_partialAuthorizationLastActionStateSessionKey, false);
        return $this;
    }

    /**
     * Retrieve session object
     *
     * @return Mage_Core_Model_Session_Abstract
     */
    protected function _getSession() {
        if (Mage::app()->getStore()->isAdmin()) {
            return Mage::getSingleton('adminhtml/session_quote');
        } else {
            return Mage::getSingleton('checkout/session');
        }
    }

}