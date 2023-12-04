<?php

defined('ABSPATH') or exit;
// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
	return;
}


/**
 * Adds plugin page links
 *
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 * @since 1.0.0
 */
function bulupay_wc_special_gateway_plugin_links($links)
{
	$plugin_links = array(
		'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=bulupay_payment_gateway') . '">' . __('Configure', 'wcpg-special') . '</a>'
	);
	return array_merge($plugin_links, $links);
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bulupay_wc_special_gateway_plugin_links');
/**
 * Custom Payment Gateway
 *
 * Provides an Custom Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       WC_Gateway_Special
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      Me
 */
add_action('plugins_loaded', 'init_bulupay_gateway_class');


function init_bulupay_gateway_class()
{
	if (!class_exists('WC_Payment_Gateway')) return;

	class WC_BuluPay_Payment_Gateway extends WC_Payment_Gateway
	{

		/**
		 * Gateway instructions that will be added to the thank you page and emails.
		 *
		 * @var string
		 */
		public $instructions;
		public $bulupay_api_access_token;
		public $bulupay_api_gateway_token;


		public function supports($feature)
		{
			if ('block' === $feature) {
				return true;
			}
			return parent::supports($feature);
		}

		/**
		 * Constructor for the gateway.
		 */
		public function __construct()
		{

			$this->id = 'bulupay_gateway';
			// $this->supports = array('products');
			$this->domain = 'woocommerce';
			$this->icon = apply_filters('woocommerce_payment_gateway_icon', '');
			$this->has_fields = false;
			$this->method_title = _x('BuluPay Payment Gateway', 'Check payment method', 'woocommerce');
			$this->method_description = __('Add a custom payment gateway to WooCommerce', 'woocommerce');


			// Define "payment type" radio buttons options field
			$this->options = array(
				'type1' => __('Type 1', $this->domain),
				'type2' => __('Type 2', $this->domain),
				'type3' => __('Type 3', $this->domain),
			);

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables.
			$this->title = $this->get_option('title');
			$this->description = $this->get_option('description');
			$this->instructions = $this->get_option('instructions');
			$this->bulupay_api_access_token = $this->get_option('bulupay_api_access_token');
			$this->bulupay_api_gateway_token = $this->get_option('bulupay_api_gateway_token');
			$this->enabled = $this->get_option('enabled');

			// Actions.
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

			// Customer Emails.
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);

			// Actions
			add_action('woocommerce_checkout_create_order', array($this, 'save_order_payment_type_meta_data'), 10, 2);
			add_filter('woocommerce_get_order_item_totals', array($this, 'display_transaction_type_order_item_totals'), 10, 3);
			add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_payment_type_order_edit_pages'), 10, 1);
			add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

			// Customer Emails
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);


			// Additional actions for block-based checkout
			/*   add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
			   add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));*/

			add_action('woocommerce_api_bulupay_payment_complete_callback', array($this, 'payment_callback_webhook'));


		}

		public function payment_callback_webhook()
		{
			$order = wc_get_order($_GET['id']);
			$order->payment_complete();
			$order->reduce_order_stock();

			update_option('webhook_debug', $_GET);


			$red = $this->get_return_url($order);
			wp_redirect($red);
			exit();

			/*return array(
				'result'    => 'success',
				'redirect'  => $this->get_return_url( $order )
			);*/


		}


		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields()
		{

			$this->form_fields =
				apply_filters('bulupay_gateway_form_fields', array(
					'enabled' => array(
						'title' => __('Enable/Disable', 'woocommerce'),
						'type' => 'checkbox',
						'label' => __('Enable check payments', 'woocommerce'),
						'default' => 'no',
					),
					'title' => array(
						'title' => __('Title', $this->domain),
						'type' => 'text',
						'description' => __('This controls the title for the payment method the customer sees during checkout.', $this->domain),
						'default' => __('Special Payment', $this->domain),
						'desc_tip' => true,
					),
					'description' => array(
						'title' => __('Description', $this->domain),
						'type' => 'textarea',
						'description' => __('Payment method description that the customer will see on your checkout.', $this->domain),
						'default' => __('Please remit payment to Store Name upon pickup or delivery.', $this->domain),
						'desc_tip' => true,
					),
					'instructions' => array(
						'title' => __('Instructions', $this->domain),
						'type' => 'textarea',
						'description' => __('Instructions that will be added to the thank you page and emails.', $this->domain),
						'default' => '', // Empty by default
						'desc_tip' => true,
					),
					'bulupay_api_access_token' => array(
						'title' => __('BuluPay Access Token', $this->domain),
						'type' => 'textarea',
						'description' => __('BuluPay Access Api Token, you can Get it from <a href="https://dash.bulupay.com/admin/users">Your Profile</a>', $this->domain),
						'default' => '', // Empty by default
						'desc_tip' => true,
					),
					'bulupay_api_gateway_token' => array(
						'title' => __('BuluPay Gateway Token', $this->domain),
						'type' => 'textarea',
						'description' => __('BuluPay Gateway Token, you can Get it from <a href="https://dash.bulupay.com/admin/content/Gateway">My Gateways</a>', $this->domain),
						'default' => '', // Empty by default
						'desc_tip' => true,
					)
				));


		}

		/**
		 * Output the "payment type" radio buttons fields in checkout.
		 */
		public function payment_fields()
		{
			if ($description = $this->get_description()) {
				echo wpautop(wptexturize($description));
			}

			echo '<style>#transaction_type_field label.radio { display:inline-block; margin:0 .8em 0 .4em}</style>';

			$option_keys = array_keys($this->options);

			woocommerce_form_field('transaction_type', array(
				'type' => 'radio',
				'class' => array('transaction_type form-row-wide'),
				'label' => __('Payment Information', $this->domain),
				'options' => $this->options,
			), reset($option_keys));
		}


		/**
		 * Save the chosen payment type as order meta data.
		 *
		 * @param object $order
		 * @param array $data
		 */
		public function save_order_payment_type_meta_data($order, $data)
		{
			if ($data['payment_method'] === $this->id && isset($_POST['transaction_type']))
				$order->update_meta_data('_transaction_type', esc_attr($_POST['transaction_type']));
		}

		/**
		 * Output for the order received page.
		 */
		public function thankyou_page()
		{
			if ($this->instructions) {
				echo wp_kses_post(wpautop(wptexturize($this->instructions)));
			}
		}

		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order Order object.
		 * @param bool $sent_to_admin Sent to admin.
		 * @param bool $plain_text Email format: plain text or HTML.
		 */
		public function email_instructions($order, $sent_to_admin, $plain_text = false)
		{
			/**
			 * Filter the email instructions order status.
			 *
			 * @param string $terms The order status.
			 * @param object $order The order object.
			 * @since 7.4
			 */
			/*  if ( $this->instructions && ! $sent_to_admin && 'cheque' === $order->get_payment_method() && $order->has_status( apply_filters( 'woocommerce_cheque_email_instructions_order_status', 'on-hold', $order ) ) ) {
				  echo wp_kses_post(bulupay - peyment - method . phpwpautop(wptexturize($this->instructions)) . PHP_EOL);
			  }*/
		}

		/**
		 * Display the chosen payment type on the order edit pages (backend)
		 *
		 * @param object $order
		 */
		public function display_payment_type_order_edit_pages($order)
		{
			if ($this->id === $order->get_payment_method() && $order->get_meta('_transaction_type')) {
				$options = $this->options;
				echo '<p><strong>' . __('Transaction type') . ':</strong> ' . $options[$order->get_meta('_transaction_type')] . '</p>';
			}
		}

		/**
		 * Display the chosen payment type on order totals table
		 *
		 * @param array $total_rows
		 * @param WC_Order $order
		 * @param bool $tax_display
		 * @return array
		 */
		public function display_transaction_type_order_item_totals($total_rows, $order, $tax_display)
		{
			if (is_a($order, 'WC_Order') && $order->get_meta('_transaction_type')) {
				$new_rows = []; // Initializing
				$options = $this->options;

				// Loop through order total lines
				foreach ($total_rows as $total_key => $total_values) {
					$new_rows[$total_key] = $total_values;
					if ($total_key === 'payment_method') {
						$new_rows['payment_type'] = [
							'label' => __("Transaction type", $this->domain) . ':',
							'value' => $options[$order->get_meta('_transaction_type')],
						];
					}
				}

				$total_rows = $new_rows;
			}
			return $total_rows;
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id Order ID.
		 * @return array
		 */
		public function process_payment($order_id)
		{

			global $woocommerce;

			// we need it to get any order detailes
			$order = wc_get_order($order_id);


			/*
			  * Array with parameters for API interaction
			 */


			$order->add_order_note('Hey, your order payment is received! Thank you for your order!', true);

			// Empty cart
			//$woocommerce->cart->empty_cart();


			$bulupay_api_access_token = $this->get_option('bulupay_api_access_token');
			$args = array(
				'method' => 'POST',
				'body' => array(
					'bulupay_api_key' => $bulupay_api_access_token
				)
			);

			$response = wp_remote_post('http://localhost/payiran/wp-json/bulupay_gen/token', $args);

			if (!is_wp_error($response)) {

				$body = json_decode($response['body'], true);

				// it could be different depending on your payment processor
				if (isset($body['temp_api_key'])) {


					// some notes to customer (replace true with false to make it private)
					$order->add_order_note('Hey, your order payment is received! Thank you for your order!', true);


					// The key and value for the hidden metadata
					$meta_key = 'temp_api_key';
					$meta_value = $body['temp_api_key'];

					// Add the hidden metadata to the order
					update_post_meta($order_id, $meta_key, $meta_value);


					// -------------------------------------------------------------------------
					// ------------------------------------------------------------------------- redirect

					$base_url = 'http://localhost/payiran/order';

					$bulupay_api_gateway_token = $this->get_option('bulupay_api_gateway_token');


					$description_ = $this->description;
					if (is_null($description_)) {
						$description_ = "";
					}
					if (strlen($this->description) > 30) {
						$description_ = substr($description_, 0, 30);
					}

					$params = array(
						'order_id' => $order_id,
						'amount' => $order->get_total(),
						'name' => $order->get_title(),
						'payer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
						'phone' => $order->get_billing_phone(),
						'mail' => $order->get_billing_email(),
						'desc' => $description_,
//'callback'=>"http://localhost/tpay/wc-api/bulupay_payment_complete_callback?id=",
						'callback' => "http://localhost/tpay/wc-api/bulupay_payment_complete_callback",
						'gateway_token' => $bulupay_api_gateway_token,
						'access_token' => $meta_value,
					);

					$url_with_params = add_query_arg($params, $base_url);

					return array(
						'result' => 'success',
						//	'redirect' =>"https://localhost:44364/Home/NewOrder?orderId=" . $order_id
						'redirect' => $url_with_params
						//apply_filters('process_payment_redirect', $order->get_checkout_payment_url(true), $order), // web page redirect

						//'redirect'  => "https://localhost:44364/Home/NewOrder?orderId=" + $order_id //     $this->get_return_url( $order )
					);
					// ------------------------------------------------------------------------- redirect end
					// -------------------------------------------------------------------------


				} else {
					wc_add_notice('Please try again.', 'error');
					return;
				}

			} else {
				wc_add_notice('Connection error.', 'error');
				return;
			}

			/*
						return array(
							'result'    => 'success',
							//	'redirect' =>"https://localhost:44364/Home/NewOrder?orderId=" . $order_id
							'redirect' =>"http://localhost/payiran/order?orderId=" . $order_id
							//apply_filters('process_payment_redirect', $order->get_checkout_payment_url(true), $order), // web page redirect

							//'redirect'  => "https://localhost:44364/Home/NewOrder?orderId=" + $order_id //     $this->get_return_url( $order )
						);*/

			/*
			 * Your API interaction could be built with wp_remote_post()
			  */
			/*
			 * $response = wp_remote_post( '{payment processor endpoint}', $args );


			if( !is_wp_error( $response ) ) {

				$body = json_decode( $response['body'], true );

				// it could be different depending on your payment processor
				if ( $body['response']['responseCode'] == 'APPROVED' ) {

					// we received the payment
					$order->payment_complete();
					$order->reduce_order_stock();

					// some notes to customer (replace true with false to make it private)
					$order->add_order_note( 'Hey, your order payment is received! Thank you for your order!', true );

					// Empty cart
					$woocommerce->cart->empty_cart();

					// Redirect to the thank you page
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);

				} else {
					wc_add_notice(  'Please try again.', 'error' );
					return;
				}

			} else {
				wc_add_notice(  'Connection error.', 'error' );
				return;
			}*/

			/*    $order = wc_get_order( $order_id );

				// Mark as on-hold (we're awaiting the payment)
				$order->update_status( $this->order_status, $this->status_text );

				// Reduce stock levels
				wc_reduce_stock_levels( $order->get_id() );

				// Remove cart
				WC()->cart->empty_cart();

				// Return thankyou redirect
				return array(
					'result'    => 'success',
					'redirect'  => $this->get_return_url( $order )
				);*/
		}
	}


	function bulupay_add_custom_payment_gateway($methods)
	{
		$methods[] = 'WC_BuluPay_Payment_Gateway';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'bulupay_add_custom_payment_gateway');

}


