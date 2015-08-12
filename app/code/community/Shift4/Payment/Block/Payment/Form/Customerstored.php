<?php

/**
 * Customer stored card block, render template on the checkout page for stored card method
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Block_Payment_Form_Customerstored extends Shift4_Payment_Block_Payment_Form_Cc {

    /**
     * Set custom template
     *
     */
    public function __construct() {
        parent::__construct();
        $this->setTemplate('shift4/form/customer_stored.phtml');
    }

    /**
     * Get the list of customer's stored cards
     *
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection
     */
    public function getStoredCards() {
        return $this->getMethod()->getStoredCards();
    }

}
