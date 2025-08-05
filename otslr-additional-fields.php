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

// Plugin Constants
define('OTSLR_ADD_PLUGIN_FILE', __FILE__);
define('OTSLR_ADD_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('OTSLR_ADD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OTSLR_ADD_ASSETS_VERSION', '1.0.1');

// Autoload and Dependencies
require OTSLR_ADD_PLUGIN_PATH . 'classes/class-otslr-session.php';
require_once 'vendor/autoload.php';

use OtslrAdditionalFields\Includes\OtslrUpload;
new OtslrUpload();


add_action('wp_enqueue_scripts', function () {
	wp_enqueue_media();

    wp_enqueue_script(
        'otslr-tickets',
        OTSLR_ADD_PLUGIN_URL . 'js/tickets.js',
        [],
        time(),
        true
    );

    $session = new OtslrSession();
    $cookie = $session->get_session_cookie() ?? '';
    $post_id = get_queried_object_id() ?: null;

    wp_localize_script('otslr-tickets', 'otslrVars', [
        'postId' => $post_id,
        'siteUrl' => get_site_url(),
        'restNonce' => wp_create_nonce('wp_rest'),
        'otslrFileCookie' => $cookie,
		'loader' => OTSLR_ADD_PLUGIN_URL . 'static/loading.gif'
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


if ( is_admin() ) {
	add_filter( 'tribe_events_tickets_attendees_table_column', function ( $value, $item, $column ) {
		$attendee_id = is_array( $item ) && isset( $item['attendee_id'] )
			? $item['attendee_id']
			: ( is_object( $item ) && isset( $item->attendee_id ) ? $item->attendee_id : 0 );

		$ticket_id = is_array( $item ) && isset( $item['product_id'] ) ? $item['product_id'] : 0;
		$index     = 0;

		// Try to infer index from ticket_id and attendee_id if needed
		if ( isset( $item['attendee_id'], $item['order_id'] ) ) {
			// Find attendee position if needed, or you can store it in the transient key during upload
			$index = 1; // fallback to 1 if not tracked
		}

		$file_url = '';

		// First check attendee_meta (already passed into $item)
		if ( is_array( $item ) && isset( $item['attendee_meta']['photo-id']['value'] ) ) {
			$file_url = $item['attendee_meta']['photo-id']['value'];
		}

		// Fallback: Check post meta
		if ( empty( $file_url ) ) {
			$raw_meta = get_post_meta( $attendee_id, 'photo-id', true );

			if ( is_array( $raw_meta ) && isset( $raw_meta['url'] ) ) {
				$file_url = $raw_meta['url'];
			} elseif ( is_string( $raw_meta ) ) {
				$file_url = $raw_meta;
			}
		}

		// Final fallback: Transient
		if ( empty( $file_url ) && $ticket_id ) {
			$transient_key = 'otslr_attendee_file_' . $ticket_id . '_' . ( $index + 1 );
			$data = get_transient( $transient_key );

			if ( is_array( $data ) && ! empty( $data['url'] ) ) {
				$file_url = $data['url'];
			}
		}

		$file_url = is_string( $file_url ) ? esc_url( $file_url ) : '';

		if ( ! empty( $file_url ) ) {
			$value = '<a href="' . esc_url( $file_url ) . '" target="_blank">View File</a>';
		} else {
			$value = 'No file uploaded.';
		}

		return $value;
	}, 10, 3 );
}

 
if ( is_admin() ) {
	add_filter( 'tribe_tickets_attendee_table_columns', function( $columns ) {
		$new_column_headers = [];

		foreach ( $columns as $column_key => $column_name ) {
			$new_column_headers[ $column_key ] = $column_name;

			if ( 'ticket' === $column_key ) {
				$new_column_headers['file_upload'] = __( 'Photo ID', 'otslr-extentions' );
			}
		}

		return $new_column_headers;
	}, 20 );
}

add_action( 'tec_tickets_commerce_flag_action_generated_attendee', function( $attendee, $ticket, $order, $new_status, $old_status, $item, $i ) {
	if ( is_array( $attendee ) && isset( $attendee['ID'] ) ) {
		$attendee_id = $attendee['ID'];
	} elseif ( is_object( $attendee ) && isset( $attendee->ID ) ) {
		$attendee_id = $attendee->ID;
	} else {
		return;
	}

	if ( is_array( $ticket ) && isset( $ticket['ID'] ) ) {
		$ticket_id = $ticket['ID'];
	} elseif ( is_object( $ticket ) && isset( $ticket->ID ) ) {
		$ticket_id = $ticket->ID;
	} else {
		return;
	}

	$transient_key = 'otslr_attendee_file_' . $ticket_id . '_' . ($i + 1);
	$data = get_transient( $transient_key );

	if ( is_array( $data ) && ! empty( $data['label'] ) && ! empty( $data['url'] ) ) {
		$current_fields = get_post_meta( $attendee_id, '_tec_tickets_commerce_attendee_fields', true );
		$current_fields = is_array( $current_fields ) ? $current_fields : [];

		$new_fields    = [ $data['label'] => $data['url'] ];
		$merged_fields = array_merge( $current_fields, $new_fields );

		$commerce_module = tribe( \TEC\Tickets\Commerce\Module::class );
		if ( method_exists( $commerce_module, 'update_attendee' ) ) {
			$commerce_module->update_attendee( $attendee_id, [
				'attendee_meta' => $merged_fields,
			]);
		}
	}
}, 20, 7 );

add_action( 'woocommerce_email_after_order_table', 'otslr_add_attendee_photo_ids_to_email', 20, 4 );
function otslr_add_attendee_photo_ids_to_email( $order, $sent_to_admin, $plain_text, $email ) {
	if ( $plain_text || ! $sent_to_admin || ! is_a( $email, 'WC_Email_New_Order' ) ) {
		return;
	}

	$order_id  = $order->get_id();
	$post_type = get_post_type( $order_id );
	$meta      = get_post_meta( $order_id );

	$attendees = [];
	$line_items = $order->get_items( apply_filters( 'woocommerce_admin_order_item_types', 'line_item' ) );

	$customer_name  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
	$customer_email = $order->get_billing_email();
	$user_id        = $order->get_user_id();

	foreach ( $line_items as $item_id => $item ) {
		$ticket_id = $item->get_product_id();
		$quantity  = $item->get_quantity();

		for ( $i = 0; $i < $quantity; $i++ ) {
			$attendees[] = [
				'attendee_name'   => $customer_name,
				'attendee_email'  => $customer_email,
				'ticket_id'       => $ticket_id,
				'attendee_index'  => $i,
				'user_id'         => $user_id,
			];
		}
	}

	foreach ( $attendees as &$attendee ) {
		$attendee_id = $attendee['attendee_id'] ?? null;

		if ( $attendee_id ) {
			$stored_meta = get_post_meta( $attendee_id, '_tribe_tickets_meta', true );

			if ( is_array( $stored_meta ) ) {
				$attendee['attendee_meta'] = $stored_meta;
			}
		}
	}

	ob_start();

	echo '<h3>Event Attendees & Photo IDs</h3>';
	echo '<table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">';
	echo '<thead><tr><th style="border: 1px solid #ddd; padding: 8px;">Attendee Name</th><th style="border: 1px solid #ddd; padding: 8px;">Photo ID</th></tr></thead>';
	echo '<tbody>';

	foreach ( $attendees as $attendee ) {
		$name  = esc_html( $attendee['attendee_name'] ?? 'N/A' );
		$photo = '';

		// Primary: From saved attendee meta
		if ( isset( $attendee['attendee_meta']['photo-id'] ) ) {
			$value = $attendee['attendee_meta']['photo-id'];

			if ( is_array( $value ) && isset( $value['value'] ) ) {
				$photo = esc_url( $value['value'] );
			} elseif ( is_string( $value ) ) {
				$photo = esc_url( $value );
			}
		}

		// Fallback: From transient
		if ( empty( $photo ) && isset( $attendee['ticket_id'], $attendee['attendee_index'] ) ) {
			$ticket_id      = $attendee['ticket_id'];
			$index          = $attendee['attendee_index'];
			$transient_key  = 'otslr_attendee_file_' . $ticket_id . '_' . ( $index + 1 );
			$transient_data = get_transient( $transient_key );

			if ( is_array( $transient_data ) && ! empty( $transient_data['url'] ) ) {
				$photo = esc_url( $transient_data['url'] );
			}

			// OtslrLogger::custom_write([
			// 	'transient_fallback_check' => true,
			// 	'key'                      => $transient_key,
			// 	'data'                     => $transient_data,
			// 	'photo'                    => $photo,
			// ]);
		}

		echo '<tr>';
		echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $name . '</td>';
		echo '<td style="border: 1px solid #ddd; padding: 8px;">' . ( $photo ? '<a href="' . $photo . '" target="_blank">View File</a>' : 'No file uploaded.' ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';

	$html = ob_get_clean();

	// OtslrLogger::custom_write([
	// 	'email_block' => $html,
	// ]);

	echo $html;
}


add_filter( 'tribe_tickets_plus_meta_storage_combine_new_and_saved_meta', function( $meta ) {
	foreach ( $meta as $ticket_id => &$attendees ) {
		foreach ( $attendees as $index => &$fields ) {
			// OtslrLogger::custom_write([
			// 	'ticket_id' => $ticket_id,
			// 	'attendee_index' => $index,
			// 	'before' => $fields,
			// ]);

			$transient_key = 'otslr_attendee_file_' . $ticket_id . '_' . ($index + 1);
			$data = get_transient( $transient_key );

			if ( is_array( $data ) && ! empty( $data['label'] ) && ! empty( $data['url'] ) ) {
				$fields[ 'photo-id' ] = $data['url'];
			} elseif ( isset( $fields['photo-id'] ) ) {
				unset( $fields['photo-id'] );
			}

			// OtslrLogger::custom_write([
			// 	'ticket_id' => $ticket_id,
			// 	'attendee_index' => $index,
			// 	'after' => $fields,
			// 	'data' => $data
			// ]);
		}
	}
	unset( $attendees, $fields );

	return $meta;
}, 10 );

add_filter( 'woocommerce_email_headers', 'otslr_cc_philip_on_admin_email', 10, 3 );
function otslr_cc_philip_on_admin_email( $headers, $email_id, $order ) {
	$headers .= 'Cc: me@philiparudy.com' . "\r\n";
	
	return $headers;
}


// add_filter( 'ajax_query_attachments_args', 'otslr_restrict_media_library_to_own_uploads' );

// function otslr_restrict_media_library_to_own_uploads( $query ) {
// 	// Only apply to users who are NOT admins/editors/etc
// 	if ( is_user_logged_in() && ! current_user_can( 'manage_media_library' ) ) {
// 		$query['author'] = get_current_user_id();
// 	}
// 	return $query;
// }

// add_filter('wp_redirect', function ( $location ) {
// 	OtslrLogger::custom_write([
// 		'🔁 wp_redirect to' => $location,
// 		'🧵 Backtrace summary' => array_map(function ($trace) {
// 			if (isset($trace['file'], $trace['line'], $trace['function'])) {
// 				return "{$trace['function']}() in {$trace['file']}:{$trace['line']}";
// 			}
// 			return '';
// 		}, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)),
// 	]);
// 	return $location;
// });

// add_filter('tribe_tickets_plus_meta_storage_get_hash_cookie', function($hash, $ticket_id = null) {
// 	OtslrLogger::custom_write([
// 		'🔍 tribe_tickets_plus_meta_storage_get_hash_cookie' => [
// 			'original_hash' => $hash,
// 			'ticket_id' => $ticket_id,
// 		]
// 	]);
// }, 10, 2);


