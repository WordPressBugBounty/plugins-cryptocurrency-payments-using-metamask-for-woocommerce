<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get plugin options
$options = (array) get_option( 'cpmw_settings', array() );

// Enqueue necessary styles
wp_enqueue_style( 'cpmw_checkout', CPMW_URL . 'assets/css/checkout.css', array(), CPMW_VERSION );
// Trigger WooCommerce action to start the form
do_action( 'woocommerce_cpmw_form_start', $this->id );

// Get Metamask settings link
$cpmw_settings = admin_url() . 'admin.php?page=cpmw-metamask-settings';

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
// Generate settings link HTML for admin
$link_html = ( current_user_can( 'manage_options' ) ) ?
'<a href="' . esc_url( $cpmw_settings ) . '" target="_blank" rel="noopener noreferrer">' .
esc_html__( 'Click here', 'cpmw' ) . '</a> ' . esc_html__( 'to open settings', 'cpmw' ) : '';

// Check for various conditions
if ( empty( $user_wallet ) ) {
	echo '<strong>' . esc_html( $const_msg['metamask_address'] ) . wp_kses_post( $link_html ) . '</strong>';
	return false;
}
if ( ! empty( $user_wallet ) && strlen( $user_wallet ) != '42' ) {
	echo '<strong>' . esc_html( $const_msg['valid_wallet_address'] ) . wp_kses_post( $link_html ) . '</strong>';
	return false;
}
if ( $select_currecny == 'cryptocompare' && empty( $compare_key ) ) {
	echo '<strong>' . esc_html( $const_msg['required_fiat_key'] ) . wp_kses_post( $link_html ) . '</strong>';
	return false;
}
if ( empty( $bnb_currency ) || empty( $eth_currency ) ) {
	echo '<strong>' . esc_html( $const_msg['required_currency'] ) . wp_kses_post( $link_html ) . '</strong>';
	return false;
}

// Securely construct and validate the asset path to prevent path traversal.
$jsbuildUrl         = '';
$checkout_asset_dir = realpath( CPMW_PATH . '/assets/pay-with-metamask/build/checkout' );

// Ensure the directory exists and is within the plugin's folder.
if ( $checkout_asset_dir && strpos( $checkout_asset_dir, realpath( CPMW_PATH ) ) === 0 ) {
	// Use glob to get an array of file names in the folder.
	$filePaths = glob( $checkout_asset_dir . '/*.php' );

	// Ensure we found a file and it's the correct asset file.
	if ( ! empty( $filePaths ) ) {
		$first_file = realpath( $filePaths[0] );
		if ( $first_file && strpos( $first_file, $checkout_asset_dir ) === 0 ) {
			$fileName   = pathinfo( $filePaths[0], PATHINFO_FILENAME );
			$jsbuildUrl = str_replace( '.asset', '', $fileName );
		}
	}
}

// Stop if the build file is not found, and notify admin.
if ( empty( $jsbuildUrl ) ) {
	if ( current_user_can( 'manage_options' ) ) {
		echo '<strong>' . esc_html__( 'Error: Checkout asset file not found. Please run the build process.', 'cpmw' ) . '</strong>';
	}
	return;
}

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
$total_price     = $this->get_order_total();
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
			$error = $in_crypto['restricted'];
			break; // Exit the loop if the API is restricted.
		}
		if ( isset( $in_crypto['error'] ) ) {
			$error = $in_crypto['error'];
			break; // Exit the loop if the API is restricted.
		}
    $enabledCurrency[ $symbol ] = array(
        'symbol' => $symbol,
			'price'  => $in_crypto,
			'url'    => $image_url,
		);
	}
}
// Enqueue the connect wallet script
// Ensure safe file name for enqueued script
$jsbuildUrl = sanitize_file_name( $jsbuildUrl );
wp_enqueue_script( 'cpmw_connect_wallet', CPMW_URL . 'assets/pay-with-metamask/build/checkout/' . $jsbuildUrl . '.js', array( 'wp-element' ), CPMW_VERSION, true );

$network_rpc_map = [
	'0x1' => 'eth_rpc_url',
	'0x38' => 'bsc_rpc_url',
	'0x61' => 'bsc_testnet_rpc_url',
	'0xaa36a7' => 'sepolia_rpc_url'
];

$rpc_url = ( isset( $network_rpc_map[ $get_network ] ) && isset( $options[ $network_rpc_map[ $get_network ] ] ) ) ? esc_url_raw( $options[ $network_rpc_map[ $get_network ] ] ) : '';

// Sanitize network name for client use
$network_name_value = isset( $network_name[ $get_network ] ) ? sanitize_text_field( $network_name[ $get_network ] ) : '';

// Localize the connect wallet script with required data
wp_localize_script(
	'cpmw_connect_wallet',
	'connect_wallts',
	array(
		'total_price'     => $total_price,
        'api_type'        => $type,
        'decimalchainId'  => $get_network ? hexdec( $get_network ) : false,
        'active_network'  => $get_network ? $get_network : false,
		'nonce'           => wp_create_nonce( 'wp_rest' ),
        'restUrl'         => esc_url_raw( get_rest_url() . 'pay-with-metamask/v1/' ),
		'currency_lbl'    => $select_currency_lbl,
		'const_msg'       => $const_msg,
        'networkName'     => $network_name_value,
		'enabledCurrency' => $enabledCurrency,
        'rpcUrl'          => $rpc_url,
	)
);
// Output supported wallets if available
if ( $error ) {
	echo esc_html( $error );
} else {
	if ( $this->description ) {
		echo '<div class="cpmwp_gateway_desc">' . esc_html( $this->description ) . '</div>';
	}
	echo '<div class="cpmwp-supported-wallets-wrap">';
	echo '<div class="cpmwp-supported-wallets" id="cpmwp-connect-wallets">';
	echo '<div class="cegc-ph-item">';
	echo '<div class="cegc-ph-col-12">';
	echo '<div class="ph-row">';
	echo '<div class="cegc-ph-col-6 big"></div>';
	echo '<div class="cegc-ph-col-4  big"></div>';
	echo '<div class="cegc-ph-col-2 big"></div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
}

// Trigger WooCommerce action to end the form
do_action( 'woocommerce_cpmw_form_end', $this->id );
