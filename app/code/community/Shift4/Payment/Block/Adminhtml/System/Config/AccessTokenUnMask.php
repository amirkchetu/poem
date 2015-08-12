<?php

/*
 * Set the Access Token text box read only
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */

class Shift4_Payment_Block_Adminhtml_System_Config_AccessTokenUnMask extends Mage_Adminhtml_Block_System_Config_Form_Field {

    /**
     * Method for Set Element
     * 
     * @param Varien_Data_Form_Element_Abstract $element
     * 
     * @return String
     */
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
			function addNew() {
				if(confirm('Are you sure ?')){
                                    $$(\"label[for='payment_shift4_payment_auth_token']\").first().update('Auth Token*');
                                    $('row_payment_shift4_payment_auth_token').show();
                                    $('row_payment_shift4_payment_masked_access_token').hide();
                                    jQuery('#row_payment_shift4_payment_auth_token label').css('font-weight','bold');
                                    jQuery('#payment_shift4_payment_auth_token').css({'transition':'all 2s ease'});
                                    setTimeout( function(){
                                        jQuery('#payment_shift4_payment_auth_token').css({'border':'1px dashed #FF2828','box-shadow':'0 0 1px 0 red inset'});
                                    },200);
                                    jQuery('#payment_shift4_payment_auth_token').focus();
                                }
			}
                        jQuery('#payment_shift4_payment_auth_token').keyup(function(){
                            jQuery('#payment_shift4_payment_auth_token').css({'border':'1px solid #aaa'});
                            setTimeout(function () {
                                jQuery('#payment_shift4_payment_auth_token').css({'border':'1px solid #aaa','box-shadow':'0 0 0 0 #fff inset','transition':'all 2s ease'});
                            }, 1000);
                        });
		</script>
                <style>#payment_shift4_payment td button { margin-right: 60%;}</style>";
        $html = parent::getAfterElementHtml();

        $html .= $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass('scalable save')
                ->setLabel('New Token')
                ->setOnClick("javascript:addNew()")
                ->toHtml();

        return $html . $buttonScript;
    }

}
