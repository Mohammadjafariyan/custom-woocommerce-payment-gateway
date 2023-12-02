<?php

defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}



/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function bulupay_wc_special_gateway_plugin_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=bulupay_payment_gateway' ) . '">' . __( 'Configure', 'wcpg-special' ) . '</a>'
    );
    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bulupay_wc_special_gateway_plugin_links' );
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
add_action( 'plugins_loaded', 'init_bulupay_gateway_class' );


function init_bulupay_gateway_class() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_BuluPay_Payment_Gateway extends WC_Payment_Gateway {

        /**
         * Gateway instructions that will be added to the thank you page and emails.
         *
         * @var string
         */
        public $instructions;



		public function supports($feature) {
			if ('block' === $feature) {
				return true;
			}
			return parent::supports($feature);
		}

		/**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->id = 'CustomCheque';
            // $this->supports = array('products');
            $this->domain='woocommerce';
            $this->icon               = apply_filters('woocommerce_payment_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = _x( 'BuluPay Payment Gateway', 'Check payment method', 'woocommerce' );
            $this->method_description = __( 'Add a custom payment gateway to WooCommerce', 'woocommerce' );


            // Define "payment type" radio buttons options field
            $this->options = array(
                'type1' => __( 'Type 1', $this->domain ),
                'type2' => __( 'Type 2', $this->domain ),
                'type3' => __( 'Type 3', $this->domain ),
            );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions' );
            $this->enabled = $this->get_option( 'enabled' );

            // Actions.
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Customer Emails.
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

            // Actions
            add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_payment_type_meta_data' ), 10, 2 );
            add_filter( 'woocommerce_get_order_item_totals', array( $this, 'display_transaction_type_order_item_totals'), 10, 3 );
            add_action( 'woocommerce_admin_order_data_after_billing_address',  array( $this, 'display_payment_type_order_edit_pages'), 10, 1 );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            // Customer Emails
            add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );


            // Additional actions for block-based checkout
         /*   add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));*/


        }




        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {

            $this->form_fields =
                apply_filters( 'wc_special_payment_form_fields', array(
                    'enabled'      => array(
                        'title'   => __( 'Enable/Disable', 'woocommerce' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Enable check payments', 'woocommerce' ),
                        'default' => 'no',
                    ),
                    'title' => array(
                        'title'       => __( 'Title', $this->domain ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title for the payment method the customer sees during checkout.', $this->domain ),
                        'default'     => __( 'Special Payment', $this->domain ),
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => __( 'Description', $this->domain ),
                        'type'        => 'textarea',
                        'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
                        'default'     => __( 'Please remit payment to Store Name upon pickup or delivery.', $this->domain ),
                        'desc_tip'    => true,
                    ),
                    'instructions' => array(
                        'title'       => __( 'Instructions', $this->domain ),
                        'type'        => 'textarea',
                        'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
                        'default'     => '', // Empty by default
                        'desc_tip'    => true,
                    )
                ) );


        }
        /**
         * Output the "payment type" radio buttons fields in checkout.
         */
        public function payment_fields(){
            if ( $description = $this->get_description() ) {
                echo wpautop( wptexturize( $description ) );
            }

            echo '<style>#transaction_type_field label.radio { display:inline-block; margin:0 .8em 0 .4em}</style>';

            $option_keys = array_keys($this->options);

            woocommerce_form_field( 'transaction_type', array(
                'type'          => 'radio',
                'class'         => array('transaction_type form-row-wide'),
                'label'         => __('Payment Information', $this->domain),
                'options'       => $this->options,
            ), reset( $option_keys ) );
        }


        /**
         * Save the chosen payment type as order meta data.
         *
         * @param object $order
         * @param array $data
         */
        public function save_order_payment_type_meta_data( $order, $data ) {
            if ( $data['payment_method'] === $this->id && isset($_POST['transaction_type']) )
                $order->update_meta_data('_transaction_type', esc_attr($_POST['transaction_type']) );
        }
        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions ) {
                echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
            }
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order Order object.
         * @param bool     $sent_to_admin Sent to admin.
         * @param bool     $plain_text Email format: plain text or HTML.
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
            /**
             * Filter the email instructions order status.
             *
             * @since 7.4
             * @param string $terms The order status.
             * @param object $order The order object.
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
        public function display_payment_type_order_edit_pages( $order ){
            if( $this->id === $order->get_payment_method() && $order->get_meta('_transaction_type') ) {
                $options  = $this->options;
                echo '<p><strong>'.__('Transaction type').':</strong> ' . $options[$order->get_meta('_transaction_type')] . '</p>';
            }
        }

        /**
         * Display the chosen payment type on order totals table
         *
         * @param array    $total_rows
         * @param WC_Order $order
         * @param bool     $tax_display
         * @return array
         */
        public function display_transaction_type_order_item_totals( $total_rows, $order, $tax_display ){
            if( is_a( $order, 'WC_Order' ) && $order->get_meta('_transaction_type') ) {
                $new_rows = []; // Initializing
                $options  = $this->options;

                // Loop through order total lines
                foreach( $total_rows as $total_key => $total_values ) {
                    $new_rows[$total_key] = $total_values;
                    if( $total_key === 'payment_method' ) {
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
        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

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
            );
        }
    }


    function bulupay_add_custom_payment_gateway($methods) {
        $methods[] = 'WC_BuluPay_Payment_Gateway';
        return $methods;
    }
    add_filter( 'woocommerce_payment_gateways', 'bulupay_add_custom_payment_gateway' );

}


