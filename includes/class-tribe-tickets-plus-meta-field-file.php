<?php

class Tribe__Tickets_Plus__Meta__Field__File extends Tribe__Tickets_Plus__Meta__Field__Abstract_Field {
    public $label = 'Photo ID';
    public $slug = 'photo_id';
    public $required = false;
    public $extra = [];

    public $type = 'file';

    public static function get_identifier() {
        return 'file';
    }

    public function render( $attendee_id = null ) {
        $field = $this->get_field_settings();
        $field = $this->sanitize_field_options_for_render( $field );
        $value = $this->get_field_value( $attendee_id );

        return $this->render_field( $field, $value, $attendee_id );
    }


    public function save( $attendee_id, $field, $value ) {
        // Save logic here
    }

    public function render_admin_field( $open = false ) {
        $template_path = OTSLR_ADD_PLUGIN_PATH . 'src/admin-views/meta-fields/' . sanitize_file_name( $this->type ) . '.php';
        $wrapper_path  = OTSLR_ADD_PLUGIN_PATH . 'src/admin-views/meta-fields/_field.php';

        if ( ! file_exists( $template_path ) || ! file_exists( $wrapper_path ) ) {
            return '';
        }

        $ticket_specific_settings = $this->get_field_settings();
        $ticket_specific_settings = $this->sanitize_field_options_for_render( $ticket_specific_settings );
        $data = array_merge( (array) $this, (array) $ticket_specific_settings );

        // Extract values for template access
        $field        = $this;
        $label        = ! empty( $this->label ) ? $this->label : ( $data['label'] ?? '' );
        $placeholder  = ! empty( $this->get_placeholder() ) ? $this->get_placeholder() : '';
        $description  = ! empty( $this->get_description() ) ? $this->get_description() : '';
        $type         = $this->type;
        $type_name    = $type; // optionally get readable name from your own lookup
        $field_id     = wp_rand();
        $required     = ! empty( $this->required ) ? $this->required : '';
        $slug         = ! empty( $this->slug ) ? $this->slug : sanitize_title( $label );
        $extra        = $this->extra;

        // Output buffer capture
        ob_start();
        include $wrapper_path; // inside _field.php you can then include the $template_path if needed
        return ob_get_clean();
    }


    public static function get_name() {
        return  $this->label;
    }

    public function save_value( $attendee_id, $field, $value ) {
        // Save the uploaded file URL or ID
        update_post_meta( $attendee_id, $field['slug'], $value );
    }

}
