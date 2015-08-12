<?php

/**
 * Helper file for 'Stored card' payment methods
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Helper_Storedcard extends Mage_Core_Helper_Data {

    /**
     * Keeps CC types
     *
     * @var array
     */
    protected $_ccTypes = array();

    /**
     * Get CC names list
     *
     * @return array
     */
    protected function _getCcTypes() {
        if (empty($this->_ccTypes)) {
            $this->_ccTypes = Mage::getSingleton('payment/config')->getCcTypes();
        }

        return $this->_ccTypes;
    }

    /**
     * Obtain the full CC type name
     *
     * @param string $ccTypeShort
     * 
     * @return string
     */
    public function translateCcType($ccTypeShort) {
        $ccTypes = $this->_getCcTypes();
        if (!empty($ccTypes) && $ccTypeShort && isset($ccTypes[$ccTypeShort])) {
            return $ccTypes[$ccTypeShort];
        }

        return '';
    }

}
