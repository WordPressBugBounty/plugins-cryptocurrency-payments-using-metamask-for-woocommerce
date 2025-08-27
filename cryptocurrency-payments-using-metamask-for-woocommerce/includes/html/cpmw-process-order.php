<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Get constant messages
$const_msg = $this->cpmw_const_messages();

// Get plugin options
$options = get_option( 'cpmw_settings' );

// Get supported network names
$network_name = $this->cpmw_supported_networks();

// Default messages
$payment_msg        = ! empty( $options['payment_msg'] ) ? sanitize_text_field( $options['payment_msg'] ) : esc_html__( 'Payment Completed Successfully', 'cpmw' );
$confirm_msg        = ! empty( $options['confirm_msg'] ) ? sanitize_text_field( $options['confirm_msg'] ) : esc_html__( 'Confirm Payment in your wallet', 'cpmw' );
$process_msg        = ! empty( $options['payment_process_msg'] ) ? sanitize_text_field( $options['payment_process_msg'] ) : esc_html__( 'Payment in process', 'cpmw' );
$rejected_msg       = ! empty( $options['rejected_message'] ) ? sanitize_text_field( $options['rejected_message'] ) : esc_html__( 'Transaction Rejected ', 'cpmw' );
$place_order_button = ( isset( $options['place_order_button'] ) && ! empty( $options['place_order_button'] ) ) ? sanitize_text_field( $options['place_order_button'] ) : esc_html__( 'Pay With Crypto Wallets', 'cpmwp' );
// Get network and redirect options
$network      = ! empty( $options['Chain_network'] ) ? sanitize_text_field( $options['Chain_network'] ) : '';
$redirect_url = ! empty( $options['redirect_page'] ) ? esc_url_raw( $options['redirect_page'] ) : '';

// Determine crypto currency based on network
$crypto_currency = ( $network == '0x1' || $network == '0x5' ) ? $options['eth_select_currency'] : $options['bnb_select_currency'];

// Get order details
$order           = new WC_Order( $order_id );
$total           = $order->get_total();
$nonce           = wp_create_nonce( 'cpmw_metamask_pay' . $order_id );
$user_wallet     = sanitize_text_field( (string) $order->get_meta( 'cpmwp_user_wallet' ) );
$in_crypto       = (string) $order->get_meta( 'cpmwp_in_crypto' );
$currency_symbol = sanitize_text_field( (string) $order->get_meta( 'cpmwp_currency_symbol' ) );
$payment_status  = sanitize_text_field( (string) $order->get_status() );

// Get additional network and token information

$add_tokens        = $this->cpmw_add_tokens();
$token_address     = isset( $add_tokens[ $network ][ $currency_symbol ] ) ? sanitize_text_field( $add_tokens[ $network ][ $currency_symbol ] ) : '';
$transaction_id    = ( ! empty( $order->get_meta( 'TransactionId' ) ) ) ? sanitize_text_field( (string) $order->get_meta( 'TransactionId' ) ) : '';
$sig_token_address = sanitize_text_field( (string) $order->get_meta( 'cpmwp_contract_address' ) );

// Generate signature for transaction request
$secret_key     = $this->cpmw_get_secret_key();
$tx_req_data    = json_encode(
	array(
		'order_id'         => $order_id,
		'selected_network' => $network,
		'receiver'         => strtoupper( $user_wallet ),
		'amount'           => str_replace( ',', '', $in_crypto ),
		'token_address'    => strtoupper( $sig_token_address ),
	)
);
$block_explorer = $this->cpmw_get_explorer_url();
$signature      = hash_hmac( 'sha256', $tx_req_data, $secret_key );
$filePaths      = glob( CPMW_PATH . '/assets/pay-with-metamask/build/main' . '/*.php' );
$jsbuildUrl     = '';
if ( is_array( $filePaths ) && ! empty( $filePaths ) && file_exists( $filePaths[0] ) ) {
	$fileName   = pathinfo( $filePaths[0], PATHINFO_FILENAME );
	$jsbuildUrl = sanitize_file_name( str_replace( '.asset', '', $fileName ) );
}
// Enqueue required script when build asset exists
if ( ! empty( $jsbuildUrl ) ) {
	wp_enqueue_script( 'cpmw_react_widget', CPMW_URL . 'assets/pay-with-metamask/build/main/' . $jsbuildUrl . '.js', array( 'wp-element' ), CPMW_VERSION, true );
}

// Add RPC URL mapping before wp_localize_script
$network_rpc_map = [
	'0x1' => 'eth_rpc_url',
	'0x38' => 'bsc_rpc_url',
	'0x61' => 'bsc_testnet_rpc_url',
	'0xaa36a7' => 'sepolia_rpc_url'
];

$rpc_url = isset($network_rpc_map[$network]) ? $options[$network_rpc_map[$network]] : '';

wp_localize_script(
	'cpmw_react_widget',
	'extradataRest',
	array(
		'url'                => CPMW_URL,
		'supported_networks' => $network_name,
		'restUrl'            => esc_url_raw( trailingslashit( get_rest_url() ) . 'pay-with-metamask/v1/' ),
		'fiatSymbol'         => get_woocommerce_currency_symbol(),
		'totalFiat'          => $total,
		'network_name'       => isset( $network_name[ $network ] ) ? sanitize_text_field( $network_name[ $network ] ) : '',
		'token_address'      => $token_address,
		'transaction_id'     => $transaction_id,
		'const_msg'          => $const_msg,
		'wallet_image'       => esc_url_raw( CPMW_URL . 'assets/images/metamask.png' ),
		'redirect'           => $redirect_url,
		'currency_logo'      => esc_url_raw( $this->cpmw_get_coin_logo( $currency_symbol ) ),
		'order_page'         => esc_url_raw( get_home_url( null, '/my-account/orders/' ) ),
		'currency_symbol'    => $currency_symbol,
		'confirm_msg'        => $confirm_msg,
		'block_explorer'     => isset( $block_explorer[ $network ] ) ? esc_url_raw( $block_explorer[ $network ] ) : '',
		'network'            => $network,
		'is_paid'            => $order->is_paid(),
		'decimalchainId'     => isset( $network ) ? hexdec( $network ) : false,
		'process_msg'        => $process_msg,
		'payment_msg'        => $payment_msg,
		'rejected_msg'       => $rejected_msg,
		'in_crypto'          => is_string($in_crypto) ? preg_replace( '/[^0-9.]/', '', str_replace( ',', '', $in_crypto ) ) : '0',
		'receiver'           => $user_wallet,
		'order_status'       => $payment_status,
		'id'                 => (int) $order_id,
		'place_order_btn'    => $place_order_button,
		'nonce'              => wp_create_nonce( 'wp_rest' ),
		'payment_status'     => isset( $options['payment_status'] ) ? sanitize_text_field( $options['payment_status'] ) : '',
		'signature'          => $signature,
		'rpcUrl'             => $rpc_url,
	)
);
wp_enqueue_style( 'cpmw_custom_css', CPMW_URL . 'assets/css/style.css', array(), CPMW_VERSION );

$trasn_id  = sanitize_text_field( (string) $order->get_meta( 'TransactionId' ) );
$link_hash = '--';

if ( ! empty( $trasn_id ) && $trasn_id != 'false' ) {

    $base_url = isset( $block_explorer[ $network ] ) ? $block_explorer[ $network ] : '';
    if ( ! empty( $base_url ) ) {
        $tx_url   = rtrim( $base_url, '/' ) . '/tx/' . rawurlencode( $trasn_id );
        $link_hash = '<a href="' . esc_url( $tx_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $trasn_id ) . '</a>';
    }
}


// Display payment information
echo '<div class="cmpw_meta_connect1" id="cmpw_meta_connect">
<div class="ccpwp-card">
<div class="ccpwp-card__image ccpwp-loading"></div>
<div class="ccpwp-card__title ccpwp-loading"></div>
<div class="ccpwp-card__description ccpwp-loading"></div>
</div>
</div>';
?>
<section class="cpmw-woocommerce-woocommerce-order-details">
	<h2 class="woocommerce-order-details__title"><?php echo __( 'Crypto payment details', 'cpmw' ); ?></h2>
	<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
		<tbody>
			<tr>
				<th scope="row">   <?php echo __( 'Price:', 'cpmw' ); ?></th>
				<td><?php echo esc_html( $in_crypto ) . esc_html( $currency_symbol ); ?></td>
			</tr>
			<tr>
				<th scope="row"> <?php echo __( 'Payment Status', 'cpmw' ); ?></th>
				<td class="cpmwp_statu_<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( $order->get_status() ); ?></td>
			</tr>
			<?php
			if ( ! empty( $trasn_id ) && $trasn_id != 'false' ) {
				?>
			 <tr>
				<th scope="row"> <?php echo __( 'Transaction id:', 'cpmw' ); ?></th>
				<td><?php echo wp_kses_post( $link_hash ); ?></td>
			</tr>
				<?php
			}
			?>

		</tbody>
	</table>
</section>

		<?php
