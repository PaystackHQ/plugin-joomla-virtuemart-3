<?php

/**
 * @package       SimplePay
 * @author        SimplePay Ltd
 * @copyright     Copyright (C) 2016 SimplePay Ltd. All rights reserved.
 * @version       1.0.0, March 2016
 * @license       MIT, see LICENSE
 */

defined('_JEXEC') or die('Direct access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentSimplepay extends vmPSPlugin
{
    function __construct(& $subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';

        $this->tableFields = array_keys($this->getTableSQLFields());

        $varsToPush = array(
            'test_mode' => array(0, 'int'), // simplepay.xml (test_mode)
            'private_live_key' => array('', 'char'), // simplepay.xml (private_live_key)
            'public_live_key' => array('', 'char'), // simplepay.xml (public_live_key)
            'private_test_key' => array('', 'char'), // simplepay.xml (private_test_key)
            'public_test_key' => array('', 'char'), // simplepay.xml (public_test_key)
            'description' => array('', 'char'), // simplepay.xml (description)
            'image_url' => array('', 'char'), // simplepay.xml (image_url)

            'status_pending' => array('', 'char'),
            'status_success' => array('', 'char'),
            'status_canceled' => array('', 'char'),

            'min_amount' => array(0, 'int'),
            'max_amount' => array(0, 'int'),
            'cost_per_transaction' => array(0, 'int'),
            'cost_percent_total' => array(0, 'int'),
            'tax_id' => array(0, 'int')
        );

        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment SimplePay Table');
    }

    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'tinyint(1) unsigned NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED DEFAULT NULL',
            'order_number' => 'char(32) DEFAULT NULL',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED DEFAULT NULL',
            'payment_name' => 'char(255) NOT NULL DEFAULT \'\' ',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
            'payment_currency' => 'char(3) ',
            'cost_per_transaction' => ' decimal(10,2) DEFAULT NULL ',
            'cost_percent_total' => ' decimal(10,2) DEFAULT NULL ',
            'tax_id' => 'smallint(11) DEFAULT NULL',
            'simplepay_transaction_id' => 'char(32) DEFAULT NULL'
        );

        return $SQLfields;
    }

    function getSimplePaySettings($payment_method_id)
    {
        $simplepay_settings = $this->getPluginMethod($payment_method_id);

        if ($simplepay_settings->test_mode) {
            $private_key = $simplepay_settings->private_test_key;
            $public_key = $simplepay_settings->public_test_key;
        } else {
            $private_key = $simplepay_settings->private_live_key;
            $public_key = $simplepay_settings->public_live_key;
        }

        return array(
            'private_key' => $private_key,
            'public_key' => $public_key,
            'description' => $simplepay_settings->description,
            'image_url' => $simplepay_settings->image_url
        );
    }

    function verifySimplePayTransaction($token, $payment_method_id)
    {
        // Get Private API Key from settings
        $simplepay_settings = $this->getSimplePaySettings($payment_method_id);

        $data = array(
            'token' => $token
        );

        $dataString = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://checkout.simplepay.ng/v1/payments/verify/');
        curl_setopt($ch, CURLOPT_USERPWD, $simplepay_settings['private_key'] . ':');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($dataString)
        ));
        $curlResponse = curl_exec($ch);
        $curlResponse = preg_split("/\r\n\r\n/", $curlResponse);
        $responseContent = $curlResponse[1];
        $jsonResponse = json_decode(chop($responseContent), true);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseCode == '200' && $jsonResponse['response_code'] == '20000') {
            return true;
        }

        return false;
    }

    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');

        if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');

        // Get current order info
        $order_info = $order['details']['BT'];
        $country_code = ShopFunctions::getCountryByID($order_info->virtuemart_country_id, 'country_3_code');

        // Get payment currency
        $this->getPaymentCurrency($method);
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db = &JFactory::getDBO();
        $db->setQuery($q);
        $currency_code = $db->loadResult();

        // Get total amount for the current payment currency
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);

        // Prepare data that should be stored in the database
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $method->payment_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['tax_id'] = $method->tax_id;
        $this->storePSPluginInternalData($dbValues);

        // Return URL - Verify SimplePay payment
        $return_url = JURI::root() .
            'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' .
            $order['details']['BT']->order_number .
            '&pm=' .
            $order['details']['BT']->virtuemart_paymentmethod_id .
            '&Itemid=' . vRequest::getInt('Itemid') .
            '&lang=' . vRequest::getCmd('lang', '');

        // SimplePay Settings
        $payment_method_id = vRequest::getInt('virtuemart_paymentmethod_id');
        $simplepay_settings = $this->getSimplePaySettings($payment_method_id);

        // SimplePay Gateway HTML code
        $html = '
        <p>Your order is being processed</p>
        <script src="https://checkout.simplepay.ng/simplepay.js"></script>
        <script type="text/javascript">
        function formatAmount(amount) {
            var strAmount = amount.toString().split(".");
            var decimalPlaces = strAmount[1] === undefined ? 0: strAmount[1].length;
            var formattedAmount = strAmount[0];

            if (decimalPlaces === 0) {
                formattedAmount += \'00\';

            } else if (decimalPlaces === 1) {
                formattedAmount += strAmount[1] + \'0\';

            } else if (decimalPlaces === 2) {
                formattedAmount += strAmount[1];
            }

            return formattedAmount;
        }
        var amount = formatAmount("' . $totalInPaymentCurrency['value'] . '");

        // Gateway dialog
		var handler = SimplePay.configure({
			token: function(token) {
			    var form = jQuery("<form />", { action: "' . $return_url . '", method: "POST" });
                form.append(
                    jQuery("<input />", { name: "token", type: "hidden", value: token }),
                    jQuery("<input />", { name: "payment_method_id", type: "hidden", value: "' . $payment_method_id . '" })
                );
                form.submit();
			},
			key: "' . $simplepay_settings['public_key'] . '",
			platform: "VirtueMart",
			image: "' . $simplepay_settings['image_url'] . '"
		});

        var customDescription = "' . $simplepay_settings['description'] . '";
        if (customDescription) {
            customDescription += " - Order #' . $order_info->order_number . '";
        } else {
            customDescription = "Order #' . $order_info->order_number . '";
        }

        var paymentData = {
			email: "' . $order_info->email . '",
			phone: "' . $order_info->phone_1 . '",
			description: customDescription,
			address: "' . $order_info->address_1 . ' ' . $order_info->address_2 . '",
			postal_code: "' . $order_info->zip . '",
			city: "' . $order_info->city . '",
			country: "' . $country_code . '",
			amount: amount,
			currency: "' . $currency_code . '"
		};

		jQuery(function() {
		    handler.open(SimplePay.CHECKOUT, paymentData);
		});
        </script>
        ';

        $cart->_confirmDone = FALSE;
        $cart->_dataValidated = FALSE;
        $cart->setCartIntoSession();

        vRequest::setVar('html', $html);
    }

    function plgVmOnPaymentResponseReceived(&$html)
    {
        if (!class_exists('VirtueMartCart')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        if (!class_exists('shopFunctionsF')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        VmConfig::loadJLang('com_virtuemart_orders', TRUE);
        $post_data = vRequest::getPost();

        // The payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = vRequest::getInt('pm', 0);
        $order_number = vRequest::getString('on', 0);
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return NULL;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
            return NULL;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return '';
        }

        VmConfig::loadJLang('com_virtuemart');
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($virtuemart_order_id);

        if ($this->verifySimplePayTransaction($post_data['token'], $post_data['payment_method_id'])) {
            // Update order status - From pending to complete
            $order['order_status'] = 'C';
            $order['customer_notified'] = 1;
            $orderModel->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);

            // Empty cart
            $cart = VirtueMartCart::getCart();
            $cart->emptyCart();

            return True;
        }

        // Update order status - From pending to canceled
        $order['order_status'] = 'X';
        $order['customer_notified'] = 1;
        $orderModel->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, TRUE);

        return False;
    }

    function plgVmOnUserPaymentCancel()
    {
        return true;
    }

    /**
     * Required functions by Joomla or VirtueMart. Removed code comments due to 'file length'.
     * All copyrights are (c) respective year of author or copyright holder, and/or the author.
     */
    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }
        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert_condition_amount($method);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
        $amount = $this->getCartAmount($cart_prices);
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR ($method->min_amount <= $amount AND ($method->max_amount == 0)));
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }
        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }
        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            if ($amount_cond) {
                return TRUE;
            }
        }
        return FALSE;
    }

    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
    {
        return $this->OnSelectCheck($cart);
    }

    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

}
