<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
if ( ! class_exists( 'CPMW_API_DATA' ) ) {
	class CPMW_API_DATA {

		use CPMW_HELPER;

		/**
		 * CRYPTOCOMPARE_TRANSIENT used for fiat conversion API transient time.
		 */
		const CRYPTOCOMPARE_TRANSIENT = 10 * MINUTE_IN_SECONDS;

		/**
		 * OPENEXCHANGERATE_TRANSIENT used for fiat conversion API  transient time.
		 */

		const OPENEXCHANGERATE_TRANSIENT = 120 * MINUTE_IN_SECONDS;

		/**
		 * BINANCE_TRANSIENT used for fiat conversion API  transient time.
		 */
		const BINANCE_TRANSIENT = 10 * MINUTE_IN_SECONDS;

		/**
		 * CMC_API_ENDPOINT
		 *
		 * Holds the URL of the coins data API.
		 *
		 * @access public
		 */
		const CRYPTOCOMPARE_API = 'https://min-api.cryptocompare.com/data/price?fsym=';

		/**
		 * COINGECKO_API_ENDPOINT
		 *
		 * Holds the URL of the coingecko API.
		 *
		 * @access public
		 */
		const BINANCE_API_COM = 'https://api.binance.com/api/v3/ticker/24hr?symbol=';
		const BINANCE_API_US  = 'https://api.binance.us/api/v3/ticker/24hr?symbol=';

		/**
		 * OPENEXCHANGERATE_API_ENDPOINT
		 *
		 * Holds the URL of the openexchangerates API.
		 *
		 * @access public
		 */

		const OPENEXCHANGERATE_API_ENDPOINT = 'https://openexchangerates.org/api/latest.json?app_id=';

		/**
		 * WALLETCONNECT_API_ENDPOINT
		 *
		 * Holds the URL of the WalletConnect API endpoint.
		 *
		 * @access public
		 */
		const WALLETCONNECT_API_ENDPOINT = 'https://rpc.walletconnect.com/v1';

		public function __construct() {
			 // self::CMC_API_ENDPOINT = 'https://apiv3.coinexchangeprice.com/v3/';
		}

		public static function cpmw_crypto_compare_api( $fiat, $crypto_token ) {
			$settings_obj      = get_option( 'cpmw_settings' );
			$api               = ! empty( $settings_obj['crypto_compare_key'] ) ? sanitize_text_field( $settings_obj['crypto_compare_key'] ) : '';
			$fsym              = strtoupper( sanitize_text_field( $fiat ) );
			$crypto_token_sane = strtoupper( sanitize_text_field( $crypto_token ) );
			$transient         = get_transient( 'cpmw_currency' . $crypto_token_sane );
			if ( empty( $transient ) || $transient === '' ) {
				$url      = esc_url_raw( self::CRYPTOCOMPARE_API . rawurlencode( $fsym ) . '&tsyms=' . rawurlencode( $crypto_token_sane ) );
				$options  = array(
					'timeout'   => 120,
					'sslverify' => true,
					'headers'   => array(
						'Authorization' => 'Apikey ' . $api,
					),
				);
				$response = wp_remote_post( $url, $options );
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					return $error_message;
				}
				$body      = wp_remote_retrieve_body( $response );
				$data_body = json_decode( $body );
				set_transient( 'cpmw_currency' . $crypto_token_sane, $data_body, self::CRYPTOCOMPARE_TRANSIENT );
				return $data_body;
			} else {
				return $transient;
			}
		}

		public static function cpmw_openexchangerates_api() {
			$settings_obj = get_option( 'cpmw_settings' );
			$api          = ! empty( $settings_obj['openexchangerates_key'] ) ? sanitize_text_field( $settings_obj['openexchangerates_key'] ) : '';
			if ( empty( $api ) ) {
				return;
			}
			$transient = get_transient( 'cpmw_openexchangerates' );
			if ( empty( $transient ) || $transient === '' ) {
				$url      = esc_url_raw( self::OPENEXCHANGERATE_API_ENDPOINT . rawurlencode( $api ) );
				$response = wp_remote_post(
					$url,
					array(
						'timeout'   => 120,
						'sslverify' => true,
					)
				);
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					return $error_message;
				}
				$body      = wp_remote_retrieve_body( $response );
				$data_body = json_decode( $body );
				if ( isset( $data_body->error ) ) {
					return (object) array(
						'error'       => true,
						'message'     => $data_body->message,
						'description' => $data_body->description,
					);
				}
				set_transient( 'cpmw_openexchangerates', $data_body, self::OPENEXCHANGERATE_TRANSIENT );
				return $data_body;
			} else {
				return $transient;
			}
		}
		public static function cpmw_binance_price_api( $symbol ) {
			$settings_obj = get_option( 'cpmw_settings' );
			$symbol       = strtoupper( sanitize_text_field( $symbol ) );
			$trans_name   = 'cpmw_binance_price_' . $symbol;
			$transient    = get_transient( $trans_name );
			if ( empty( $transient ) || $transient === '' ) {
				$response = wp_remote_get(
					esc_url_raw( self::BINANCE_API_COM . rawurlencode( $symbol ) ),
					array(
						'timeout'   => 120,
						'sslverify' => true,
					)
				);
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					return $error_message;
				}
				$body      = wp_remote_retrieve_body( $response );
				$data_body = json_decode( $body );
				if ( isset( $data_body->msg ) ) {
					$response = wp_remote_get(
						esc_url_raw( self::BINANCE_API_US . rawurlencode( $symbol ) ),
						array(
							'timeout'   => 120,
							'sslverify' => true,
						)
					);
					if ( is_wp_error( $response ) ) {
						$error_message = $response->get_error_message();
						return $error_message;
					}
					$body      = wp_remote_retrieve_body( $response );
					$data_body = json_decode( $body );

				}
				set_transient( $trans_name, $data_body, self::BINANCE_TRANSIENT );
				return $data_body;
			} else {
				return $transient;
			}
		}

		/**
		 * Verify transaction info and return status along with verification result.
		 *
		 * @param string $txHash The transaction hash.
		 * @param string $network The network hash.
		 * @param int    $order_id The order ID.
		 * @param string $amount The amount to verify.
		 * @return array
		 */
		public static function verify_transaction_info( $txHash, $network_hash = '0x1', $order_id = 0, $amount = false ) {

			// Sanitize and validate inputs
			$txHash       = is_string( $txHash ) ? sanitize_text_field( $txHash ) : '';
			$network_hash = is_string( $network_hash ) ? sanitize_text_field( $network_hash ) : '0x1';
			$order_id     = (int) $order_id;
			$amount       = ( $amount !== false ) ? sanitize_text_field( str_replace( ',', '', (string) $amount ) ) : false;
			if ( ! preg_match( '/^0x[a-fA-F0-9]{64}$/', $txHash ) ) {
				return array(
					'tx_status'        => false,
					'tx_amount_verify' => false,
					'invalid_tx_hash'  => true,
				);
			}

			$network      = self::cpmw_chain_id( $network_hash );
			$rpc_endpoint = esc_url_raw( self::cpmw_rpc_endpoint( $network_hash ) );

			$options          = get_option( 'cpmw_settings' );
			$reciever_address = ! empty( $options['user_wallet'] ) ? strtolower( sanitize_text_field( $options['user_wallet'] ) ) : '';

			$db          = new CPMW_database();
			$tx_order_id = $db->cpmw_get_tx_order_id( $txHash );

			if ( count( $tx_order_id ) !== 1 ) {
				return array(
					'tx_already_exists' => true,
				);
			}

			if ( (int) $tx_order_id[0] !== $order_id ) {
				return array(
					'tx_already_exists' => true,
				);
			}

			$get_datas = array(
				array(
					'jsonrpc' => '2.0',
					'id'      => $network,
					'method'  => 'eth_getTransactionReceipt',
					'params'  => array( $txHash ),
				),
				array(
					'jsonrpc' => '2.0',
					'id'      => $network,
					'method'  => 'eth_getTransactionByHash',
					'params'  => array( $txHash ),
				),
			);

			$json_data = array();

			foreach ( $get_datas as $data ) {
				$options = array(
					'body'      => wp_json_encode( $data ),
					'timeout'   => 120,
					'sslverify' => true,
					'headers'   => array( 'Content-Type' => 'application/json' ),
				);
				$result = wp_remote_post( $rpc_endpoint, $options );
				if ( is_wp_error( $result ) ) {
					continue;
				}
				$json = json_decode( wp_remote_retrieve_body( $result ), true );
				if ( is_array( $json ) ) {
					$json_data[] = $json;
				}
			}

			$return_data = array(
				'tx_status'        => false,
				'tx_amount_verify' => false,
			);

			! defined( 'CPMW_TX_INFO' ) && define( 'CPMW_TX_INFO', true );
			require_once CPMW_PATH . 'includes/tx_info/class-cpmw-tx-info.php';

			$tx_verifier = CPMW_TX_INFO::get_instance();

			foreach ( $json_data as $data ) {
				if ( ! empty( $reciever_address ) ) {
					$reciever_address = trim( $reciever_address );
					if ( isset( $data['result']['status'] ) ) {
						$return_data['tx_status'] = $data['result']['status'];
					}
					if ( isset( $data['result']['value'] ) && is_array( $data['result'] ) ) {
						$tx_result = $tx_verifier->cpmw_tx_verification( $data['result'], $amount, $reciever_address );
						if ( $tx_result === 'receiver are not same' ) {
							$return_data['receiver_failed'] = true;
						} else {
							$return_data['tx_amount_verify'] = $tx_result;
						}
					}
				}
			}

			return $return_data;
		}

	}
}
