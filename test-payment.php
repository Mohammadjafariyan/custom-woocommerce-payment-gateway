<?php
/**
 * Plugin Name:       Test Payment
 * Description:       Example block scaffolded with Create Block tool.
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       test-payment
 *
 * @package           create-block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
/*function test_payment_test_payment_block_init() {
	register_block_type( __DIR__ . '/build' );
}
add_action( 'init', 'test_payment_test_payment_block_init' );*/


define( 'WC_TEST_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_TEST_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );


use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

add_action( 'woocommerce_blocks_loaded', 'my_extension_woocommerce_blocks_support' );


include_once "register-payment-gateway.php";

function my_extension_woocommerce_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once __DIR__ . '/Cheque.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( PaymentMethodRegistry $payment_method_registry ) {

				$container = Automattic\WooCommerce\Blocks\Package::container();

				// registers as shared instance.
				$container->register(
					\Automattic\WooCommerce\Blocks\Payments\Integrations\bulupay_gateway::class,
					function() {
						return new \Automattic\WooCommerce\Blocks\Payments\Integrations\bulupay_gateway();
					}
				);

				$payment_method_registry->register(
					$container->get( \Automattic\WooCommerce\Blocks\Payments\Integrations\bulupay_gateway::class )
				);

				//$payment_method_registry->register( new \Automattic\WooCommerce\Blocks\Payments\Integrations\bulupay_gateway );
			}
		);
	}
}
