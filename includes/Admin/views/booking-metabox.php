<?php
/**
 * Booking metabox fields (same meta keys as the former ACF group).
 *
 * @package SponsorManager
 *
 * @var string $company  Advertiser name.
 * @var string $link     Sponsor URL.
 * @var string $start    YYYY-MM-DD start.
 * @var string $end      YYYY-MM-DD end.
 * @var int    $priority Rotation priority.
 * @var bool   $paused   Manual pause.
 * @var string $notes    Internal notes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="sponsor-booking-company"><?php esc_html_e( 'Advertiser / Sponsor Name', 'sponsor-manager' ); ?></label></th>
		<td>
			<input name="sponsor_booking[company]" id="sponsor-booking-company" type="text" class="regular-text" value="<?php echo esc_attr( $company ); ?>" />
			<p class="description"><?php esc_html_e( 'Who this ad is sold to. Shown in the admin list and inventory report.', 'sponsor-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sponsor-booking-link"><?php esc_html_e( 'Sponsor URL', 'sponsor-manager' ); ?></label></th>
		<td>
			<input name="sponsor_booking[link]" id="sponsor-booking-link" type="url" class="regular-text" value="<?php echo esc_attr( $link ); ?>" placeholder="https://" />
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sponsor-booking-start"><?php esc_html_e( 'Start date', 'sponsor-manager' ); ?></label></th>
		<td>
			<input name="sponsor_booking[start]" id="sponsor-booking-start" type="date" value="<?php echo esc_attr( $start ); ?>" />
			<p class="description"><?php esc_html_e( 'Leave blank to go live immediately.', 'sponsor-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sponsor-booking-end"><?php esc_html_e( 'End date', 'sponsor-manager' ); ?></label></th>
		<td>
			<input name="sponsor_booking[end]" id="sponsor-booking-end" type="date" value="<?php echo esc_attr( $end ); ?>" />
			<p class="description"><?php esc_html_e( 'Leave blank to run indefinitely.', 'sponsor-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sponsor-booking-priority"><?php esc_html_e( 'Rotation Priority', 'sponsor-manager' ); ?></label></th>
		<td>
			<input name="sponsor_booking[priority]" id="sponsor-booking-priority" type="number" class="small-text" min="1" value="<?php echo esc_attr( (string) $priority ); ?>" />
			<p class="description"><?php esc_html_e( 'Higher numbers show first / more often when a placement has more than one active sponsor.', 'sponsor-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Pause', 'sponsor-manager' ); ?></th>
		<td>
			<label>
				<input name="sponsor_booking[paused]" type="checkbox" value="1" <?php checked( $paused ); ?> />
				<?php esc_html_e( 'Paused (manual override)', 'sponsor-manager' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Pull this ad from rotation immediately, regardless of its scheduled dates.', 'sponsor-manager' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="sponsor-booking-notes"><?php esc_html_e( 'Internal notes', 'sponsor-manager' ); ?></label></th>
		<td>
			<textarea name="sponsor_booking[notes]" id="sponsor-booking-notes" class="large-text" rows="4"><?php echo esc_textarea( $notes ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Deal terms, contact, PO#. Not displayed on the front end.', 'sponsor-manager' ); ?></p>
		</td>
	</tr>
</table>
