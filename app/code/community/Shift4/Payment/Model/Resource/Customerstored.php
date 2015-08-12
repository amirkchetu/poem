<?php

/**
 * Customer Stored card collection, initialize the collection
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Resource_Customerstored extends Mage_Core_Model_Mysql4_Abstract {

    /**
     * Initialize main table and table id field
     */
    protected function _construct() {
        $this->_init('shift4/customerstored', 'stored_card_id');
    }

}
