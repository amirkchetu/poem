<?php

/**
 * Shift4 Processing Mode Dropdown source
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Shift4_Source_ProcessingMode {

    public function toOptionArray() {
        return array(
            array(
                'value' => Shift4_Payment_Model_Api::PROCESSING_MODE_DEMO,
                'label' => Mage::helper('shift4')->__('Demo')
            ),
            array(
                'value' => Shift4_Payment_Model_Api::PROCESSING_MODE_LIVE,
                'label' => Mage::helper('shift4')->__('Live')
            ),
        );
    }

}
