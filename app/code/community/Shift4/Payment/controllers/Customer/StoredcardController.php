<?php

/**
 * Controller class for the user stored card, add, delete and display
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */
class Shift4_Payment_Customer_StoredcardController extends Mage_Core_Controller_Front_Action {

    /**
     * Retrieve customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession() {
        return Mage::getSingleton('customer/session');
    }

    public function preDispatch() {
        parent::preDispatch();

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        }
    }

    /**
     * List customer's stored cards
     */
    public function indexAction() {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');

        $this->getLayout()->getBlock('head')->setTitle($this->__('My Saved Credit Cards'));

        $this->renderLayout();
    }

    /**
     * Delete stored card
     */
    public function deleteAction() {
        $storedCardId = (int) $this->getRequest()->getParam('stored_card_id');

        if ($storedCardId) {
            $storedCardModel = Mage::getModel('shift4/customerstored')->load($storedCardId);

            $customerId = $this->_getSession()->getCustomerId();

            // Perform a security check
            if (
                    $storedCardModel->getId() && ($storedCardModel->getCustomerId() == $customerId)
            ) {
                // Delete card
                $storedCardModel->delete();

                $this->_getSession()->addSuccess($this->__('The card had been deleted.'));
            }
        }

        $this->_redirect('*/*/');
    }

    /**
     * Save a new credit card
     */
    public function savecardAction() {
        $data = $this->getRequest()->getParam('payment');
        // Tokenization process 
        $api = Mage::getModel('shift4/api');
        $result = array();
        $result['error'] = true;
        $result['error_text'] = '';
        $result['redirect_url'] = Mage::getUrl('*/*/');
        // wrap in try catch add error
        try {
            $response = $api->saveI4GoTokenizedData($data);
            if ($response) {
                $this->_getSession()->addSuccess(Mage::helper('shift4')->__('The credit card was added Successfully'));
                $result['error'] = false;
                $result['redirect'] = true;
            } else {
                $result['error_text'] = 'An error occurred while saving the card information. Please try again.';
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $result['error_text'] = $e->getMessage();
        }

        echo Mage::helper('core')->jsonEncode($result);

        exit;
    }

}
