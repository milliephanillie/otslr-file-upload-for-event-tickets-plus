<?php
/**
 * Plugin Name: OTSLR Additional Fields
 * Description: Custom extentions for additional field types for Events Tickets Plus
 * Version: 1.0.0
 * Author: Philip Rudy
 * Author URI: https://www.philiparudy.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: otslr-forms
 * Domain Path: /languages
 */
 if(!defined('ABSPATH')) {
     exit;
 }

 if(!defined('OTSLR_ADD_PLUGIN_FILE')) {
     define('OTSLR_ADD_PLUGIN_FILE', __FILE__);
 }

 if(!defined('OTSLR_ADD_PLUGIN_PATH')) {
     define('OTSLR_ADD_PLUGIN_PATH', plugin_dir_path(__FILE__));
 }  

 if(!defined('OTSLR_ADD_PLUGIN_URL')) {
     define('OTSLR_ADD_PLUGIN_URL', plugin_dir_url(__FILE__));
 }

 if(!defined('OTSLR_ADD_ASSETS_VERSION')) {
     define('OTSLR_ADD_ASSETS_VERSION', '1.0.1');
 }

require OTSLR_ADD_PLUGIN_PATH . 'classes/class-otslr-logger.php';
//require OTSLR_ADD_PLUGIN_PATH . 'classes/class-otslr-session.php';
require OTSLR_ADD_PLUGIN_PATH . 'classes/class-otslr-upload.php';

if(class_exists('OtslrUpload')) {
    $upload = new OtslrUpload();
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'otslr-tickets',
        OTSLR_ADD_PLUGIN_URL . 'js/tickets.js',
        [],
        null,
        true
    );

    $session = new OtslrSession();
   // $cookie = $session->get_session_cookie() ?? '';
    $post_id = get_queried_object_id() ?: null;

    wp_localize_script('otslr-tickets', 'otslrVars', [
        'postId' => $post_id,
        'siteUrl' => get_site_url(),
        'restNonce' => wp_create_nonce('wp_rest'),
        // 'otslrFileCookie' => $cookie,
    ]);
});

add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script(
        'otslr-admin',
        OTSLR_ADD_PLUGIN_URL . 'js/admin.js',
        [ 'tribe-ea-fields' ], // <-- wait for this to be loaded first
        null,
        true
    );

    wp_localize_script('otslr-admin', 'otslrAdminVars', [
        'restNonce' => wp_create_nonce('wp_rest'),
        'siteUrl' => get_site_url(),
    ]);
});

add_filter( 'tribe_tickets_plus_field_types', function( $field_types ) {
    $field_types['file'] = 'Photo ID';
    return $field_types;
} );

add_filter( 'tribe_tickets_plus_meta_field_class', function( $class, $type, $data ) {
    if ( 'file' === $type ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-tribe-tickets-plus-meta-field-file.php';
        return 'Tribe__Tickets_Plus__Meta__Field__File';
    }
    return $class;
}, 10, 3 );


add_filter( 'tribe_events_tickets_attendees_table_column', function( $value, $item, $column ) {
	$attendee_id = $item['ID'];
	$meta_data   = get_post_meta( $attendee_id );
    $file_upload = $file = get_post_meta( $attendee_id, 'file-upload', true );
    // $log_output  = "\n--- Admin Attendee Meta Data ---\n";
	// $log_output .= "Attendee ID:" . print_r($attendee_id, true) . "\n";
	// $log_output .= "Attendee Meta: " . (print_r($meta_data , true) ?? 'Not Set') . "\n";
	// $log_output .= "Item Dump:\n" . print_r($item, true);
	// $log_output .= "----------------------------\n";

    OtslrLogger::custom_write([
        'the_hook'          => 'tribe_events_tickets_attendees_table_column',
        'logoutput'  => $log_output,
        'attendee_id'    => $attendee_id,
    ]);


	$file_url    = $item['attendee_meta'] && $item['attendee_meta']['file-upload'] ? $item['attendee_meta']['file-upload'] : '';

	if ( ! empty( $file_url ) ) {
		$value = '<a href="' . esc_url( $file_url ) . '" target="_blank">View File</a>';
	} else {
		$value = 'No file uploaded.';
	}

	return $value;
}, 10, 3 );
 
add_filter( 'tribe_tickets_attendee_table_columns', function( $columns ) {
	$new_column_headers = [];

	foreach ( $columns as $column_key => $column_name ) {
		$new_column_headers[ $column_key ] = $column_name;

		if ( 'ticket' === $column_key ) {
			$new_column_headers['file_upload'] = __( 'File Upload', 'otslr-extentions' );
		}
	}

	return $new_column_headers;
}, 20 );

add_action( 'tec_tickets_commerce_flag_action_generated_attendee', function( $attendee, $ticket, $order, $new_status, $old_status, $item, $i ) {
    $attendee_id = $attendee->ID;
    $ticket_id   = $ticket->ID;

    $transient_key = 'otslr_attendee_file_' . $ticket_id . '_' . ($i + 1);
    $data = get_transient( $transient_key );

    OtslrLogger::custom_write([
        'where'          => 'generated_attendee',
        'transient_key'  => $transient_key,
        'attendee_id'    => $attendee_id,
        'ticket_id'      => $ticket_id,
        'data'           => $data,
    ]);

    if ( ! empty( $data['label'] ) && ! empty( $data['url'] ) ) {
        $current_fields = get_post_meta( $attendee_id, '_tec_tickets_commerce_attendee_fields', true );
        $current_fields = is_array( $current_fields ) ? $current_fields : [];

        $new_fields = [ $data['label'] => $data['url'] ];
        $merged_fields = array_merge( $current_fields, $new_fields );

        tribe( \TEC\Tickets\Commerce\Module::class )->update_attendee( $attendee_id, [
            'attendee_meta' => $merged_fields,
        ]);
    }
}, 20, 7 );

add_action('wp_enqueue_scripts', function() {
    wp_add_inline_script(
        'tribe-aggregator-fields', // Handle from plugin
        'window.tribe_ev = window.tribe_ev || { state: {} };',
        'before'
    );
}, 20);

add_action('woocommerce_email_order_meta', function( $order, $sent_to_admin, $plain_text, $email ) {
    if ( ! $order instanceof \WC_Order ) {
        return;
    }

    $order_id = $order->get_id();
    $attendees_output = '';

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();

        // Get matching attendees for this order + product (tickets)
        $attendees = tribe_tickets_get_attendees( $order_id );

        foreach ( $attendees as $attendee ) {
            if ( intval( $attendee['product_id'] ) !== $product_id ) {
                continue;
            }

            $file_url = $attendee['attendee_meta']['file-upload'] ?? '';

            if ( $file_url ) {
                $attendees_output .= '<p><strong>' . esc_html( $attendee['purchaser_name'] ) . '</strong>: <a href="' . esc_url( $file_url ) . '" target="_blank">View Uploaded File</a></p>';
            }
        }
    }

    if ( $attendees_output ) {
        echo '<h3>Uploaded Files</h3>';
        echo $attendees_output;
    }
}, 40, 4);


add_filter( 'tribe_tickets_completed_order_email_attendee_content', function( $content, $attendee, $order ) {
	$file_url = $attendee['attendee_meta']['file-upload'] ?? '';

    OtslrLogger::custom_write([
        'howdy' => $attendee
    ]);

	if ( ! empty( $file_url ) ) {
		$custom_row = sprintf(
			'<tr>
				<td colspan="2" style="padding: 6px 0;">
					<strong>Photo ID:</strong> <a href="%s" target="_blank" rel="noopener noreferrer">View File</a>
				</td>
			</tr>',
			esc_url( $file_url )
		);

		return $content . $custom_row;
	}

	return $content;
}, 10, 3 );



