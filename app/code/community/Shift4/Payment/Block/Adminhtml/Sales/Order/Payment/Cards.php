<?php
/**
 * Used for partial refunding process, display the used card in the credit memo form
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Block_Adminhtml_Sales_Order_Payment_Cards extends Mage_Adminhtml_Block_Template {

    /**
     * Set custom template
     *
     */
    public function __construct() {
        $this->setTemplate('shift4/sales/order/payment/cards.phtml');
    }

    /**
     * Get the list of customer's stored cards
     *
     * @return Shift4_Payment_Model_Resource_Customerstored_Collection
     */
    public function getUsedCards($payment) {
        $paymentMethod = Mage::helper('payment')->getMethodInstance($payment->getMethod());
        $usedCards = $paymentMethod->getCardsStorage($payment)->getCards();
        $_code = $paymentMethod->getCode();
        if ($_code == Shift4_Payment_Model_SecurePayment::METHOD_CODE) {
            $cardIdKey = Shift4_Payment_Model_Shift4_Cards::CARD_ID_KEY_CC;
        } else if ($_code == Shift4_Payment_Model_UserCardStored::METHOD_CODE) {
            $cardIdKey = Shift4_Payment_Model_Shift4_Cards::CARD_ID_KEY_STORED;
        }
        $this->setCardIdKey($cardIdKey);

        return $usedCards;
    }

}
