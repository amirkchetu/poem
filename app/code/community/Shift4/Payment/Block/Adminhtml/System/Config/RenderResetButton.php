<?php
/**
 * Render reset button for the server addresses
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */
class Shift4_Payment_Block_Adminhtml_System_Config_RenderResetButton extends Mage_Adminhtml_Block_System_Config_Form_Field {

    /**
     * Method for Set Element
     * 
     * @param Varien_Data_Form_Element_Abstract $element
     * @return String
     */
    protected $_server_addresses = 'server1.dollarsonthenet.net';

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
        try {
            $this->setElement($element);

            $html = $element->getElementHtml();
            $html .= $this->getAfterElementHtml();

            return $html;
        } catch (Exception $e) {
            Mage::log(__LINE__ . 'Exception Cdon_Product_Block_Adminhtml_CheckApiButton  _getElementHtml ' . $e, null, 'exception.log');
        }
    }
    
    /**
     * Add the required Script for the reset button
     *     
     * @return string
     */
    public function getAfterElementHtml() {
        $buttonScript = "<script>
			function resetServerAddresses() {
				var confirmText = 'If you confirm this reset and the Universal Transaction Gateway (UTG) is in use,the default UTG IP addresses and port numbers will be populated in the Server Address field. If the UTG is not in use,the default server addresses will be populated. Are you sure to want to reset the Server address field? For this reset to take effect, you must click Save Config on the Payment Methods page.';
				if (confirm(confirmText)) {
					$('payment_shift4_payment_server_addresses').setValue('" . $this->_server_addresses . "');
					
				}
			}
		</script>";
        $html = parent::getAfterElementHtml();

        $html .= $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass('scalable save')
                ->setLabel('Reset')
                ->setOnClick("javascript:resetServerAddresses()")
                ->toHtml();

        return $html . $buttonScript;
    }

}
