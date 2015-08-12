<?php

/**
 * Shift4 Logging Options Dropdown source
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Shift4_Source_LoggingOption {

    public function toOptionArray() {
        return array(
            array(
                'value' => 'off',
                'label' => Mage::helper('shift4')->__('Off')
            ),
            array(
                'value' => 'problems',
                'label' => Mage::helper('shift4')->__('Log Problems Only')
            ),
            array(
                'value' => 'all',
                'label' => Mage::helper('shift4')->__('Log All Communications')
            )
        );
    }

}
