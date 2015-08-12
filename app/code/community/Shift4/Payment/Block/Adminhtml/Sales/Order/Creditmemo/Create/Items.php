<?php

/**
 * Adminhtml creditmemo items grid
 *
 * @category   Shift4
 * @package    Payment
 * @author     Chetu Team
 */
class Shift4_Payment_Block_Adminhtml_Sales_Order_Creditmemo_Create_Items extends Mage_Adminhtml_Block_Sales_Order_Creditmemo_Create_Items {

    /**
     * Prepare child blocks
     *
     * @return Shift4_Payment_Block_Adminhtml_Sales_Order_Creditmemo_Create_Items
     */
    protected function _prepareLayout() {
        $this->setTemplate('shift4/sales/order/creditmemo/create/items.phtml');
        $onclick = "submitAndReloadArea($('creditmemo_item_container'),'" . $this->getUpdateUrl() . "')";
        $this->setChild(
                'update_button', $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                    'label' => Mage::helper('sales')->__('Update Qty\'s'),
                    'class' => 'update-button',
                    'onclick' => $onclick,
                ))
        );

        if ($this->getCreditmemo()->canRefund()) {
            if ($this->getCreditmemo()->getInvoice() && $this->getCreditmemo()->getInvoice()->getTransactionId()) {
                $this->setChild(
                        'submit_button', $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                            'label' => Mage::helper('sales')->__('Refund'),
                            'class' => 'save submit-button',
                            'onclick' => 'disableElements(\'submit-button\');submitCreditMemo()',
                        ))
                );

                $_code = $this->getCreditmemo()->getInvoice()->getOrder()->getPayment()->getMethod();
                if ($_code == Shift4_Payment_Model_SecurePayment::METHOD_CODE || Shift4_Payment_Model_UserCardStored::METHOD_CODE) {
                    $this->setChild(
                            'shift4_partial_payment', $this->getLayout()->createBlock('shift4/adminhtml_sales_order_payment_cards')->setData(array(
                                'payment' => $this->getCreditmemo()->getInvoice()->getOrder()->getPayment(),
                                'title' => Mage::helper('sales')->__('Select Card')
                            ))
                    );
                }
            }

            $this->setChild(
                    'submit_offline', $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                        'label' => Mage::helper('sales')->__('Refund Offline'),
                        'class' => 'save submit-button',
                        'onclick' => 'disableElements(\'submit-button\');submitCreditMemoOffline()',
                    ))
            );
        } else {
            $this->setChild(
                    'submit_button', $this->getLayout()->createBlock('adminhtml/widget_button')->setData(array(
                        'label' => Mage::helper('sales')->__('Refund Offline'),
                        'class' => 'save submit-button',
                        'onclick' => 'disableElements(\'submit-button\');submitCreditMemoOffline()',
                    ))
            );
        }

        return parent::_prepareLayout();
    }

}
