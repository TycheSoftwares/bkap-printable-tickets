<?php 
/*
Plugin Name: Printable Ticket Addon for WooCommerce Booking & Appointment Plugin
Plugin URI: http://www.tychesoftwares.com/store/premium-plugins/bkap-printable-ticket
Description: This addon to the Woocommerce Booking and Appointment Plugin allows you to email the tickets for the bookings to customers when an order is placed.
Version: 1.1
Author: Ashok Rane
Author URI: http://www.tychesoftwares.com/
*/

/*require 'plugin-updates/plugin-update-checker.php';
$ExampleUpdateChecker = new PluginUpdateChecker(
	'http://www.tychesoftwares.com/plugin-updates/bkap-printable-ticket/info.json',
	__FILE__
);*/
global $PrintTicketUpdateChecker;
$PrintTickerUpdateChecker = '1.1';

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
		'version' 	=> '1.1', 		// current version number
		'license' 	=> $license_key, 	// license key (used get_option above to retrieve from DB)
		'item_name' => EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK, 	// name of this plugin
		'author' 	=> 'Ashok Rane'  // author of this plugin
)
);
register_uninstall_hook( __FILE__, 'woocommerce_booking_meta_delete');

function woocommerce_booking_meta_delete()
{
	
	global $wpdb;
	$table_name_booking_meta = $wpdb->prefix . "booking_item_meta";
	$sql_table_name_booking_meta = "DROP TABLE " . $table_name_booking_meta;
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	$wpdb->get_results($sql_table_name_booking_meta);
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
				// used to add new settings on the product page booking box
				add_action('bkap_after_global_holiday_field', array(&$this, 'bkap_show_printable_ticket_settings'), 10, 1);
				add_filter('bkap_save_global_settings', array(&$this, 'bkap_save_printable_ticket_settings'), 10, 1);
				add_filter('bkap_send_ticket', array(&$this, 'bkap_send_ticket_content'), 10, 2);
				add_action('bkap_send_email', array(&$this,'bkap_send_ticket_email'),10,1);
				add_filter('bkap_view_bookings', array(&$this,'bkap_view_bookings_fields'),10,3);
				add_action('woocommerce_order_status_completed' , array(&$this,'woocommerce_complete_order'),10,1);
				add_action('bkap_add_submenu',array(&$this, 'printable_ticket_menu'));
				
				add_action('admin_init', array(&$this, 'edd_sample_register_option_print_ticket'));
				add_action('admin_init', array(&$this, 'edd_sample_deactivate_license_print_ticket'));
				add_action('admin_init', array(&$this, 'edd_sample_activate_license_print_ticket'));
			}
			function edd_sample_activate_license_print_ticket() 
			{
				// listen for our activate button to be clicked
				if( isset( $_POST['edd_print_ticket_license_activate'] ) )
				{
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
			
					function printable_ticket_menu()
					{
						$page = add_submenu_page('booking_settings', __( 'Activate Printable Ticket License', 'woocommerce-booking' ), __( 'Activate Printable Ticket License', 'woocommerce-booking' ), 'manage_woocommerce', 'print_ticket_license_page', array(&$this, 'edd_sample_license_page_print_ticket' ));
					}
									
			function printable_ticket_activate()
			{
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
			
			function bkap_show_printable_ticket_settings($product_id)
			{
				$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
				?>
				<tr>
					<th>
						<label for="booking_printable_ticket"><b><?php _e('Send tickets via email: ', 'woocommerce-booking');?></b></label>
					</th>
					<td>
						<?php
						$printable_ticket = ""; 
						if (isset($saved_settings->booking_printable_ticket) && $saved_settings->booking_printable_ticket == 'on')
						{
							$printable_ticket = 'checked';
						}
						?>
						<input type="checkbox" id="booking_printable_ticket" name="booking_printable_ticket" <?php echo $printable_ticket; ?>/>
						<img class="help_tip" width="16" height="16" data-tip="<?php _e('Allow customers to send ticket to email when an order is placed.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png" />
					</td>
				</tr>
				<script type="text/javascript">
						jQuery("#booking_printable_ticket").change(function() {
							if(jQuery('#booking_printable_ticket').attr('checked'))
							{
								jQuery('#booking_send_ticket_method').show();
									
							}
							else
							{
								jQuery('#booking_send_ticket_method').hide();
							}
							});
					</script>
				<?php 
					$booking_send_ticket_method = 'none';
					if (isset($saved_settings->booking_printable_ticket) && $saved_settings->booking_printable_ticket == 'on')
					{
						$booking_send_ticket_method = 'show';
					}
					?>
					<tr id="booking_send_ticket_method" style="display:<?=$booking_send_ticket_method?>;">
						<th>
							<label for="booking_send_ticket_label"><b><?php _e( 'Ticket Sending Method:', 'woocommerce-booking');?></b></label>
						</th>
						<td>
							<?php 
							$send_by_order = "";
							if(isset($saved_settings->booking_send_ticket_method) && $saved_settings->booking_send_ticket_method == "send_by_product" )
							{
								$send_by_order = "checked";
								$send_individually = "";
							}
							else
							{
								$send_by_order = "";
								$send_individually = "checked";
							}
							?>
							<input type="radio" name="booking_send_ticket_method_radio" id="booking_send_ticket_method_radio" value="send_by_quantity" <?php echo $send_individually;?>><b><?php _e('Send 1 ticket per quantity&nbsp&nbsp&nbsp&nbsp&nbsp;', 'woocommerce-booking');?> </b></input>
							<input type="radio" id="booking_send_ticket_method_radio" name="booking_send_ticket_method_radio" value="send_by_product"<?php echo $send_by_order;?>><b><?php _e('Send 1 ticket per product', 'woocommerce-booking');?> </b></input>
							<img class="help_tip" width="16" height="16" data-tip="<?php _e('Enable Send 1 ticket per quantity to send ticket for each product and each quantity of product in order and Send 1 ticket per product to send each ticket per product in order.', 'woocommerce-booking');?>" src="<?php echo plugins_url() ;?>/woocommerce/assets/images/help.png"/>
						</td>
					</tr>
				
				
				<?php 
			}
				
				
				function bkap_save_printable_ticket_settings($booking_settings)
				{
					if (isset($_POST['booking_printable_ticket']))
					{
						$booking_settings->booking_printable_ticket = $_POST['booking_printable_ticket'];
					}
					if (isset($_POST['booking_send_ticket_method_radio']))
					{
						$booking_settings->booking_send_ticket_method = $_POST['booking_send_ticket_method_radio'];
					}
					return $booking_settings;
				}
				function assign_rand_value($num)
				{
					// accepts 1 - 36
					switch($num)
					{
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
				function get_rand_id($length)
				{
					 if($length>0) 
					 { 
						$rand_id="";
						for($i=1; $i<=$length; $i++)
						{
							 mt_srand((double)microtime() * 1000000);
							$num = mt_rand(1,36);
							$rand_id .= $this->assign_rand_value($num);
						}
					}
					return $rand_id;
				}
				 
				function bkap_send_ticket_content($values,$order)
				{
					global $wpdb;
					if($order->status == 'completed')
					{
						$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
						$booking_settings = get_post_meta( $values['product_id'], 'woocommerce_booking_settings', true);
						//print_r($saved_settings->booking_send_ticket_method);exit;
						if(isset($saved_settings->booking_printable_ticket) && $saved_settings->booking_printable_ticket == 'on')
						{
							if(isset($booking_settings['booking_enable_date']) && $booking_settings['booking_enable_date'] == 'on')
							{
								//print_r($values);exit;
								//print_r($order);exit;
								if(array_key_exists('data',$values) )
								{
									$_product = $values['data'];
									$product_name = $_product->get_title();
								}
								else
								{
									$product_name = $values['name'];
								}
								$from_email = get_option('admin_email');
								$buyers_firstname = $order->billing_first_name;
								$buyers_lastname = $order->billing_last_name;
								$to = $order->billing_email;
								$post_id = $values['product_id'];
								$headers[] = "From:".$from_email;
								$headers[] = "Content-type: text/html";
								$completed_date = date('F j, Y',strtotime($order->completed_date));
								$subject = "Your Ticket for Order #".$order->id." from ".$completed_date;
								//echo $subject;exit;
								
							
								$logo = get_header_image();
								$message = '';
								$booking = '';
								$addon = '';
								$site_url = get_site_url();
								$site_title = get_option('blogname');
								$site_tagline = get_option('blogdescription'); 
								if(array_key_exists('booking',$values) )
								{
									$bookings = $values['booking'];
											
									if (array_key_exists('date',$bookings[0]) && $bookings[0]['date'] != "")
									{
										$booking_date = date('d F, Y',strtotime($bookings[0]["hidden_date"]));
										$booking = get_option("book.item-meta-date").': '.$booking_date.'<br>';
									}
									if (array_key_exists('date_checkout',$bookings[0]) && $bookings[0]['date_checkout'] != "")
									{
										$booking_date_checkout = date('d F, Y',strtotime($bookings[0]["hidden_date_checkout"]));
										$booking .= get_option("checkout.item-meta-date").': '.$booking_date_checkout.'<br>';
									}
									if (array_key_exists('time_slot',$bookings[0]) && $bookings[0]['time_slot'] != "")
									{
										$booking .= get_option("book.item-meta-time").': '. $bookings[0]["time_slot"].'<br>';
									}
									$hidden_date = $bookings[0]['hidden_date'];
									$date_query = date('Y-m-d', strtotime($hidden_date));
									if (isset($bookings[0]['date_checkout']) && $bookings[0]['date_checkout'] != "")
									{
										$date_checkout = $bookings[0]['hidden_date_checkout'];
										$date_checkout_query = date('Y-m-d',strtotime($date_checkout));
										$booking_id_query = "SELECT booking_id FROM `".$wpdb->prefix."booking_order_history`
														WHERE order_id = '".$order->id."'";
										$booking_id_results = $wpdb->get_results( $booking_id_query );
										foreach($booking_id_results as $key => $val)
										{
											$booking_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
														WHERE post_id = '".$values['product_id']."' AND
													start_date = '".$date_query."' AND
													end_date = '".$date_checkout_query."' AND
													id = '".$val->booking_id."'";
											$booking_results = $wpdb->get_results( $booking_query );
											//print_r($booking_results);
											if(count($booking_results) > 0)
											{
												$booking_id[] = $booking_results[0]->id;
											}
										}
										//print_r($booking_id);exit;
									}
									else if (isset($bookings[0]['time_slot']) && $bookings[0]['time_slot'] != "")
									{
										$time_select = $bookings[0]['time_slot'];
										$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
										if (isset($saved_settings))
										{
											$time_format = $saved_settings->booking_time_format;
										}
										else
										{
											$time_format = "12";
										}
										if(strpos($time_select,"<br>") !== false )
										{
											$time_exploded = explode("<br>", $time_select);
											//print_r($time_exploded);exit;
											array_shift($time_exploded);
											$time_slot = array();
											foreach($time_exploded as $k => $v)
											{
												$time_slot = explode("-",$v);
												$from_time = trim($time_slot[0]);
												if(isset($time_slot[1]))$to_time = trim($time_slot[1]);
												else $to_time = '';
												if ($time_format == '12')
												{
													$from_time = date('h:i A', strtotime($time_slot[0]));
													if(isset($time_slot[1]))$to_time = date('h:i A', strtotime($time_slot[1]));
												}
												$query_from_time = date('G:i', strtotime($time_slot[0]));
												if(isset($time_slot[1]))$query_to_time = date('G:i', strtotime($time_slot[1]));
												else $query_to_time = '';
												if($query_to_time != '')
												{
													$booking_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$values['product_id']."' AND
													start_date = '".$date_query."' AND
													from_time = '".$query_from_time."' AND
													to_time = '".$query_to_time."' ";
													$booking_results = $wpdb->get_results( $booking_query );
													$booking_id[] = $booking_results[0]->id;
												}		
												else
												{
													$booking_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
													WHERE post_id = '".$values['product_id']."' AND
													start_date = '".$date_query."' AND
													from_time = '".$query_from_time."'";
													$booking_results = $wpdb->get_results( $booking_query );
													$booking_id[] = $booking_results[0]->id;
												}
											}
										}		
										else
										{
											$time_slot = explode("-",$time_select);
											$from_time = trim($time_slot[0]);
											if(isset($time_slot[1]))$to_time = trim($time_slot[1]);
											else $to_time = '';
											if ($time_format == '12')
											{
												$from_time = date('h:i A', strtotime($time_slot[0]));
												if(isset($time_slot[1]))$to_time = date('h:i A', strtotime($time_slot[1]));
											}
											$query_from_time = date('G:i', strtotime($time_slot[0]));
											if(isset($time_slot[1]))$query_to_time = date('G:i', strtotime($time_slot[1]));
											else $query_to_time = '';
											if($query_to_time != '')
											{
												$booking_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
												WHERE post_id = '".$values['product_id']."' AND
												start_date = '".$date_query."' AND
												from_time = '".$query_from_time."' AND
												to_time = '".$query_to_time."' ";
												$booking_results = $wpdb->get_results( $booking_query );
												$booking_id[] = $booking_results[0]->id;
											}
											else
											{
												$booking_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
												WHERE post_id = '".$values['product_id']."' AND
												start_date = '".$date_query."' AND
												from_time = '".$query_from_time."'";
												$booking_results = $wpdb->get_results( $booking_query );
												$booking_id[] = $booking_results[0]->id;
											}
										}	
									}
									else
									{
										$booking_query = "SELECT id FROM `".$wpdb->prefix."booking_history`
										WHERE post_id = '".$values['product_id']."' AND
										start_date = '".$date_query."'";
										$booking_results = $wpdb->get_results( $booking_query );
										$booking_id[] = $booking_results[0]->id;
									}
								}
								if(is_plugin_active('bkap-tour-operators/tour_operators_addon.php'))
								{
									if($booking_settings['show_tour_operator'] == 'on')
									{
										$booking_tour_operator = $booking_settings["booking_tour_operator"];
										$user = get_userdata( $booking_tour_operator );
										if(isset($user->user_login))
										{
											$booking.= 'Tour Operator: '.$user->user_login.'<br>';
										}
										if($booking_settings['booking_show_comment'] == 'on')
										{
											$booking.= book_t('book.item-comments').': '.$bookings[0]['comments'].'<br>';
										}
									}
								}
								//print_r($booking_id);exit;
								$f = 0;
								//print_r($addons);
								if(is_plugin_active('woocommerce-product-addons/product-addons.php'))
								{
									$addons = $values['addons'];
									foreach($addons as $key )
									{
										$addon .= $addons[$f]["name"].': '.$addons[$f]["value"].'<br>';
										$f++;		
									}
								}
								$instructions = get_post_meta($values['product_id'],'instructions');
								//print_r($addon);exit;
								if(isset($saved_settings->booking_send_ticket_method) && $saved_settings->booking_send_ticket_method == 'send_by_quantity')
								{
									$quantity = $values['quantity'];
						
									for($i=0;$i<$quantity;$i++)
									{
										$ticket_sql = "SELECT MAX(CAST(booking_meta_value AS unsigned)) AS ticket_id FROM `".$wpdb->prefix."booking_item_meta` WHERE booking_meta_key = '_ticket_id'";
										//echo $ticket_sql;exit;
										$ticket_results = $wpdb->get_results($ticket_sql);
										$ticket_no = $ticket_results[0]->ticket_id;
										if($ticket_no == '')
										{
											$ticket_no = 1;
										}
										else
										{
											//echo $ticket_no;
											$ticket_no = $ticket_no + 1;
									
										}
										//echo $ticket_no;exit;
										$security_unique_no = $this->get_rand_id(10);
										$message .= '
									<table>
<tbody>
<tr>
    <td width="580" valign="top" style="background:#f7f7f7;width:435.0pt;padding:15.0pt 15.0pt 15.0pt 15.0pt">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr>
      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in">
      <h1 style="margin:0!important"><span style="font-size:21.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;color:#0a0a0e">'.$site_title.'<u></u><u></u></span></h1>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$site_tagline.'</span> </p>
      </td>
     </tr>
     <tr>
     <td width="100%" align="left">
     <hr noshade size=1 width="100%">
     </td>
     </tr>
    </tbody></table>
    <p class="MsoNormal"><span><u></u><u></u></span></p>
    <div align="center">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr>
      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in 0!important;margin:0!important">
      <h1 style="margin:0!important"><span style="font-size:21.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;color:#0a0a0e">'.$product_name.'<u></u><u></u></span></h1>
      </td>
     </tr>
    </tbody></table>
    </div>
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr style="height:22.5pt">
      <td width="100%" valign="top" style="width:100.0%;background:#f7f7f7;padding:0in 0in 0in 0in;height:22.5pt;padding:0!important;margin:0!important"></td>
     </tr>
    </tbody></table>
    <div align="center">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr>
      <td width="100" valign="top" style="width:75.0pt;padding:0in 0in 0in 0in;margin:0!important">
      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">'.$this->headings["ticket_number"].'<u></u><u></u></span></h6>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$ticket_no.'</span>
      </p>
      </td>
      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in;margin:0!important">
      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">'.$this->headings["booking_details"].'<u></u><u></u></span></h6>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$product_name.'</span> </p>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$booking.'</span> </p>
       <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$addon.'</span> </p>
      </td>
      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in 0!important;margin:0!important">
      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">'.$this->headings["buyer"].'<u></u><u></u></span></h6>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$buyers_firstname.' '.$buyers_lastname.'</span> </p>
      </td>
      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in;margin:0!important">
      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">'.$this->headings["security_code"].'<u></u><u></u></span></h6>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$security_unique_no.'</span>
      </p>
      </td>
     </tr>
    </tbody></table>
    </div>
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr style="height:22.5pt">
      <td width="100%" valign="top" style="width:100.0%;background:#f7f7f7;padding:0in 0in 0in 0in;height:22.5pt;padding:0!important;margin:0!important"></td>
     </tr>
    </tbody></table>
      <div align="center">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr>
      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in 0!important;margin:0!important">
      <p class="MsoNormal"><a href="'.$site_url.'" target="_blank"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$site_url.'</span></a>
      </td>
     </tr>
    </tbody></table>
    </div>
    </td>
   </tr>
   </table>';
										$j = 0;
										foreach($booking_id as $b_key => $b_val)
										{
											$query_ticket= "INSERT INTO `".$wpdb->prefix."booking_item_meta`
											(order_id,booking_id,booking_meta_key,booking_meta_value)
											VALUES (
											'".$order->id."',
											'".$booking_id[$j]."',
											'_ticket_id',
											'".$ticket_no."')";
											$wpdb->query($query_ticket );
											$query_security_code = "INSERT INTO `".$wpdb->prefix."booking_item_meta`
											(order_id,booking_id,booking_meta_key,booking_meta_value)
											VALUES (
											'".$order->id."',
											'".$booking_id[$j]."',
											'_security_code',
											'".$security_unique_no."')";
											$wpdb->query($query_security_code );
											$j++;
										}
										//$i++;
									}
								}
								else if(isset($saved_settings->booking_send_ticket_method) && $saved_settings->booking_send_ticket_method == 'send_by_product')
								{
									$ticket_sql = "SELECT MAX(CAST(booking_meta_value AS unsigned)) AS ticket_id FROM `".$wpdb->prefix."booking_item_meta` WHERE booking_meta_key = '_ticket_id'";
									$ticket_results = $wpdb->get_results($ticket_sql);
									//echo $ticket_no;
						
									$ticket_no = $ticket_results[0]->ticket_id;
									//echo "egre";exit;
									if($ticket_no == '')
									{
										$ticket_no = 1;
									}
									else
									{
										$ticket_no = $ticket_no + 1;
									}
									$security_unique_no = $this->get_rand_id(10);
									$message .= '
									<table>
<tbody>
<tr>
    <td width="580" valign="top" style="background:#f7f7f7;width:435.0pt;padding:15.0pt 15.0pt 15.0pt 15.0pt">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr>
      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in">
      <h1 style="margin:0!important"><span style="font-size:21.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;color:#0a0a0e">'.$site_title.'<u></u><u></u></span></h1>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$site_tagline.'</span> </p>
      </td>
     </tr>
     <tr>
     <td width="100%" align="left">
     <hr noshade size=1 width="100%">
     </td>
     </tr>
    </tbody></table>
    <p class="MsoNormal"><span><u></u><u></u></span></p>
    <div align="center">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr>
      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in 0!important;margin:0!important">
      <h1 style="margin:0!important"><span style="font-size:21.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;color:#0a0a0e">'.$product_name.'<u></u><u></u></span></h1>
      </td>
     </tr>
    </tbody></table>
    </div>
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr style="height:22.5pt">
      <td width="100%" valign="top" style="width:100.0%;background:#f7f7f7;padding:0in 0in 0in 0in;height:22.5pt;padding:0!important;margin:0!important"></td>
     </tr>
    </tbody></table>
    <div align="center">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr>
      <td width="100" valign="top" style="width:75.0pt;padding:0in 0in 0in 0in;margin:0!important">
      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">'.$this->headings["ticket_number"].'<u></u><u></u></span></h6>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$ticket_no.'</span>
      </p>
      </td>
      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in;margin:0!important">
      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">'.$this->headings["booking_details"].'<u></u><u></u></span></h6>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$product_name.'</span> </p>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$booking.'</span> </p>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$addon.'</span> </p>
      </td>
      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in 0!important;margin:0!important">
      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">'.$this->headings["buyer"].'<u></u><u></u></span></h6>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$buyers_firstname.' '.$buyers_lastname.'</span> </p>
      </td>
      <td width="120" valign="top" style="width:1.25in;padding:0in 0in 0in 0in;margin:0!important">
      <h6 style="margin-right:0in;margin-bottom:7.5pt;margin-left:0in;color:#909090!important;font-weight:700!important"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;;text-transform:uppercase">'.$this->headings["security_code"].'<u></u><u></u></span></h6>
      <p class="MsoNormal"><span style="font-size:11.5pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$security_unique_no.'</span>
      </p>
      </td>
     </tr>
    </tbody></table>
    </div>
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr style="height:22.5pt">
      <td width="100%" valign="top" style="width:100.0%;background:#f7f7f7;padding:0in 0in 0in 0in;height:22.5pt;padding:0!important;margin:0!important"></td>
     </tr>
    </tbody></table>
      <div align="center">
    <table border="0" cellspacing="0" cellpadding="0" width="100%" style="width:100.0%">
     <tbody><tr>
      <td width="100%" valign="top" style="width:100.0%;padding:0in 0in 0in 0in 0!important;margin:0!important">
      <p class="MsoNormal"><a href="'.$site_url.'" target="_blank"><span style="font-size:10.0pt;font-family:&quot;Helvetica&quot;,&quot;sans-serif&quot;">'.$site_url.'</span></a>
      </td>
     </tr>
    </tbody></table>
    </div>
    </td>
   </tr>
   </table>';
									$j = 0;
									foreach($booking_id as $b_key => $b_val)
									{
										$query_ticket= "INSERT INTO `".$wpdb->prefix."booking_item_meta`
										(order_id,booking_id,booking_meta_key,booking_meta_value)
										VALUES (
										'".$order->id."',
										'".$booking_id[$j]."',
										'_ticket_id',
										'".$ticket_no."')";
										$wpdb->query( $query_ticket );
										$query_security_code = "INSERT INTO `".$wpdb->prefix."booking_item_meta`
										(order_id,booking_id,booking_meta_key,booking_meta_value)
										VALUES (
										'".$order->id."',
										'".$booking_id[$j]."',
										'_security_code',
										'".$security_unique_no."')";
										$wpdb->query(  $query_security_code );
										$j++;
									}
								}
								$ticket[] = array('to'=>$to, 'subject' => $subject, 'message' => $message, 'headers' => $headers);
							}
							else
							{
								$ticket = array();
							}
						}
						//echo $message;exit;
					}
					else
					{
						$ticket = array();
					}
					return $ticket;

				}
				function bkap_send_ticket_email($ticket_content)
				{
					$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					if(isset($saved_settings->booking_printable_ticket) && $saved_settings->booking_printable_ticket == 'on')
					{
						$i = 0;
						$send_ticket = 'Y';
						foreach($ticket_content as $key => $value)
						{
							if(count($value) > 0)
							{
								$to = $value[$i]['to'];
								$headers = $value[$i]['headers'];
								$subject = $value[$i]['subject'];
								$message .= $value[$i]['message'];
							}
							else
							{
								$send_ticket = 'N';
							}
						}
						if($send_ticket == 'Y')
						{
							wp_mail($to,$subject,$message,$headers);
						}
					}
				}
				function woocommerce_complete_order($order_id)
				{
					global $wpdb;
					$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
					$date_format = $saved_settings->booking_date_format;
					$message = '';
					if(isset($saved_settings->booking_printable_ticket) && $saved_settings->booking_printable_ticket == 'on')
					{
						$order_obj = new WC_order($order_id);
						$order_items = $order_obj->get_items();
						//print_r($order_items);exit;
						$ticket_content = array();
						foreach($order_items as $item_key => $item_value)
						{
							$values = array();
							$values['quantity'] = $item_value['qty'];
							$values['product_id'] = $item_value['product_id'];
							$values['name'] = $item_value['name'];
							//print_r($item_value);
							if (array_key_exists(get_option("book.item-meta-date"),$item_value) &&  $item_value[get_option("book.item-meta-date")] != "")
							{
								$date = $item_value[get_option("book.item-meta-date")];
								if($date_format == 'dd/mm/y')
								{
									$date_explode = explode("/",$date);
									//print_r($date_explode);
									$hidden_date = date('j-n-Y',mktime(0,0,0,$date_explode[1],$date_explode[0],$date_explode[2]));
								}
								else
								{
									//print_r(date_parse_from_format($date_format, $date));exit;
									$date_str = str_replace(",","",$date);
									$hidden_date = date('j-n-Y',strtotime($date_str));
								}
								$values['booking'][0]['date'] = $date;
								$values['booking'][0]['hidden_date'] = $hidden_date;
							}
							if (array_key_exists(get_option("checkout.item-meta-date"),$item_value) && $item_value[get_option("checkout.item-meta-date")] != "")
							{
								$date_checkout = $item_value[get_option("checkout.item-meta-date")];
								if($date_format == 'dd/mm/y')
								{
									$date_checkout_explode = explode("/",$date_checkout);
									$hidden_date_checkout = date('j-n-Y',mktime(0,0,0,$date_checkout_explode[1],$date_checkout_explode[0],$date_checkout_explode[2]));
								}
								else
								{
									//print_r(date_parse_from_format($date_format, $date));exit;
									$date_checkout_str = str_replace(",","",$date_checkout);
									$hidden_date_checkout = date('j-n-Y',strtotime($date_checkout_str));
								}
								$values['booking'][0]['date_checkout'] = $date_checkout;
								$values['booking'][0]['hidden_date_checkout'] = $hidden_date_checkout;
							}
							if (array_key_exists(get_option("book.item-meta-time"),$item_value) && $item_value[get_option("book.item-meta-time")] != "")
							{
								$time_slot = $item_value[get_option("book.item-meta-time")];
								$values['booking'][0]['time_slot'] = $time_slot;
							}
							if(is_plugin_active('woocommerce-product-addons/product-addons.php'))
							{
								$addons = get_product_addons($item_value['product_id']);
								//print_r($addons);exit;
								foreach($addons as $key => $value)
								{
									$addon = $value['options'];
									$i = 0;
									foreach($addon as $k => $v)
									{
										//print_r($v);
										$name = $v['label'];
										$values['addons'][$i] = array("name" => $name, "value" => $item_value[$name]);
										//print_r($values);exit;
										$i++;
									}
									//print_r($values['addons']);exit;
								}
							}
							$ticket = array(apply_filters('bkap_send_ticket',$values,$order_obj));
							$ticket_content = array_merge($ticket_content,$ticket);
						}
						//exit;
						//print_r($ticket_content);
						/*$to = $ticket_content[0][0]['to'];
						$headers = $ticket_content[0][0]['headers'];
						$subject = $ticket_content[0][0]['subject'];
						foreach($ticket_content as $key => $value)
						{
							$message .= $value[0]['message'];
						}*/
						//print_r($to);
					//	print_r($subject);
						//print_r($message);
						//print_r($headers);exit;
						$i = 0;
						$send_ticket = 'Y';
						foreach($ticket_content as $key => $value)
						{
							if(count($value) > 0)
							{
								$to = $value[$i]['to'];
								$headers = $value[$i]['headers'];
								$subject = $value[$i]['subject'];
								$message .= $value[$i]['message'];
							}
							else
							{
								$send_ticket = 'N';
							}
						}
						if($send_ticket == 'Y')
						{
							wp_mail($to,$subject,$message,$headers);
						}
						//wp_mail($to,$subject,$message,$headers);
					}
					//print_r($ticket_content);exit;
				}
				function bkap_view_bookings_fields($order_id,$booking_id,$quantity)
				{
					global $wpdb;
					$ticket_id_str = '';
					$security_code_str = '';
					$date_lockout = "SELECT booking_meta_value FROM `".$wpdb->prefix."booking_item_meta`
					WHERE order_id='".$order_id."' AND booking_id='".$booking_id."'";
					$results_date_lock = $wpdb->get_results($date_lockout);
					$j = 1;
					$k = 0;
					for($i = 0;$i<$quantity;$i++)
					{
						if(!empty($results_date_lock))
						{
							if(array_key_exists($j,$results_date_lock) && array_key_exists($k,$results_date_lock))
							{
								$ticket_id = $results_date_lock[$k]->booking_meta_value;
								$security_code = $results_date_lock[$j]->booking_meta_value;
								$ticket_id_str .= $ticket_id.",";
								$security_code_str .= $security_code.",";
							}						
						}
						$j = $j+2;
						$k = $k+2;
					}
					$ticket_id_str = trim($ticket_id_str, ",");
					$security_code_str = trim($security_code_str,",");
					$var_ticket = "<td>".$ticket_id_str."</td>";
					$var_security = "<td>".$security_code_str."</td>";
					$var_ticket_field = "Ticket ID";
					$var_security_field = "Security Code";
					$var_array = array('ticket_id'=>$var_ticket,'security_code'=>$var_security,'ticket_field'=>$var_ticket_field,'security_field'=>$var_security_field);
					return $var_array;
				}
			}
		}
			
	$printable_tickets = new printable_tickets();
}
?>