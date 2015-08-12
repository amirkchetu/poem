<?php

/*
 * Set the Access Token hidden and disabled
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */

class Shift4_Payment_Block_Adminhtml_System_Config_AccessTokenDisabled extends Mage_Adminhtml_Block_System_Config_Form_Field {

    /**
     * Method for Set Element
     * 
     * @param Varien_Data_Form_Element_Abstract $element
     * @return String
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
        $element->setReadonly('readonly');

        return parent::_getElementHtml($element);
    }

}
