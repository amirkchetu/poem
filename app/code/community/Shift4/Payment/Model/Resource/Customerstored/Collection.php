<?php

/**
 * Resource initialization abd filter
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Resource_Customerstored_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract {

    /**
     * Resource initialization
     *
     */
    protected function _construct() {
        $this->_init('shift4/customerstored');
    }

    /**
     * Apply filter by customer
     *
     * @param int $customerId
     * 
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection
     */
    public function filterByCustomerId($customerId) {
        $this->getSelect()->where('customer_id = ?', $customerId);

        return $this;
    }

    /**
     * Apply filter by card active state (based on last usage date)
     *
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection
     */
    public function filterByActiveState() {
        $minDate = new Zend_Date(null);
        $minDate->addMonth(0 - 12);
        $this->getSelect()->where('date >= ?', date('Y-m-d', $minDate->get(Zend_Date::TIMESTAMP)));

        return $this;
    }

    /**
     * Apply filter by card expiration date (extract valid cards only)
     *
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection
     */
    public function filterByExpirationDate() {
        $now = new Zend_Date(null);
        $dateArray = $now->toArray();

        $this->getSelect()->where("
            (cc_exp_year > '{$dateArray['year']}') OR
            (cc_exp_year = '{$dateArray['year']}' AND cc_exp_month >= {$dateArray['month']})
        ");

        return $this;
    }

}
