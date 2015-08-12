<?php

/**
 * iFrame block responsible for fetching the Access Block and Server URL via Shift4 API and do authorizing client request
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Block_Iframe extends Mage_Core_Block_Template {

    /**
     * Return the list of customer's stored cards
     *
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection|null
     */
    public function __construct() {
        // Initiate the authorize client request to set the access block and i4Go server
        $this->authorizeClientRequest();
    }

    /**
     * Build 'Delete' URL
     *
     * @param Shift4_Payment_Model_Customerstored $storedCard
     * 
     * @return string
     */
    public function getAddUrl() {
        return $this->getUrl('*/*/savecard', array('_secure' => true));
    }

    /**
     * Authorize the Client IP address using i4Go API
     *
     * @param none
     *
     * @return $this 
     */
    public function authorizeClientRequest() {
        $api = Mage::getModel('shift4/api');
        $accessBlock = null;
        $i4GoServer = null;
        try {
            $authorizeResponse = $api->_authorizeClientRequest();
            if ($authorizeResponse['error']) {
                $errorMsg = Mage::helper('shift4')->__('The client can not be authorized at this time. Please try again.');
                $this->_getSession()->addError(Mage::helper('shift4')->__($errorMsg));
            } else {
                $accessBlock = $authorizeResponse['response']['i4go_accessblock'];
                $i4GoServer = $authorizeResponse['response']['i4go_server'];
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError(Mage::helper('shift4')->__($e->getMessage()));
        }

        $this->setI4goAccessBlock($accessBlock);
        $this->setI4goServer($i4GoServer);
    }

    /**
     * Retrieve customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession() {
        return Mage::getSingleton('customer/session');
    }
}
