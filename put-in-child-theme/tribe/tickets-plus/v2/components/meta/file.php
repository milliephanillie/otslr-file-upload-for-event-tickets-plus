<?php
/**
 * This template renders the File Upload field.
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/tribe/tickets-plus/v2/components/meta/file.php
 *
 * @since 5.0.0
 *
 * @var string $field_name The meta field name.
 * @var string $field_id The meta field id.
 * @var bool   $required A bool indicating if the meta field is required or not.
 * @var string|int $attendee_id The attendee ID, to build the ID/name.
 * @var array $classes Array containing the CSS classes for the field.
 * @var array $attributes Array containing the HTML attributes for the field.
 * @var string $placeholder The field placeholder text.
 * @var bool $disabled Field is disabled or not.
 * @var string $value Value for field (usually not used for file inputs).
 * @var Tribe__Tickets__Ticket_Object $ticket The ticket object.
 * @var Tribe__Tickets_Plus__Meta__Field__File $field
 * @var string $description A user-defined description for meta field.
 */

?>

<div
	<?php tribe_classes( $classes ); ?>
	<?php tribe_attributes( $attributes ); ?>
>
	<label
		class="tribe-tickets__form-field-label"
		for="<?php echo esc_attr( $field_id ); ?>"
	>
		Photo ID
		<?php tribe_required_label( $required ); ?>
		<?php
		?>
	</label>

	<div class="tribe-tickets__form-field-input-wrapper">
		<input
			type="file"
			id="<?php echo esc_attr( $field_id ); ?>"
			class="tribe-common-form-control-file__input tribe-tickets__form-field-input otslr-device"
			name="<?php echo esc_attr( $field_name ); ?>"
			value="<?php echo $file; ?>"
			<?php echo $required ? 'required' : ''; ?>
			<?php tribe_disabled( $disabled ); ?>
			accept=".pdf,image/*"
			data-attendee="<?php echo $attendee_id; ?>"
		/>
		<?php if ( ! empty( $description ) ) : ?>
			<div class="tribe-common-b3 tribe-tickets__form-field-description">
				<?php echo wp_kses_post( $description ); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
