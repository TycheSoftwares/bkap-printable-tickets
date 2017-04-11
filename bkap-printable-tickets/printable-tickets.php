<?php 
/*
Plugin Name: Printable Tickets Addon
Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/printable-tickets-addon-woocommerce-booking-appointment-plugin/
Description: This is an addon for the WooCommerce Booking & Appointment Plugin which allows you to email the tickets for the bookings to customers when an order is placed.
Version: 1.5
Author: Tyche Softwares
Author URI: http://www.tychesoftwares.com/
*/

/*require 'plugin-updates/plugin-update-checker.php';
$ExampleUpdateChecker = new PluginUpdateChecker(
	'http://www.tychesoftwares.com/plugin-updates/bkap-printable-ticket/info.json',
	__FILE__
);*/
global $PrintTicketUpdateChecker;
$PrintTickerUpdateChecker = '1.5';

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'EDD_SL_STORE_URL_PRINT_TICKET_BOOK', 'http://www.tychesoftwares.com/' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

// the name of your product. This is the title of your product in EDD and should match the download title in EDD exactly
define( 'EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK', 'Printable Tickets Addon for WooCommerce Booking & Appointment Plugin' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system

if( !class_exists( 'EDD_PRINT_TICKET_BOOK_Plugin_Updater' ) ) {
	// load our custom updater if it doesn't already exist
	include( dirname( __FILE__ ) . '/plugin-updates/EDD_PRINT_TICKET_BOOK_Plugin_Updater.php' );
}

// retrieve our license key from the DB
$license_key = trim( get_option( 'edd_sample_license_key_print_ticket_book' ) );

// setup the updater
$edd_updater = new EDD_PRINT_TICKET_BOOK_Plugin_Updater( EDD_SL_STORE_URL_PRINT_TICKET_BOOK, __FILE__, array(
		'version' 	=> '1.5', 		// current version number
		'license' 	=> $license_key, 	// license key (used get_option above to retrieve from DB)
		'item_name' => EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK, 	// name of this plugin
		'author' 	=> 'Ashok Rane'  // author of this plugin
)
);
register_uninstall_hook( __FILE__, 'woocommerce_booking_meta_delete');

function woocommerce_booking_meta_delete() {
	global $wpdb;
	$table_name_booking_meta = $wpdb->prefix . "booking_item_meta";
	$sql_table_name_booking_meta = "DROP TABLE " . $table_name_booking_meta;
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$wpdb->get_results($sql_table_name_booking_meta);
}

function is_bkap_tickets_active() {
	if (is_plugin_active('bkap-printable-tickets/printable-tickets.php')) {
		return true;
	}
	else {
		return false;
	}
}
//register_uninstall_hook( __FILE__, 'bkap_unavailability_period_delete');

//if (is_woocommerce_active())
{
	/**
	 * Localisation
	 **/
	load_plugin_textdomain('printable-tickets', false, dirname( plugin_basename( __FILE__ ) ) . '/');

	/**
	 * printable_tickets class
	 **/
	if (!class_exists('printable_tickets')) {

		class printable_tickets {
				
		public function __construct() {
				$this->headings = array('ticket_number' => 'Ticket# ',
					'booking_details' => 'Booking Details',
					'buyer' => 'Buyer',
					'security_code' => 'Security Code'
				);
				// Initialize settings
				register_activation_hook( __FILE__, array(&$this, 'printable_ticket_activate'));
				add_action( 'admin_notices', array( &$this, 'printable_ticket_error_notice' ) );
				// used to add new settings on the product page booking box
			add_action('bkap_add_addon_settings', array( &$this, 'bkap_show_printable_ticket_settings' ), 10 );
			add_action('admin_init', array( &$this, 'bkap_printable_ticket_plugin_options' ) );
				add_filter('bkap_send_ticket', array(&$this, 'bkap_send_ticket_content'), 10, 2);
				add_action('bkap_send_email', array(&$this,'bkap_send_ticket_email'),10,1);
				add_action('woocommerce_order_status_completed' , array(&$this,'woocommerce_complete_order'),10,1);
				add_action('bkap_add_submenu',array(&$this, 'printable_ticket_menu'));
				// Add columns headers in the View Bookings page
				add_filter( 'bkap_view_bookings_table_columns' , array(&$this,'bkap_printable_tickets_column_name'),10,1);
				// Add column values
				add_filter( 'bkap_bookings_table_data' ,array(&$this,'bkap_printable_tickets_column_value'),10,1);
				// Add data in CSV and Print files array
				add_filter('bkap_bookings_export_data',array(&$this,'bkap_printable_export_data'),10,1);
				// CSV file
				add_filter('bkap_bookings_csv_data',array(&$this,'bkap_printable_csv_data'),10,2);
				// Add filter for adding columns to the print data
				add_filter('bkap_view_bookings_print_columns',array(&$this,'bkap_printable_tickets_add_print_columns'),10,1);
				// Add filter for adding column data to the print data
				add_filter('bkap_view_bookings_print_rows',array(&$this,'bkap_printable_tickets_add_print_rows'),10,2);
				add_action('admin_init', array(&$this, 'edd_sample_register_option_print_ticket'));
				add_action('admin_init', array(&$this, 'edd_sample_deactivate_license_print_ticket'));
				add_action('admin_init', array(&$this, 'edd_sample_activate_license_print_ticket'));
			}
			
			function printable_ticket_error_notice() {
			    if ( !is_plugin_active( 'woocommerce-booking/woocommerce-booking.php' ) ) {
			        echo "<div class=\"error\"><p>Printable Ticket Addon is enabled but not effective. It requires WooCommerce Booking and Appointment plugin in order to work.</p></div>";
			    }
			}
			
			function edd_sample_activate_license_print_ticket() {
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_print_ticket_license_activate'] ) ) {
					//exit;
					// run a quick security check
					if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
						return; // get out if we didn't click the Activate button
						
					// retrieve the license from the database
					$license = trim( get_option('edd_sample_license_key_print_ticket_book' ) );
			
						// data to send in our API request
					$api_params = array(
							'edd_action'=> 'activate_license',
							'license' 	=> $license,
							'item_name' => urlencode( EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK ) // the name of our product in EDD
					);
						
					// Call the custom API.
					$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_PRINT_TICKET_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
						//print_r($response);exit;
					// make sure the response came back okay
					if ( is_wp_error( $response ) )
						return false;
						
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );
					//print_r($license_data);exit;	
					// $license_data->license will be either "active" or "inactive"
						
					update_option( 'edd_sample_license_status_print_ticket_book', $license_data->license );
						
				}
			}
			/***********************************************
			 * Illustrates how to deactivate a license key.
			* This will descrease the site count
			***********************************************/
				
			function edd_sample_deactivate_license_print_ticket()
			{
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_print_ticket_license_deactivate'] ) ) 
				{
					// run a quick security check
					if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) )
						return; // get out if we didn't click the Activate button
						
					// retrieve the license from the database
					$license = trim( get_option( 'edd_sample_license_key_print_ticket_book' ) );
			
						
					// data to send in our API request
					$api_params = array(
							'edd_action'=> 'deactivate_license',
							'license' 	=> $license,
							'item_name' => urlencode( EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK ) // the name of our product in EDD
					);
						
					// Call the custom API.
					$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_PRINT_TICKET_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
						
					// make sure the response came back okay
					if ( is_wp_error( $response ) )
						return false;
						
					// decode the license data
					$license_data = json_decode( wp_remote_retrieve_body( $response ) );
						
					// $license_data->license will be either "deactivated" or "failed"
					if( $license_data->license == 'deactivated' )
						delete_option( 'edd_sample_license_status_print_ticket_book' );
						
				}
			}
			/************************************
			 * this illustrates how to check if
			* a license key is still valid
			* the updater does this for you,
			* so this is only needed if you
			* want to do something custom
			*************************************/
				
			function edd_sample_check_license_print_ticket() 
			{
				global $wp_version;
					
				$license = trim( get_option( 'edd_sample_license_key_print_ticket_book' ) );
					
				$api_params = array(
						'edd_action' => 'check_license',
						'license' => $license,
						'item_name' => urlencode( EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK )
				);
					
				// Call the custom API.
				$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_PRINT_TICKET_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );
					
					
				if ( is_wp_error( $response ) )
					return false;
					
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
					
				if( $license_data->license == 'valid' ) {
					echo 'valid'; exit;
					// this license is still valid
				} else {
					echo 'invalid'; exit;
					// this license is no longer valid
				}
			}	
			
			function edd_sample_register_option_print_ticket() 
			{
				// creates our settings in the options table
				register_setting('edd_print_ticket_license', 'edd_sample_license_key_print_ticket_book', array(&$this, 'edd_sanitize_license_print_ticket' ));
			}
				
				
			function edd_sanitize_license_print_ticket( $new ) 
			{
				$old = get_option( 'edd_sample_license_key_print_ticket_book' );
				if( $old && $old != $new ) {
					delete_option( 'edd_sample_license_status_print_ticket_book' ); // new license has been entered, so must reactivate
				}
				return $new;
			}
				
			function edd_sample_license_page_print_ticket() 
			{
				$license 	= get_option( 'edd_sample_license_key_print_ticket_book' );
				$status 	= get_option( 'edd_sample_license_status_print_ticket_book' );
					
				?>
										<div class="wrap">
											<h2><?php _e('Plugin License Options'); ?></h2>
											<form method="post" action="options.php">
											
												<?php settings_fields('edd_print_ticket_license'); ?>
												
												<table class="form-table">
													<tbody>
														<tr valign="top">	
															<th scope="row" valign="top">
																<?php _e('License Key'); ?>
															</th>
															<td>
																<input id="edd_sample_license_key_print_ticket_book" name="edd_sample_license_key_print_ticket_book" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
																<label class="description" for="edd_sample_license_key"><?php _e('Enter your license key'); ?></label>
															</td>
														</tr>
														<?php if( false !== $license ) { ?>
															<tr valign="top">	
																<th scope="row" valign="top">
																	<?php _e('Activate License'); ?>
																</th>
																<td>
																	<?php if( $status !== false && $status == 'valid' ) { ?>
																		<span style="color:green;"><?php _e('active'); ?></span>
																		<?php wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
																		<input type="submit" class="button-secondary" name="edd_print_ticket_license_deactivate" value="<?php _e('Deactivate License'); ?>"/>
																	<?php } else {
																		wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
																		<input type="submit" class="button-secondary" name="edd_print_ticket_license_activate" value="<?php _e('Activate License'); ?>"/>
																	<?php } ?>
																</td>
															</tr>
														<?php } ?>
													</tbody>
												</table>	
												<?php submit_button(); ?>
											
											</form>
										<?php
									}
			
			function printable_ticket_menu() {
				$page = add_submenu_page('booking_settings', __( 'Activate Printable Ticket License', 'woocommerce-booking' ), __( 'Activate Printable Ticket License', 'woocommerce-booking' ), 'manage_woocommerce', 'print_ticket_license_page', array(&$this, 'edd_sample_license_page_print_ticket' ));
			}
									
			function printable_ticket_activate() {
				global $wpdb;
				$table_name = $wpdb->prefix . "booking_item_meta";
				$sql = "CREATE TABLE IF NOT EXISTS $table_name (
						`booking_meta_id` int(11) NOT NULL AUTO_INCREMENT,
						`order_id` int(11) NOT NULL,
						`booking_id` int(11) NOT NULL,
						`booking_meta_key` varchar(255) NOT NULL,
  						`booking_meta_value` longtext NOT NULL,
						PRIMARY KEY (`booking_meta_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1" ;
				require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
				dbDelta($sql);
			}
			
		function bkap_show_printable_ticket_settings() {
		    if ( isset( $_GET[ 'action' ] ) ) {
		        $action = $_GET[ 'action' ];
		    } else {
		        $action = '';
		    }
		    
		    if ( 'addon_settings' == $action ) {
		        ?>
   				<div id="content">
   					<form method="post" action="options.php">
				    <?php settings_fields( 'bkap_printable_tickets_settings' ); ?>
			        <?php do_settings_sections( 'woocommerce_booking_page-bkap_printable_tickets_settings_section' ); ?> 
					<?php submit_button(); ?>
   			        </form>
   			    </div>
                <?php 
            }
		}
            
		function bkap_printable_ticket_plugin_options() {
		    add_settings_section (
                'bkap_printable_tickets_settings_section',         // ID used to identify this section and with which to register options
                __( 'Printable Tickets Addon Settings', 'printable-tickets' ),                  // Title to be displayed on the administration page
                array( $this, 'bkap_printable_tickets_callback' ), // Callback used to render the description of the section
                'woocommerce_booking_page-bkap_printable_tickets_settings_section'     // Page on which to add this section of options
		    );
		    
		    add_settings_field (
                'booking_printable_ticket',
                __( 'Send tickets via email:', 'printable-tickets' ),
                array( &$this, 'booking_printable_ticket_callback' ),
                'woocommerce_booking_page-bkap_printable_tickets_settings_section',
                'bkap_printable_tickets_settings_section',
                array( __( 'Allow customers to send ticket to email when an order is placed.', 'printable-tickets' ) )
		    );
		    
		    add_settings_field (
                'booking_send_ticket_method',
                __( 'Ticket Sending Method:', 'printable-tickets' ),
                array( &$this, 'booking_send_ticket_method_callback' ),
                'woocommerce_booking_page-bkap_printable_tickets_settings_section',
                'bkap_printable_tickets_settings_section',
                array( __( 'Enable Send 1 ticket per quantity to send ticket for each product and each quantity of product in order and Send 1 ticket per product to send each ticket per product in order.', 'printable-tickets' ) )
		    );
		    
		    register_setting ( 
                'bkap_printable_tickets_settings',
                'booking_printable_ticket'
		    );
		    
		    register_setting (
                'bkap_printable_tickets_settings',
                'booking_send_ticket_method'
		    );
		}
			
        function bkap_printable_tickets_callback() { }
			
		function booking_printable_ticket_callback( $args ) {
		    $printable_ticket = "";
		    if( get_option( 'booking_printable_ticket' ) == 'on' ) {
		        $printable_ticket = 'checked';
		    }
		    echo '<input type="checkbox" id="booking_printable_ticket" name="booking_printable_ticket"' . $printable_ticket .'/>';
		    $html = '<label for="booking_printable_ticket"> ' . $args[ 0 ] . '</label>';
		    echo $html;
		}

		function booking_send_ticket_method_callback( $args ) {
		    $send_by_order = "";
		    if( get_option( 'booking_send_ticket_method' ) == "send_by_product" ) {
		        $send_by_order = "checked";
		        $send_individually = "";
		    } else {
		        $send_by_order = "";
		        $send_individually = "checked";
		    }

		    ?>
            <p><label><input type="radio" name="booking_send_ticket_method" id="booking_send_ticket_method" value="send_by_quantity" <?php echo $send_individually; ?>/><?php _e( 'Send 1 ticket per quantity&nbsp&nbsp&nbsp&nbsp&nbsp;', 'printable-tickets' ) ;?></label>
            <label><input type="radio" name="booking_send_ticket_method" id="booking_send_ticket_method" value="send_by_product" <?php echo $send_by_order; ?>/><?php _e( 'Send 1 ticket per product', 'printable-tickets' ) ;?></label></p>
		    <?php 
		    $html = '<label for="booking_send_ticket_method"> ' . $args[ 0 ] . '</label>';
		    echo $html;
		}
			
		function assign_rand_value($num) { 
			// accepts 1 - 36
			switch($num) {
				case "1":
					$rand_value = "a";
					 break;
				case "2":
					$rand_value = "b";
					break;
				case "3":
					$rand_value = "c";
					 break;
				case "4":
					$rand_value = "d";
					break;
				case "5":
					$rand_value = "e";
					break;
				case "6":
					$rand_value = "f";
					break;
				case "7":
					$rand_value = "g";
					break;
				case "8":
					$rand_value = "h";
					break;
				case "9":
					$rand_value = "i";
					break;
				case "10":
					$rand_value = "j";
					break;
				case "11":
					$rand_value = "k";
                    break;
                case "12":
                    $rand_value = "l";
                    break;
                case "13":
                    $rand_value = "m";
                    break;
                case "14":
                    $rand_value = "n";
                    break;
                case "15":
                    $rand_value = "o";
                    break;
                case "16":
                    $rand_value = "p";
                    break;
                case "17":
                    $rand_value = "q";
                    break;
                case "18":
                    $rand_value = "r";
                    break;
                case "19":
                    $rand_value = "s";
                    break;
                case "20":
                    $rand_value = "t";
                    break;
                case "21":
                    $rand_value = "u";
                    break;
                case "22":
                    $rand_value = "v";
                    break;
                case "23":
                    $rand_value = "w";
                    break;
                case "24":
                    $rand_value = "x";
                    break;
                case "25":
                    $rand_value = "y";
                    break;
                case "26":
                    $rand_value = "z";
                    break;
                case "27":
                    $rand_value = "0";
                    break;
                case "28":
                    $rand_value = "1";
                    break;
                case "29":
                    $rand_value = "2";
                    break;
                case "30":
                    $rand_value = "3";
                    break;
                case "31":
                    $rand_value = "4";
                    break;
                case "32":
                    $rand_value = "5";
                    break;
                case "33":
                    $rand_value = "6";
                    break;
                case "34":
                    $rand_value = "7";
                    break;
                case "35":
                    $rand_value = "8";
                    break;
                case "36":
                    $rand_value = "9";
                    break;
            }
            return $rand_value;
        }
        
        function get_rand_id($length) {
			 if($length>0) { 
				$rand_id="";
				for($i=1; $i<=$length; $i++) {
					 mt_srand((double)microtime() * 1000000);
					$num = mt_rand(1,36);
					$rand_id .= $this->assign_rand_value($num);
				}
			}
			return $rand_id;
        }
				 
				function bkap_send_ticket_content($values,$order) {
					global $wpdb;
                    $order_status = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->status : $order->get_status();
					if( $order_status == 'completed') {
						$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
						$booking_settings = get_post_meta( $values['product_id'], 'woocommerce_booking_settings', true);
				if( get_option( 'booking_printable_ticket' )  == 'on') {
							if(isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on') {
								if(array_key_exists('data',$values) ) {
									$_product = $values['data'];
									$product_name = $_product->get_title();
								}
								else {
									$product_name = $values['name'];
								}
								$product_id = $values['product_id'];
								$from_email = get_option('admin_email');
								$buyers_firstname = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->billing_first_name : $order->get_billing_first_name();
								$buyers_lastname = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->billing_last_name : $order->get_billing_last_name();
								$to = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->billing_email : $order->get_billing_email();
								$post_id = $values['product_id'];
								$headers_email[] = "From:".$from_email;
								$headers_email[] = "Content-type: text/html";
								
								$order_id = ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) ? $order->id : $order->get_id();
								if ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) {
								    $completed_date = date('F j, Y',strtotime($order->completed_date));
								} else {
								    $order_post = get_post( $order_id );
								    $post_date = strtotime ( $order_post->post_date );
								    $completed_date = date( 'Y-m-d H:i:s', $post_date );
								}
								
								$subject = "Your Ticket for Order #".$order_id." from ".$completed_date;
								
								$logo = get_header_image();
								$message = '';
								$booking = '';
								$addon = '';
								$site_url = get_site_url();
								$site_title = get_option('blogname');
								$site_tagline = get_option('blogdescription'); 
								if(array_key_exists('bkap_booking',$values) ) {
									$bookings = $values['bkap_booking'];
											
									if (array_key_exists('date',$bookings[0]) && $bookings[0]['date'] != "") {
										$booking_date = date('d F, Y',strtotime($bookings[0]["hidden_date"]));
								$booking = get_option("book_item-meta-date").': '.$booking_date.'<br>';
									}
									if (array_key_exists('date_checkout',$bookings[0]) && $bookings[0]['date_checkout'] != "") {
										$booking_date_checkout = date('d F, Y',strtotime($bookings[0]["hidden_date_checkout"]));
								$booking .= get_option("checkout_item-meta-date").': '.$booking_date_checkout.'<br>';
									}
									if (array_key_exists('time_slot',$bookings[0]) && $bookings[0]['time_slot'] != "") {
								$booking .= get_option("book_item-meta-time").': '. $bookings[0]["time_slot"].'<br>';
									}
									$hidden_date = $bookings[0]['hidden_date'];
									$date_query = date('Y-m-d', strtotime($hidden_date));
									$booking_id = array();
									
									$booking_id_query = "SELECT booking_id FROM `".$wpdb->prefix."booking_order_history`
															WHERE order_id = %d";
									$booking_id_results = $wpdb->get_results($wpdb->prepare($booking_id_query,$order_id));
									// This is to figure out for which Item in the order are tickets to be created for.	
									foreach ($booking_id_results as $k => $v) {
										
										$booking_id_to_use_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
																	WHERE id = %d
																	AND post_id = %d";
								
										$booking_id_to_use = $wpdb->get_results($wpdb->prepare($booking_id_to_use_query,$v->booking_id,$product_id));
									
										if (count($booking_id_to_use) > 0) {
											break;
										}
									}
								}
								if (function_exists('is_bkap_tours_active') && is_bkap_tours_active()) {
									if(isset($booking_settings['show_tour_operator']) && $booking_settings['show_tour_operator'] == 'on') {
										$booking_tour_operator = $booking_settings["booking_tour_operator"];
										$user = get_userdata( $booking_tour_operator );
										if(isset($user->user_login)) {
											$booking.= 'Tour Operator: '.$user->user_login.'<br>';
										}
										if(isset($booking_settings['booking_show_comment']) && $booking_settings['booking_show_comment'] == 'on') {
											$booking.= bkap_get_book_t('book.item-comments').': '.$bookings[0]['comments'].'<br>';
										}
									}
								}
							
								$f = 0;
								if(is_plugin_active('woocommerce-product-addons/product-addons.php')) {
									$addons = $values['addons'];
									foreach($addons as $key ) {
										$addon .= $addons[$f]["name"].': '.$addons[$f]["value"].'<br>';
										$f++;		
									}
								}
								$instructions = get_post_meta($values['product_id'],'instructions');
							
						if( get_option( 'booking_send_ticket_method' ) == 'send_by_quantity') {
									$quantity = $values['quantity'];
									foreach($booking_id_to_use as $b_key => $b_val) {
										for($i=0;$i<$quantity;$i++) {
											$ticket_sql = "SELECT MAX(CAST(booking_meta_value AS unsigned)) AS ticket_id FROM `".$wpdb->prefix."booking_item_meta` WHERE booking_meta_key = '_ticket_id'";
											
											$ticket_results = $wpdb->get_results($ticket_sql);
											$ticket_no = $ticket_results[0]->ticket_id;
											if($ticket_no == '') {
												$ticket_no = 1;
											}
											else {
												$ticket_no = $ticket_no + 1;
											}
											
											$security_unique_no = $this->get_rand_id(10);
											//get the content
											$message .= $this->get_template();
												
											$message = str_replace( '{{site_title}}', $site_title, $message );
											$message = str_replace( '{{site_tagline}}', $site_tagline, $message );
											$message = str_replace( '{{product_name}}', $product_name, $message );
											$message = str_replace( '{{heading_ticket_number}}', $this->headings[ "ticket_number" ], $message );
											$message = str_replace( '{{ticket_no}}', $ticket_no, $message );
											$message = str_replace( '{{headings_booking_details}}', $this->headings[ "booking_details" ], $message );
											$message = str_replace( '{{booking}}', $booking, $message );
											$message = str_replace( '{{addon}}', $addon, $message );
											$message = str_replace( '{{headings_buyer}}', $this->headings[ "buyer" ], $message );
											$message = str_replace( '{{buyers_firstname}}', $buyers_firstname, $message );
											$message = str_replace( '{{buyers_lastname}}', $buyers_lastname, $message );
											$message = str_replace( '{{headings_security_code}}', $this->headings[ "security_code" ], $message );
											$message = str_replace( '{{security_unique_no}}', $security_unique_no, $message );
											$message = str_replace( '{{site_url}}', $site_url, $message );
																		
											$query_ticket= "INSERT INTO `".$wpdb->prefix."booking_item_meta`
															(order_id,booking_id,booking_meta_key,booking_meta_value)
															VALUES (
															'".$order_id."',
															'".$b_val->id."',
															'_ticket_id',
															'".$ticket_no."')";
															$wpdb->query($query_ticket );
											$query_security_code = "INSERT INTO `".$wpdb->prefix."booking_item_meta`
															(order_id,booking_id,booking_meta_key,booking_meta_value)
															VALUES (
															'".$order_id."',
															'".$b_val->id."',
															'_security_code',
															'".$security_unique_no."')";
											$wpdb->query($query_security_code );
										}
									}
								}
						else if( get_option( 'booking_send_ticket_method' ) == 'send_by_product') {
									$ticket_sql = "SELECT MAX(CAST(booking_meta_value AS unsigned)) AS ticket_id FROM `".$wpdb->prefix."booking_item_meta` WHERE booking_meta_key = '_ticket_id'";
									$ticket_results = $wpdb->get_results($ticket_sql);
									
									$ticket_no = $ticket_results[0]->ticket_id;
								
									if($ticket_no == '') {
										$ticket_no = 1;
									}
									else {
										$ticket_no = $ticket_no + 1;
									}
									$security_unique_no = $this->get_rand_id(10);
									//get the content
									$message = $this->get_template();
									
									$message = str_replace( '{{site_title}}', $site_title, $message );
									$message = str_replace( '{{site_tagline}}', $site_tagline, $message );
									$message = str_replace( '{{product_name}}', $product_name, $message ); 
									$message = str_replace( '{{heading_ticket_number}}', $this->headings[ "ticket_number" ], $message );
									$message = str_replace( '{{ticket_no}}', $ticket_no, $message );
									$message = str_replace( '{{headings_booking_details}}', $this->headings[ "booking_details" ], $message );
									$message = str_replace( '{{booking}}', $booking, $message );
									$message = str_replace( '{{addon}}', $addon, $message );
									$message = str_replace( '{{headings_buyer}}', $this->headings[ "buyer" ], $message );
									$message = str_replace( '{{buyers_firstname}}', $buyers_firstname, $message );
									$message = str_replace( '{{buyers_lastname}}', $buyers_lastname, $message );
									$message = str_replace( '{{headings_security_code}}', $this->headings[ "security_code" ], $message );
									$message = str_replace( '{{security_unique_no}}', $security_unique_no, $message );
									$message = str_replace( '{{site_url}}', $site_url, $message );
									$j = 0;
									foreach($booking_id_to_use as $b_key => $b_val) {
										$query_ticket= "INSERT INTO `".$wpdb->prefix."booking_item_meta`
														(order_id,booking_id,booking_meta_key,booking_meta_value)
														VALUES (
														'".$order_id."',
														'".$b_val->id."',
														'_ticket_id',
														'".$ticket_no."')";
														$wpdb->query( $query_ticket );
										$query_security_code = "INSERT INTO `".$wpdb->prefix."booking_item_meta`
														(order_id,booking_id,booking_meta_key,booking_meta_value)
														VALUES (
														'".$order_id."',
														'".$b_val->id."',
														'_security_code',
														'".$security_unique_no."')";
										$wpdb->query(  $query_security_code );
										$j++;
									}
								}
								$ticket[] = array('to'=>$to, 'subject' => $subject, 'message' => $message, 'headers' => $headers_email);
							}
							else {
								$ticket = array();
							}
						}
					}
					else {
						$ticket = array();
					}
					return $ticket;
				}
				
				/**********************************************************
				 * Fetches the email template to be sent to the user.
				 *********************************************************/
				function get_template() {
				
				    ob_start();
				    wc_get_template( 'printable-ticket-email-template.php',array(),'bkap-printable-tickets/', dirname( __FILE__ ).'/templates/' );
				    return ob_get_clean();
				}
				
				function bkap_send_ticket_email($ticket_content) {
					$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
			if( get_option( 'booking_printable_ticket' ) == 'on') {
						$i = 0;
						$send_ticket = 'Y';
						foreach($ticket_content as $key => $value) {
							if(count($value) > 0) {
								$to = $value[$i]['to'];
								$headers_email = $value[$i]['headers'];
								$subject = $value[$i]['subject'];
								$message .= $value[$i]['message'];
							}
							else {
								$send_ticket = 'N';
							}
						}
						if($send_ticket == 'Y') {
							wp_mail($to,$subject,$message,$headers_email);
						}
					}
				}
				/********************************************************
				 * Sends the tickets when the order status is changed to
				 * "Completed"
				 *******************************************************/
				function woocommerce_complete_order($order_id) {
					global $wpdb, $date_formats;
					$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					$message = '';
			if( get_option( 'booking_printable_ticket' ) == 'on') {
						$order_obj = new WC_order($order_id);
						$order_items = $order_obj->get_items();
						
						$ticket_content = array();
						foreach($order_items as $item_key => $item_value) {	
							$values = array();
							$hidden_date = '';
						    $hidden_date_checkout = '';
							// CRUD has been implemented since Woo 3.0.0
							// continuing to provide backward compatibility
							if ( version_compare( WOOCOMMERCE_VERSION, "3.0.0" ) < 0 ) {
							    $quantity = $item_value['qty'];
                                $product_id = $item_value[ 'product_id' ];
                                $product_name = $item_value['name'];
							
                                if (array_key_exists(get_option("book_item-meta-date"),$item_value) &&  $item_value[get_option("book_item-meta-date")] != "") {
                                    $date = $item_value[get_option("book_item-meta-date")];
                                }
					
                                if (array_key_exists( get_option( "checkout_item-meta-date" ),$item_value ) && $item_value[get_option("checkout_item-meta-date")] != "") {
                                    $date_checkout = $item_value[get_option("checkout_item-meta-date")];
                                }
                                
                                if (array_key_exists(get_option("book_item-meta-time"),$item_value) && $item_value[get_option("book_item-meta-time")] != "") {
                                    $time_slot = $item_value[get_option("book_item-meta-time")];
                                }

                                if(is_plugin_active('woocommerce-product-addons/product-addons.php')) {
                                	$addons = get_product_addons($item_value['product_id']);
                                	foreach($addons as $key => $value) {
                                		$addon = $value['options'];
                                		$i = 0;
                                		foreach($addon as $k => $v) {
                                			$name = $v['label'];
                                			$values['addons'][$i] = array("name" => $name, "value" => $item_value[$name]);
                                			$i++;
                                		}
                                	}
                                }

                                if (function_exists('is_bkap_tours_active') && is_bkap_tours_active()) {
    								$comment = bkap_get_book_t('book.item-comments');
    								$tour_comments = '';
    								if (isset($item_value[$comment])) {
    								    $tour_comments = $item_value[$comment];
    								}
    							}
							} else { // Woo 3.0.0
							    	
							    $quantity = $item_value->get_quantity();
							    $product_id = $item_value->get_product_id();
							    $product_name = $item_value->get_name();
							    	
							    $start_date_label = get_option( "book_item-meta-date" );
							    $end_date_label = get_option( "checkout_item-meta-date" );
							    $time_slot_label = get_option( "book_item-meta-time" );
							    	
							    $item_meta = $item_value->get_meta_data();
							    	
							    $comment = '';
							    if (function_exists('is_bkap_tours_active') && is_bkap_tours_active()) {
							        $comment = bkap_get_book_t('book.item-comments');
							        $tour_comments = '';
							    }
							    foreach( $item_meta as $meta_data ) {
							        switch ( $meta_data->key ) {
							            case $start_date_label:
							                $date = $meta_data->value;
							                break;
							            case $end_date_label:
							                $date_checkout = $meta_data->value;
							                break;
							            case $time_slot_label:
							                $time_slot = $meta_data->value;
							                break;
							            case $comment:
							                $tour_comments = $meta_data->value;
							                break;
							            default:
							                break;
							        }
							    }
							}
								
							// Populate $values
							$values['quantity'] = $quantity;
								
							$duplicate_of = bkap_common::bkap_get_product_id( $product_id );
							$values['product_id'] = $duplicate_of;
								
							$values['name'] = $product_name;
								
							if ( isset( $date ) && '' != $date ) {
							    $date_format_set = $date_formats[ $saved_settings->booking_date_format ];
							    $date_formatted = date_create_from_format( $date_format_set, $date );
							    if ( isset( $date_formatted ) && $date_formatted != '' ) {
							        $hidden_date = date_format( $date_formatted, 'j-n-Y' );
							    }
							    $values['bkap_booking'][0]['date'] = $date;
							    $values['bkap_booking'][0]['hidden_date'] = $hidden_date;
							}
								
							if ( isset( $date_checkout ) && '' != $date_checkout ) {
							    $date_format_set = $date_formats[ $saved_settings->booking_date_format ];
							    $date_formatted = date_create_from_format( $date_format_set, $date_checkout );
							    if ( isset( $date_formatted ) && $date_formatted != '' ) {
							        $hidden_date_checkout = date_format($date_formatted, 'j-n-Y');
							    }
							    $values[ 'bkap_booking' ][0][ 'date_checkout'] = $date_checkout;
							    $values[ 'bkap_booking' ][0][ 'hidden_date_checkout' ] = $hidden_date_checkout;
							}
								
							if ( isset( $time_slot ) && '' != $time_slot ) {
							    $values['bkap_booking'][0]['time_slot'] = $time_slot;
							}
								
							if ( isset( $tour_comments ) ) {
							    $values['bkap_booking'][0]['comments'] = $tour_comments;
							}
							$ticket = array(apply_filters('bkap_send_ticket',$values,$order_obj));
							$ticket_content = array_merge($ticket_content,$ticket);
						}
						
						$i = 0;
						$send_ticket = 'Y';
						foreach($ticket_content as $key => $value) {
							if(count($value) > 0) {
								$to = $value[$i]['to'];
								$headers_email = $value[$i]['headers'];
								$subject = $value[$i]['subject'];
								$message .= $value[$i]['message'];
							}
							else {
								$send_ticket = 'N';
							}
						}
						if($send_ticket == 'Y') {
							wp_mail($to,$subject,$message,$headers_email);
						}
					}
				}
				
				/***********************************************************
				 * Add the columns Ticket ID and Security Code in the
				* View Bookings page
				**********************************************************/
				function bkap_printable_tickets_column_name($columns) {
					$columns = array(
								'ID'     		=> __( 'Order ID', 'woocommerce-booking' ),
								'name'  		=> __( 'Customer Name', 'woocommerce-booking' ),
								'product_name'  => __( 'Product Name', 'woocommerce-booking' ),
								'checkin_date'  => __( 'Check-in Date', 'woocommerce-booking' ),
								'checkout_date' => __( 'Check-out Date', 'woocommerce-booking' ),
								'booking_time'  => __( 'Booking Time', 'woocommerce-booking' ),
								'quantity'  	=> __( 'Quantity', 'woocommerce-booking' ),
								'amount'  		=> __( 'Amount', 'woocommerce-booking' ),
								'order_date'  	=> __( 'Order Date', 'woocommerce-booking' ),
								'ticket_id'		=> __( 'Ticket ID', 'woocommerce-booking' ),
								'security_code' => __( 'Security Code', 'woocommerce-booking'),
								'actions'  		=> __( 'Actions', 'woocommerce-booking' )
					);
					return $columns;
				}
				/**************************************************************
				 * Add the column values in the View bookings page
				*************************************************************/
		function bkap_printable_tickets_column_value($booking_data) {
					global $wpdb;
					
					foreach ($booking_data as $key => $value) {
						$value->ticket_id = $value->security_code = '';
						$booking_meta = "SELECT booking_meta_value FROM `".$wpdb->prefix."booking_item_meta`
										WHERE order_id= %d AND booking_id = %d";
						$results_meta = $wpdb->get_results($wpdb->prepare($booking_meta,$value->ID,$value->booking_id));
						$j = 1;
						$k = 0;
						for($i = 0;$i<$value->quantity;$i++) {
							if(!empty($results_meta)) {
								if(array_key_exists($j,$results_meta) && array_key_exists($k,$results_meta)) {
									if ($value->ticket_id != '') {
										$value->ticket_id .= ',';
									}
									if ($value->security_code != '') {
										$value->security_code .= ',';
									}
									$ticket_id = $results_meta[$k]->booking_meta_value;
									$security_code = $results_meta[$j]->booking_meta_value;
									
									$value->ticket_id .= $ticket_id;
									$value->security_code .= $security_code;
										
								} 
							}
							$j = $j + 2;
							$k = $k + 2;
						}
					}
					return $booking_data;
				}
				/**************************************************************
				 * Add ticket Id and security code in the array which contains
				 * data being exported
				*************************************************************/
				function bkap_printable_export_data($report) {
					global $wpdb;
					foreach ($report as $key => $value) {
						$value->ticket_id = $value->security_code = '';
						$booking_meta = "SELECT booking_meta_value FROM `".$wpdb->prefix."booking_item_meta`
						WHERE order_id= %d AND booking_id = %d";
						$results_meta = $wpdb->get_results($wpdb->prepare($booking_meta,$value->order_id,$value->booking_id));
						$j = 1;
						$k = 0;
						for($i = 0;$i<$value->quantity;$i++) {
							if(!empty($results_meta)) {
								if(array_key_exists($j,$results_meta) && array_key_exists($k,$results_meta)) {
									if ($value->ticket_id != '') {
										$value->ticket_id .= ';';
									}
									if ($value->security_code != '') {
										$value->security_code .= ';';
									}
									$ticket_id = $results_meta[$k]->booking_meta_value;
									$security_code = $results_meta[$j]->booking_meta_value;
				
									$value->ticket_id .= $ticket_id;
									$value->security_code .= $security_code;
										
								}
							}
							$j = $j + 2;
							$k = $k + 2;
						}
					}
					return $report;
				}
				/*************************************************************
				 * Add ticket ID and security code in the csv file
				 ************************************************************/
				function bkap_printable_csv_data($csv,$report) {
					// Column Names
					$csv = 'Order ID,Customer Name,Product Name,Check-in Date, Check-out Date,Booking Time,Quantity,Amount, Order Date, Ticket ID, Security Code';
					$csv .= "\n";
					foreach ($report as $key => $value) {
						// Order ID
						$order_id = $value->order_id;
						// Customer Name
						$customer_name = $value->customer_name;
						// Product Name
						$product_name = $value->product_name;
						// Check-in Date
						$checkin_date = $value->checkin_date;
						// Checkout Date
						$checkout_date = $value->checkout_date;
						// Booking Time
						$time = $value->time;
						// Quantity & amount
						$selected_quantity = $value->quantity;
						$amount = $value->amount;
						// Order Date
						$order_date = $value->order_date;
						// Ticket ID
						$ticket_id = $value->ticket_id;
						// Security code
						$security_code = $value->security_code;
						// CReate the data row
						$csv .= $order_id . ',' . $customer_name . ',' . $product_name . ',"' . $checkin_date . '","' . $checkout_date . '","' . $time . '",' . $selected_quantity . ',' . $amount . ',' . $order_date . ',' . $ticket_id . ',' . $security_code;
						$csv .= "\n";
					}
					return $csv;
				}
				/*********************************************************************
				 * Add ticket ID and security code in the print headers
				 ********************************************************************/
				function bkap_printable_tickets_add_print_columns($print_data_columns) {
					$print_data_columns = "
					<tr>
					<th style='border:1px solid black;padding:5px;'>".__('Order ID','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Customer Name','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Product Name','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Check-in Date','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Check-out Date','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Booking Time','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Quantity','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Amount','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Order Date','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Ticket ID','woocommerce-booking')."</th>
					<th style='border:1px solid black;padding:5px;'>".__('Security Code','woocommerce-booking')."</th>
					</tr>";
					return $print_data_columns;
				}
				/**********************************************************************
				 * Add ticket ID and security code in the rows to be printed
				 *********************************************************************/
				function bkap_printable_tickets_add_print_rows($print_data_row_data,$report) {
					$print_data_row_data = '';
					foreach ($report as $key => $value) {
						$print_data_row_data .= "<tr>
						<td style='border:1px solid black;padding:5px;'>".$value->order_id."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->customer_name."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->product_name."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->checkin_date."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->checkout_date."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->time."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->quantity."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->amount."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->order_date."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->ticket_id."</td>
						<td style='border:1px solid black;padding:5px;'>".$value->security_code."</td>
						</tr>";
					}
					return $print_data_row_data;
				}
			}
		}
			
	$printable_tickets = new printable_tickets();
}
?>