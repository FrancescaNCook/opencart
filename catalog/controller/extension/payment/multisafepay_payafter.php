<?php

/**
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the MultiSafepay plugin
 * to newer versions in the future. If you wish to customize the plugin for your
 * needs please document your changes and make backups before you update.
 *
 * @category    MultiSafepay
 * @package     Connect
 * @author      TechSupport <techsupport@multisafepay.com>
 * @copyright   Copyright (c) 2017 MultiSafepay, Inc. (http://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
class ControllerExtensionPaymentMultiSafePayPayafter extends Controller
{

    public function index()
    {
        $this->load->language('extension/payment/multisafepay');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['button_back'] = $this->language->get('button_back');
        $data['entry_select_gateway'] = $this->language->get('text_select_payment_method');
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $data['gateway'] = 'PAYAFTER';
        $data['MSP_CARTID'] = $this->session->data['order_id'];
        //$this->load->library('encryption');
        $data['action'] = $this->url->link('extension/payment/multisafepay_payafter/multisafepayProcess', '', 'SSL');
        $data['back'] = $this->url->link('checkout/checkout', '', 'SSL');
        $data['order_id'] = $order_info['order_id'];
        $data['text_paymentmethod'] = $this->language->get('text_paymentmethod');


        $data['text_description'] = $this->language->get('text_description');
        $data['text_initial'] = substr($order_info['payment_firstname'] . ' ', 0, 1);
        if (isset($this->session->data['multisafepay_payafter_fee']['fee'])) {
            $fee = $this->session->data['multisafepay_payafter_fee']['fee'];
            if (isset($this->session->data['multisafepay_payafter_fee']['feetax'])) {
                $fee += $this->session->data['multisafepay_payafter_fee']['feetax'];
            }
            $data['text_paymentfee'] = str_replace('{fee}', $this->currency->format($fee), $this->language->get('text_paymentfee'));
        } else {
            $data['text_paymentfee'] = '';
        }


        $data['order_id'] = $this->session->data['order_id'];

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/multisafepay_payafter')) {
            return $this->load->view($this->config->get('config_template') . '/template/extension/payment/multisafepay_payafter', $data);
        } elseif (file_exists(DIR_TEMPLATE . 'default/template/extension/payment/multisafepay_payafter') && VERSION < '2.2.0.0') {
            return $this->load->view('default/template/extension/payment/multisafepay_payafter', $data);
        } else {
            return $this->load->view('extension/payment/multisafepay_payafter', $data);
        }
    }

    public function validateVersion()
    {
        //default 1.5
        $version = 1.5;
        return $version;
    }

    public function multisafepayProcess()
    {
        $storeid = $this->config->get('config_store_id');
        $this->load->language('extension/payment/multisafepay');
        $db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
        // Language Detection
        $languages = array();

        $query = $db->query("SELECT * FROM " . DB_PREFIX . "language WHERE code='" . $this->session->data['language'] . "'");

        foreach ($query->rows as $result) {
            $languages[$result['code']] = $result;
        }

        $language_string = $languages[$this->session->data['language']]['locale'];

        $loc1 = explode(',', $language_string);

        if (!isset($loc1[1])) {
            $locale = $loc1[0];
        } else {
            $locale = $loc1[1];
        }



        $multisafepay_redirect_url = $this->config->get('multisafepay_redirect_url_' . $storeid);
        if ($multisafepay_redirect_url == 1) {
            $redirect_url = true;
        } else {
            $redirect_url = false;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->request->post['cartId']);

        $itemsstring = '';

        $html = "<ul>";
        foreach ($this->cart->getProducts() as $product) {
            $html .= '<li>' . $product['quantity'] . ' x ' . $product['name'] . ' </li>';
        }
        $html .= "</ul>";

        //MSP SET DATA FOR TRANSACTION REQUEST
        require_once(dirname(__FILE__) . '/MultiSafepay.combined.php');
        $msp = new MultiSafepay();
        $msp->test = $this->config->get('multisafepay_payafter_environment_' . $storeid);
        $msp->merchant['account_id'] = $this->config->get('multisafepay_payafter_merchant_id_' . $storeid);
        $msp->merchant['site_id'] = $this->config->get('multisafepay_payafter_site_id_' . $storeid);
        $msp->merchant['site_code'] = $this->config->get('multisafepay_payafter_secure_code_' . $storeid);
        $msp->merchant['notification_url'] = $this->url->link('extension/payment/multisafepay/fastcheckout&type=initial', '', 'SSL');
        $msp->merchant['cancel_url'] = $this->url->link('checkout/checkout', '', 'SSL');
        $msp->merchant['redirect_url'] = $this->url->link('checkout/success', '', 'SSL');
        $msp->merchant['close_window'] = $this->config->get('multisafepay_redirect_url_' . $storeid);
        $msp->customer['locale'] = $locale;
        $msp->customer['firstname'] = $order_info['payment_firstname'];
        $msp->customer['lastname'] = $order_info['payment_lastname'];
        $msp->customer['zipcode'] = $order_info['payment_postcode'];
        $msp->customer['city'] = $order_info['payment_city'];
        $msp->customer['email'] = $order_info['email'];
        if (!empty($order_info['telephone'])) {
            $msp->customer['phone'] = $order_info['telephone'];
        }
        $msp->customer['country'] = $order_info['payment_iso_code_2'];
        $msp->parseCustomerAddress($order_info['payment_address_1']);
        $msp->transaction['id'] = $order_info['order_id'];
        $msp->transaction['currency'] = 'EUR'; //MSP only supports EUR at the moment  ->  $order_info['currency_code'];
        //PLGOPN-48 test
        $msp->transaction['currency'] = $order_info['currency_code'];
        $msp->transaction['description'] = 'Order #' . $msp->transaction['id'];
        //$msp->transaction['amount'] = $this->currency->format($order_info['total'], 'EUR', '', FALSE) * 100;
        //PLGOPN-48 test
        $msp->transaction['amount'] = round(($order_info['total'] * $order_info['currency_value']) * 100);
        $msp->plugin_name = 'OpenCart ' . VERSION;
        $msp->version = '(2.0.1)';
        $msp->transaction['items'] = $html;
        $msp->plugin['shop'] = 'OpenCart';
        $msp->plugin['shop_version'] = VERSION;
        $msp->plugin['plugin_version'] = '2.0.1';
        $msp->plugin['partner'] = '';
        $msp->plugin['shop_root_url'] = '';

        if ($this->customer->isLogged()) {
            $msp->transaction['var1'] = $this->customer->getId() . '|' . $this->customer->getBalance();
            $msp->transaction['var2'] = $this->config->get('config_customer_group_id');
        }

        $msp->transaction['gateway'] = 'PAYAFTER';
        $msp->gatewayinfo['email'] = $this->customer->getEmail();

        $msp->gatewayinfo['bankaccount'] = ''; //not available
        $msp->gatewayinfo['referrer'] = $_SERVER['HTTP_REFERER'];
        $msp->gatewayinfo['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $msp->gatewayinfo['birthday'] = ''; //not available
        $products = $this->cart->getProducts();

        // Tax for products
        $taxname = '0';
        $taxtable = new MspAlternateTaxTable('0', 'true');
        $taxrule = new MspAlternateTaxRule(0.00);
        $taxtable->AddAlternateTaxRules($taxrule);
        $msp->cart->AddAlternateTaxTables($taxtable);


        $taxes = array();

        $taxtable = Array();
        foreach ($products AS $product) {
            $ratiotax = $this->tax->getRates($product['total'], $product['tax_class_id']);
            foreach ($ratiotax AS $tax_array) {
                $taxes[] = $tax_array;
            }
        }

        //$unique_taxes 						= 	array_unique($taxes);

        $unique_taxes = $taxes;


        $unique_taxes2 = array();

        foreach ($taxes as $tax) {
            if (!array_key_exists($tax['tax_rate_id'], $unique_taxes)) {
                $unique_taxes2[$tax['tax_rate_id']] = array('tax_rate_id' => $tax['tax_rate_id'], 'name' => $tax['name'], 'rate' => $tax['rate']);
            }
        }


        foreach ($unique_taxes2 as $tax) {
            $taxname = $tax['name'];
            $taxtable = new MspAlternateTaxTable($tax['name'], 'true');
            $taxrule = new MspAlternateTaxRule($tax['rate'] / 100);
            $taxtable->AddAlternateTaxRules($taxrule);
            $msp->cart->AddAlternateTaxTables($taxtable);
        }

        if (isset($this->session->data['coupon'])) {
            $coupon_set = true;
            $this->load->model('extension/total/coupon');
            $coupon_info = $this->model_extension_total_coupon->getCoupon($this->session->data['coupon']);
        } else {
            $coupon_set = false;
        }

        $product_ids = array();
        foreach ($products AS $product) {
            $product_ids[$product['product_id']] = $product['product_id'];
        }


        //add products
        foreach ($products AS $product) {
            // Retrieve which tax table to use.
            $ratiotax = $this->tax->getRates($product['price'], $product['tax_class_id']);

            $i = 0;
            foreach ($ratiotax AS $tax_array) {
                $taxes[$i] = $tax_array;
                $i++;
            }

            if (isset($taxes[0]['name'])) {
                $taxname = $taxes[0]['name'];
            } else {
                $taxname = '0';
            }
            if ($coupon_set) {
                if ($coupon_info['type'] == 'F') {
                    $c_item = new MspItem($product['name'], strip_tags($product['model']), $product['quantity'], $product['price'], 'KG', $product['weight']);
                    $c_item->merchant_item_id = $product['product_id'];
                    $c_item->SetTaxTableSelector($taxname);
                    $msp->cart->AddItem($c_item);
                } else {
                    $price_new = $product['price'] - ($product['price'] / 100 * $coupon_info['discount']);
                    $c_item = new MspItem($product['name'], strip_tags($product['model']), $product['quantity'], $price_new, 'KG', $product['weight']);
                    $c_item->merchant_item_id = $product['product_id'];
                    $c_item->SetTaxTableSelector($taxname);
                    $msp->cart->AddItem($c_item);
                }
            } else {

                $c_item = new MspItem($product['name'], strip_tags($product['model']), $product['quantity'], $product['price'], 'KG', $product['weight']);
                $c_item->merchant_item_id = $product['product_id'];
                $c_item->SetTaxTableSelector($taxname);
                $msp->cart->AddItem($c_item);
            }
        }

        //Customer credit processing
        if ($this->customer->getBalance() > 0) {
            $credit = 0 - $this->customer->getBalance();
            $c_item = new MspItem('Credit', 'Credit', 1, $credit);
            $c_item->merchant_item_id = '10101010';
            $c_item->SetTaxTableSelector('0');
            $msp->cart->AddItem($c_item);
        }

        //add discounts
        if ($coupon_set) {
            $this->load->model('extension/total/coupon');
            $total_data = array();
            $total = $this->cart->getTotal();
            $start_total = $this->cart->getTotal();
            $taxes = $this->cart->getTaxes();

            $total_data_arr = array(
                'totals' => &$total_data,
                'total' => &$total,
                'taxes' => &$taxes
            );

            $this->model_extension_total_coupon->getTotal($total_data_arr);

            if ($coupon_info['type'] == 'F') {
                if ($start_total != $total) {
                    $discount_total = 0;
                    $start_total = $start_total;
                    $total = $total;
                    $discount_total = $discount_total - ($start_total - $total);

                    $c_item = new MspItem('Coupon', 'Coupon', 1, $discount_total);
                    $c_item->merchant_item_id = '10101010';
                    $c_item->SetTaxTableSelector('0');
                    $msp->cart->AddItem($c_item);
                }
            }
        }


        // Payment fee

        $fee = $this->config->get('multisafepaypayafterfee');



        if ($fee['NLD']['status']) {

            $tax_rates = $this->tax->getRates($fee['NLD']['fee'], $fee['NLD']['tax_class_id']);


            $feetaxrate = $this->_getRate($fee['NLD']['tax_class_id']);
            $fee = $this->_getAmount($order_info, $fee['NLD']['fee']);



            //$btw= $fee/ (100+$feetaxrate)*$feetaxrate;
            //$fee= $fee -$btw;


            $c_item = new MspItem($this->language->get('entry_paymentfee'), 'Fee', '1', $fee, 'KG', '0');
            $c_item->merchant_item_id = 'payment fee';
            $c_item->SetTaxTableSelector('fee');
            $msp->cart->AddItem($c_item);

            $taxtable = new MspAlternateTaxTable('fee', 'true');
            $taxrule = new MspAlternateTaxRule($feetaxrate / 100);
            $taxtable->AddAlternateTaxRules($taxrule);
            $msp->cart->AddAlternateTaxTables($taxtable);
        }



        $shipping_select = '';

        //add shippingmethod

        if ($this->session->data['shipping_method']['tax_class_id']) {
            $shipping_tax = $this->tax->getRates($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id']);

            foreach ($shipping_tax as $key => $value) {
                $correct_rate = round($value['rate'], 2) / 100;
                $rule = new MspDefaultTaxRule($correct_rate, 'true'); // Tax rate, shipping taxed
                $msp->cart->AddDefaultTaxRules($rule);
                $shipping_select = $value['name'];
            }
        } else {
            $shipping_select = '0';
        }


        $c_item = new MspItem($this->session->data['shipping_method']['title'] . " " . 'EUR', 'Shipping', '1', $this->session->data['shipping_method']['cost'], '0', '0');
        $msp->cart->AddItem($c_item);
        $c_item->SetMerchantItemId('Shipping');
        $c_item->SetTaxTableSelector($shipping_select); //shipping.... $this->session->data['shipping_method']['tax_class_id']
        //$c_item->SetTaxTableSelector('fee');

        $msp->transaction['amount'] = $this->currency->format($order_info['total'], 'EUR', '', FALSE) * 100;

        $url = $msp->startCheckout();

        /* echo '<pre>';	
          print_r($msp);
          echo '</pre>';exit; */

        if (!isset($msp->error)) {
            $this->load->model('checkout/order');
            if (!$this->config->get('multisafepay_confirm_order_' . $storeid)) {
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('multisafepay_order_status_id_initialized_' . $storeid), '', true);
            }

            header('Location: ' . $url);
            exit;
        } else {
            $this->language->load('extension/payment/multisafepay');
            $data['back_to_store'] = $this->language->get('back_to_store');
            $data['errorcode'] = $msp->error_code;
            $data['errorstring'] = $msp->error;
            $data['charset'] = $this->language->get('charset');
            $data['language'] = $this->language->get('code');
            $data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));
            $data['text_success_wait'] = sprintf($this->language->get('text_success_wait'), $this->url->link('checkout/success', '', 'SSL'));
            $data['text_failure'] = $this->language->get('text_failure');
            $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('checkout/checkout', '', 'SSL'));
            $data['button_continue'] = $this->language->get('button_continue');
            $data['continue'] = $this->url->link('checkout/checkout', '', 'SSL');






            if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/multisafepay_failure')) {
                echo $this->load->view($this->config->get('config_template') . '/template/extension/payment/multisafepay_failure', $data);
            } elseif (file_exists(DIR_TEMPLATE . 'default/template/extension/payment/multisafepay_failure') && VERSION < '2.2.0.0') {
                echo $this->load->view('default/template/extension/payment/multisafepay_failure', $data);
            } else {
                echo $this->load->view('extension/payment/multisafepay_failure', $data);
            }

            exit;
        }
    }

    private function _getAmount($order_info, $amount)
    {

        $amt = $this->currency->format($amount, $order_info['currency_code'], $order_info['currency_value'], false);

        if ($this->session->data['currency'] != 'EUR') {
            $amt = $this->currency->convert($amt, $this->session->data['currency'], 'EUR');
        }
        return $amt;
    }

    private function _getRate($tax_class_id)
    {
        if (method_exists($this->tax, 'getRate')) {
            return $this->tax->getRate($tax_class_id);
        } else {
            $tax_rates = $this->tax->getRates(100, $tax_class_id);
            foreach ($tax_rates as $tax_rate) {
                return $tax_rate['amount'];
            }
        }
    }

}

?>