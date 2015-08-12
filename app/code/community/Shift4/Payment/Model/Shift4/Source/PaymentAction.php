<?php

/**
 * Shift4 Payment Action Dropdown source
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Shift4_Source_PaymentAction {

    public function toOptionArray() {
        return array(
            array(
                'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('shift4')->__('Immediate Charge')
            ),
            array(
                'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE,
                'label' => Mage::helper('shift4')->__('Book and Ship')
            ),
        );
    }

}
