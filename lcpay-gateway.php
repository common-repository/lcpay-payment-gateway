<?php
/*
 * Plugin Name: LCPay Payment Gateway
 * Plugin URI: https://guide.lcpay.my/product-catalogue
 * Description:LCPay payment gateway plugin
 * Author: LCPay SDN BHD
 * Author URI:
 * Version: 1.0.1
 */

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'lcpay_add_gateway_class' );

function lcpay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_LCPay_Gateway'; // your class name is here
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'lcpay_init_gateway_class' );

function lcpay_init_gateway_class() {

	class WC_LCPay_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {

			
	$this->id = 'lcpay'; // payment gateway plugin ID
	$this->icon = 'https://guide.lcpay.my/img/LCpay_logo.3ece3d4f.png'; // URL of the icon that will be displayed on checkout page near your gateway name
	$this->has_fields = true; // in case you need a custom credit card form
	$this->method_title = 'LCPay Gateway';
	$this->method_description = 'LCPay payment gateway plugin'; // will be displayed on the options page

	// gateways can support subscriptions, refunds, saved payment methods,
	// but in this tutorial we begin with simple payments
	$this->supports = array(
		'products'
	);

	// Method with all the options fields
	$this->init_form_fields();

	// Load the settings.
	$this->init_settings();
	$this->title = $this->get_option( 'title' );
	$this->description = $this->get_option( 'description' );
	$this->enabled = $this->get_option( 'enabled' );
	$this->private_key =  $this->get_option( 'private_key' );
	$this->merchant_id = $this->get_option('merchant_id');

	// This action hook saves the settings
	add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	
	
	// You can also register a webhook here
	add_action( 'woocommerce_api_lccallback',  array($this,'webhook'));

 		}

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){

		$this->form_fields = array(
			'enabled' => array(
				'title'       => 'Enable/Disable',
				'label'       => 'Enable LCPay Gateway',
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => 'Title',
				'type'        => 'text',
				'description' => 'This controls the title which the user sees during checkout.',
				'default'     => 'LCPay',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => 'Description',
				'type'        => 'textarea',
				'description' => 'This controls the description which the user sees during checkout.',
				'default'     => 'Pay with your credit card and FPX',
			),
			
			'private_key' => array(
				'title'       => 'Live Private Key',
				'type'        => 'text'
			),
			'merchant_id' => array(
				'title'       => 'Merchant ID',
				'type'        => 'text'
			)
		);
	
	 	}

		
		

		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			
			$products = array();
			$buyer =  $order->get_user();

			foreach ($order->get_items() as $item_key => $item ):
				$item_name    = $item->get_name(); 
				$quantity     = $item->get_quantity();  

				$product = new \stdClass;
				$product->name = $item_name;
				$product->quantity= floatval($quantity);
				$item_data    = $item->get_data();
				$product->price = floatval($item_data['total']);
				array_push($products,$product);
			endforeach;

			$object = new \stdClass;
			$object->merchant_id = $this->merchant_id;
			$object->detail=$products;
			$object->amount=$order->get_total();
			$object->order_id = $order->get_order_number();
			
			$object->name =  $order->get_billing_first_name().$order->get_billing_last_name();
			$object->email =sanitize_email($order->get_billing_email());
			$object->phone =$order->get_billing_phone();

			
			
		
			$hash = hash("sha256",$this->private_key.'|'.json_encode($object->detail).'|'.$object->amount.'|'.$object->order_id);
			$object->hash =$hash;
			$encoded = base64_encode(json_encode($object));
		

			//use this if you need to redirect the user to the payment page of the bank.
			$querystring = http_build_query( $encoded);
			return array(
							'result'   => 'success',
							'redirect' => "https://payment.lcpay.my/integration/"  . $encoded,
						);
					
	 	}

		
		public function webhook() {
			$order = wc_get_order( sanitize_text_field($_GET['id']) );
			$status = intval($_GET['p']);
			if($status == 0){

			}
			if($status == 1){
				$order->payment_complete();
				$order->reduce_order_stock();
			}
			

			
		
			update_option('webhook_debug', $_GET);
					
	 	}
 	}
}