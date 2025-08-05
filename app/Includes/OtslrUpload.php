<?php
namespace OtslrAdditionalFields\Includes;

use OtslrAdditionalFields\Includes\OtslrLogger;
use Aws\S3\S3Client;
use WP_REST_Request;
use WP_REST_Response;

class OtslrUpload {
    const CLOUDFRONT_DOMAIN = 'dit3gqke7df7w.cloudfront.net';
    const BUCKET_NAME = '540training';
    const BUCKET_REGION = 'us-east-2';
    const SUBDIR_PARENT = 'tribe/attendee-meta/';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route(): void {
        register_rest_route('otslr/v1', '/upload-file-to-temp', [
            'methods'  => 'POST',
            'callback' => [$this, 'otslr_upload_file_to_temp'],
            'permission_callback' => function () {
                $nonce = $_SERVER['HTTP_X_WP_NONCE'] ?? null;

                if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                    OtslrLogger::custom_write('[REST PERMISSION BLOCKED] Invalid or missing nonce from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                    return false;
                }

                return true;
            }
        ]);
    }

    public function upload_to_s3(?string $subdir = null, ?string $local_file = null): ?array {
        OtslrLogger::custom_write('[S3 Upload Start]', [
            'local_file' => $local_file,
            'subdir'     => $subdir
        ]);

        $credentials = $this->get_aws_credentials();
        if (!$credentials || !$subdir || !$local_file) {
            OtslrLogger::custom_write('[S3 Upload Error] Missing credentials or parameters.');
            return null;
        }

        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => self::BUCKET_REGION,
            'credentials' => $credentials
        ]);

        try {
            $result = $s3->putObject([
                'Bucket'     => self::BUCKET_NAME,
                'Key'        => $subdir . basename($local_file),
                'SourceFile' => $local_file,
                'ACL' => 'bucket-owner-full-control' 
            ]);

            OtslrLogger::custom_write('[S3 Upload Success]', $result->toArray());
            return $result->toArray();
        } catch (\Aws\S3\Exception\S3Exception $e) {
            OtslrLogger::custom_write('[S3 Upload Exception] ' . $e->getAwsErrorMessage());
            return null;
        }
    }

    public function otslr_upload_file_to_temp(WP_REST_Request $request): WP_REST_Response {
        if (empty($_FILES['file'])) {
            OtslrLogger::custom_write('[Upload Error] No file found in request.');
            return new WP_REST_Response(['error' => 'No file uploaded'], 400);
        }

        $file = $_FILES['file'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_extensions, true)) {
            OtslrLogger::custom_write('[Upload Error] Invalid file type: ' . $file_ext);
            return new WP_REST_Response([
                'error' => 'Invalid file type. Only JPG, JPEG, PNG, WEBP, and PDF files are allowed.'
            ], 400);
        }

        $params       = $request->get_params();
        $input_name   = $params['name'] ?? '';
        $post_id      = $params['post_id'] ?? null;
        $attendee_id  = $params['attendee_id'] ?? null;
        $label        = null;
        $ticket_id    = null;

        if (preg_match('/\[meta\]\[([^\]]+)\]/', $input_name, $matches)) {
            $label = $matches[1];
        }

        if (preg_match('/^tribe_tickets\[(\d+)\]/', $input_name, $matches)) {
            $ticket_id = $matches[1];
        }

        if ($ticket_id && $attendee_id) {
            $subdir = $this->generate_sub_dir($ticket_id, $attendee_id);
            OtslrLogger::custom_write('[Upload Init]', compact('ticket_id', 'attendee_id', 'subdir', 'label'));

            add_filter('upload_dir', function ($dirs) use ($subdir) {
                $dirs['subdir'] = $subdir;
                $dirs['path']   = $dirs['basedir'] . $subdir;
                $dirs['url']    = $dirs['baseurl'] . $subdir;
                return $dirs;
            });

            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload = wp_handle_upload($file, ['test_form' => false]);
            remove_filter('upload_dir', '__return_false');

            if (!empty($upload['file']) && !empty($upload['url'])) {
                OtslrLogger::custom_write('[Local Upload Success]', $upload);

                $s3_result = $this->upload_to_s3($subdir, $upload['file']);
                $use_cloudfront = false;
                $final_url = $upload['url']; // fallback

                if ($s3_result && !empty($s3_result['ObjectURL'])) {
                    $cloudfront_url = rtrim('https://' . self::CLOUDFRONT_DOMAIN, '/') . '/' . ltrim($subdir, '/') . basename($upload['file']);
                    $final_url = $cloudfront_url;
                    $use_cloudfront = true;

                    OtslrLogger::custom_write('[S3 Upload Success] Using CloudFront URL: ' . $cloudfront_url);

                    // Attempt to delete the local file
                    if (file_exists($upload['file'])) {
                        if (@unlink($upload['file'])) {
                            OtslrLogger::custom_write('[Cleanup] Deleted local file: ' . $upload['file']);
                        } else {
                            OtslrLogger::custom_write('[Cleanup Error] Failed to delete local file: ' . $upload['file']);
                        }
                    }
                } else {
                    OtslrLogger::custom_write('[S3 Upload Failed] Falling back to WordPress URL: ' . $upload['url']);
                }

                $transient_id = 'otslr_attendee_file_' . $ticket_id . '_' . $attendee_id;
                $data = [
                    'url'          => $final_url,
                    'transient_id' => $transient_id,
                    'attendee_id'  => $attendee_id,
                    'ticket_id'    => $ticket_id,
                    'label'        => $label,
                    'source'       => $use_cloudfront ? 'cloudfront' : 'wordpress'
                ];

                set_transient($transient_id, $data, 2 * DAY_IN_SECONDS);
                return new WP_REST_Response($data);
            }

            OtslrLogger::custom_write('[Upload Error] wp_handle_upload failed.', $upload);
            return new WP_REST_Response(['error' => 'Upload to WordPress failed'], 500);
        }

        OtslrLogger::custom_write('[Upload Error] Missing ticket_id or attendee_id.', compact('input_name', 'ticket_id', 'attendee_id'));
        return new WP_REST_Response(['error' => 'Upload failed'], 500);
    }

    public function generate_sub_dir(?int $ticket_id = null, ?int $attendee_id = null): ?string {
        if ($ticket_id && $attendee_id) {
            return self::SUBDIR_PARENT . $ticket_id . '/' . $attendee_id . '/';
        }
        return null;
    }

    public function get_aws_credentials(): ?array {
        if (!defined('OTSLR_AWS_ACCESS_KEY_ID') || !defined('OTSLR_AWS_SECRET_ACCESS_KEY')) {
            OtslrLogger::custom_write('[AWS Credential Error] Constants not defined.');
            return null;
        }

        return [
            'key'    => OTSLR_AWS_ACCESS_KEY_ID,
            'secret' => OTSLR_AWS_SECRET_ACCESS_KEY,
        ];
    }
}
