<?php
/**
 * Event observer model for Shift4 payment
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Model_Observer {

    /**
     * Handle custom payment data fields
     *
     * @param Varien_Event_Observer $observer
     * 
     * @return Varien_Event_Observer
     */
    public function salesQuotePaymentImportDataBefore(Varien_Event_Observer $observer) {
        /** @var $input Varien_Object */
        $input = $observer->getInput();
        /** @var $payment Mage_Sales_Model_Quote_Payment */
        $payment = $observer->getPayment();

        $paymentMethod = $input->getMethod();
        if (isset($paymentMethod)) {
            if ($input['cc_save_future'] == 'Y') {
                // Set 'Save for future use' flag
                $payment->setAdditionalInformation('cc_save_future', 'Y');
            } else if ($input['stored_card_id']) {
                // Handle "Customer stored card" payment methods selection
                // Save stared card ID into the additional information,
                // card ID will be validated afterwards
                $payment->setAdditionalInformation('stored_card_id', $input['stored_card_id']);
            }
        }
    }

    /**
     * Save stored card details
     *
     * @param Varien_Event_Observer $observer
     * 
     * @return Varien_Event_Observer
     */
    public function checkoutSubmitAllAfter(Varien_Event_Observer $observer) {
        $orders = $observer->getOrders();
        if (is_null($orders)) {
            $orders = array($observer->getOrder());
        }
        $helper = Mage::helper('shift4');
        $methodCode = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethod();
        if ($helper->isCustomerstoredPaymentMethod($methodCode) || $methodCode == Shift4_Payment_Model_SecurePayment::METHOD_CODE) {
            foreach ($orders as $order) {
                /** @var $order Mage_Sales_Model_Order */
                $customerId = $order->getData('customer_id');

                if (!$customerId) {
                    // Save transaction details for registered customers only
                    return;
                }

                $payment = $order->getPayment();
                $paymentMethod = $payment->getMethod();

                if (isset($paymentMethod)) {
                    $methodInstance = $payment->getMethodInstance();
                    $cardStorage = $methodInstance->getCardsStorage($payment);

                    /** @var $helper Shift4_Payment_Helper_Data */
                    $helper = Mage::helper('shift4');
                    foreach ($cardStorage->getCards() as $card) {
                        if ($card->getSaveForFutureUse()) {
                            // Create new stored card
                            $customerstoredModel = Mage::getModel('shift4/customerstored');
                            $customerstoredModel->setData(array(
                                'transaction_id' => $card->getLastTransId(),
                                'customer_id' => $customerId,
                                'cc_type' => $card->getCcType(),
                                'cc_last4' => $card->getCcLast4(),
                                'cc_exp_month' => $card->getCcExpMonth(),
                                'cc_exp_year' => $card->getCcExpYear(),
                                'date' => date('Y-m-d H:i:s'),
                                'payment_method' => $paymentMethod,
                                'card_token' => $card->getI4GoTrueToken()
                            ));
                            // if card does not exists, save it
                            if (!$customerstoredModel->checkCardDuplicate($customerId, $card->getCcType(), $card->getCcLast4())) {
                                $customerstoredModel->save();
                            } else {
                                //Update the last tranid....TBD later on                          
                            }
                        } elseif ($card->getShift4StoredCardId()) {
                            if ($helper->isCustomerstoredPaymentMethod($paymentMethod)) {
                                $customerstoredModel = Mage::getModel('shift4/customerstored');
                                $storedCardId = $card->getShift4StoredCardId();
                                $customerstoredModel->load($storedCardId);
                                if ($customerstoredModel->getId()) {
                                    // Update stored card record with a new transaction ID
                                    $customerstoredModel
                                            ->setData('transaction_id', $card->getLastTransId()) // Currently working
                                            ->setData('date', date('Y-m-d'));
                                    $customerstoredModel->save();
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Save the payment action value for the Stored card payment method
     *
     * @param Varien_Event_Observer $observer
     * 
     * @return Varien_Event_Observer
     */
    function adminSystemConfigSavePaymentActionForStoredCard() {
        $payment_action = Mage::getStoreConfig('payment/shift4_payment/payment_action', Mage::app()->getStore());
        if ($payment_action) {
            Mage::getConfig()->saveConfig('payment/shift4_payment_cardsaved/payment_action', $payment_action);
            Mage::getConfig()->reinit();
            Mage::app()->reinitStores();
        }
    }

}
