<?php

/**
 * Call the Shift4 event -> sales_quote_payment_import_data_i4go event to Tokenize the Card Holder Data via i4Go API request
 *
 * @category    Shift4
 * @package     Payment
 * @auth        Chetu Team
 */
class Shift4_Payment_Model_Quote_Payment extends Mage_Sales_Model_Quote_Payment {

    /**
     * Import data array to payment method object,
     * Method calls quote totals collect because payment method availability
     * can be related to quote totals
     *
     * @param   array $data
     * @throws  Mage_Core_Exception
     * 
     * @return  Shift4_Payment_Model_Quote_Payment
     */
    public function importData(array $data) {
        $data = new Varien_Object($data);
        Mage::dispatchEvent(
                $this->_eventPrefix . '_import_data_before', array(
            $this->_eventObject => $this,
            'input' => $data,
                )
        );

        $this->setMethod($data->getMethod());
        $method = $this->getMethodInstance();

        /**
         * Payment availability related with quote totals.
         * We have to recollect quote totals before checking
         */
        $this->getQuote()->collectTotals();

        if (!$method->isAvailable($this->getQuote()) || !$method->isApplicableToQuote($this->getQuote(), $data->getChecks())
        ) {
            Mage::throwException(Mage::helper('sales')->__('The requested Payment Method is not available.'));
        }

        $method->assignData($data);
        /*
         * validating the payment data skiped
         */
        /* call the Shift4 API event to save the true token */
        Mage::dispatchEvent(
                $this->_eventPrefix . '_import_data_i4go', array(
            $this->_eventObject => $this,
            'input' => $data,
                )
        );

        return $this;
    }

}
