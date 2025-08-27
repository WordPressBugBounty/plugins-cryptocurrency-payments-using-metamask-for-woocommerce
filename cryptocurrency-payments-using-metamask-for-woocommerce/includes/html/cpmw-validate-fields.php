<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get constant messages
$const_msg = $this->cpmw_const_messages();
// Get plugin options
$options = (array) get_option( 'cpmw_settings', array() );
// Get user wallet settings
$user_wallet = isset( $options['user_wallet'] ) ? sanitize_text_field( $options['user_wallet'] ) : '';
// Get currency conversion API options
$compare_key     = isset( $options['crypto_compare_key'] ) ? sanitize_text_field( $options['crypto_compare_key'] ) : '';
$openex_key      = isset( $options['openexchangerates_key'] ) ? sanitize_text_field( $options['openexchangerates_key'] ) : '';
$select_currecny = isset( $options['currency_conversion_api'] ) ? sanitize_key( $options['currency_conversion_api'] ) : '';
// Comprehensive input validation and sanitization
$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
if ( $request_method !== 'POST' ) {
    return $this->add_error_custom_notice( __( 'Invalid request method.', 'cpmw' ), false );
}

$symbol_raw          = isset( $_POST['cpmwp_crypto_coin'] ) ? wp_unslash( $_POST['cpmwp_crypto_coin'] ) : '';
$payment_network_raw = isset( $_POST['cpmw_payment_network'] ) ? wp_unslash( $_POST['cpmw_payment_network'] ) : '';
$balance_raw         = isset( $_POST['current_balance'] ) ? wp_unslash( $_POST['current_balance'] ) : '';

$symbol          = strtoupper( sanitize_text_field( $symbol_raw ) );
$payment_network = sanitize_text_field( $payment_network_raw );
$current_balance = is_numeric( $balance_raw ) ? (float) $balance_raw : null;

// Check for various conditions and add WooCommerce notices
if ( empty( $user_wallet ) ) {
	return $this->add_error_custom_notice( $const_msg['metamask_address'] );
}
// Validate wallet address format (0x + 40 hex chars)
if ( ! empty( $user_wallet ) && ! preg_match( '/^0x[a-fA-F0-9]{40}$/', $user_wallet ) ) {
	return $this->add_error_custom_notice( $const_msg['valid_wallet_address'] );
}
if ( $select_currecny === 'cryptocompare' && empty( $compare_key ) ) {
	return $this->add_error_custom_notice( $const_msg['required_fiat_key'] );
}

// Whitelist validation for crypto currency
$network = isset( $options['Chain_network'] ) ? (string) $options['Chain_network'] : '';
$allowed_currencies = in_array( $network, array( '0x1', '0x5', '0xaa36a7' ), true ) ?
	( isset( $options['eth_select_currency'] ) ? (array) $options['eth_select_currency'] : array() ) :
	( isset( $options['bnb_select_currency'] ) ? (array) $options['bnb_select_currency'] : array() );
if ( empty( $symbol ) || ! in_array( $symbol, (array) $allowed_currencies, true ) ) {
	return $this->add_error_custom_notice( $const_msg['required_currency'], false );
}

// Whitelist validation for payment network
$allowed_networks = array_keys($this->cpmw_supported_networks());
if ( empty( $payment_network ) || ! in_array( $payment_network, $allowed_networks, true ) ) {
	return $this->add_error_custom_notice( $const_msg['required_network_check'] );
}
$total_price   = $this->get_order_total();
$in_crypto     = $this->cpmw_price_conversion( $total_price, $symbol, $select_currecny );
$in_crypto_val = ( $in_crypto !== null && is_scalar( $in_crypto ) ) ? (float) str_replace( ',', '', (string) $in_crypto ) : null;
// Check if current balance is less than the required amount to pay
if ( $current_balance !== null && $in_crypto_val !== null && $current_balance < $in_crypto_val ) {
    $msg = __( 'Current Balance:', 'cpmw' ) . esc_html( $current_balance ) . ' ' . __( 'Required amount to pay:', 'cpmw' ) . esc_html( $in_crypto_val );
	return $this->add_error_custom_notice( $msg, false );
}
// Check if current balance is not set (Wallet not connected)
if ( $current_balance === null ) {
	return $this->add_error_custom_notice( __( 'Please connect Wallet first', 'cpmw' ), false );
}
// Check if the selected network matches configured network
if ( $network !== $payment_network ) {
	return $this->add_error_custom_notice( __( 'Network not supported in this server', 'cpmw' ), false );
}
// If all checks pass, return true
return true;

