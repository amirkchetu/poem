<?php
/**
 * Shift4 credit card, debit card and gift card info block
 * Credit card generic payment info
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Block_Payment_Info_Cc extends Mage_Payment_Block_Info_Cc {

    /**
     * Checkout progress information block flag
     *
     * @var bool
     */
    protected $_isCheckoutProgressBlockFlag = true;

    /**
     * Set block template
     */
    protected function _construct() {
        parent::_construct();
        $this->setTemplate('shift4/info/cc.phtml');
    }

    /**
     * Retrieve card info object
     *
     * @return mixed
     */
    public function getInfo() {
        if ($this->hasCardInfoObject()) {
            return $this->getCardInfoObject();
        }
        return parent::getInfo();
    }

    /**
     * Set checkout progress information block flag
     * to avoid showing credit card information from payment quote
     * in Previously used card information block
     *
     * @param bool $flag
     * 
     * @return Shift4_Payment_Block_Payment_Info_Cc
     */
    public function setCheckoutProgressBlock($flag) {
        $this->_isCheckoutProgressBlockFlag = $flag;
        return $this;
    }

    /**
     * Retrieve credit cards info
     *
     * @return array
     */
    public function getCards() {
        $action = Mage::app()->getRequest()->getActionName();
        $cardsData = $this->getMethod()->getCardsStorage()->getCards();
        $cards = array();

        if (is_array($cardsData)) {
            foreach ($cardsData as $cardInfo) {
                $data = array();
                if ($cardInfo->getProcessedAmount()) {
                    $amount = Mage::helper('core')->currency($cardInfo->getProcessedAmount(), true, false);
                    $data[Mage::helper('shift4')->__('Processed Amount')] = $amount;
                }
                if ($cardInfo->getRefundedAmount()) {
                    $amount = Mage::helper('core')->currency($cardInfo->getRefundedAmount(), true, false);
                    $data[Mage::helper('shift4')->__('Refunded Amount')] = $amount;
                }
                if (strtolower($action) == 'email' || strtolower($action == 'saveorder')) {
                    // Show only in email section
                    if ($cardInfo->getAuthorizationCode()) {
                        $data[Mage::helper('shift4')->__('Authorization Code')] = $cardInfo->getAuthorizationCode();
                    }                   
                    if ($cardInfo->getShift4InvoiceId()) {                
                        $data[Mage::helper('shift4')->__('Invoice Id')] = $cardInfo->getShift4InvoiceId();
                    }
                    if ($cardInfo->getReceipttext()) {
                        $data[Mage::helper('shift4')->__('Receipt Text')] = $cardInfo->getReceipttext();
                    }
                }

                $this->setCardInfoObject($cardInfo);
                $cards[] = array_merge($this->getSpecificInformation(), $data);
                $this->unsCardInfoObject();
                $this->_paymentSpecificInformation = null;
            }
        }
        if ($this->getInfo()->getCcType() && $this->_isCheckoutProgressBlockFlag) {
            $cards[] = $this->getSpecificInformation();
        }
        
        return $cards;
    }

    /**
     * Get some specific information in format of array($label => $value)
     *
     * @return array
     */
    public function getSpecificInformation() {
        return $this->_prepareSpecificInformation()->getData();
    }

    /**
     * Prepare credit card related payment info
     *
     * @param Varien_Object|array $transport
     * 
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null) {
        if (null !== $this->_paymentSpecificInformation) {
            return $this->_paymentSpecificInformation;
        }
        $transport = $this->_preparePaymentSpecificInformation($transport);
        $data = array();
        if ($ccType = $this->getCcTypeName()) {
            $data[Mage::helper('payment')->__('Card Type')] = $ccType;
        }
        if ($this->getInfo()->getCcLast4()) {
            $data[Mage::helper('payment')->__('Card Number')] = sprintf('xxxx-%s', $this->getInfo()->getCcLast4());
        }
        if (!$this->getIsSecureMode()) {
            if ($ccSsIssue = $this->getInfo()->getCcSsIssue()) {
                $data[Mage::helper('payment')->__('Switch/Solo/Maestro Issue Number')] = $ccSsIssue;
            }
            $year = $this->getInfo()->getCcSsStartYear();
            $month = $this->getInfo()->getCcSsStartMonth();
            if ($year && $month) {
                $data[Mage::helper('payment')->__('Switch/Solo/Maestro Start Date')] = $this->_formatCardDate($year, $month);
            }
        }
        
        return $transport->setData(array_merge($data, $transport->getData()));
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param Varien_Object|array $transport
     * 
     * @return Varien_Object
     */
    protected function _preparePaymentSpecificInformation($transport = null) {
        if (null === $this->_paymentSpecificInformation) {
            if (null === $transport) {
                $transport = new Varien_Object;
            } elseif (is_array($transport)) {
                $transport = new Varien_Object($transport);
            }
            Mage::dispatchEvent('payment_info_block_prepare_specific_information', array(
                'transport' => $transport,
                'payment' => $this->getInfo(),
                'block' => $this,
            ));
            $this->_paymentSpecificInformation = $transport;
        }
        
        return $this->_paymentSpecificInformation;
    }

}
