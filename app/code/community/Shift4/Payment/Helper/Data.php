<?php

/**
 * Shift4 Data Helper
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * Check if the payment method belongs to the 'shift4' family
     *
     * @param string $paymentMethod
     * 
     * @return bool
     */
    public function isCustomerstoredPaymentMethod($paymentMethod) {
        if ($paymentMethod && (strpos($paymentMethod, 'cardsaved') !== false)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the "Save this card" feature is allowed
     * (for any not-guest order, child customerstored method should be active)
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string $paymentMethodCode
     * 
     * @return bool
     */
    public function isCcSaveAllowed($quote, $paymentMethodCode) {
        if (!$paymentMethodCode) {
            return false;
        }
        if (
                $quote &&
                !(
                $quote->getData('checkout_method') == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST ||
                $quote->getData('customer_is_guest')
                ) &&
                Mage::getStoreConfig("payment/shift4_payment/active")
        ) {
            return true;
        }

        return false;
    }

    /**
     * Function return customer session quote
     *
     * @return Mage_Customer_Model_Session
     */
    public function getSession() {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Function check if customer is logged in
     *
     * @return bool
     */
    public function isCustomerLoggedIn() {
        return Mage::getSingleton('customer/session')->isLoggedIn();
    }

    /**
     * Function return the customer info
     *
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer() {
        if (empty($this->_customer)) {
            $this->_customer = Mage::getSingleton('customer/session')->getCustomer();
        }
        
        return $this->_customer;
    }

    /**
     * Get available credit card types for Shift4, defined in the module config
     * 
     * @return Array $types
     */
    public function getCcAvailableTypes() {
        $types = Mage::getSingleton('payment/config')->getCcTypes();
        $availableTypes = Mage::getStoreConfig('payment/shift4_payment/cctypes');
        if ($availableTypes) {
            $availableTypes = explode(',', $availableTypes);
            foreach ($types as $code => $name) {
                if (!in_array($code, $availableTypes)) {
                    unset($types[$code]);
                }
            }
        }

        return $types;
    }

    /**
     * Converts a lot of messages to message
     *
     * @param  array $messages
     * 
     * @return string
     */
    public function convertMessagesToMessage($messages) {
        return implode(' | ', $messages);
    }

    /**
     * Return message for gateway transaction request
     *
     * @param  Mage_Payment_Model_Info $payment
     * @param  string $requestType
     * @param  string $lastTransactionId
     * @param  Varien_Object $card
     * @param float $amount
     * @param string $exception
     * 
     * @return bool|string
     */
    public function getTransactionMessage($payment, $requestType, $lastTransactionId, $card, $amount = false, $exception = false
    ) {
        return $this->getExtendedTransactionMessage(
                        $payment, $requestType, $lastTransactionId, $card, $amount, $exception
        );
    }

    /**
     * Return message for gateway transaction request
     *
     * @param  Mage_Payment_Model_Info $payment
     * @param  string $requestType
     * @param  string $lastTransactionId
     * @param  Varien_Object $card
     * @param float $amount
     * @param string $exception
     * @param string $additionalMessage Custom message, which will be added to the end of generated message
     * 
     * @return bool|string
     */
    public function getExtendedTransactionMessage($payment, $requestType, $lastTransactionId, $card, $amount = false, $exception = false, $additionalMessage = false
    ) {
        $operation = $this->_getOperation($requestType);

        if (!$operation) {
            return false;
        }

        if ($amount) {
            $amount = $this->__('amount %s', $this->_formatPrice($payment, $amount));
        }

        if ($exception) {
            $result = $this->__('failed');
        } else {
            $result = $this->__('successful');
        }
        $shift4InvoiceId = $card->getShift4InvoiceId();
        $card = $this->__('Card Number: xxxx-%s', $card->getCcLast4());

        $pattern = '%s %s %s - %s.';
        $texts = array($card, $amount, $operation, $result);

        if (!is_null($lastTransactionId)) {
            $pattern .= ' %s.';
            $texts[] = $this->__('Shift4 Transaction ID %s', $lastTransactionId);
            $texts[] = $this->__('Shift4 Invoice ID %s', $shift4InvoiceId);
        }

        if ($additionalMessage) {
            $pattern .= ' %s.';
            $texts[] = $additionalMessage;
        }
        $pattern .= ' %s';
        $texts[] = $exception;

        return call_user_func_array(array($this, '__'), array_merge(array($pattern), $texts));
    }

    /**
     * Return operation name for request type
     *
     * @param  string $requestType
     * 
     * @return bool|string
     */
    protected function _getOperation($requestType) {
        switch ($requestType) {
            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_AUTH_ONLY:
                return $this->__('authorize');
            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_AUTH_CAPTURE:
                return $this->__('authorize and capture');
            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_PRIOR_AUTH_CAPTURE:
                return $this->__('capture');
            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_CREDIT:
                return $this->__('refund');
            case Shift4_Payment_Model_SecurePayment::REQUEST_TYPE_VOID:
                return $this->__('void');
            default:
                return false;
        }
    }

    /**
     * Format price with currency sign
     * @param  Mage_Payment_Model_Info $payment
     * @param float $amount
     * 
     * @return string
     */
    protected function _formatPrice($payment, $amount) {
        return $payment->getOrder()->getBaseCurrency()->formatTxt($amount);
    }

}
