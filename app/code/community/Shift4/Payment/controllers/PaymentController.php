<?php
/**
 * Shift4 Payment controller for getting access token and cancel partial authorizations etc
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */

class Shift4_Payment_PaymentController extends Mage_Core_Controller_Front_Action {

    /**
     * get the access token for the Merchant
     *
     * @param String authToken
     * @param String endPoint
     *
     * @return string
     */
    public function getAccessTokenAction() {
        $authToken = Mage::app()->getRequest()->getParam('authToken');
        $endPoint = Mage::app()->getRequest()->getParam('endPoint');

        $pattern = '/^[a-fA-F0-9{8}\-a-fA-F0-9{4}\-a-fA-F0-9{4}\-a-fA-F0-9{16}]{35}$/';
        $isMatch = preg_match($pattern, $authToken);

        $data = array();
        $data['error_message'] = '';
        if (!$isMatch) {
            $data['error_message'] = 'The Auth Token you entered is not valid. Please try again.';
        } else {
            try {
                // Get the Access token for the Merchant
                $result = Mage::getModel('shift4/api')->getShift4AccessToken($authToken, $endPoint);
                
                if ($result->getError()) {
                    // Error while token exchange request
                    $data['error_message'] = $result->getRrrorToShowUser();
                } else {
                    $data['accessToken'] = (string) urldecode($result->getResponse()->getAccesstoken());
                }
            } catch (Exception $ex) {
                $data['error_message'] = $ex->getMessage();
            }
        }

        echo Mage::helper('core')->jsonEncode($data);

        exit;
    }

    /**
     * Cancel active partial authorizations
     */
    public function cancelAction() {
        $result['success'] = false;
        try {
            $_code = Mage::app()->getRequest()->getParam('method_code');
            $paymentMethod = Mage::helper('payment')->getMethodInstance($_code);
            if ($paymentMethod) {
                $paymentMethod->cancelPartialAuthorization(Mage::getSingleton('checkout/session')->getQuote()->getPayment());
            }
            $result['success'] = true;
            $result['update_html'] = $this->_getPaymentMethodsHtml();
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $result['error_message'] = $e->getMessage();
        } catch (Exception $e) {
            Mage::logException($e);
            $result['error_message'] = $this->__('There was an error cancelling transactions. Please contact us or try again later.');
        }

        Mage::getSingleton('checkout/session')->getQuote()->getPayment()->save();
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    /**
     * Get payment method step html
     *
     * @return string
     */
    protected function _getPaymentMethodsHtml() {
        $layout = $this->getLayout();
        $update = $layout->getUpdate();
        $update->load('checkout_onepage_paymentmethod');
        $layout->generateXml();
        $layout->generateBlocks();
        $output = $layout->getOutput();
        
        return $output;
    }

}
