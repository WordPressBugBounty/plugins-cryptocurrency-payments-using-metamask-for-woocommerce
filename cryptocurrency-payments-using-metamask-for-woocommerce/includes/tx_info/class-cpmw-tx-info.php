<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CPMW_TX_INFO' ) ) {
	exit;
}

use CPMW\CONVERTER\ConverterUtils;

if ( ! class_exists( 'CPMW_TX_INFO' ) ) {
	/**
	 * Class CPMW_TX_VERIFY
	 *
	 * This class handles the verify transaction.
	 */
	class CPMW_TX_INFO {
		private static $instance     = null;
		private static $ether_amount = false;

		/**
		 * Normalize and sanitize an Ethereum address (lowercase, strip 0x, validate hex length).
		 *
		 * @param string $address
		 * @return string 40-char hex string without 0x, or empty string if invalid
		 */
		private function normalize_address( $address ) {
			$address = is_string( $address ) ? sanitize_text_field( $address ) : '';
			$address = strtolower( trim( $address ) );
			if ( strpos( $address, '0x' ) === 0 ) {
				$address = substr( $address, 2 );
			}
			if ( preg_match( '/^[0-9a-f]{40}$/', $address ) !== 1 ) {
				return '';
			}
			return $address;
		}

		/**
		 * Sanitize and validate a hex string. Optionally allow an 0x prefix.
		 *
		 * @param string $value
		 * @param bool   $allow_0x
		 * @return string sanitized lowercase hex without 0x, or empty string if invalid
		 */
		private function sanitize_hex( $value, $allow_0x = true ) {
			$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
			$value = strtolower( trim( $value ) );
			if ( $allow_0x && strpos( $value, '0x' ) === 0 ) {
				$value = substr( $value, 2 );
			}
			if ( $value === '' || preg_match( '/^[0-9a-f]+$/', $value ) !== 1 ) {
				return '';
			}
			return $value;
		}

		/**
		 * Constructor for initializing the class with Infura ID and chain ID.
		 *
		 * @param string $infura_id The Infura project ID.
		 * @param int    $chain_id The chain ID.
		 */
		public function __construct() {
			$this->cpmw_autoload_files();
		}

		/**
		 * Get an instance of the class with Infura ID and chain ID.
		 *
		 * @param string $infura_id The Infura project ID.
		 * @param int    $chain_id The chain ID.
		 * @return CPMW_TX_INFO
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Autoload necessary files for Web3.
		 *
		 * @return mixed
		 */
		private function cpmw_autoload_files() {

			require_once CPMW_PATH . 'includes/crypto_converter/Crypt/Random.php';
			require_once CPMW_PATH . 'includes/crypto_converter/Math/BigInteger.php';
			require_once CPMW_PATH . 'includes/crypto_converter/converter-utils.php';
		}

		/**
		 * Get transaction receipt based on transaction hash.
		 *
		 * @param string $txHash The transaction hash.
		 * @return false
		 */
		public function get_transaction_type( $receipt, $receiver_id ) {
			if ( $receipt ) {
				$normalized_receiver = $this->normalize_address( $receiver_id );
				if ( $normalized_receiver === '' ) {
					return 'receiver are not same';
				}
				// Token transfer: non-empty input indicates contract call
				if ( isset( $receipt['input'] ) && is_string( $receipt['input'] ) ) {
					$input = $this->sanitize_hex( $receipt['input'], true );
					if ( $input !== '' && strlen( $input ) >= 136 ) { // 8 (sig) + 64 (addr) + 64 (amount)
						$to_slot_hex = substr( $input, 8, 64 );
						$to_arg_addr = substr( $to_slot_hex, 24, 40 );
						if ( $to_arg_addr === $normalized_receiver ) {
							self::$ether_amount = $this->convert_token_amount( $input );
							return 'success';
						}
						return 'receiver are not same';
					}
				}

				// Native currency transfer: empty/"0x" input; compare 'to' address and read 'value' in wei
				$send_to = isset( $receipt['to'] ) ? sanitize_text_field( $receipt['to'] ) : '';
				if ( $this->normalize_address( $send_to ) === $normalized_receiver ) {
					if ( isset( $receipt['value'] ) ) {
						$value_hex = $this->sanitize_hex( $receipt['value'], true );
						if ( $value_hex === '' ) {
							return 'receiver are not same';
						}
						$wei_value          = ConverterUtils::convert_to_bignumber( $value_hex );
						self::$ether_amount = $wei_value->toString();
						return 'success';
					}
				} else {
					return 'receiver are not same';
				}
			}
			return 'success';
		}

		/**
		 * Verify transaction based on the provided amount.
		 *
		 * @param string $amount The amount to verify.
		 * @return bool
		 */
		public function cpmw_tx_verification( $receipt, $amount, $receiver_id ) {

			// reset between calls
 			self::$ether_amount = false;

			$result = self::get_transaction_type( $receipt, $receiver_id );

			if ( $result === 'receiver are not same' ) {
				return $result;
			}

			if ( self::$ether_amount !== false ) {

				$actualTokenAmount = ConverterUtils::convert_to_wei( $amount, 'ether' );
				$actualTokenAmount = $actualTokenAmount->toString();

				if ( self::$ether_amount === $actualTokenAmount ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Convert token amount to Ether value.
		 *
		 * @param string $tk_amount The token amount.
		 * @return mixed
		 */
		private function convert_token_amount( $tk_amount ) {
			// $tk_amount is the sanitized input hex WITHOUT 0x. Extract amount (2nd 32-byte slot)
			$amount_hex_64 = substr( $tk_amount, 8 + 64, 64 );
			if ( preg_match( '/^[0-9a-f]{64}$/', $amount_hex_64 ) !== 1 ) {
				return '0';
			}
			$amount_wei = ConverterUtils::convert_to_bignumber( $amount_hex_64 );
			return $amount_wei->toString();
		}
	}
}



