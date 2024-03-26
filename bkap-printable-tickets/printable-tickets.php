<?php
/**
 * Plugin Name: Printable Tickets Addon
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/printable-tickets-addon-woocommerce-booking-appointment-plugin/
 * Description: This is an addon for the WooCommerce Booking & Appointment Plugin which allows you to email the tickets for the bookings to customers when an order is placed.
 * Version: 1.10
 * Author: Tyche Softwares
 * Author URI: http://www.tychesoftwares.com/
 *
 * @package BKAP_Printable_Tickets
 */

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed.
define( 'EDD_SL_STORE_URL_PRINT_TICKET_BOOK', 'https://www.tychesoftwares.com/' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system.

// the name of your product. This is the title of your product in EDD and should match the download title in EDD exactly.
define( 'EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK', 'Printable Tickets Addon for WooCommerce Booking & Appointment Plugin' ); // IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system.

if ( ! class_exists( 'EDD_PRINT_TICKET_BOOK_Plugin_Updater' ) ) {
	// load our custom updater if it doesn't already exist.
	include dirname( __FILE__ ) . '/plugin-updates/EDD_PRINT_TICKET_BOOK_Plugin_Updater.php';
}

// retrieve our license key from the DB.
$license_key = trim( get_option( 'edd_sample_license_key_print_ticket_book' ) );

// setup the updater.
$edd_updater = new EDD_PRINT_TICKET_BOOK_Plugin_Updater(
	EDD_SL_STORE_URL_PRINT_TICKET_BOOK,
	__FILE__,
	array(
		'version'   => '1.10', // current version number.
		'license'   => $license_key, // license key (used get_option above to retrieve from DB).
		'item_name' => EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK, 	// name of this plugin.
		'author'    => 'Ashok Rane', // author of this plugin.
	)
);

register_uninstall_hook( __FILE__, 'woocommerce_booking_meta_delete' );

function woocommerce_booking_meta_delete() {

	global $wpdb;
	$table_name_booking_meta     = $wpdb->prefix . 'booking_item_meta';
	$sql_table_name_booking_meta = 'DROP TABLE ' . $table_name_booking_meta;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$wpdb->get_results( $sql_table_name_booking_meta );
}

function is_bkap_tickets_active() {

	if ( is_plugin_active( 'bkap-printable-tickets/printable-tickets.php' ) ) {
		return true;
	}

	return false;
}

/**
 * Localisation
 */
load_plugin_textdomain( 'printable-tickets', false, dirname( plugin_basename( __FILE__ ) ) . '/' );

/**
 * BKAP_Printable_Tickets class.
 */
if ( ! class_exists( 'BKAP_Printable_Tickets' ) ) {

	/**
	 * BKAP_Printable_Tickets class.
	 *
	 * @since 1.0.
	 */
	class BKAP_Printable_Tickets {

		/**
		 * The unique identifier of this plugin.
		 *
		 * @since  1.0.0
		 * @access protected
		 * @var    string $type Booking Post Type.
		 */
		protected $type;

		/**
		 * The unique identifier of this plugin.
		 *
		 * @since  1.0.0
		 * @access protected
		 * @var    array $headings Heading of Printable Ticket Template email.
		 */
		protected $headings;

		/**
		 * Constructor.
		 */
		public function __construct() {

			$this->type     = 'bkap_booking';
			$this->headings = array(
				'ticket_number'   => 'Ticket# ',
				'booking_details' => 'Booking Details',
				'buyer'           => 'Buyer',
				'security_code'   => 'Security Code',
			);

			// Initialize settings.
			register_activation_hook( __FILE__, array( &$this, 'printable_ticket_activate' ) );
			add_action( 'admin_notices', array( &$this, 'printable_ticket_error_notice' ) );

			add_action( 'admin_init', array( &$this, 'printable_include_files' ) );

			// used to add new settings on the product page booking box.
			add_action( 'bkap_add_addon_settings', array( &$this, 'bkap_show_printable_ticket_settings' ), 10 );
			add_action( 'admin_init', array( &$this, 'bkap_printable_ticket_plugin_options' ) );
			add_filter( 'bkap_send_ticket', array( &$this, 'bkap_send_ticket_content' ), 10, 2 );
			add_action( 'bkap_send_email', array( &$this, 'bkap_send_ticket_email' ), 10, 1 );
			add_action( 'woocommerce_order_status_completed', array( &$this, 'woocommerce_complete_order' ), 10, 1 );
			add_action( 'bkap_add_submenu', array( &$this, 'printable_ticket_menu' ) );

			add_action( 'admin_init', array( &$this, 'edd_sample_register_option_print_ticket' ) );
			add_action( 'admin_init', array( &$this, 'edd_sample_deactivate_license_print_ticket' ) );
			add_action( 'admin_init', array( &$this, 'edd_sample_activate_license_print_ticket' ) );

			// Add Printable Ticket Columns in the new View Bookings page.
			add_filter( 'manage_edit-' . $this->type . '_columns', array( &$this, 'bkap_printable_edit_columns' ), 20, 1 );
			add_action( 'manage_' . $this->type . '_posts_custom_column', array( &$this, 'bkap_printable_custom_columns' ), 20, 1 );
			add_filter( 'bkap_bookings_csv_columns', array( $this, 'bkap_bookings_csv_columns' ), 10, 1 );
			add_filter( 'bkap_bookings_csv_individual_data', array( &$this, 'bkap_printable_csv_data' ), 10, 3 );
			add_filter( 'bkap_view_bookings_print_individual_row', array( $this, 'bkap_print_individual_row' ), 10, 3 );
		}

		/**
		 * Include the Update file.
		 *
		 * @since 1.7
		 */
		public function printable_include_files() {
			include_once 'update-functions.php' ;
		}

		/**
		 * Show error notice is Booking plugin is not active.
		 *
		 * @since 1.0
		 */
		public function printable_ticket_error_notice() {
			if ( ! is_plugin_active( 'woocommerce-booking/woocommerce-booking.php' ) ) {
				echo "<div class=\"error\"><p>Printable Ticket Addon is enabled but not effective. It requires WooCommerce Booking and Appointment plugin in order to work.</p></div>";
			}
		}

		/**
		 * Called on the activation of the License.
		 *
		 * @since 1.0
		 */
		public function edd_sample_activate_license_print_ticket() {

			// listen for our activate button to be clicked.
			if ( isset( $_POST['edd_print_ticket_license_activate'] ) ) {
				// run a quick security check.
				if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) ) {
					return; // get out if we didn't click the Activate button.
				}

				// retrieve the license from the database.
				$license = trim( get_option( 'edd_sample_license_key_print_ticket_book' ) );

				// data to send in our API request.
				$api_params = array(
					'edd_action' => 'activate_license',
					'license'    => $license,
					'item_name'  => urlencode( EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK ), // the name of our product in EDD.
				);

				// Call the custom API.
				$response = wp_remote_get(
					add_query_arg(
						$api_params,
						EDD_SL_STORE_URL_PRINT_TICKET_BOOK
					),
					array(
						'timeout'   => 15,
						'sslverify' => false,
					)
				);

				// make sure the response came back okay.
				if ( is_wp_error( $response ) ) {
					return false;
				}

				// decode the license data.
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );

				update_option( 'edd_sample_license_status_print_ticket_book', $license_data->license );
			}
		}

		/**
		 * Illustrates how to deactivate a license key. This will descrease the site count.
		 */
		public function edd_sample_deactivate_license_print_ticket() {

			// listen for our activate button to be clicked.
			if ( isset( $_POST['edd_print_ticket_license_deactivate'] ) ) {

				// run a quick security check.
				if( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) ) {
					return; // get out if we didn't click the Activate button.
				}

				// retrieve the license from the database.
				$license = trim( get_option( 'edd_sample_license_key_print_ticket_book' ) );
				// data to send in our API request.
				$api_params = array(
					'edd_action' => 'deactivate_license',
					'license'    => $license,
					'item_name'  => urlencode( EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK ), // the name of our product in EDD.
				);

				// Call the custom API.
				$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_PRINT_TICKET_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );

				// make sure the response came back okay
				if ( is_wp_error( $response ) ) {
					return false;
				}

				// decode the license data.
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );

				if ( 'deactivated' === $license_data->license ) {
					delete_option( 'edd_sample_license_status_print_ticket_book' );
				}
			}
		}

		/**
		 * This illustrates how to check if a license key is still valid the updater does this for you, so this is only needed if you want to do something custom.
		 */
		public function edd_sample_check_license_print_ticket() {
			global $wp_version;

			$license = trim( get_option( 'edd_sample_license_key_print_ticket_book' ) );

			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $license,
				'item_name'  => urlencode( EDD_SL_ITEM_NAME_PRINT_TICKET_BOOK ),
			);

			// Call the custom API.
			$response = wp_remote_get( add_query_arg( $api_params, EDD_SL_STORE_URL_PRINT_TICKET_BOOK ), array( 'timeout' => 15, 'sslverify' => false ) );

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( 'valid' === $license_data->license ) {
				echo 'valid';
				exit;
			} else {
				echo 'invalid';
				exit;
			}
		}

		/**
		 * REgister license option.
		 *
		 * @since 1.0
		 */
		public function edd_sample_register_option_print_ticket() {
			// creates our settings in the options table.
			register_setting( 'edd_print_ticket_license', 'edd_sample_license_key_print_ticket_book', array( &$this, 'edd_sanitize_license_print_ticket' ));
		}

		/**
		 * Removing old license value.
		 *
		 * @since 1.0
		 */
		public function edd_sanitize_license_print_ticket( $new ) {
			$old = get_option( 'edd_sample_license_key_print_ticket_book' );
			if ( $old && $old != $new ) {
				delete_option( 'edd_sample_license_status_print_ticket_book' ); // new license has been entered, so must reactivate.
			}
			return $new;
		}

		/**
		 * License activation page.
		 *
		 * @since 1.0
		 */
		public function edd_sample_license_page_print_ticket() {

			$license = get_option( 'edd_sample_license_key_print_ticket_book' );
			$status  = get_option( 'edd_sample_license_status_print_ticket_book' );

			?>
			<div class="wrap">
				<h2><?php _e( 'Plugin License Options' ); ?></h2>
				<form method="post" action="options.php">

					<?php settings_fields( 'edd_print_ticket_license' ); ?>

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

		/**
		 * Menu for activating the plugin license.
		 *
		 * @since 1.0
		 */
		public function printable_ticket_menu() {
			$page = add_submenu_page('edit.php?post_type=bkap_booking', __( 'Activate Printable Ticket License', 'woocommerce-booking' ), __( 'Activate Printable Ticket License', 'woocommerce-booking' ), 'manage_woocommerce', 'print_ticket_license_page', array(&$this, 'edd_sample_license_page_print_ticket' ));
		}

		/**
		 * Adding custom table.
		 *
		 * @since 1.0
		 */
		public function printable_ticket_activate() {
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

		/**
		 * Settings Booking-> Settings-> Addon Settings.
		 *
		 * @since 1.0
		 */
		public function bkap_show_printable_ticket_settings() {

			if ( isset( $_GET[ 'action' ] ) ) {
				$action = $_GET[ 'action' ];
			} else {
				$action = '';
			}

			if ( 'addon_settings' == $action ) {
				?>
				<div id="content">
					<?php do_action( 'printable_notes' );?>
					<form method="post" action="options.php">
					<?php settings_fields( 'bkap_printable_tickets_settings' ); ?>
					<?php do_settings_sections( 'woocommerce_booking_page-bkap_printable_tickets_settings_section' ); ?>
					<?php submit_button(); ?>
					</form>
				</div>
				<?php
			}
		}

		/**
		 * Settings Booking-> Settings-> Addon Settings.
		 *
		 * @since 1.0
		 */
		public function bkap_printable_ticket_plugin_options() {

			add_settings_section(
				'bkap_printable_tickets_settings_section',
				__( 'Printable Tickets Addon Settings', 'printable-tickets' ),
				array( $this, 'bkap_printable_tickets_callback' ),
				'woocommerce_booking_page-bkap_printable_tickets_settings_section'
			);

			add_settings_field(
				'booking_printable_ticket',
				__( 'Send tickets via email:', 'printable-tickets' ),
				array( &$this, 'booking_printable_ticket_callback' ),
				'woocommerce_booking_page-bkap_printable_tickets_settings_section',
				'bkap_printable_tickets_settings_section',
				array( __( 'Allow customers to send ticket to email when an order is placed.', 'printable-tickets' ) )
			);

			add_settings_field(
				'booking_send_ticket_method',
				__( 'Ticket Sending Method:', 'printable-tickets' ),
				array( &$this, 'booking_send_ticket_method_callback' ),
				'woocommerce_booking_page-bkap_printable_tickets_settings_section',
				'bkap_printable_tickets_settings_section',
				array( __( 'Enable Send 1 ticket per quantity to send ticket for each product and each quantity of product in order and Send 1 ticket per product to send each ticket per product in order.', 'printable-tickets' ) )
			);

			register_setting(
				'bkap_printable_tickets_settings',
				'booking_printable_ticket'
			);

			register_setting(
				'bkap_printable_tickets_settings',
				'booking_send_ticket_method'
			);
		}

		public function bkap_printable_tickets_callback() { }

		/**
		 * Send 1 ticket per quantity.
		 *
		 * @since 1.0
		 */
		public function booking_printable_ticket_callback( $args ) {
			$printable_ticket = "";
			if ( get_option( 'booking_printable_ticket' ) == 'on' ) {
				$printable_ticket = 'checked';
			}
			echo '<input type="checkbox" id="booking_printable_ticket" name="booking_printable_ticket"' . $printable_ticket .'/>';
			$html = '<label for="booking_printable_ticket"> ' . $args[ 0 ] . '</label>';
			echo $html;
		}

		/**
		 * Send 1 ticket per quantity.
		 *
		 * @since 1.0
		 */
		public function booking_send_ticket_method_callback( $args ) {
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

		/**
		 * Get random id.
		 *
		 * @since 1.0
		 */
		public function assign_rand_value( $num ) {
			// accepts 1 - 36
			switch( $num ) {
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

		/**
		 * Get random id.
		 *
		 * @since 1.0
		 */
		public function get_rand_id( $length ) {
			if ( $length > 0 ) {
				$rand_id = '';

				for( $i=1; $i <= $length; $i++ ) {
						mt_srand( (double)microtime() * 1000000);
					$num      = mt_rand( 1, 36 );
					$rand_id .= $this->assign_rand_value( $num) ;
				}
			}

			return $rand_id;
		}

		/**
		 * Replacing the booking data with the mergecodes in ticket content.
		 *
		 * @since 1.0
		 */
		public function bkap_send_ticket_content( $values, $order ) {

			global $wpdb;

			$ticket = array();

			$order_status = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->status : $order->get_status();
			if ( 'completed' === $order_status ) {

				$saved_settings   = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
				$product_id       = $values['product_id'];
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				if ( 'on' === get_option( 'booking_printable_ticket' ) ) {
					if ( isset( $booking_settings['booking_enable_date'] ) && 'on' === $booking_settings['booking_enable_date'] ) {
						if ( array_key_exists( 'data', $values ) ) {
							$_product     = $values['data'];
							$product_name = $_product->get_title();
						} else {
							$product_name = $values['name'];
						}
						$from_email       = get_option( 'admin_email' );
						$wc_v300_check    = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 );
						$buyers_firstname = $wc_v300_check ? $order->billing_first_name : $order->get_billing_first_name();
						$buyers_lastname  = $wc_v300_check ? $order->billing_last_name : $order->get_billing_last_name();
						$to               = $wc_v300_check ? $order->billing_email : $order->get_billing_email();
						$post_id          = $values['product_id'];
						$headers_email[]  = 'From:' . $from_email;
						$headers_email[]  = 'Content-type: text/html';
						$order_id         = $wc_v300_check ? $order->id : $order->get_id();

						// get the booking post ID.
						$item_id = ( isset( $values['item_id'] ) ) ? $values['item_id'] : 0;

						if ( $item_id > 0 ) {
							$bkap_post_id = bkap_common::get_booking_id( $item_id );

							if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
								$completed_date = date( 'F j, Y', strtotime( $order->completed_date ) );
							} elseif ( bkap_wc_hpos_enabled() ) {
									$order          = wc_get_order( $order_id );
									$order_date     = strtotime( $order->get_date_created() );
									$completed_date = date( 'F d, Y', $order_date );
							} else {
								$order_post     = get_post( $order_id );
								$post_date      = strtotime( $order_post->post_date );
								$completed_date = date( 'F d, Y', $post_date );
							}

							$subject = 'Your Ticket for Order #' . $order_id . ' from ' . $completed_date;

							$logo         = get_header_image();
							$message      = '';
							$booking      = '';
							$addon        = '';
							$site_url     = get_site_url();
							$site_title   = get_option( 'blogname' );
							$site_tagline = get_option( 'blogdescription' );

							if ( array_key_exists( 'bkap_booking', $values ) ) {
								$bookings = $values['bkap_booking'];

								if ( array_key_exists( 'date', $bookings[0] ) && '' !== $bookings[0]['date']  ) {
									$booking_date = date( 'd F, Y', strtotime( $bookings[0]['hidden_date'] ) );
									$booking      = get_option( 'book_item-meta-date' ) . ': ' . $booking_date . '<br>';
								}
								if ( array_key_exists( 'date_checkout', $bookings[0] ) && '' !== $bookings[0]['date_checkout'] ) {
									$booking_date_checkout = date('d F, Y', strtotime( $bookings[0]['hidden_date_checkout'] ) );
									$booking              .= get_option( 'checkout_item-meta-date' ) . ': ' . $booking_date_checkout . '<br>';
								}
								if ( array_key_exists( 'time_slot', $bookings[0] ) && '' !== $bookings[0]['time_slot'] ) {
									$booking .= get_option( 'book_item-meta-time' ) . ': ' . $bookings[0]["time_slot"] . '<br>';
								}
								$hidden_date = $bookings[0]['hidden_date'];
								$date_query  = date('Y-m-d', strtotime( $hidden_date ) );
								$booking_id  = array();

								$booking_id_query   = 'SELECT booking_id FROM `' . $wpdb->prefix . 'booking_order_history` WHERE order_id = %d';
								$booking_id_results = $wpdb->get_results( $wpdb->prepare( $booking_id_query, $order_id ) );
								// This is to figure out for which Item in the order are tickets to be created for.
								foreach ( $booking_id_results as $k => $v ) {

									$booking_id_to_use_query = 'SELECT id FROM `' . $wpdb->prefix . 'booking_history` WHERE id = %d AND post_id = %d';
									$booking_id_to_use       = $wpdb->get_results( $wpdb->prepare( $booking_id_to_use_query, $v->booking_id, $product_id ) );

									if ( count( $booking_id_to_use ) > 0 ) {
										break;
									}
								}
							}
							if ( function_exists( 'is_bkap_tours_active' ) && is_bkap_tours_active() ) {
								if ( isset( $booking_settings['show_tour_operator'] ) && 'on' === $booking_settings['show_tour_operator'] ) {
									$booking_tour_operator = $booking_settings['booking_tour_operator'];
									$user                  = get_userdata( $booking_tour_operator );
									if ( isset( $user->user_login ) ) {
										$booking .= 'Tour Operator: ' . $user->user_login . '<br>';
									}
									if ( isset( $booking_settings['booking_show_comment'] ) && 'on' === $booking_settings['booking_show_comment'] ) {
										$booking .= bkap_get_book_t( 'book.item-comments' ) . ': ' . $bookings[0]['comments'] . '<br>';
									}
								}
							}

							if ( is_plugin_active( 'woocommerce-product-addons/product-addons.php' ) ) {
								$addons = $values['addons'];
								foreach ( $addons as $key ) {
									$addon .= $addons[ $f ]['name'] . ': ' . $addons[ $f ]['value'] . '<br>';
								}
							}

							$send_by = get_option( 'booking_send_ticket_method', '' );

							switch ( $send_by ) {
								case 'send_by_quantity':
									$quantity = $values['quantity'];
									for ( $i = 0; $i < $quantity; $i++ ) {
										$ticket_data        = $this->create_ticket_details();
										$ticket_no          = $ticket_data['ticket_id'];
										$security_unique_no = $ticket_data['security_code'];

										// get the content.
										$message .= $this->get_template();
										$message  = str_replace( '{{site_title}}', $site_title, $message );
										$message  = str_replace( '{{site_tagline}}', $site_tagline, $message );
										$message  = str_replace( '{{product_name}}', $product_name, $message );
										$message  = str_replace( '{{heading_ticket_number}}', $this->headings['ticket_number'], $message );
										$message  = str_replace( '{{ticket_no}}', $ticket_no, $message );
										$message  = str_replace( '{{headings_booking_details}}', $this->headings['booking_details'], $message );
										$message  = str_replace( '{{booking}}', $booking, $message );
										$message  = str_replace( '{{addon}}', $addon, $message );
										$message  = str_replace( '{{headings_buyer}}', $this->headings['buyer'], $message );
										$message  = str_replace( '{{buyers_firstname}}', $buyers_firstname, $message );
										$message  = str_replace( '{{buyers_lastname}}', $buyers_lastname, $message );
										$message  = str_replace( '{{headings_security_code}}', $this->headings['security_code'], $message );
										$message  = str_replace( '{{security_unique_no}}', $security_unique_no, $message );
										$message  = str_replace( '{{site_url}}', $site_url, $message );

										// check if a record is already present.
										$existing_ticket = get_post_meta( $bkap_post_id, '_bkap_ticket_id', true );

										if ( is_array( $existing_ticket ) && count( $existing_ticket ) > 0 ) {
											if ( ! in_array( $ticket_no, $existing_ticket ) ) {
												array_push( $existing_ticket, $ticket_no );
											}
										} else {
											$existing_ticket = array( $ticket_no );
										}

										$existing_codes = get_post_meta( $bkap_post_id, '_bkap_security_code', true );

										if ( is_array( $existing_codes ) && count( $existing_codes ) > 0 ) {
											if ( ! in_array( $security_unique_no, $existing_codes ) ) {
												array_push( $existing_codes, $security_unique_no );
											}
										} else {
											$existing_codes = array( $security_unique_no );
										}

										update_post_meta( $bkap_post_id, '_bkap_ticket_id', $existing_ticket );
										update_post_meta( $bkap_post_id, '_bkap_security_code', $existing_codes );
									}
									break;
								case 'send_by_product':
									$ticket_data        = $this->create_ticket_details();
									$ticket_no          = $ticket_data['ticket_id'];
									$security_unique_no = $ticket_data['security_code'];

									// get the content.
									$message = $this->get_template();

									$message = str_replace( '{{site_title}}', $site_title, $message );
									$message = str_replace( '{{site_tagline}}', $site_tagline, $message );
									$message = str_replace( '{{product_name}}', $product_name, $message );
									$message = str_replace( '{{heading_ticket_number}}', $this->headings['ticket_number'], $message );
									$message = str_replace( '{{ticket_no}}', $ticket_no, $message );
									$message = str_replace( '{{headings_booking_details}}', $this->headings['booking_details'], $message );
									$message = str_replace( '{{booking}}', $booking, $message );
									$message = str_replace( '{{addon}}', $addon, $message );
									$message = str_replace( '{{headings_buyer}}', $this->headings['buyer'], $message );
									$message = str_replace( '{{buyers_firstname}}', $buyers_firstname, $message );
									$message = str_replace( '{{buyers_lastname}}', $buyers_lastname, $message );
									$message = str_replace( '{{headings_security_code}}', $this->headings['security_code'], $message );
									$message = str_replace( '{{security_unique_no}}', $security_unique_no, $message );
									$message = str_replace( '{{site_url}}', $site_url, $message );
									$j       = 0;

									// check if a record is already present.
									$existing_ticket = get_post_meta( $bkap_post_id, '_bkap_ticket_id', true );

									if ( is_array( $existing_ticket ) && count( $existing_ticket ) > 0 ) {
										if ( ! in_array( $ticket_no, $existing_ticket ) ) {
											array_push( $existing_ticket, $ticket_no );
										}
									} else {
										$existing_ticket = array( $ticket_no );
									}

									$existing_codes = get_post_meta( $bkap_post_id, '_bkap_security_code', true );

									if( is_array( $existing_codes ) && count( $existing_codes ) > 0 ) {
										if ( ! in_array( $security_unique_no, $existing_codes ) )
											array_push( $existing_codes, $security_unique_no );
									} else {
										$existing_codes = array( $security_unique_no );
									}

									update_post_meta( $bkap_post_id, '_bkap_ticket_id', $existing_ticket );
									update_post_meta( $bkap_post_id, '_bkap_security_code', $existing_codes );
									break;
							}
							$ticket[] = array(
								'to'      => $to,
								'subject' => $subject,
								'message' => $message,
								'headers' => $headers_email
							);
						}
					}
				}
			}

			return $ticket;
		}

		/**
		 * Generates the Ticket ID and unique Security Code and retrns the same to be used in the tickset sent to the user.
		 * @return array:number string
		 * @since 1.7
		 */
		public function create_ticket_details() {

			global $wpdb;

			$last_count = get_option( '_bkap_last_ticket_id' );

			if ( isset( $last_count ) && is_numeric( $last_count ) ) {
			} else {

				$ticket_sql = "SELECT MAX(CAST(booking_meta_value AS unsigned)) AS ticket_id FROM `".$wpdb->prefix."booking_item_meta` WHERE booking_meta_key = '_ticket_id'";

				$ticket_results = $wpdb->get_results($ticket_sql);
				$last_count = $ticket_results[0]->ticket_id;
				if($last_count == '') {
					$last_count = 1;
				}

			}
			$ticket_no = $last_count + 1;

			$security_unique_no = $this->get_rand_id(10);

			update_option( '_bkap_last_ticket_id', $ticket_no );

			return array( 'ticket_id' => $ticket_no,
				'security_code' => $security_unique_no
			);
		}

		/**
		 * Fetches the email template to be sent to the user.
		 * @since 1.0
		 */
		public function get_template() {

			ob_start();
			wc_get_template( 'printable-ticket-email-template.php',array(),'bkap-printable-tickets/', dirname( __FILE__ ).'/templates/' );
			return ob_get_clean();
		}

		/**
		 * Sends the tickets email.
		 * @param string $ticket_content Ticket Content.
		 * @since 1.0
		 */
		public function bkap_send_ticket_email($ticket_content) {

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

		/**
		 * Sends the tickets when the order status is changed to "Completed".
		 * @param int $order_id Order ID.
		 * @since 1.0
		 */
		public function woocommerce_complete_order( $order_id ) {
			global $wpdb, $bkap_date_formats;
			$saved_settings = json_decode(get_option('woocommerce_booking_global_settings'));
			$message = '';
			if ( get_option( 'booking_printable_ticket' ) == 'on' ) {
				$order_obj   = wc_get_order($order_id);
				$order_items = $order_obj->get_items();

				$ticket_content = array();
				foreach($order_items as $item_key => $item_value) {
					$values = array();
					$hidden_date = '';
					$hidden_date_checkout = '';
					// CRUD has been implemented since Woo 3.0.0
					// continuing to provide backward compatibility
					if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
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
					$values[ 'item_id' ] = $item_key;
					$values['quantity'] = $quantity;

					$duplicate_of = bkap_common::bkap_get_product_id( $product_id );
					$values['product_id'] = $duplicate_of;

					$values['name'] = $product_name;

					if ( isset( $date ) && '' != $date ) {
						$date_format_set = $bkap_date_formats[ $saved_settings->booking_date_format ];
						$date_formatted = date_create_from_format( $date_format_set, $date );
						if ( isset( $date_formatted ) && $date_formatted != '' ) {
							$hidden_date = date_format( $date_formatted, 'j-n-Y' );
						}
						$values['bkap_booking'][0]['date'] = $date;
						$values['bkap_booking'][0]['hidden_date'] = $hidden_date;
					}

					if ( isset( $date_checkout ) && '' != $date_checkout ) {
						$date_format_set = $bkap_date_formats[ $saved_settings->booking_date_format ];
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

		/**
		 * Add ticket ID and security code in the csv file.
		 * @param array $cols
		 * @since 1.7
		 */
		public function bkap_bookings_csv_columns( $cols ) {

			$cols['ticket_id'] = __( 'Ticket ID', 'woocommerce-booking' );
			$cols['security_code'] = __( 'Security Code', 'woocommerce-booking' );

			$user_id = get_current_user_id();
			if ( ! empty( $user_id ) ) {
				$h_cols = get_user_meta( $user_id, 'manageedit-bkap_bookingcolumnshidden', true );
				if ( ! empty( $h_cols ) ) {
					foreach ( $h_cols as $column ) {
						switch ( $column ) {
							case 'bkap_ticket_id':
								unset( $cols['ticket_id'] );
								break;
							case 'bkap_security_code':
								unset( $cols['security_code'] );
								break;
						}
					}
				}
			}

			return $cols;
		}

		/**
		 * Add ticket ID and security code in the csv to be downloaded.
		 * @param str $column
		 * @since 1.7
		 */
		public function bkap_printable_csv_data( $csv, $booking, $booking_id ) {

			$columns = BKAP_Admin_API_View_Bookings::bkap_get_csv_cols();

			if ( isset( $columns['ticket_id'] ) ) {
				// ticket ID
				$ticket_array = get_post_meta( $booking_id, '_bkap_ticket_id', true );
				$ticket_ids   = ( is_array( $ticket_array ) ) ? implode( ',', $ticket_array ) : '';
				$csv         .= ',"' . $ticket_ids . '"';
			}

			if ( isset( $columns['security_code'] ) ) {
				// security code
				$security_array = get_post_meta( $booking_id, '_bkap_security_code', true );
				$security_codes = is_array( $security_array ) ? implode( ',', $security_array ) : '';
				$csv           .= ',"' . $security_codes . '"';
			}

			return $csv;
		}

		/**
		 * Add ticket ID and security code in the rows to be printed.
		 * @param str $column
		 * @since 1.7
		 */
		public function bkap_print_individual_row( $print_data_row_data, $booking, $booking_id ) {

			$columns = BKAP_Admin_API_View_Bookings::bkap_get_csv_cols();

			if ( isset( $columns['ticket_id'] ) ) {
				// ticket ID.
				$ticket_array         = get_post_meta( $booking_id, '_bkap_ticket_id', true );
				$ticket_ids           = ( is_array( $ticket_array ) ) ? implode( '<br>', $ticket_array ) : '';
				$print_data_row_data .= '<td style="border:1px solid black;padding:5px;">' . $ticket_ids . '</td>';
			}
			
			if ( isset( $columns['security_code'] ) ) {
				// security code.
				$security_array       = get_post_meta( $booking_id, '_bkap_security_code', true );
				$security_codes       = is_array( $security_array ) ? implode( '<br>', $security_array ) : '';
				$print_data_row_data .= '<td style="border:1px solid black;padding:5px;">' . $security_codes . '</td>';
			}

			return $print_data_row_data;
		}

		/**
		 * Add columns on the View Bookings page.
		 * @param array $existing_columns
		 * @return multitype:
		 * @since 1.7
		 */
		public function bkap_printable_edit_columns( $existing_columns ) {

			global $post_type;

			if ( $post_type === $this->type ) {

				$columns                       = array();
				$columns["bkap_ticket_id"]     = __( 'Ticket ID', 'woocommerce-booking' );
				$columns["bkap_security_code"] = __( 'Security Code', 'woocommerce-booking' );

				return array_merge( $existing_columns, $columns );
			}
		}

		/**
		 * Adds column data.
		 * @param str $column
		 * @since 1.7
		 */
		public function bkap_printable_custom_columns( $column ) {

			global $wpdb, $post;

			if ( get_post_type( $post->ID ) === $this->type ) {

				$booking_id = $post->ID;
				switch ( $column ) {
					case 'bkap_ticket_id' :
						$ticket_list = '';
						$tickets = get_post_meta( $booking_id, '_bkap_ticket_id', true );
						if ( is_array( $tickets ) && count( $tickets ) > 0 )
							$ticket_list = implode( '<br>', $tickets );

						echo $ticket_list;
						break;
					case 'bkap_security_code' :
						$code_list =
						$codes = get_post_meta( $booking_id, '_bkap_security_code', true );

						if ( is_array( $codes ) && count( $codes ) > 0 )
							$code_list = implode( '<br>', $codes );

						echo $code_list;
						break;
				}
			}
		}
	}
}
$printable_tickets = new BKAP_Printable_Tickets();
?>
