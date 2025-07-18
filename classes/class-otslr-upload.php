<?php

class OtslrUpload {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route() {
        register_rest_route('otslr/v1', '/upload-file-to-temp', [
            'methods' => 'POST',
            'callback' => [$this, 'otslr_upload_file_to_temp'],
            'permission_callback' => function () {
                $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? null;

                if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                    OtslrLogger::custom_write('Blocked REST POST from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                    return false;
                }

                return true;
            }
        ]);
    }

    function otslr_upload_file_to_temp(\WP_REST_Request $request) {
        if (empty($_FILES['file'])) {
            return new WP_REST_Response(['error' => 'No file uploaded'], 400);
        }

        $file = $_FILES['file'];
        $params = $request->get_params();
        $input_name = $params['name'] ?? null;
        $post_id = $params['post_id'] ?? null;
        $attendee_id = $params['attendee_id'] ?? null;
        $session = new OtslrSession();
        $cookie = $session->get_session_cookie();
        $label = null;

        if ($input_name && strpos($input_name, '[meta]') !== false) {
            // Match the last key inside the 'meta' field
            if (preg_match('/\[meta\]\[([^\]]+)\]/', $input_name, $matches)) {
                $label = $matches[1];
            }
        }

        $ticket_id = null;

        if (preg_match('/^tribe_tickets\[(\d+)\]/', $input_name, $matches)) {
            $ticket_id = $matches[1];
        }

        add_filter('upload_dir', function ($dirs) use ($ticket_id, $attendee_id) {
            $subdir = '/tribe/attendee-meta/' . $ticket_id . '/' . $attendee_id;

            $dirs['subdir'] = $subdir;
            $dirs['path']   = $dirs['basedir'] . $subdir;
            $dirs['url']    = $dirs['baseurl'] . $subdir;

            return $dirs;
        });

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upload = wp_handle_upload($file, ['test_form' => false]);

        remove_filter('upload_dir', '__return_false');

        if (isset($upload['url']) && $attendee_id) {

            $transient_id = 'otslr_attendee_file_' . $ticket_id . '_' . $attendee_id;
            $data = [
                'url' => $upload['url'],
                'transient_id' => $transient_id,
                'attendee_id' => $attendee_id,
                'ticket_id' => $ticket_id,
                'label' => $label
            ]; 

            set_transient($transient_id, $data, 2 * DAY_IN_SECONDS);

            return new WP_REST_Response($data);
        }

        return new WP_REST_Response(['error' => 'Upload failed'], 500);
    }
}