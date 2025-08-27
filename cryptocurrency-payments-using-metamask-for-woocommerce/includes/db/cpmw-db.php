<?php

class CPMW_database {

	public $table_name;
	public $primary_key;
	public $version;

	/**
	 * Get things started
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table_name  = $wpdb->base_prefix . 'cpmw_transaction';
		$this->primary_key = 'id';
		$this->version     = '1.0';

	}

	/**
	 * Get columns and formats
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function get_columns() {
		return array(
			'id'                => '%d',
			'order_id'          => '%d',
			'chain_id'          => '%s',
			'order_price'       => '%s',
			// 'quantity' => '%f',
			'user_name'         => '%s',
			'crypto_price'      => '%s',
			'selected_currency' => '%s',
			'chain_name'        => '%s',
			'status'            => '%s',
			'sender'            => '%s',
			'transaction_id'    => '%s',
		);
	}

	public function cpmw_get_data() {
		global $wpdb;

		// Use prepare with a placeholder for the table name to prevent SQL injection
		$query = $wpdb->prepare("SELECT * FROM %i", $this->table_name);
		$results = $wpdb->get_results($query);
		return $results;

	}

	/**
	 * Get default column values
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function get_column_defaults() {
		 return array(
			 'order_id'          => '',
			 'chain_id'          => '',
			 'order_price'       => '',
			 // 'quantity' => '',
			 'user_name'         => '',
			 'crypto_price'      => '',
			 'selected_currency' => '',
			 'chain_name'        => '',
			 'status'            => '',
			 'sender'            => '',
			 'transaction_id'    => '',
			 'last_updated'      => date( 'Y-m-d H:i:s' ),
		 );
	}
	public function update_fields_value( $order_id, $column_name, $new_value ) {
		global $wpdb;

		$columns         = $this->get_columns();
		$allowed_columns = array_keys( $columns );
		$column_name     = sanitize_key( $column_name );
		if ( ! in_array( $column_name, $allowed_columns, true ) ) {
			return false;
		}

		$format = $columns[ $column_name ];
		if ( '%d' === $format ) {
			$new_value = (int) $new_value;
		} else {
			$new_value = is_string( $new_value ) ? sanitize_text_field( $new_value ) : (string) $new_value;
		}

		$wpdb->update(
			$this->table_name,
			array(
				$column_name => $new_value,
			),
			array(
				'order_id' => (int) $order_id,
			),
			array( $format ),
			array( '%d' )
		);

	}

	public function cpmw_get_data_of_pending_transaction() {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM %i WHERE transaction_id != %s AND status = %s",
			$this->table_name,
			'false',
			'awaiting'
		);
		$results = $wpdb->get_results($query);
		return $results;

	}

	public function coin_exists_by_id( $coin_ID ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$this->table_name}` WHERE `order_id` = %d", (int) $coin_ID ) );
		if ( (int) $count === 1 ) {
			return true;
		} else {
			return false;
		}

	}

	public function check_transaction_id( $transaction_id ) {
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$this->table_name}` WHERE `transaction_id` = %s", sanitize_text_field( $transaction_id ) ) );
		if ( (int) $count === 1 ) {
			return true;
		} else {
			return false;
		}

	}

	public function cpmw_insert_data( $transactions ) {
		if ( is_array( $transactions ) && count( $transactions ) >= 1 ) {

			return $this->wp_insert_rows( $transactions, $this->table_name, true, 'transaction_id' );
		}
	}

	public function wp_insert_rows( $row_arrays, $wp_table_name, $update = false, $primary_key = null ) {
		global $wpdb;
		if ( ! is_array( $row_arrays ) || empty( $row_arrays ) ) {
			return false;
		}

		// Whitelist columns based on schema
		$column_formats = $this->get_columns();
		$allowed_cols   = array_keys( $column_formats );

		$safe_table   = esc_sql( $wp_table_name );
		$columns      = array();
		$placeholders = array();
		$values       = array();

		foreach ( $row_arrays as $key => $value ) {
			if ( ! in_array( $key, $allowed_cols, true ) ) {
				continue;
			}
			$columns[]      = '`' . $key . '`';
			$format         = isset( $column_formats[ $key ] ) ? $column_formats[ $key ] : '%s';
			$placeholders[] = $format;
			$values[]       = ( '%d' === $format ) ? (int) $value : ( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
		}

		if ( empty( $columns ) ) {
			return false;
		}

		$sql  = 'INSERT INTO `' . $safe_table . '` (' . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $placeholders ) . ')';

		if ( $update && $primary_key ) {
			$updates = array();
			foreach ( $columns as $col_backticked ) {
				$updates[] = $col_backticked . '=VALUES(' . $col_backticked . ')';
			}
			$sql .= ' ON DUPLICATE KEY UPDATE ' . implode( ', ', $updates );
		}

		$prepared = $wpdb->prepare( $sql, $values );
		return ( false !== $wpdb->query( $prepared ) );
	}/**
	  * Return the number of results found for a given query
	  *
	  * @param  array $args
	  * @return int
	  */
	public function count( $args = array() ) {
		return $this->get_coins( $args, true );
	}

	/**
	 * Delete a transaction from the database based on the order ID
	 *
	 * @param int $order_id The ID of the order to delete the transaction for
	 */
	public function delete_transaction( $order_id ): void {
		if ( $order_id ) {
			global $wpdb;
			$sql = $wpdb->prepare( "DELETE FROM $this->table_name WHERE order_id = %d", (int) $order_id );
			$wpdb->query( $sql );
		}
	}

	/**
	 * Get all transactions by transaciton id.
	 */
	public function cpmw_get_tx_order_id( $tx_id ) {
		global $wpdb;
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT order_id FROM $this->table_name WHERE transaction_id = %s", $tx_id ), ARRAY_A );
		$ids     = wp_list_pluck( $results, 'order_id' );

		return $ids;
	}

	/**
	 * Create the table
	 *
	 * @access  public
	 * @since   1.0
	 */
	public function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// IF NOT EXISTS - condition not required

		$sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table_name . ' (
		id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL ,
        chain_id longtext NOT NULL,
		order_price longtext NOT NULL,
        user_name longtext NOT NULL,
		crypto_price longtext NOT NULL,
        selected_currency longtext NOT NULL,
        chain_name longtext NOT NULL,
        status longtext NOT NULL,
        sender longtext NOT NULL,
        transaction_id varchar(250) NOT NULL UNIQUE,
		last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	    ) CHARACTER SET utf8 COLLATE utf8_general_ci;';

		// $wpdb->query($sql);
		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

	public function drop_table() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . $this->table_name );
	}
}
