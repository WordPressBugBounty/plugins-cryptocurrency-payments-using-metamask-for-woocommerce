<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Cpmw_metamask_list extends WP_List_Table
{

    public function get_columns()
    {
        $columns = array(
            'order_id' => __("Order Id", "cpmwp"),
            'transaction_id' => __("Transaction ID", "cpmwp"),
            'sender' => __("Sender", "cpmwp"),
            'chain_name' => __("Network", "cpmwp"),
            'selected_currency' => __("Coin", "cpmwp"),
            'crypto_price' => __(" Crypto Price", "cpmwp"),
            'order_price' => __("Fiat Price", "cpmwp"),
            'status' => __("Payment Confirmation", "cpmwp"),
            'order_status' => __("Order Status", "cpmwp"),
            'last_updated' => __("Date", "cpmw"),
        );
        return $columns;
    }

    public function prepare_items()
    {

        global $wpdb, $_wp_column_headers;
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        $query = 'SELECT * FROM ' . $wpdb->base_prefix . 'cpmw_transaction';
        $user_search_keyword = isset($_REQUEST['s']) ? wp_unslash(trim($_REQUEST['s'])) : '';
        $status_raw = isset($_REQUEST['payment_status']) ? wp_unslash(trim($_REQUEST['payment_status'])) : '';
        $allowed_statuses = array('awaiting', 'completed', 'unsuccessful');
        $status = in_array($status_raw, $allowed_statuses, true) ? $status_raw : '';
        if ($user_search_keyword !== '') {
            $search_like = '%' . $wpdb->esc_like($user_search_keyword) . '%';
            $query = $wpdb->prepare($query . ' WHERE ( order_id LIKE %s OR chain_name LIKE %s OR selected_currency LIKE %s OR transaction_id LIKE %s )', 
                $search_like, $search_like, $search_like, $search_like);
        } elseif ($status !== '') {
            $query = $wpdb->prepare($query . ' WHERE status = %s', $status);

        }
        // Ordering parameters (whitelist columns and direction to prevent SQL injection)
        $allowed_cols = array('order_id', 'chain_name', 'selected_currency', 'crypto_price', 'order_price', 'last_updated', 'status', 'transaction_id', 'sender');
        $req_orderby  = isset($_REQUEST['orderby']) ? wp_unslash($_REQUEST['orderby']) : '';
        $req_order    = isset($_REQUEST['order']) ? wp_unslash($_REQUEST['order']) : '';

        $orderby = in_array($req_orderby, $allowed_cols, true) ? $req_orderby : 'last_updated';
        $order   = (strtoupper($req_order) === 'ASC') ? 'ASC' : 'DESC';

        if (!empty($orderby) && !empty($order)) {
            $query .= ' ORDER BY ' . $orderby . ' ' . $order;
        }

        // Pagination parameters
        $totalitems = $wpdb->query($query);
        $perpage = 10;
        if (!is_numeric($perpage) || empty($perpage)) {
            $perpage = 10;
        }

        $paged = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 1;
        if ($paged <= 0) { $paged = 1; }
        $totalpages = ceil($totalitems / $perpage);

        if (!empty($paged) && !empty($perpage)) {
            $offset = ($paged - 1) * $perpage;
            $query .= ' LIMIT ' . (int) $offset . ',' . (int) $perpage;
        }

        // Register the pagination & build link
        $this->set_pagination_args(array(
            "total_items" => $totalitems,
            "total_pages" => $totalpages,
            "per_page" => $perpage,
        )
        );

        // Get feedback data from database
        $this->items = $wpdb->get_results($query);

    }

    public function column_default($item, $column_name)
    {
        wp_enqueue_style('woocommerce_admin_styles');
        $order = wc_get_order($item->order_id);
        switch ($column_name) {

            case 'order_id':
                return '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $item->order_id ) . '&action=edit' ) ) . '">#' . esc_html( $item->order_id ) . ' ' . esc_html( $item->user_name ) . '</a>';

            case 'transaction_id':
                if ($item->transaction_id != "false") {
                    $base = '';
                    if ($item->chain_id == '0x61') {
                        $base = 'https://testnet.bscscan.com/tx/';
                    } elseif ($item->chain_id == '0x38') {
                        $base = 'https://bscscan.com/tx/';
                    } elseif ($item->chain_id == '0x1') {
                        $base = 'https://etherscan.io/tx/';
                    } elseif ($item->chain_id == '0x5') {
                        $base = 'https://goerli.etherscan.io/tx/';
                    } elseif ($item->chain_id == '0xaa36a7') {
                        $base = 'https://sepolia.etherscan.io/tx/';
                    }

                    if ($base) {
                        return '<a href="' . esc_url( $base . $item->transaction_id ) . '" target="_blank">' . esc_html( $item->transaction_id ) . '</a>';
                    }
                }
                return ($order) ? esc_html( $order->get_status() ) : false;
                break;

            case 'sender':
                return esc_html( $item->sender );

            case 'chain_name':
                return esc_html( $item->chain_name );

            case 'selected_currency':
                return esc_html( $item->selected_currency );

            case 'crypto_price':
                return esc_html( $item->crypto_price );

            case 'order_price':
                return esc_html( $item->order_price );

            case 'status':        
                // if ($order == false) {
                //     return '<span class="order-status status-deleted tips"><span>Deleted</span></span>';
                // }        
                if ($item->status == 'completed'||$item->status == 'processing') {
                    return '<span class="order-status status-processing tips"><span>' .__('Confirmed','cpmwp') . '</span></span>';
                }
                elseif ($item->status == "awaiting") {
                    return '<span class="order-status status-cancelled tips"><span>' .__('Awaiting','cpmwp') . '</span></span>';
                }
                    elseif ($item->status == "pending"||$item->status == "canceled"||$item->status == "on-hold") {
                    return '<span class="order-status status-cancelled tips"><span>' .__('Unknown','cpmwp') . '</span></span>';
                }else {
                    return '<span class="order-status status-cancelled tips"><span>' .__('Failed','cpmwp') . '</span></span>';
                }
                

            case 'order_status':                
                if ($order == false) {
                    return '<span class="order-status status-deleted tips"><span>Deleted</span></span>';
                }
                if ($order->get_status() == "canceled") {
                    return '<span class="order-status status-cancelled tips"><span>' . esc_html( ucfirst( $order->get_status() ) ) . '</span></span>';
                } elseif ($order->get_status() == "completed") {
                    return '<span class="order-status status-completed tips"><span>' . esc_html( ucfirst( $order->get_status() ) ) . '</span></span>';
                } elseif ($order->get_status() == "processing") {
                    return '<span class="order-status status-processing tips"><span>' . esc_html( ucfirst( $order->get_status() ) ) . '</span></span>';
                } elseif ($order->get_status() == "on-hold") {
                    return '<span class="order-status status-on-hold tips"><span>' . esc_html( ucfirst( $order->get_status() ) ) . '</span></span>';
                } else {
                    return '<span class="order-status status-cancelled tips"><span>' . esc_html( ucfirst( $order->get_status() ) ) . '</span></span>';
                }
            case 'last_updated':
                if ($order == false) {
                    return esc_html( $item->last_updated );
                }
                return $this->timeAgo($order);
            default:
                return esc_html( print_r( $item, true ) ); //Show the whole array for troubleshooting purposes
        }
    }

    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'order_id' => array('order_id', false),
            'chain_name' => array('chain_name', false),
            'selected_currency' => array('selected_currency', false),
            'crypto_price' => array('crypto_price', false),
            'order_price' => array('order_price', false),
            'last_updated' => array('last_updated', false),
        );
        return $sortable_columns;
    }

    public function timeAgo($order)
    {       
        $order_date = $order->get_date_created();
        $time_ago = $order_date->getTimestamp();
        $time_difference = time() -$time_ago;

        if ($time_difference < 60) {
            return $time_difference.' seconds ago';
        } elseif ($time_difference >= 60 && $time_difference < 3600) {
            $minutes = round($time_difference / 60);
            return ($minutes == 1) ? '1 minute' : $minutes . ' minutes ago';
        } elseif ($time_difference >= 3600 && $time_difference < 86400) {
            $hours = round($time_difference / 3600);
            return ($hours == 1) ? '1 hour ago' : $hours . ' hours ago';
        } elseif ($time_difference >= 86400) {
            if (round($time_difference / 86400) == 1) {
                return date_i18n('M j, Y', $time_ago);
            } else {
                return date_i18n('M j, Y', $time_ago);
            }
        }
    }

}
