<?php
/**
 * Shift4 daily sales reports
 *
 * @category    Shift4
 * @package     Payment
 * @author	Chetu Team
 */
class Shift4_Payment_Adminhtml_SalesController extends Mage_Adminhtml_Controller_Action {

    public function indexAction() {
        $this->loadLayout()->renderLayout();
    }
    
    /**
     * Display the layout for the sales report for Shift4 order
     *
     * @return Mage_Core_Mode_Layout
     */
    public function dailyReportAction() {
        $this->loadLayout()->renderLayout();
    }
    
    /**
     * Export the CSV based on the selection
     *
     * @return String
     */
    public function exportCsvAction() {
        $payment_method_cc = Shift4_Payment_Model_SecurePayment::METHOD_CODE;
        $payment_method_stored = Shift4_Payment_Model_UserCardStored::METHOD_CODE;
        if ($data = $this->getRequest()->getPost()) {

            $orserstatus = "";
            $orders_csv_row = "Transaction,Invoice ID, Order Id,Business Date,Card Type,Card Number,Amount,Customer Name";
            $orders_csv_row.="\n";

            $filter_type = $_REQUEST['filter_type'];

            $from = $_REQUEST['from'];
            $to = $_REQUEST['to'];

            $from_date = date('Y-m-d' . ' 00:00:00', strtotime($from));
            $to_date = date('Y-m-d' . ' 23:59:59', strtotime($to));

            $filter_model = ($filter_type == 'shipping_date') ? 'sales/order_shipment_collection' : 'sales/order_collection';

            if ($_REQUEST['show_order_statuses'] > 0) {
                $orserstatus = $_REQUEST['order_statuses'];
                $_orderCollections = Mage::getResourceModel($filter_model);
                $_orderCollections->addAttributeToSelect('*');
                $_orderCollections->addFieldToFilter('created_at', array('from' => $from_date, 'to' => $to_date));
                if ($filter_type == 'order_date') {
                    $_orderCollections->addFieldToFilter('status', $orserstatus);
                }
                $_orderCollections->addFieldToFilter('created_at', array('from' => $from_date, 'to' => $to_date));
                $_orderCollections->join(array('payment' => 'sales/order_payment'), 'main_table.entity_id=payment.parent_id', array('payment_method' => 'payment.method', 'cc_last4'));

                $_orderCollections->addFieldToFilter(array('payment.method', 'payment.method'), array(
                    array('eq' => $payment_method_cc),
                    array('eq' => $payment_method_stored)
                ));
                $_orderCollections->setOrder('created_at', 'desc');
                $_orderCollections->load();
            } else {
                $_orderCollections = Mage::getResourceModel($filter_model)
                        ->addAttributeToSelect('*')
                        ->addFieldToFilter('created_at', array('from' => $from_date, 'to' => $to_date))
                        ->join(array('payment' => 'sales/order_payment'), 'main_table.entity_id=payment.parent_id', array('payment_method' => 'payment.method', 'cc_last4'))
                        ->addFieldToFilter(array('payment.method', 'payment.method'), array(
                            array('eq' => $payment_method_cc),
                            array('eq' => $payment_method_stored)
                        ))
                        ->setOrder('created_at', 'desc')
                        ->load();
            }

            $total_card_vi = 0;
            $total_card_ax = 0;
            $total_card_gc = 0;
            $total_card_mc = 0;
            $total_card_dc = 0;
            $total_card_ns = 0;
            $total_card_jc = 0;

            $total_amount_vi = 0;
            $total_amount_ax = 0;
            $total_amount_mc = 0;
            $total_amount_gc = 0;
            $total_amount_dc = 0;
            $total_amount_ns = 0;
            $total_amount_jc = 0;

            $total_refund_vi = 0;
            $total_refund_ax = 0;
            $total_refund_mc = 0;
            $total_refund_gc = 0;
            $total_refund_dc = 0;
            $total_refund_ns = 0;
            $total_refund_jc = 0;

            $usedCards = array();
            $availableCcTypes = Mage::getSingleton('payment/config')->getCcTypes();

            foreach ($_orderCollections as $single_order) {
                if (($filter_type == 'shipping_date')) {
                    $_orderId = $single_order->getOrderId();
                } else {
                    $_orderId = $single_order->getId();
                }

                $myOrder = Mage::getModel('sales/order');
                $myOrder->load($_orderId);
                $myOrder->loadByIncrementId($myOrder->getIncrementId());

                $customer_email = "";
                if ($custoer_id = $myOrder->getCustomerId()) {
                    $customer = Mage::getModel('customer/customer')->load($custoer_id);
                    $customer_email = $customer->getEmail();
                }

                if (empty($customer_email)) {
                    $customer_email = $myOrder->getCustomerEmail();
                }
                $customer_name = $myOrder->getCustomerFirstname() . ' ' . $myOrder->getCustomerLastname();

                $payment_info = $myOrder->getPayment();
                $methodInstance = $payment_info->getMethodInstance();
                $cardStorage = $methodInstance->getCardsStorage($payment_info);
                foreach ($cardStorage->getCards() as $card) {
                    $invoice_id = $card->getShift4InvoiceId();
                    $last_4no = $card->getCcLast4();
                    $card_type = $card->getCcType();
                    $transType = $card->getTransactionType();
                    $proccessed_amount = $card->getProcessedAmount();
                    $refunded_amount = $card->getRefundedAmount();
                    /* Report like DOTN */
                    if (strtolower($transType) == 'refund') {
                        $amount = $refunded_amount;
                    } else {
                        $amount = $proccessed_amount;
                    }
                    switch ($card_type) {
                        case 'VS':
                            $total_card_vi += 1;
                            $total_amount_vi += $proccessed_amount;
                            $total_refund_vi += $refunded_amount;

                            $usedCards['VS'] = array(
                                'label' => $availableCcTypes[$card_type],
                                'total' => $total_card_vi,
                                'amount' => $total_amount_vi,
                                'refund' => $total_refund_vi,
                                'net' => $total_amount_vi - $total_refund_vi
                            );
                            break;
                        case 'AX':
                            $total_card_ax += 1;
                            $total_amount_ax += $proccessed_amount;
                            $total_refund_ax += $refunded_amount;

                            $usedCards['AX'] = array(
                                'label' => $availableCcTypes[$card_type],
                                'total' => $total_card_ax,
                                'amount' => $total_amount_ax,
                                'refund' => $total_refund_ax,
                                'net' => $total_amount_ax - $total_refund_ax
                            );
                            break;
                        case 'MC':
                            $total_card_mc += 1;
                            $total_amount_mc += $proccessed_amount;
                            $total_refund_mc += $refunded_amount;

                            $usedCards['MC'] = array(
                                'label' => $availableCcTypes[$card_type],
                                'total' => $total_card_mc,
                                'amount' => $total_amount_mc,
                                'refund' => $total_refund_mc,
                                'net' => $total_amount_mc - $total_refund_mc
                            );
                            break;
                        case 'YC':
                            $total_card_gc += 1;
                            $total_amount_gc += $proccessed_amount;
                            $total_refund_gc += $refunded_amount;

                            $usedCards['YC'] = array(
                                'label' => $availableCcTypes[$card_type],
                                'total' => $total_card_gc,
                                'amount' => $total_amount_gc,
                                'refund' => $total_refund_gc,
                                'net' => $total_amount_gc - $total_refund_gc
                            );
                            break;
                        case 'DC':
                            $total_card_dc += 1;
                            $total_amount_dc += $proccessed_amount;
                            $total_refund_dc += $refunded_amount;

                            $usedCards['DC'] = array(
                                'label' => $availableCcTypes[$card_type],
                                'total' => $total_card_dc,
                                'amount' => $total_amount_dc,
                                'refund' => $total_refund_dc,
                                'net' => $total_amount_dc - $total_refund_dc
                            );
                            break;
                        case 'NS':
                            $total_card_ns += 1;
                            $total_amount_ns += $proccessed_amount;
                            $total_refund_ns += $refunded_amount;

                            $usedCards['NS'] = array(
                                'label' => $availableCcTypes[$card_type],
                                'total' => $total_card_ns,
                                'amount' => $total_amount_ns,
                                'refund' => $total_refund_ns,
                                'net' => $total_amount_ns - $total_refund_ns
                            );
                            break;
                        case 'JC':
                            $total_card_jc += 1;
                            $total_amount_jc += $proccessed_amount;
                            $total_refund_jc += $refunded_amount;

                            $usedCards['JC'] = array(
                                'label' => $availableCcTypes[$card_type],
                                'total' => $total_card_jc,
                                'amount' => $total_amount_jc,
                                'refund' => $total_refund_jc,
                                'net' => $total_amount_jc - $total_refund_jc
                            );
                    }

                    $datarow = array($transType, $invoice_id, $myOrder->getIncrementId(), date("m/d/Y", strtotime($myOrder->getCreatedAt())), $card_type, "xxxx-" . $last_4no, Mage::helper('core')->currency($amount, true, false), utf8_decode($customer_name));
                    $line = "";
                    $comma = "";

                    foreach ($datarow as $titlename) {
                        $line .= $comma . str_replace(array(','), array(""), $titlename);
                        $comma = ",";
                    }

                    $line .= "\n";

                    $orders_csv_row .=$line;
                }
            }
            $orders_csv_row .="\n";
            $orders_csv_row .= "Grand Total";
            $orders_csv_row .="\n\n";
            $orders_csv_row .= "Card Type, Total Order, Sale, Refunds, Net";
            $orders_csv_row .="\n";

            $currency_code = Mage::app()->getStore()->getCurrentCurrencyCode();
            $currency_symbol = Mage::app()->getLocale()->currency($currency_code)->getSymbol();

            $summary = array('label' => 'Total');

            $cardTotal = 0;
            $amountSummary = 0;
            $refundSummary = 0;

            foreach ($usedCards as $card) {
                $comma = "";

                $cardTotal += $card['total'];
                $amountSummary += $card['amount'];
                $refundSummary += $card['refund'];

                $summary['total'] = $cardTotal;
                $summary['amount'] = $amountSummary;
                $summary['refund'] = $refundSummary;
                $summary['net'] = $amountSummary - $refundSummary;

                foreach ($card as $key => $value) {

                    if ($key != 'label' && $key != 'total') {
                        $value = $currency_symbol . $value;
                    }
                    if ($key == 'refund') {
                        $value = '(' . $value . ')';
                    }
                    $orders_csv_row .= $comma . $value;
                    $comma = ",";
                }
                $orders_csv_row .="\n";
            }

            $comma = "";
            $orders_csv_row .="\n";

            foreach ($summary as $key => $value) {
                if ($key != 'label' && $key != 'total') {
                    $value = $currency_symbol . $value;
                }
                if ($key == 'refund') {
                    $value = '(' . $value . ')';
                }
                $orders_csv_row .= $comma . $value;
                $comma = ",";
            }
            $orders_csv_row .="\n";

            $fileName = 'Shift4DailyPaymentReport.csv';
            $this->_sendUploadResponse($fileName, $orders_csv_row);
        }
    }
    
    /**
     * Export the Sales report as CSV
     *
     * @return CSV file
     */
    protected function _sendUploadResponse($fileName, $content, $contentType = 'application/octet-stream') {
        $response = $this->getResponse();
        $response->setHeader('HTTP/1.1 200 OK', '');
        $response->setHeader('Pragma', 'public', true);
        $response->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true);
        $response->setHeader('Content-Disposition', 'attachment; filename=' . $fileName);
        $response->setHeader('Last-Modified', date('r'));
        $response->setHeader('Accept-Ranges', 'bytes');
        $response->setHeader('Content-Length', strlen($content));
        $response->setHeader('Content-type', $contentType);
        $response->setBody($content);
        $response->sendResponse();
        die;
    }

}