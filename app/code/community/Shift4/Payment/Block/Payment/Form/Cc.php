<?php

/**
 * Custom CC payment form
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Block_Payment_Form_Cc extends Mage_Payment_Block_Form_Cc {

    /**
     * Set block template
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('shift4/form/authorize/cc.phtml');
    }

    /**
     * Retreive payment method form html
     *
     * @return string
     */
    public function getShift4IframeBlock() {
        $custom_variable = Mage::getModel('core/variable')->loadByCode('iframe_checkout_css');
        $cssRulesHtml = $custom_variable->getHtmlValue();
        $cssRules = str_replace('{FORM_ID}', 'i4Go-iframe', $cssRulesHtml);

        return $this->getLayout()->createBlock('shift4/iframe')
                        ->setTemplate('shift4/form/iFrame.phtml')
                        ->setIframeCssRules($cssRules)
                        ->setFormId("i4Go-iframe");
    }

    /**
     * Check if the "Save this card" feature is allowed
     *
     * @return bool
     */
    public function isCcSaveAllowed() {
        $quote = $this->getMethod()->getInfoInstance()->getQuote();

        return Mage::helper('shift4')->isCcSaveAllowed($quote, $this->getMethod()->getCode());
    }

    /**
     * Cards info block
     *
     * @return string
     */
    public function getCardsBlock() {
        return $this->getLayout()->createBlock('shift4/payment_info_cc')
                        ->setMethod($this->getMethod())
                        ->setInfo($this->getMethod()->getInfoInstance())
                        ->setCheckoutProgressBlock(false)
                        ->setHideTitle(true);
    }

    /**
     * Return url to cancel controller
     *
     * @return string
     */
    public function getCancelUrl() {
        return $this->getUrl('shift4/payment/cancel/');
    }

    /**
     * Return url to admin cancel controller from admin url model
     *
     * @return string
     */
    public function getAdminCancelUrl() {
        return Mage::getModel('adminhtml/url')->getUrl('adminhtml/shift4_payment/cancel');
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml() {
        $this->setChild('cards', $this->getCardsBlock());
        $this->setChild('i4go_iframe', $this->getShift4IframeBlock());

        return parent::_toHtml();
    }

    /**
     * Get notice message
     *
     * @return string
     */
    public function showNoticeMessage($message) {
        return $this->getLayout()->getMessagesBlock()
                        ->addNotice($this->__($message))
                        ->getGroupedHtml();
    }

    /**
     * Return partial authorization confirmation message and unset it in payment model
     *
     * @return string
     */
    public function getPartialAuthorizationConfirmationMessage() {
        $lastActionState = $this->getMethod()->getPartialAuthorizationLastActionState();
        if ($lastActionState == Shift4_Payment_Model_SecurePayment::PARTIAL_AUTH_LAST_SUCCESS) {
            $this->getMethod()->unsetPartialAuthorizationLastActionState();
            return Mage::helper('shift4')->__('The available amount on your card has been authorized for use, but it is insufficient to complete your purchase. To complete your purchase, click OK and enter an additional card. To cancel your purchase, click Cancel');
        } elseif ($lastActionState == Shift4_Payment_Model_SecurePayment::PARTIAL_AUTH_LAST_DECLINED) {
            $this->getMethod()->unsetPartialAuthorizationLastActionState();
            return Mage::helper('shift4')->__('Your card has been declined. Click OK to specify another card to complete your purchase. Click Cancel to release the amount on hold and select another payment method.');
        }
        
        return false;
    }

    /**
     * Return partial authorization form message and unset it in payment model
     *
     * @return string
     */
    public function getPartialAuthorizationFormMessage() {
        $lastActionState = $this->getMethod()->getPartialAuthorizationLastActionState();
        $message = false;
        switch ($lastActionState) {
            case Shift4_Payment_Model_SecurePayment::PARTIAL_AUTH_ALL_CANCELED:
                $message = Mage::helper('shift4')->__('Your payment has been cancelled. All authorized amounts have been released.');
                break;
            case Shift4_Payment_Model_SecurePayment::PARTIAL_AUTH_CARDS_LIMIT_EXCEEDED:
                $message = Mage::helper('shift4')->__('You have reached the maximum number of credit cards that can be used for one payment. The available amounts on all used cards were insufficient to complete payment. The payment has been cancelled and amounts on hold have been released.');
                break;
            case Shift4_Payment_Model_SecurePayment::PARTIAL_AUTH_DATA_CHANGED:
                $message = Mage::helper('shift4')->__('Your order has not been placed, because contents of the shopping cart and/or address has been changed. Authorized amounts from your previous payment that were left pending are now released. Please go through the checkout process for your recent cart contents.');
                break;
        }
        if ($message) {
            $this->getMethod()->unsetPartialAuthorizationLastActionState();
        }
        
        return $message;
    }

    /**
     * Return cancel confirmation message
     *
     * @return string
     */
    public function getCancelConfirmationMessage() {
        return $this->__('Are you sure you want to cancel your payment? Click OK to cancel your payment and release the amount on hold. Click Cancel to enter another credit card and continue with your payment.');
    }

    /**
     * Return flag - is partial authorization process started
     *
     * @return string
     */
    public function isPartialAuthorization() {
        return $this->getMethod()->isPartialAuthorization();
    }

    /**
     * Return HTML content for creating admin panel`s button
     *
     * @return string
     */
    public function getCancelButtonHtml() {
        $cancelButton = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(array(
            'id' => 'payment_cancel',
            'label' => Mage::helper('shift4')->__('Cancel'),
            'onclick' => 'cancelPaymentAuthorizations()'
        ));
        
        return $cancelButton->toHtml();
    }

    /**
     * Alert the user on the multishipping page if selecting the payment method
     * @param String $_code
     * 
     * @return string 
     */
    public function _alertIfMultishipPartialPayment($_code) {
        // Alert the user on the multishipping page if selecting the payment method
        $script = '';
        if (Mage::getSingleton('checkout/session')->getQuote()->getIsMultiShipping()) {
            $script .= '<script type="text/javascript">
                //<![CDATA[
                    $j("input[type=radio][name=\'payment[method]\']").click(function() {
                       var method  = $j(this).val();
                       if (method == "' . $_code . '") {
                          // check if user already have some authorized amount
                          alert("' . $this->__('Multiple shipping addresses were detected. You must use one method of payment in order to ship to multiple addresses.') . '");
                       }
                    });
                //]]>
            </script>
            ';
        }

        return $script;
    }

}
