<?php

$installer = $this;
$installer->startSetup();
$connection = $installer->getConnection();
$definition = array(
    'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
    'length' => 255,
    'comment' => 'i4Go True Token'
);
$columnName = 'i4go_true_token';
$connection->addColumn($installer->getTable('sales/order_payment'), $columnName, $definition);
$connection->addColumn($installer->getTable('sales/quote_payment'), $columnName, $definition);

$installer->run("

CREATE TABLE IF NOT EXISTS `{$this->getTable('shift4/customerstored')}` (
  `stored_card_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Stored_card_id',
  `transaction_id` varchar(255) NOT NULL COMMENT 'Transaction_id',
  `customer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'Customer_id',
  `cc_type` varchar(255) NOT NULL COMMENT 'Cc_type',
  `cc_last4` varchar(255) NOT NULL COMMENT 'Cc_last4',
  `cc_exp_month` varchar(255) NOT NULL COMMENT 'Cc_exp_month',
  `cc_exp_year` varchar(255) NOT NULL COMMENT 'Cc_exp_year',
  `date` datetime DEFAULT NULL,
  `payment_method` varchar(255) NOT NULL COMMENT 'Payment_method',
  `card_token` varchar(16) NOT NULL COMMENT 'Card Token',
  PRIMARY KEY (`stored_card_id`),
  KEY `FK_SHIFT4_PAYMENT_CSTR_STORED_CSTR_ID_CSTR_ENTT_ENTT_ID` (`customer_id`),
  CONSTRAINT `FK_SHIFT4_PAYMENT_CSTR_STORED_CSTR_ID_CSTR_ENTT_ENTT_ID` FOREIGN KEY (`customer_id`) REFERENCES `{$this->getTable('customer/entity')}` (`entity_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='shift4_customer_stored';

");

$installer->endSetup();
?>