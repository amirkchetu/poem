<?php

/**
 * Shift4 payment method .
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */
class Shift4_Payment_Model_UserCardStored extends Shift4_Payment_Model_SecurePayment {
    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */

    const METHOD_CODE = 'shift4_payment_cardsaved';

    protected $_code = self::METHOD_CODE;
    protected $_formBlockType = 'shift4/payment_form_customerstored';
    protected $_infoBlockType = 'shift4/payment_info_cc';

    /**
     * Key for storing partial authorization last action state in session
     * @var string
     */
    protected $_partialAuthorizationLastActionStateSessionKey = 'shift4_stored_payment_last_action_state';

    /**
     * Key for storing split tender id in additional information of payment model
     * @var string
     */
    protected $_splitTenderIdKey = 'shift4_stored_tender_id';

    /**
     * Validate payment method information object
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    public function validate() {
        $paymentInfo = $this->getInfoInstance();
        $paymentStoredCardId = $paymentInfo->getAdditionalInformation('stored_card_id');


        $isError = true;

        // Check if customer ID is available
        if (!$this->getCustomerId()) {
            if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
                $customerId = $paymentInfo->getOrder()->getCustomerId();
            } else {
                $customerId = $paymentInfo->getQuote()->getCustomerId();
            }

            $this->setCustomerId($customerId);
        }
        // token number validation and expiry check
        // Check stored card ID for validity
        if ($paymentStoredCardId) {
            $storedCards = $this->getStoredCards();

            if ($storedCards && ($storedCards->count() > 0)) {
                foreach ($storedCards as $storedCard) {
                    if ($storedCard->getStoredCardId() == $paymentStoredCardId) {
                        // Also keep stored card transaction ID
                        $paymentInfo->setAdditionalInformation('stored_card_transaction_id', $storedCard->getTransactionId());

                        $isError = false;
                        break;
                    }
                }
            }
        }

        if ($isError) {
            Mage::throwException(Mage::helper('shift4')->__('Please select valid saved card'));
        }

        return $this;
    }

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * 
     * @return  Mage_Payment_Model_Method_Abstract
     */
    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();

        // Assign actual payment method data

        $storedCardId = $data->getStoredCardId();
        $storedCards = $this->getStoredCards();

        $info->setStoredCardId($storedCardId);

        // Make sure no extraneous data is kept
        $info->setCcType(null)
                ->setCcOwner(null)
                ->setCcLast4(null)
                ->setCcNumber(null)
                ->setCcCid(null)
                ->setCcExpMonth(null)
                ->setCcExpYear(null)
                ->setCcSsIssue(null)
                ->setCcSsStartMonth(null)
                ->setCcSsStartYear(null);

        if (!is_null($storedCardId) && !is_null($storedCards)) {
            foreach ($storedCards as $storedCard) {
                if ($storedCardId == $storedCard->getId()) {
                    // Assign CC info (taken from the selected stored card)
                    $info
                            ->setCcType($storedCard->getCcType())
                            ->setCcLast4($storedCard->getCcLast4())
                            ->setCcExpMonth($storedCard->getCcExpMonth())
                            ->setCcExpYear($storedCard->getCcExpYear());

                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Check whether payment method can be used
     * (we consider the caller payment availability has been already checked before)
     *
     * @param Mage_Sales_Model_Quote $quote
     * 
     * @return bool
     */
    public function isAvailable($quote = null) {
        $customerId = false;

        // Disallow method for guest orders
        if (!is_null($quote)) {
            $customerId = $quote->getCustomerId();
        } else {
            $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        }

        if (!$customerId || (!Mage::getSingleton('customer/session')->isLoggedIn() && !Mage::app()->getStore()->isAdmin())) {
            return false;
        }

        // Check if active saved cards exist for the customer
        $this->setCustomerId($customerId);

        $storedCards = $this->getStoredCards();
        if ($storedCards && ($storedCards->count() > 0)) {
            return true;
        }

        return false;
    }

    /**
     * Return the list of customer's stored cards
     *
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection|null
     */
    public function getStoredCards() {
        if (is_null($this->getData('stored_cards'))) {
            $customerId = $this->getCustomer()->getId();

            if (!$customerId) {
                return null;
            }

            $collection = Mage::getSingleton('shift4/customerstored')->getCustomerstoredCollection($customerId);
            $this->setData('stored_cards', $collection);
        }

        return $this->getData('stored_cards');
    }

    /**
     * Return current customer model
     *
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer() {
        $customer = $this->getData('customer');
        if (is_null($customer)) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $this->setData('customer', $customer);
        }

        return $customer;
    }

    /**
     * If partial authorization is started method will return true
     *
     * @param Mage_Payment_Model_Info $payment
     * @return bool
     */
    public function isPartialAuthorization($payment = null) {
        if (is_null($payment)) {
            $payment = $this->getInfoInstance();
        }

        return $payment->getAdditionalInformation($this->_splitTenderIdKey);
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
     * @return Shift4_Payment_Model_UserCardStored
     */
    public function setPartialAuthorizationLastActionState($state) {
        $this->_getSession()->setData($this->_partialAuthorizationLastActionStateSessionKey, $state);

        return $this;
    }

    /**
     * Unset partial authorization last action state in session
     *
     * @return Shift4_Payment_Model_UserCardStored
     */
    public function unsetPartialAuthorizationLastActionState() {
        $this->_getSession()->setData($this->_partialAuthorizationLastActionStateSessionKey, false);

        return $this;
    }

    /**
     * Cancel partial authorizations and flush current split_tender_id record
     *
     * @param Mage_Payment_Model_Info $payment
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
            }
        } else {
            Mage::throwException(Mage::helper('shift4')->__('Payment data not found'));
        }
    }

    /**
     * Set split_tender_id to quote payment if needed
     *
     * @param Varien_Object $response
     * @param Mage_Sales_Model_Order_Payment $payment
     * 
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
                    $orderPayment->setAdditionalInformation($this->_splitTenderIdKey, 'shift4_stored_partial_auth_key');
                    $this->_registerCard($response, $orderPayment, $requestType);
                    $this->_clearAssignedData($quotePayment);
                    $this->setPartialAuthorizationLastActionState(self::PARTIAL_AUTH_LAST_SUCCESS);
                    $quotePayment->setAdditionalInformation($orderPayment->getAdditionalInformation());
                    $exceptionMessage = null;
                    break;

                case self::RESPONSE_CODE_DECLINED:
                    $this->setPartialAuthorizationLastActionState(self::PARTIAL_AUTH_LAST_DECLINED);
                    $this->_voidNotApprovedTransactions($orderPayment, $response, $requestType);
                    Mage::throwException($this->_wrapGatewayError('Your card was declined as it has insufficient fund available.'));
                    break;
                case self::RESPONSE_CODE_REFERAL_ERROR:                 
                    $quotePayment->setAdditionalInformation($orderPayment->getAdditionalInformation());
                    $this->_voidNotApprovedTransactions($orderPayment, $response, $requestType);
                    $exceptionMessage = $this->_wrapGatewayError('There was an error during processing the request.');
                    break;
                case self::RESPONSE_CODE_AVS_CVV2_FAILURE:
                    // Need to void the transaction
                    // Do auto void 
                    $this->_voidNotApprovedTransactions($orderPayment, $response, $requestType);
                    Mage::throwException($this->_wrapGatewayError('Address Verification System (AVS) failure.'));
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

}
