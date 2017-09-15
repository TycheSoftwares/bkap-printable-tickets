<?php 
add_action( 'admin_notices', 'printable_17_update_notice' );
add_action( 'printable_notes', 'printable_db_update_steps' );
function printable_17_update_notice() {
    
    // get plugin data
    $plugin_data = get_plugin_data( plugin_dir_path( __FILE__ ) . '/printable-tickets.php' );
    
    $plugin_version = ( isset( $plugin_data[ 'Version' ] ) && '' !== $plugin_data[ 'Version' ] ) ? $plugin_data[ 'Version' ] : '';

    $valid_statuses = array( 'fail', 'success' );
    $_status = get_option( 'printable_17_db_status' );
    if ( $plugin_version == '1.7' && ! in_array( $_status, $valid_statuses ) ) {

        $class = 'notice notice-error';
        $class .= ' is-dismissible';
        $message = '
        			    <table width="100%">
                            <tr>
                                <td style="text-align:left;">';
        
        $message .= __( 'We need to run a database update to migrate your ticket details to the booking posts. Please click on the Update Now button to start the process.', 'woocommerce-booking' );
        
        $message .= '</td>
                                <td style="text-align:right;">
                                    <button type="submit" class="button-primary" id="bkap_db_420_update"  onClick="printable_17_update()">';
        
        $message .=  __( 'Update Now', 'woocommerce-booking' );
        
        $message .= '
                                    </button>
                                </td>
                            </tr>
        			    </table>';
        
        printf( '<div class="%1$s">%2$s</div>', $class, $message );
        
        ?>
        <script type="text/javascript">

        function printable_17_update() {

        	// take the user to a new page
        	var url = '<?php echo get_admin_url() . 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=addon_settings'; ?>';
        	window.location.href = url;
        	
        }
        </script>
        <?php
    }
    
}

function printable_db_update_steps() {

    global $wpdb;
    
    $_status = get_option( 'printable_17_db_status' );
    $valid_statuses = array( 'fail', 'success' );
    
    if ( ! empty( $_POST[ 'printable_17' ] ) || isset( $_GET[ 'loop_next_view' ] ) ) {
        $number_of_batches = bkap_get_post_count();

        if ( isset( $_GET[ 'batch' ] ) && $_GET[ 'batch' ] != 0 ) {
            $loop = $_GET[ 'batch' ];
            $loop = (int)$loop;
        }else{
            $loop = 1;
        }
        
        if ( $loop <= $number_of_batches ){
            printable_17_migrate( $loop );

            $status_percent = round( ( ( $loop * 100 ) / $number_of_batches ), 0 );
            // add the progress
            ?>
                <style type="text/css">
                    #bkap_update_progress {
                        width: 100%;
                        background-color: grey;
                    }

                    #bkap_progress_bar {
                        width: <?php echo $status_percent;?>%;
                        height: 30px;
                        background-color: #0085ba;
                        text-align: center;
                        line-height: 30px;
                        color: white; 
                    }
                </style>
                <div id="bkap_update_progress">
                    <div id="bkap_progress_bar"><?php echo $status_percent;?>%</div>
                </div>
            <?php
                
            $loop = $loop + 1;
            // reload the page so the progress can be displayed                
            $args = array( 'post_type' => $_REQUEST[ 'post_type' ],
                        'page' => $_REQUEST[ 'page' ],
                        'action' => $_REQUEST[ 'action' ],
                        'batch' => $loop,
                        'loop_next_view' => 'true',
             );

            $redirect_url = add_query_arg( $args, get_admin_url() . 'edit.php' );

            ?>
                <script type="text/javascript">
                    window.location.href = "<?php echo $redirect_url;?>";
                </script>
            <?php
        }else {
            
            // now take the last ticket number generated and save it for future use
            $ticket_sql = "SELECT MAX(CAST(booking_meta_value AS unsigned)) AS ticket_id FROM `{$wpdb->prefix}booking_item_meta` WHERE booking_meta_key = %s";
            $ticket_results = $wpdb->get_results($wpdb->prepare( $ticket_sql, '_ticket_id' ) );
            
            $ticket_no = $ticket_results[0]->ticket_id;
             
            if($ticket_no == '') {
                $ticket_no = 0;
            }
             
            // save
            update_option( '_bkap_last_ticket_id', $ticket_no );
            
            $view_status = 'success';
            $view_update_stat = get_option( 'printable_17_db_stats' );
            if ( isset( $view_update_stat ) && count( $view_update_stat ) > 0 && is_array( $view_update_stat ) ) {
                foreach ( $view_update_stat as $stat_key => $stat_value ) {
                    if ( isset( $stat_value['failed_count'] ) && $stat_value['failed_count'] > 0 ) {
                        $view_status = 'fail';
                        break;
                    }
                }
            }
            if ( $view_status === 'success' ) {
                update_option( 'printable_17_db_status', 'success' );
            }else if ( $view_status === 'fail' ) {
                update_option( 'printable_17_db_status', 'fail' );
            }
            
            // reload the page so the progress can be displayed
            $args = array( 'post_type' => $_REQUEST[ 'post_type' ],
                'page' => $_REQUEST[ 'page' ],
                'action' => $_REQUEST[ 'action' ],
            );
            
            $redirect_url = add_query_arg( $args, get_admin_url() . 'edit.php' );
            
            ?>
                <script type="text/javascript">
                    window.location.href = "<?php echo $redirect_url;?>";
                </script>
            <?php
                        
        }
        
    }

    
    if ( isset( $_status ) && ! in_array( $_status, $valid_statuses ) ) {
        ?>
        <form method="post">
            <p><h3>
        <?php 
            _e( 'To ensure you experience a smooth migration to Printable Tickets Addon Version 1.7, we suggest you run the data migration process. Please click on the Start button to begin.', 'woocommerce-booking' );
            ?>
            </h3><br>
            <input type="submit" class="button-primary" name="printable_17" value="Start" /> 
        </form>
        <?php
    }
}
function printable_17_migrate( $loop ) {
     
    global $wpdb;
    // all booking posts
    $args       = array( 'post_type' => 'bkap_booking', 'numberposts' => 500, 'suppress_filters' => false, 'post_status' => array( 'all' ), 'paged' => $loop );
    $booking_posts = get_posts( $args );

    $item_count = 0;
    $success_count = 0;
    $failed_items = array();
    
    $db_stats = array();
    $db_stats = get_option( 'printable_17_db_stats' );
    
    $update_stats = array();
    foreach ( $booking_posts as $k => $value ) {
        
        // Booking ID
        $theid = $value->ID;
         
        // get the item ID for the post
        $item_id = get_post_meta( $theid, '_bkap_order_item_id', true );
         
        $order_res = $wpdb->get_col( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item_id ) );
        // get the order ID
        $order_id = isset( $order_res[0] ) ? $order_res[0] : 0;
         
        if ( $order_id > 0 ) {
            // see if ticket details are present for that order
            $tickets_query = "SELECT booking_id, booking_meta_key, booking_meta_value FROM {$wpdb->prefix}booking_item_meta WHERE order_id = %d";
             
            $tickets_result = $wpdb->get_results( $wpdb->prepare( $tickets_query, $order_id ) );
             
            if ( is_array( $tickets_result ) && count( $tickets_result ) > 0 ) {
                $item_count++;
                // if yes, then we need to compare the booking details as we need to ensure the match
                for( $i=0; $i < count( $tickets_result ); $i += 2 ) {
                    $ticket_id = 0;
                    $security_code = '';

                    $booking_history_id = $tickets_result[$i]->booking_id;

                    $booking_details = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, start_date, end_date, from_time, to_time FROM {$wpdb->prefix}booking_history WHERE ID = %d", $booking_history_id ) );

                    if ( is_array( $booking_details ) && count( $booking_details ) > 0 ) {
                        $start_date = date( 'Ymd', strtotime( $booking_details[0]->start_date ) );

                        if ( isset( $booking_details[0]->end_date ) && '0000-00-00' !== $booking_details[0]->end_date ) {
                            $end_date = date( 'Ymd', strtotime( $booking_details[0]->end_date ) );
                        } else {
                            $end_date = $start_date;
                        }

                        if ( isset( $booking_details[0]->from_time ) && '' !== $booking_details[0]->from_time ) {
                            $start_date .= date( 'His', strtotime( $booking_details[0]->from_time ) );
                            if ( isset( $booking_details[0]->to_time ) && '' !== $booking_details[0]->to_time ) {
                                $end_date .= date( 'His', strtotime( $booking_details[0]->to_time ) );
                            }
                        } else {
                            $start_date .= '000000';
                            $end_date .= '000000';
                        }

                        $booking_start = get_post_meta( $theid, '_bkap_start', true );
                        $booking_end = get_post_meta( $theid, '_bkap_end', true );

                        if ( $booking_end === $end_date && $booking_start === $start_date ) {
                            
                            if ( $tickets_result[$i]->booking_meta_key === '_ticket_id' ) {
                                $ticket_id = $tickets_result[$i]->booking_meta_value;
                            }
                            if ( $tickets_result[$i+1]->booking_meta_key === '_security_code' ) {
                                $security_code = $tickets_result[ $i+1 ]->booking_meta_value;
                            }

                            if ( $ticket_id > 0 && $security_code !== '' ) {
                                
                                // check if a record is already present
                                $existing_ticket = get_post_meta( $theid, '_bkap_ticket_id', true );

                                if ( is_array( $existing_ticket ) && count( $existing_ticket ) > 0 ) {
                                    if ( ! in_array( $ticket_id, $existing_ticket ) )
                                        array_push( $existing_ticket, $ticket_id );
                                } else {
                                    $existing_ticket = array( $ticket_id );
                                }

                                $existing_codes = get_post_meta( $theid, '_bkap_security_code', true );

                                if( is_array( $existing_codes ) && count( $existing_codes ) > 0 ) {
                                    if ( ! in_array( $security_code, $existing_codes ) )
                                        array_push( $existing_codes, $security_code );
                                } else {
                                    $existing_codes = array( $security_code );
                                }

                                update_post_meta( $theid, '_bkap_ticket_id', $existing_ticket );
                                update_post_meta( $theid, '_bkap_security_code', $existing_codes );
                            } else {
                                $failed_items[] = $theid;
                            }

                        }
                    }

                }
                
                if ( ! in_array( $theid, $failed_items ) ) 
                    $success_count++;
                $update_stats = array(
                    'item_count' => $item_count,
                    'post_count' => $success_count,
                    'failed_count' => count( $failed_items ),
                    'failed_items' => $failed_items
                );
    
            }
        }
    }
    $db_stats[] = $update_stats;
    
    update_option( 'printable_17_db_stats', $db_stats );
    
}

function bkap_get_post_count() {
    
    $args = array( 'post_type' => 'bkap_booking', 'numberposts' => -1, 'post_status' => array( 'all' ), 'suppress_filters' => false );
    $booking_posts = get_posts( $args );
    
    $count = count( $booking_posts );
    
    $number_of_batches = $count/500;
    wp_reset_postdata();
    return $number_of_batches; 
}
?>