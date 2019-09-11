<?php
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Stream\Stream;

class ControllerCheckoutConfirm extends Controller {
    public function vertexTax() {
        $env = simplexml_load_string('<Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:vertexinc:o-series:tps:8:0"></Envelope>');

        /*$xml = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?>
        <VertexEnvelope xmlns="urn:vertexinc:o-series:tps:8:0"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
        </VertexEnvelope>');*/

        /*$vertenv = $env->addChild('<VertexEnvelope xmlns="urn:vertexinc:o-series:tps:8:0"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
            </VertexEnvelope>');*/
        
        $body = $env->addChild("sBody");

        $vertenv = $body->addChild('VertexEnvelope', "", null);
        $vertenv->addAttribute('xmlns', 'urn:vertexinc:o-series:tps:8:0');
        $vertenv->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

        $Login = $vertenv->addChild('Login');
        $Login -> addChild("UserName","austin");
        $Login -> addChild("Password","vertex");
        
        $QuotationRequest = $vertenv->addChild('QuotationRequest');
        $QuotationRequest->addAttribute('transactionType', 'SALE');
        $TodayDate = date("Y-m-d");
        $QuotationRequest->addAttribute('documentDate', $TodayDate );
        
        $Customer = $QuotationRequest->addChild('Customer');
        $CustomerCode = $Customer ->addChild('CustomerCode');
        $CustomerCode->addAttribute('classCode', '2002' );
        
        /*$order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
        $order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
        $order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
        $order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
        $order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
        $order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
        $order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
        $order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
        $order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
        $order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
        $order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
        $order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
        $order_data['shipping_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array());
*/
        //echo $this->session->data['shipping_address']['lastname'];
        //echo $this->session->data['shipping_address']['city'];
        $Destination = $Customer ->addChild('Destination');
        $Destination-> addChild("StreetAddress1", $this->session->data['shipping_address']['address_1']);
        $Destination-> addChild("City", $this->session->data['shipping_address']['city']);
        $Destination-> addChild("MainDivision", $this->session->data['shipping_address']['zone']);
        $Destination-> addChild("PostalCode", $this->session->data['shipping_address']['postcode']);
        $Destination-> addChild("Country", $this->session->data['shipping_address']['country']);
        
        $x = 1;
        foreach ($this->cart->getProducts() as $product) {
            $LineItem = $QuotationRequest->addChild('LineItem');
            $LineItem ->addAttribute('isMulticomponent', 'false' );
            $LineItem ->addAttribute('lineItemId', $x );
            $LineItem ->addAttribute('taxDate', $TodayDate );
            $Product = $LineItem->addChild('Product','product code value');
            $Product ->addAttribute('productClass', 'product class attribute value');
            $LineItem ->addChild('Quantity', $product['quantity']);
            $LineItem ->addChild('Freight', 0);
            $LineItem ->addChild('UnitPrice', $product['price']);
            $LineItem ->addChild('shipping', $this->customer->getAddressId());
            $x++;
        }
        
        Header('Content-type: text/xml');
        //print($xml->asXML());
        
        //$dom = dom_import_simplexml($xml)->ownerDocument;
        //$dom->formatOutput = true;
        //echo $dom->saveXML();

        $url = 'https://oseries.vertex.tax/vertex-ws/services/CalculateTax80';
        /*$opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: text/xml',
                'body' => $env
            )
        );
        $context  = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);*/

        $client = new Client();
        //$request = $client->createRequest();
        $request = new Request(
            'POST', 
            $url,
            ['Content-Type' => 'text/xml; charset=UTF8']
        );
        $env = (string)$env->asXML();
        $env = str_replace("<sBody", "<soapenv:Body", (string)$env);
        $env = str_replace("</sBody", "</soapenv:Body", (string)$env);
        $env = str_replace("<Envelope", "<soapenv:Envelope", (string)$env);
        $env = str_replace("</Envelope", "</soapenv:Envelope", (string)$env);
        //echo $env;
        $request->setBody(Stream::factory($env));

        $response = $client->send($request);
        $response = $response->getBody()->getContents();

        $totalpattern = "/\<TotalTax\>(.+)\<\/TotalTax\>/";
        $matches_out = array();
        preg_match($totalpattern, $response, $matches_out);
        
        $taxpattern = "/\<Total\>(.+)\<\/Total\>/";
        $taxmatch = array();
        preg_match($taxpattern, $response, $taxmatch);

        /*echo($matches_out[0]);
        echo($taxmatch[0]);*/

        //return array($matches_out[0], $taxmatch[0]);
		
		return array($this->stringToDouble($matches_out[0]));
	}

	public function stringToDouble($string) {
		$dotloc = strrpos($string, ".");
		$total = 0.0;
		for($x = 0; $x < strlen($string); $x++) {
			$currentChar = $string[$x];

			if($currentChar == ".") {
				continue;
			}

			if($x > $dotloc) {
				$pow = $dotloc - $x;
			} else {
				$pow = ($dotloc - $x) - 1;
			}
			
			$mult = pow(10, $pow);
			if($currentChar == "0") {
				$total = $total + $mult * 0;
			} else if($currentChar == "1") {
				$total = $total + $mult * 1;
			} else if($currentChar == "2") {
				$total = $total + $mult * 2;
			} else if($currentChar == "3") {
				$total = $total + $mult * 3;
			} else if($currentChar == "4") {
				$total = $total + $mult * 4;
			} else if($currentChar == "5") {
				$total = $total + $mult * 5;
			} else if($currentChar == "6") {
				$total = $total + $mult * 6;
			} else if($currentChar == "7") {
				$total = $total + $mult * 7;
			} else if($currentChar == "8") {
				$total = $total + $mult * 8;
			} else if($currentChar == "9") {
				$total = $total + $mult * 9;
			}
		}

		return $total;
	}
	
	public function index() {
		$redirect = '';

		if ($this->cart->hasShipping()) {
			// Validate if shipping address has been set.
			if (!isset($this->session->data['shipping_address'])) {
				$redirect = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));
			}

			// Validate if shipping method has been set.
			if (!isset($this->session->data['shipping_method'])) {
				$redirect = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));
			}
		} else {
			unset($this->session->data['shipping_address']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
		}

		// Validate if payment address has been set.
		if (!isset($this->session->data['payment_address'])) {
			$redirect = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));
		}

		// Validate if payment method has been set.
		if (!isset($this->session->data['payment_method'])) {
			$redirect = $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'));
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$redirect = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'));
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$redirect = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'));

				break;
			}
		}

		if (!$redirect) {
			$order_data = array();

			$totals = array();
			$taxes = $this->vertexTax();
			$total = 0;

			$this->load->model('setting/extension');

			$sort_order = array();

			$results = $this->model_setting_extension->getExtensions('total');

			foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
			}

			array_multisort($sort_order, SORT_ASC, $results);

			foreach ($results as $result) {
				if ($this->config->get('total_' . $result['code'] . '_status')) {
					$this->load->model('extension/total/' . $result['code']);

					// __call can not pass-by-reference so we get PHP to call it as an anonymous function.
					($this->{'model_extension_total_' . $result['code']}->getTotal)($totals, $taxes, $total);
				}
			}

			$sort_order = array();

			foreach ($totals as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}

			array_multisort($sort_order, SORT_ASC, $totals);

			$order_data['totals'] = $totals;

			$this->load->language('checkout/checkout');

			$order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
			$order_data['store_id'] = $this->config->get('config_store_id');
			$order_data['store_name'] = $this->config->get('config_name');
			$order_data['store_url'] = $this->config->get('config_url');

			$this->load->model('account/customer');

			if ($this->customer->isLogged()) {
				$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

				$order_data['customer_id'] = $this->customer->getId();
				$order_data['customer_group_id'] = $customer_info['customer_group_id'];
				$order_data['firstname'] = $customer_info['firstname'];
				$order_data['lastname'] = $customer_info['lastname'];
				$order_data['email'] = $customer_info['email'];
				$order_data['telephone'] = $customer_info['telephone'];
				$order_data['custom_field'] = json_decode($customer_info['custom_field'], true);
			} elseif (isset($this->session->data['guest'])) {
				$order_data['customer_id'] = 0;
				$order_data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
				$order_data['firstname'] = $this->session->data['guest']['firstname'];
				$order_data['lastname'] = $this->session->data['guest']['lastname'];
				$order_data['email'] = $this->session->data['guest']['email'];
				$order_data['telephone'] = $this->session->data['guest']['telephone'];
				$order_data['custom_field'] = $this->session->data['guest']['custom_field'];
			}

			$order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
			$order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
			$order_data['payment_company'] = $this->session->data['payment_address']['company'];
			$order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
			$order_data['payment_address_2'] = $this->session->data['payment_address']['address_2'];
			$order_data['payment_city'] = $this->session->data['payment_address']['city'];
			$order_data['payment_postcode'] = $this->session->data['payment_address']['postcode'];
			$order_data['payment_zone'] = $this->session->data['payment_address']['zone'];
			$order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
			$order_data['payment_country'] = $this->session->data['payment_address']['country'];
			$order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
			$order_data['payment_address_format'] = $this->session->data['payment_address']['address_format'];
			$order_data['payment_custom_field'] = (isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array());

			if (isset($this->session->data['payment_method']['title'])) {
				$order_data['payment_method'] = $this->session->data['payment_method']['title'];
			} else {
				$order_data['payment_method'] = '';
			}

			if (isset($this->session->data['payment_method']['code'])) {
				$order_data['payment_code'] = $this->session->data['payment_method']['code'];
			} else {
				$order_data['payment_code'] = '';
			}

			if ($this->cart->hasShipping()) {
				$order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
				$order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
				$order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
				$order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
				$order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
				$order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
				$order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
				$order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
				$order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
				$order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
				$order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
				$order_data['shipping_address_format'] = $this->session->data['shipping_address']['address_format'];
				$order_data['shipping_custom_field'] = (isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array());

				if (isset($this->session->data['shipping_method']['title'])) {
					$order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
				} else {
					$order_data['shipping_method'] = '';
				}

				if (isset($this->session->data['shipping_method']['code'])) {
					$order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
				} else {
					$order_data['shipping_code'] = '';
				}
			} else {
				$order_data['shipping_firstname'] = '';
				$order_data['shipping_lastname'] = '';
				$order_data['shipping_company'] = '';
				$order_data['shipping_address_1'] = '';
				$order_data['shipping_address_2'] = '';
				$order_data['shipping_city'] = '';
				$order_data['shipping_postcode'] = '';
				$order_data['shipping_zone'] = '';
				$order_data['shipping_zone_id'] = '';
				$order_data['shipping_country'] = '';
				$order_data['shipping_country_id'] = '';
				$order_data['shipping_address_format'] = '';
				$order_data['shipping_custom_field'] = array();
				$order_data['shipping_method'] = '';
				$order_data['shipping_code'] = '';
			}

			$order_data['products'] = array();

			foreach ($this->cart->getProducts() as $product) {
				$option_data = array();

				foreach ($product['option'] as $option) {
					$option_data[] = array(
						'product_option_id' => $option['product_option_id'],
						'product_option_value_id' => $option['product_option_value_id'],
						'option_id' => $option['option_id'],
						'option_value_id' => $option['option_value_id'],
						'name' => $option['name'],
						'value' => $option['value'],
						'type' => $option['type']
					);
				}

				$order_data['products'][] = array(
					'product_id' => $product['product_id'],
					'master_id' => $product['master_id'],
					'name' => $product['name'],
					'model' => $product['model'],
					'option' => $option_data,
					'download' => $product['download'],
					'quantity' => $product['quantity'],
					'subtract' => $product['subtract'],
					'price' => $product['price'],
					'total' => $product['total'],
					'tax' => $this->tax->getTax($product['price'], $product['tax_class_id']),
					'reward' => $product['reward']
				);
			}

			// Gift Voucher
			$order_data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $voucher) {
					$order_data['vouchers'][] = array(
						'description' => $voucher['description'],
						'code' => token(10),
						'to_name' => $voucher['to_name'],
						'to_email' => $voucher['to_email'],
						'from_name' => $voucher['from_name'],
						'from_email' => $voucher['from_email'],
						'voucher_theme_id' => $voucher['voucher_theme_id'],
						'message' => $voucher['message'],
						'amount' => $voucher['amount']
					);
				}
			}

			$order_data['comment'] = $this->session->data['comment'];
			$order_data['total'] = $total;

			if (isset($this->request->cookie['tracking'])) {
				$order_data['tracking'] = $this->request->cookie['tracking'];

				$subtotal = $this->cart->getSubTotal();

				// Affiliate
				$this->load->model('account/affiliate');

				$affiliate_info = $this->model_account_affiliate->getAffiliateByTracking($this->request->cookie['tracking']);

				if ($affiliate_info) {
					$order_data['affiliate_id'] = $affiliate_info['customer_id'];
					$order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
				} else {
					$order_data['affiliate_id'] = 0;
					$order_data['commission'] = 0;
				}

				// Marketing
				$this->load->model('marketing/marketing');

				$marketing_info = $this->model_marketing_marketing->getMarketingByCode($this->request->cookie['tracking']);

				if ($marketing_info) {
					$order_data['marketing_id'] = $marketing_info['marketing_id'];
				} else {
					$order_data['marketing_id'] = 0;
				}
			} else {
				$order_data['affiliate_id'] = 0;
				$order_data['commission'] = 0;
				$order_data['marketing_id'] = 0;
				$order_data['tracking'] = '';
			}

			$order_data['language_id'] = $this->config->get('config_language_id');
			$order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
			$order_data['currency_code'] = $this->session->data['currency'];
			$order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
			$order_data['ip'] = $this->request->server['REMOTE_ADDR'];

			if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
				$order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
			} elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
				$order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
			} else {
				$order_data['forwarded_ip'] = '';
			}

			if (isset($this->request->server['HTTP_USER_AGENT'])) {
				$order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
			} else {
				$order_data['user_agent'] = '';
			}

			if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
				$order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
			} else {
				$order_data['accept_language'] = '';
			}

			$this->load->model('checkout/order');

			$this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);

			$this->load->model('tool/upload');

			$frequencies = array(
				'day' => $this->language->get('text_day'),
				'week' => $this->language->get('text_week'),
				'semi_month' => $this->language->get('text_semi_month'),
				'month' => $this->language->get('text_month'),
				'year' => $this->language->get('text_year'),
			);

			$data['products'] = array();

			foreach ($this->cart->getProducts() as $product) {
				$option_data = array();

				foreach ($product['option'] as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name' => $option['name'],
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				$recurring = '';

				if ($product['recurring']) {
					if ($product['recurring']['trial']) {
						$recurring = sprintf($this->language->get('text_trial_description'), $this->currency->format($this->tax->calculate($product['recurring']['trial_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['trial_cycle'], $frequencies[$product['recurring']['trial_frequency']], $product['recurring']['trial_duration']) . ' ';
					}

					if ($product['recurring']['duration']) {
						$recurring .= sprintf($this->language->get('text_payment_description'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
					} else {
						$recurring .= sprintf($this->language->get('text_payment_cancel'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
					}
				}

				$data['products'][] = array(
					'cart_id' => $product['cart_id'],
					'product_id' => $product['product_id'],
					'name' => $product['name'],
					'model' => $product['model'],
					'option' => $option_data,
					'recurring' => $recurring,
					'quantity' => $product['quantity'],
					'subtract' => $product['subtract'],
					'price' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
					'total' => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
					'href' => $this->url->link('product/product', 'language=' . $this->config->get('config_language') . '&product_id=' . $product['product_id'])
				);
			}

			// Gift Voucher
			$data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $voucher) {
					$data['vouchers'][] = array(
						'description' => $voucher['description'],
						'amount' => $this->currency->format($voucher['amount'], $this->session->data['currency'])
					);
				}
			}

			$data['totals'] = array();
			
			$index = 0;
			foreach ($totals as $total) {
				if($index == 2) {
					$data['totals'][] = array(
						'title' => 'Total Tax',
						'text' => $this->currency->format($total['value'], $this->session->data['currency'])
					);
				} else {
					$data['totals'][] = array(
						'title' => $total['title'],
						'text' => $this->currency->format($total['value'], $this->session->data['currency'])
					);
				}
				$index++;
			}

			$data['payment'] = $this->load->controller('extension/payment/' . $this->session->data['payment_method']['code']);
		} else {
			$data['redirect'] = str_replace('&amp;', '&', $redirect);
		}

		$this->response->setOutput($this->load->view('checkout/confirm', $data));
	}
}
