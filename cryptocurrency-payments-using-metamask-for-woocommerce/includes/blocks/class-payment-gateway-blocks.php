<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * cpmw Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_cpmw_Gateway_Blocks_Support extends AbstractPaymentMethodType {

	use CPMW_HELPER;

	/**
	 * The gateway instance.
	 *
	 * @var WC_cpmw_Gateway
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'cpmw';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_cpmw_settings', array() );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$block_dir          = CPMW_PATH . 'assets/pay-with-metamask/build/block';
		$filePaths          = glob( $block_dir . '/*.php' );
		$jsbuildUrl         = '';
		if ( ! empty( $filePaths ) ) {
			$first_file_real = realpath( $filePaths[0] );
			$block_dir_real  = realpath( $block_dir );
			if ( $first_file_real && $block_dir_real && strpos( $first_file_real, $block_dir_real ) === 0 ) {
				$fileName  = pathinfo( $first_file_real, PATHINFO_FILENAME );
				$jsbuildUrl = sanitize_file_name( str_replace( '.asset', '', $fileName ) );
			}
		}
		// Fallback if we couldn't determine a build file
		if ( empty( $jsbuildUrl ) ) {
			return array();
		}
		$script_path       = 'assets/pay-with-metamask/build/block/' . $jsbuildUrl . '.js';
		$script_asset_path = CPMW_PATH . 'assets/pay-with-metamask/build/block/' . $jsbuildUrl . '.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => CPMW_VERSION,
			);
		$script_url        = CPMW_URL . $script_path;

		wp_register_script(
			'wc-cpmw-payments-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_enqueue_style( 'cpmw-checkout', CPMW_URL . 'assets/css/checkout.css', null, CPMW_VERSION );
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-cpmw-payments-blocks', 'woocommerce-gateway-cpmw', CPMW_PATH . 'languages/' );
		}

		return array( 'wc-cpmw-payments-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		 // Get plugin options
		$options = (array) get_option( 'cpmw_settings', array() );

		// Enqueue necessary styles
		wp_enqueue_style( 'cpmw_checkout', CPMW_URL . 'assets/css/checkout.css', array(), CPMW_VERSION );

		// Get user wallet settings
		$user_wallet = isset( $options['user_wallet'] ) ? sanitize_text_field( $options['user_wallet'] ) : '';

		// Get currency options
		$bnb_currency = isset( $options['bnb_select_currency'] ) ? (array) $options['bnb_select_currency'] : array();
		$eth_currency = isset( $options['eth_select_currency'] ) ? (array) $options['eth_select_currency'] : array();

		// Get currency conversion API options
		$compare_key     = isset( $options['crypto_compare_key'] ) ? sanitize_text_field( $options['crypto_compare_key'] ) : '';
		$openex_key      = isset( $options['openexchangerates_key'] ) ? sanitize_text_field( $options['openexchangerates_key'] ) : '';
		$select_currecny = isset( $options['currency_conversion_api'] ) ? sanitize_text_field( $options['currency_conversion_api'] ) : '';
		$const_msg       = $this->cpmw_const_messages();

		// Get supported network names
		$network_name = $this->cpmw_supported_networks();

		// Get selected network
		$get_network = isset( $options['Chain_network'] ) ? sanitize_text_field( $options['Chain_network'] ) : '';

		// Get constant messages

		// Determine crypto currency based on network
		$crypto_currency     = in_array( $get_network, array( '0x1', '0x5', '0xaa36a7' ), true ) ? $eth_currency : $bnb_currency;
		$select_currency_lbl = ( isset( $options['select_a_currency'] ) && ! empty( $options['select_a_currency'] ) ) ? sanitize_text_field( $options['select_a_currency'] ) : __( 'Please Select a Currency', 'cpmwp' );
		// Get type and total price
		$type            = $select_currecny;
		$logo_url        = esc_url_raw( CPMW_URL . 'assets/images/metamask.png' );
		$total_price     = ( isset( WC()->cart->subtotal ) && is_numeric( WC()->cart->subtotal ) ) ? (float) WC()->cart->subtotal : 0.0;
		$enabledCurrency = array();
		$error           = '';
		if ( is_array( $crypto_currency ) ) {
			foreach ( $crypto_currency as $key => $value ) {
				$symbol = sanitize_text_field( $value );
				// Get coin logo image URL
				$image_url = esc_url_raw( $this->cpmw_get_coin_logo( $symbol ) );
				// Perform price conversion
				$in_crypto = $this->cpmw_price_conversion( $total_price, $symbol, $type );
				if ( isset( $in_crypto['restricted'] ) ) {
					$error = sanitize_text_field( $in_crypto['restricted'] );
					break; // Exit the loop if the API is restricted.
				}
				if ( isset( $in_crypto['error'] ) ) {
					$error = sanitize_text_field( $in_crypto['error'] );
					break; // Exit the loop if the API is restricted.
				}
				$enabledCurrency[ $symbol ] = array(
					'symbol' => $symbol,
					'price'  => $in_crypto,
					'url'    => $image_url,
				);
			}
		}

		// Define network to RPC URL mapping
		$network_rpc_map = [
			'0x1' => 'eth_rpc_url',
			'0x38' => 'bsc_rpc_url',
			'0x61' => 'bsc_testnet_rpc_url',
			'0xaa36a7' => 'sepolia_rpc_url'
		];

		$rpc_url = ( isset( $network_rpc_map[ $get_network ] ) && isset( $options[ $network_rpc_map[ $get_network ] ] ) ) ? esc_url_raw( $options[ $network_rpc_map[ $get_network ] ] ) : '';
		$network_name_value = isset( $network_name[ $get_network ] ) ? sanitize_text_field( $network_name[ $get_network ] ) : '';

		return array(
			'title'             => ! empty( $this->get_setting( 'title' ) ) ? sanitize_text_field( $this->get_setting( 'title' ) ) : __( 'Pay With Cryptocurrency', 'cpmw' ),
			'description'       => wp_kses_post( $this->get_setting( 'custom_description' ) ),
			'supports'          => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'total_price'       => $total_price,
			'error'             => $error,
			'api_type'          => $type,
			'logo_url'          => $logo_url,
			'decimalchainId'    => $get_network ? hexdec( $get_network ) : false,
			'active_network'    => $get_network ? $get_network : false,
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'restUrl'           => esc_url_raw( get_rest_url() . 'pay-with-metamask/v1/' ),
			'currency_lbl'      => $select_currency_lbl,
			'const_msg'         => $const_msg,
			'networkName'       => $network_name_value,
			'enabledCurrency'   => $enabledCurrency,
			'rpcUrl'            => $rpc_url,
			'order_button_text' => ( isset( $options['place_order_button'] ) && ! empty( $options['place_order_button'] ) ) ? sanitize_text_field( $options['place_order_button'] ) : __( 'Pay With Crypto Wallets', 'cpmw' ),

		);
	}
}
