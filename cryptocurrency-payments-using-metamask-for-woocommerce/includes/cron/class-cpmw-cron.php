<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

if ( ! class_exists( 'CPMW_cronjob' ) ) {
	class CPMW_cronjob {

		use CPMW_HELPER;

		public function __construct() {

			// Register cron jobs
			add_filter( 'cron_schedules', array( $this, 'cpmw_cron_schedules' ) );
			add_action( 'cpmwp_order_autoupdate', array( $this, 'pending_orders_autoupdater' ) );
			add_action( 'init', array( $this, 'cpmw_schedule_events' ) );
			add_action('cpmw_extra_data_update', array($this, 'cpmw_cron_extra_data_autoupdater'));
		}

		public function cpmw_schedule_events() {
			if ( ! wp_next_scheduled( 'cpmwp_order_autoupdate' ) ) {
				wp_schedule_event( time(), '5min', 'cpmwp_order_autoupdate' );
			}
		}


		/**
		 * Cron status schedule(s).
		 */
		public function cpmw_cron_schedules( $schedules ) {

			// 5 minute schedule for grabbing all coins
			if ( ! isset( $schedules['5min'] ) ) {
				$schedules['5min'] = array(
					'interval' => 5 * 60,
					'display'  => __( 'Once every 5 minutes' ),
				);
			}
			if (!isset($schedules['every_30_days'])) {

                $schedules['every_30_days'] = array(
                    'interval' => 30 * 24 * 60 * 60, // 2,592,000 seconds
                    'display'  => __('Once every 30 days'),
                );
            }

			return $schedules;
		}

		function cpmw_cron_extra_data_autoupdater(){
            
            $settings       = get_option('cpmw_settings', []);
            $cpmw_response  = isset($settings['cpmw_extra_info']) ? $settings['cpmw_extra_info'] : '';

			
            if (!empty($cpmw_response) || $cpmw_response === 'on' || $cpmw_response === '1'){
          
                if (class_exists('CPMW_cronjob')) {

                    CPMW_cronjob::cpmw_send_data();
                }
            }

        }

		static public function cpmw_send_data() {
                   
            $feedback_url = CPMW_FEEDBACK_API.'wp-json/coolplugins-feedback/v1/site';
            require_once CPMW_PATH . 'admin/feedback/admin-feedback-form.php';
			
		

            if (!defined('CPMW_PATH')  || !class_exists('\CPMW\feedback\Cpmw_feedback') ) {
                return;
            }
            
            $extra_data         = new \CPMW\feedback\Cpmw_feedback();
            $extra_data_details = $extra_data->cpfm_get_user_info();


            $server_info    = $extra_data_details['server_info'];
            $extra_details  = $extra_data_details['extra_details'];
            $site_url       = get_site_url();
            $install_date   = get_option('cpmw-install-date');
            $uni_id         = '7';
            $site_id        = $site_url . '-' . $install_date . '-' . $uni_id;
            $initial_version = get_option('cpmw_initial_save_version');
            $initial_version = is_string($initial_version) ? sanitize_text_field($initial_version) : 'N/A';
            $plugin_version = defined('CPMW_VERSION') ? CPMW_VERSION : 'N/A';
            $admin_email    = sanitize_email(get_option('admin_email') ?: 'N/A');
            
            $post_data = array(

                'site_id'           => md5($site_id),
                'plugin_version'    => $plugin_version,
                'plugin_name'       => 'Cryptocurrency Payments Using MetaMask For WooCommerce',
                'plugin_initial'    => $initial_version,
                'email'             => $admin_email,
                'site_url'          => esc_url_raw($site_url),
                'server_info'       => $server_info,
                'extra_details'     => $extra_details,
            );
            
            $response = wp_remote_post($feedback_url, array(

                'method'    => 'POST',
                'timeout'   => 30,
                'headers'   => array(
                    'Content-Type' => 'application/json',
                ),
                'body'      => wp_json_encode($post_data),
            ));
            
            if (is_wp_error($response)) {

                error_log('CPMW Feedback Send Failed: ' . $response->get_error_message());
                return;
            }
            
            $response_body  = wp_remote_retrieve_body($response);
            $decoded        = json_decode($response_body, true);
            
            if (!wp_next_scheduled('cpmw_extra_data_update')) {

                wp_schedule_event(time(), 'every_30_days', 'cpmw_extra_data_update');
            }
        }

		/*
		|-----------------------------------------------------------
		|   This will update the database after a specific interval
		|-----------------------------------------------------------
		|   Always use this function to update the database
		|-----------------------------------------------------------
		 */
		public function pending_orders_autoupdater() {
			$db                   = new CPMW_database();
			$pending_transactions = $db->cpmw_get_data_of_pending_transaction();
			if ( is_array( $pending_transactions ) && count( $pending_transactions ) >= 1 ) {

				foreach ( $pending_transactions as $key => $value ) {
					$pending_orderid = $value->order_id;
					$order_exits     = wc_get_order( $pending_orderid );
					$order_exits     = isset( $order_exits ) && $order_exits ? true : false;

					if ( ! $order_exits ) {
						continue;
					}

					$pendingorder = new WC_Order( $pending_orderid );
					$chain_id     = $this->cpmw_chain_id( $value->chain_id );

					if ( $pendingorder->is_paid() == false && $chain_id ) {

						$amount = $pendingorder->get_meta( 'cpmwp_in_crypto' );
						$amount = str_replace( ',', '', $amount );

						$receipt = CPMW_API_DATA::verify_transaction_info( $value->transaction_id, esc_html( $value->chain_id ), esc_html( $pending_orderid ), $amount );

						if ( $receipt['tx_status'] == '0x1' && $receipt['tx_amount_verify'] && ! isset( $receipt['tx_already_exists'] ) ) {
							$block_explorer = $this->cpmw_get_explorer_url();
							$link_hash      = '<a href="' . $block_explorer[ $value->chain_id ] . 'tx/' . $value->transaction_id . '" target="_blank">' . $value->transaction_id . '</a>';
							$transection    = __( 'Payment Received via Pay with MetaMask - Transaction ID:', 'cpmwp' ) . $link_hash;
							$pendingorder->add_order_note( $transection );
							$pendingorder->payment_complete( $value->transaction_id );
							$db->update_fields_value( $pending_orderid, 'status', 'completed' );
						}
					}
				}
			}

		}

	}

	$cron_init = new CPMW_cronjob();
}
