<?php
/**
 * Sponsor Grid block — up to 4 ads, priority-weighted rotation on the frontend.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$placement = isset( $attributes['placement'] ) ? sanitize_title( $attributes['placement'] ) : '';

if ( ! $placement ) {
	return;
}

$all_ads = sponsor_manager_get_ads( $placement );
if ( empty( $all_ads ) ) {
	return;
}

$slot_count = min( 4, count( $all_ads ) );
$visible    = sponsor_manager_weighted_pick_multiple( $all_ads, $slot_count );
$can_rotate = count( $all_ads ) > $slot_count;

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                    => 'sponsor-manager-grid' . ( $can_rotate ? ' sponsor-manager-grid--rotating' : '' ),
		'style'                    => sponsor_manager_get_block_style_attribute( $attributes ),
		'data-sponsor-rotate'      => $can_rotate ? '1' : '0',
		'data-sponsor-initial-ms'  => '60000',
		'data-sponsor-interval-ms' => '60000',
	)
);

if ( $can_rotate ) {
	$wrapper_attrs .= ' data-sponsor-pool="' . esc_attr( wp_json_encode( sponsor_manager_prepare_ads_for_client( $all_ads ) ) ) . '"';
}
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="sponsor-manager-grid__slots">
		<?php foreach ( $visible as $ad ) : ?>
			<div class="sponsor-manager-grid__slot" data-ad-id="<?php echo esc_attr( (string) $ad['id'] ); ?>">
				<?php echo sponsor_manager_render_ad_markup( $ad, 'grid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
