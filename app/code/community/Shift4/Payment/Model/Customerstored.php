<?php

/**
 * Customer Stored Card model for saving and retriving the card stored by user
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Customerstored extends Mage_Core_Model_Abstract {

    protected function _construct() {
        parent::_construct();
        $this->_init('shift4/customerstored');
    }

    /**
     * Return customer's stored card collection
     *
     * @param int $customerId
     * @param string $paymentMethod
     * 
     * @return Shift_Payment_Model_Resource_Customerstored_Collection
     */
    public function getCustomerstoredCollection($customerId) {
        /** @var $collection Shift4_Payment_Model_Resource_Customerstored_Collection */
        $collection = $this->getCollection();

        $collection
                ->filterByCustomerId($customerId)
                //->filterByActiveState()
                ->filterByExpirationDate();

        return $collection;
    }

    /**
     * Check if the newly save card already exists
     *
     * @return Shift_Payment_Model_Customerstored
     */
    protected function _beforeSave() {
        parent::_beforeSave();

        if (!$this->getId()) {
            $cardDuplicate = $this->checkCardDuplicate(
                    $this->getData('customer_id'), $this->getData('cc_type'), $this->getData('cc_last4'));

            if ($cardDuplicate) {
                // Disallow card save
                $this->_dataSaveAllowed = false;
                Mage::throwException('This credit card already exists. Please chose another card.');
            }
        }

        return $this;
    }

    /**
     * Check card for duplicates
     *
     * @param int $customerId
     * @param string $ccType
     * @param string $ccLast4
     * 
     * @return array
     */
    public function checkCardDuplicate($customerId, $ccType, $ccLast4) {

        $collection = $this->getCollection();
        $collection->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('cc_type', $ccType)
                ->addFieldToFilter('cc_last4', $ccLast4);
        //->addFieldToFilter('cc_exp_month', $ccExpMonth)
        //->addFieldToFilter('cc_exp_year', $ccExpYear);

        return ($collection->count()) ? true : false;
    }

}
