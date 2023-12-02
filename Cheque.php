<?php
namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

use Exception;
use Automattic\WooCommerce\Blocks\Assets\Api;

/**
 * Cheque payment method integration
 *
 * @since 2.6.0
 */
final class CustomCheque extends AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'CustomCheque';

	/**
	 * An instance of the Asset Api
	 *
	 * @var Api
	 */
	private $asset_api;

	/**
	 * Constructor
	 *
	 * @param Api $asset_api An instance of Api.
	 */
	public function __construct(  ) {
	//	$this->asset_api = $asset_api;
	}

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_cheque_settings', [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		$res= filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
		$res=true;
		return $res;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		/*$this->asset_api->register_script(
			'cheque-block',
			'build/cheque-block.js'
		);*/

		$asset_path   = WC_TEST_PLUGIN_PATH . '/build/index.asset.php';
		$version      = '0.1';
		$dependencies = [];
		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}
		wp_register_script(
			'cheque-block',
			WC_TEST_PLUGIN_URL . '/build/index.js',
			$dependencies,
			$version,
			true
		);
		/*wp_set_script_translations(
			'cheque-block',
			'woocommerce-gateway-stripe'
		);*/


		return [ 'cheque-block' ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
		];
	}
}
