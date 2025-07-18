<?php
/**
 * Event Tickets Emails: Order Attendee Info (Modified with Photo ID row)
 *
 * Override this template in your own theme by creating a file at:
 * [your-theme]/tribe/tickets/emails/template-parts/body/order/attendees-table/attendee-info.php
 *
 * @version 5.5.11
 * @since 5.5.11
 *
 * @var \Tribe__Template $this          Current template object.
 * @var array            $attendee      The current attendee data.
 * @var array            $order         [Global] The order object.
 * @var bool             $is_tec_active [Global] Whether `The Events Calendar` is active or not.
 */

if ( empty( $attendee ) ) {
	return;
}
?>
<tr class="tec-tickets__email-table-content-order-attendee-info-row">
	<td class="tec-tickets__email-table-content-order-attendee-info tec-tickets__email-table-content-align-left" align="left">
		<?php $this->template( 'template-parts/body/order/attendees-table/attendee-name' ); ?>
		<?php $this->template( 'template-parts/body/order/attendees-table/attendee-email' ); ?>
		<?php $this->template( 'template-parts/body/order/attendees-table/custom-fields' ); ?>
	</td>
	<?php $this->template( 'template-parts/body/order/attendees-table/ticket-title' ); ?>
	<?php $this->template( 'template-parts/body/order/attendees-table/ticket-id' ); ?>
</tr>

<?php if ( ! empty( $attendee['attendee_meta']['file-upload'] ) ) : ?>
<tr class="tec-tickets__email-table-content-order-attendee-info-photo-id-row">
	<td colspan="3" class="tec-tickets__email-table-content-align-left" align="left" style="padding: 6px 0;">
		<strong>Photo ID:</strong>
		<a href="<?php echo esc_url( $attendee['attendee_meta']['file-upload'] ); ?>" target="_blank" rel="noopener noreferrer">
			View Uploaded File
		</a>
	</td>
</tr>
<?php endif; ?>