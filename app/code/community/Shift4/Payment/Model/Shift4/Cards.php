<?php

/**
 * Model handling the card storage for the stored card and normal cards, register and update in the payment additional info field
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Shift4_Cards {

    protected $_cardNamespace;
    protected $_cardId;

    const CARDS_NAMESPACE_CC = 'shift4_authorize_cards';
    const CARD_ID_KEY_CC = 'card_id';
    const CARDS_NAMESPACE_STORED = 'shift4_stored_authorize_cards';
    const CARD_ID_KEY_STORED = 'stored_card_id';
    const CARD_PROCESSED_AMOUNT_KEY = 'processed_amount';
    const CARD_CAPTURED_AMOUNT_KEY = 'captured_amount';
    const CARD_REFUNDED_AMOUNT_KEY = 'refunded_amount';
    const PAYMENT_METHOD_CC = Shift4_Payment_Model_SecurePayment::METHOD_CODE;
    const PAYMENT_METHOD_STORED = Shift4_Payment_Model_UserCardStored::METHOD_CODE;

    /**
     * Cards information
     *
     * @var mixed
     */
    protected $_cards = array();

    /**
     * Payment instance
     *
     * @var Mage_Payment_Model_Info
     */
    protected $_payment = null;

    /**
     * Set card storage name space and key
     *
     * @return Shift4_Payment_Model_Shift4_Cards
     */
    public function __construct() {
        
    }

    /**
     * Set payment instance for storing credit card information and partial authorizations
     *
     * @param Mage_Payment_Model_Info $payment
     * 
     * @return Shift4_Payment_Model_Shift4_Cards
     */
    public function setPayment(Mage_Payment_Model_Info $payment) {
        $_code = $payment->getMethodInstance()->getCode();
        if ($_code == self::PAYMENT_METHOD_CC) {
            $this->_cardNamespace = self::CARDS_NAMESPACE_CC;
            $this->_cardId = self::CARD_ID_KEY_CC;
        } else if ($_code == self::PAYMENT_METHOD_STORED) {
            $this->_cardNamespace = self::CARDS_NAMESPACE_STORED;
            $this->_cardId = self::CARD_ID_KEY_STORED;
        }
        $this->_payment = $payment;
        $paymentCardsInformation = $this->_payment->getAdditionalInformation($this->_cardNamespace);
        if ($paymentCardsInformation) {
            $this->_cards = $paymentCardsInformation;
        }

        return $this;
    }

    /**
     * Add based on $cardInfo card to payment and return Id of new item
     *
     * @param mixed $cardInfo
     * 
     * @return string
     */
    public function registerCard($cardInfo = array()) {
        $this->_isPaymentValid();
        $cardId = md5(microtime(1));
        $cardInfo[$this->_cardId] = $cardId;
        $this->_cards[$cardId] = $cardInfo;
        $this->_payment->setAdditionalInformation($this->_cardNamespace, $this->_cards);
        
        return $this->getCard($cardId);
    }

    /**
     * Save data from card object in cards storage
     *
     * @param Varien_Object $card
     * 
     * @return Shift4_Payment_Model_Shift4_Cards
     */
    public function updateCard($card) {
        $cardId = $card->getData($this->_cardId);
        if ($cardId && isset($this->_cards[$cardId])) {
            $this->_cards[$cardId] = $card->getData();
            $this->_payment->setAdditionalInformation($this->_cardNamespace, $this->_cards);
        }

        return $this;
    }

    /**
     * Retrieve card by ID
     *
     * @param string $cardId
     * 
     * @return Varien_Object|bool
     */
    public function getCard($cardId) {
        if (isset($this->_cards[$cardId])) {
            $card = new Varien_Object($this->_cards[$cardId]);
            return $card;
        }

        return false;
    }

    /**
     * Get all stored cards
     *
     * @return array
     */
    public function getCards() {
        $this->_isPaymentValid();
        $_cards = array();
        foreach (array_keys($this->_cards) as $key) {
            $_cards[$key] = $this->getCard($key);
        }

        return $_cards;
    }

    /**
     * Return count of saved cards
     *
     * @return int
     */
    public function getCardsCount() {
        $this->_isPaymentValid();

        return count($this->_cards);
    }

    /**
     * Return processed amount for all cards
     *
     * @return float
     */
    public function getProcessedAmount() {
        return $this->_getAmount(self::CARD_PROCESSED_AMOUNT_KEY);
    }

    /**
     * Return captured amount for all cards
     *
     * @return float
     */
    public function getCapturedAmount() {
        return $this->_getAmount(self::CARD_CAPTURED_AMOUNT_KEY);
    }

    /**
     * Return refunded amount for all cards
     *
     * @return float
     */
    public function getRefundedAmount() {
        return $this->_getAmount(self::CARD_REFUNDED_AMOUNT_KEY);
    }

    /**
     * Remove all cards from payment instance
     *
     * @return Shift4_Payment_Model_Shift4_Cards
     */
    public function flushCards() {
        $this->_cards = array();
        $this->_payment->setAdditionalInformation($this->_cardNamespace, null);

        return $this;
    }

    /**
     * Check for payment instance present
     *
     * @throws Exception
     */
    protected function _isPaymentValid() {
        if (!$this->_payment) {
            throw new Exception('Payment instance is not set');
        }
    }

    /**
     * Return total for cards data fields
     *
     * $param string $key
     * 
     * @return float
     */
    public function _getAmount($key) {
        $amount = 0;
        foreach ($this->_cards as $card) {
            if (isset($card[$key])) {
                $amount += $card[$key];
            }
        }
        
        return $amount;
    }

}
