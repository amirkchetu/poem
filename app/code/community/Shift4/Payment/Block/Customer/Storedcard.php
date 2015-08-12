<?php

/**
 * Customer Stored Card block, retrive customer saved card and display the iFrame HTML
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Block_Customer_Storedcard extends Mage_Core_Block_Template {

    /**
     * Get the customer stored cards
     *
     * @param null
     * 
     * @return Shift4_Payment_Block_Customer_Storedcard
     */
    public function getStoredCards() {
        if (is_null($this->getData('stored_cards'))) {
            $customerId = $this->getCustomer()->getId();

            if (!$customerId) {
                return null;
            }

            $collection = Mage::getSingleton('shift4/customerstored')->getCustomerstoredCollection($customerId);
            $this->setData('stored_cards', $collection);
        }

        return $this->getData('stored_cards');
    }

    /**
     * Transform date to store format
     *
     * @param string $date
     * 
     * @return Zend_Date
     */
    public function transformDate($date) {
        return Mage::app()->getLocale()->storeDate(
                        $this->getCustomer()->getStoreId(), strtotime($date)
        );
    }

    /**
     * Return current customer model
     *
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer() {
        $customer = $this->getData('customer');
        if (is_null($customer)) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $this->setData('customer', $customer);
        }
        return $customer;
    }

    /**
     * Build 'Delete' URL
     *
     * @param Shift4_Payment_Model_Customerstored $storedCard
     * 
     * @return string
     */
    public function getDeleteUrl($storedCard) {
        return $this->getUrl('*/*/delete', array('stored_card_id' => $storedCard->getId()));
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml() {
        $this->setChild('i4go_iframe', $this->getIframeHtml());
        $this->setFormId('addcardform');

        return parent::_toHtml();
    }

    /**
     * Retreive payment method form html
     *
     * @return string
     */
    public function getIframeHtml() {
        $custom_variable = Mage::getModel('core/variable')->loadByCode('iframe_customer_account_css');
        $cssRulesHtml = $custom_variable->getHtmlValue();
        $cssRules = str_replace('{FORM_ID}', 'addcardform', $cssRulesHtml);

        return $this->getLayout()->createBlock('shift4/iframe')
                        ->setTemplate('shift4/customer/iFrame.phtml')
                        ->setFormId('addcardform')
                        ->setIframeCssRules($cssRules)
                        ->setSaveUrl($this->getUrl('*/*/savecard', array('_secure' => true)));
    }

}
?>