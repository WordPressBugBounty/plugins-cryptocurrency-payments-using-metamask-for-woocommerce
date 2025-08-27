<?php

if( !class_exists('Cpmw_Review_Notice')):
    class Cpmw_Review_Notice{
        CONST PLUGIN = 'Cryptocurrency Payments Using MetaMask For WooCommerce';
        CONST SLUG = 'cpmw'; 
        CONST LOGO = CPMW_URL . 'assets/images/icon-256x256.png';
        CONST SPARE_ME = 'cpmw_spare_me';
        CONST ACTIVATE_TIME = 'cpmw_activation_time';
        CONST REVIEW_LINK = 'https://wordpress.org/plugins/cryptocurrency-payments-using-metamask-for-woocommerce/#reviews';
        CONST AJAX_REQUEST = 'cpmw_dismiss_notice';
    }
endif;

if (!class_exists('CPMW_Review_Class')) {
    class CPMW_Review_Class {
        /**
         * The Constructor
         */
        public function __construct() {
            // register actions
         
            if(is_admin()){
                add_action( 'admin_notices',array($this,'atlt_admin_notice_for_reviews'));
                add_action( 'wp_ajax_'.Cpmw_Review_Notice::AJAX_REQUEST ,array($this,'atlt_dismiss_review_notice' ) );
            }
        }

    // ajax callback for review notice
    public function atlt_dismiss_review_notice(){
        // Capability check to restrict to admins who can manage plugins/options
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'error' => 'Unauthorized' ), 403 );
        }

        // Nonce verification to prevent CSRF
        check_ajax_referer( 'cpmw_review_nonce', 'nonce' );

        update_option( Cpmw_Review_Notice::SPARE_ME, 'yes' );
        wp_send_json_success();
    }
   // admin notice  
    public function atlt_admin_notice_for_reviews(){
        
        // Use consistent capability check with AJAX handler
        if( !current_user_can( 'manage_options' ) ){
            return;
         }
        

         $activation_time = get_option( Cpmw_Review_Notice::ACTIVATE_TIME );
         if ( false === $activation_time || empty( $activation_time ) ) {
             return;
         }
         // get installation date based on saved activation timestamp
         $installation_date = date( 'Y-m-d h:i:s', (int) $activation_time );
       
         // check user already rated 
        if(get_option( Cpmw_Review_Notice::SPARE_ME )) {
            return;
           }           

            // grab plugin installation date and compare it with current date
            $display_date = date( 'Y-m-d h:i:s' );
            $install_date= new DateTime( $installation_date );
            $current_date = new DateTime( $display_date );
            $difference = $install_date->diff($current_date);
            $diff_days= $difference->days;
          
            // check if installation days is greator then week
			if (isset($diff_days) && $diff_days>=3) {
                echo $this->atlt_create_notice_content();
             }
       }  

       // generated review notice HTML
       function atlt_create_notice_content(){
        
        $ajax_url=admin_url( 'admin-ajax.php' );
        $ajax_callback = Cpmw_Review_Notice::AJAX_REQUEST ;
        $wrap_cls="notice notice-info is-dismissible";
        $img_path= Cpmw_Review_Notice::LOGO;
        $nonce = wp_create_nonce( 'cpmw_review_nonce' );

        $p_name = Cpmw_Review_Notice::PLUGIN ;
        $like_it_text='Rate Now! ★★★★★';
        $already_rated_text=esc_html__( 'I already rated it', 'atlt2' );
        $not_like_it_text=esc_html__( 'Not Interested', 'atlt2' );
        $p_link=esc_url( Cpmw_Review_Notice::REVIEW_LINK );

        $message = sprintf(
            'Thanks for using <b>%s</b> -WordPress plugin.
            We hope you liked it! <br/>Please give us a quick rating, it works as a boost for us to keep working on more <a href="%s" target="_blank" rel="noopener noreferrer"><strong>Cool Plugins</strong></a>!<br/>',
            esc_html( $p_name ),
            esc_url( 'https://coolplugins.net' )
        );
        $message = wp_kses_post( $message );

        // Escape variables per context for safe output in attributes/HTML
        $slug = sanitize_html_class( Cpmw_Review_Notice::SLUG );
        $slug_js = esc_js( $slug );
        $wrapper_class_attr = esc_attr( $slug . '-feedback-notice-wrapper ' . $wrap_cls );
        $img_url = esc_url( $img_path );
        $img_alt = esc_attr( $p_name );
        $ajax_url_attr = esc_url( $ajax_url );
        $ajax_callback_attr = esc_attr( $ajax_callback );
        $nonce_attr = esc_attr( $nonce );
        $like_it_title_attr = esc_attr( $like_it_text );
        $like_it_text_html = esc_html( $like_it_text );
        $already_rated_title_attr = esc_attr( $already_rated_text );
        $already_rated_text_html = esc_html( $already_rated_text );
        $not_like_title_attr = esc_attr( $not_like_it_text );
        $not_like_text_html = esc_html( $not_like_it_text );
        $dismiss_btn_class_attr = esc_attr( 'already_rated_btn button ' . $slug . '_dismiss_notice' );
      
        $html='<div data-ajax-url="%8$s" data-ajax-callback="%9$s" data-nonce="%11$s" class="%1$s">
        <div class="logo_container"><a href="%5$s"><img src="%2$s" alt="%3$s" style="max-width:80px;"></a></div>
        <div class="message_container">%4$s
        <div class="callto_action">
        <ul>
            <li class="love_it"><a href="%5$s" class="like_it_btn button button-primary" target="_new" rel="noopener noreferrer" title="%12$s">%13$s</a></li>
            <li class="already_rated"><a href="javascript:void(0);" class="%18$s" title="%14$s">%15$s</a></li>  
            <li class="already_rated"><a href="javascript:void(0);" class="%18$s" title="%16$s">%17$s</a></li>           
        </ul>
        <div class="clrfix"></div>
        </div>
        </div>
        </div>';
        
        $style = '<style>.'.$slug.'-feedback-notice-wrapper.notice.notice-info.is-dismissible {
            padding: 5px;
            display: table;
            width: fit-content;
            max-width: 855px;
            clear: both;
            border-radius: 5px;
            border: 2px solid #b7bfc7;
        }
        .'.$slug.'-feedback-notice-wrapper .logo_container {
            width: 100px;
            display: table-cell;
            padding: 5px;
            vertical-align: middle;
        }
        .'.$slug.'-feedback-notice-wrapper .logo_container a,
        .'.$slug.'-feedback-notice-wrapper .logo_container img {
            width:fit-content;
            height:auto;
            display:inline-block;
        }
        .'.$slug.'-feedback-notice-wrapper .message_container {
            display: table-cell;
            padding: 5px 25px 5px 5px;
            vertical-align: middle;
        }
        .'.$slug.'-feedback-notice-wrapper ul li {
            float: left;
            margin: 0px 10px 0 0;
        }
        .'.$slug.'-feedback-notice-wrapper ul li.already_rated a:after {
            color: #e86011;
            content: "\f153";
            display: inline-block;
            vertical-align: middle;
            margin: -1px 0 0 5px;
            font-size: 15px;
            font-family: dashicons;
        }
        .'.$slug.'-feedback-notice-wrapper ul li .button-primary {
            background: #e86011;
            text-shadow: none;
            border-color: #943b07;
            box-shadow: none;
        }
        .'.$slug.'-feedback-notice-wrapper ul li .button-primary:hover {
            background: #222;
            border-color: #000;
        }
        .'.$slug.'-feedback-notice-wrapper a {
            color: #008bff;
        }
        
        /* This css is for license registration page */
        .'.$slug.'-notice-red.uninstall {
            max-width: 700px;
            display: block;
            padding: 8px;
            border: 2px solid #157d0f;
            margin: 10px 0;
            background: #13a50b;
            font-weight: bold;
            font-size: 13px;
            color: #ffffff;
        }
        .clrfix{
            clear:both;
        }</style>';

        $script = '<script>
        jQuery(document).ready(function ($) {
            $(".'.$slug_js.'_dismiss_notice").on("click", function (event) {
                var $this = $(this);
                var wrapper=$this.parents(".'.$slug_js.'-feedback-notice-wrapper");
                var ajaxURL=wrapper.data("ajax-url");
                var ajaxCallback=wrapper.data("ajax-callback");
                var nonce=wrapper.data("nonce");
                
                $.post(ajaxURL, { action: ajaxCallback, nonce: nonce }, function( data ) {
                    wrapper.slideUp("fast");
                  }, "json");
        
            });
        });
        </script>';

        $html .= '
        '.$style.'
        '.$script;

 return sprintf($html,
        $wrapper_class_attr,
        $img_url,
        $img_alt,
        $message,
        $p_link,
        $like_it_text,
        $already_rated_text,
        $ajax_url_attr,// 8
        $ajax_callback_attr,//9
        $not_like_it_text,//10
        $nonce_attr, // 11
        $like_it_title_attr, // 12
        $like_it_text_html, // 13
        $already_rated_title_attr, // 14
        $already_rated_text_html, // 15
        $not_like_title_attr, // 16
        $not_like_text_html, // 17
        $dismiss_btn_class_attr // 18
        );
        
       }

    } //class end
    new CPMW_Review_Class();
} 