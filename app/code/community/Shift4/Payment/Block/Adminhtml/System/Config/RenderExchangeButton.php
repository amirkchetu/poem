<?php
/**
 * Render Token exchange button in the system config section 
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */
class Shift4_Payment_Block_Adminhtml_System_Config_RenderExchangeButton extends Mage_Adminhtml_Block_System_Config_Form_Field {

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
     * add the Javascript and css of Shift4 module
     *
     * @return Shift4_Payment_Block_Adminhtml_System_Config_RenderExchangeButton
     */
    public function _prepareLayout() {
        $head = $this->getLayout()->getBlock('head');
        $head->addItem('skin_js','shift4/jquery-1.10.2.min.js');
        $head->addItem('skin_js','shift4/noconflict.js');
        $head->addItem('skin_js','shift4/custom.js');
        $head->addCss('shift4/custom.css');

        return parent::_prepareLayout();
    }

    /**
     * Token exchange function and JavaScript for token exchange
     *
     * @param NULL
     *
     * @return string
     */
    public function getAfterElementHtml() {
        $buttonScript = "<script>
            	function requestAccessToken(url) {
		  var authToken = $('payment_shift4_payment_auth_token').getValue();
		  var endPoint = $('payment_shift4_payment_server_addresses').getValue();
		  
		  var errorMsg = ''; 
		  if (authToken == '') {
			errorMsg += 'Auth token '; 
			if (endPoint == '') {
				errorMsg += 'and Server Address '; 
			}
		  } 
		  
		  if (errorMsg != '') {
			  alert('Please enter the '+errorMsg+'value for exchange request');
			  return;
		  }
		  new Ajax.Request(url, {
            method:'post', 
			parameters: { 
				authToken: authToken,
				endPoint: endPoint
			}, 
			requestHeaders: {Accept: 'application/json'},
            onSuccess: function(response) {
				var json = response.responseText.evalJSON();
				$$(\"label[for='payment_shift4_payment_auth_token']\").first().update('Auth Token*');
                if (json.error_message != '') {
					$('payment_shift4_payment_auth_token').addClassName('required-entry');
					alert(json.error_message);
				} else {
					$$(\"label[for='payment_shift4_payment_auth_token']\").first().update('Auth Token');
					$('payment_shift4_payment_auth_token').removeClassName('required-entry');
					$('payment_shift4_payment_auth_token').setValue('');
					$('payment_shift4_payment_access_token').setValue(json.accessToken);
					$('row_payment_shift4_payment_auth_token').hide();
                                        $('row_payment_shift4_payment_masked_access_token').show();
                                        jQuery('#payment_shift4_payment_masked_access_token').css({'border':'1px solid #00C61D','box-shadow':'0 0 0 0 #fff inset','transition':'all 2s ease'});
                                        setTimeout(function () {
                                            jQuery('#payment_shift4_payment_masked_access_token').css({'border':'1px solid #aaa','box-shadow':'0 0 0 0 #fff inset','transition':'all 2s ease'});
                                        }, 1000);
					unMaskAccessCode();
				}
             },
			onFailure: function() {
				$$(\"label[for='payment_shift4_payment_auth_token']\").first().update('Auth Token*');
				$('payment_shift4_payment_auth_token').addClassName('required-entry');
				alert('An error occurred during token exchange. Please try again');
			}
          });							
		}
</script>";
        $html = parent::getAfterElementHtml();
        $url = $this->getUrl('shift4/payment/getAccessToken/');

        $html .= $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass('scalable save')
                ->setLabel('Exchange')
                ->setOnClick("javascript:requestAccessToken('$url')")
                ->toHtml();

        return $html . $buttonScript;
    }

}
