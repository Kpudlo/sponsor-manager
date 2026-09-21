<?php
/**
 * Sponsor Sidebar block — all eligible ads for a placement, stacked vertically.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$placement = isset( $attributes['placement'] ) ? sanitize_title( $attributes['placement'] ) : '';

if ( ! $placement ) {
	return;
}

$ads = sponsor_manager_get_ads( $placement );
if ( empty( $ads ) ) {
	return;
}

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class' => 'sponsor-manager-sidebar',
		'style' => sponsor_manager_get_block_style_attribute( $attributes ),
	)
);
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<h3 class="sponsor-manager-sidebar__heading"><?php esc_html_e( 'Sponsored', 'sponsor-manager' ); ?></h3>
	<div class="sponsor-manager-sidebar__list">
		<?php foreach ( $ads as $ad ) : ?>
			<div class="sponsor-manager-sidebar__slot" data-ad-id="<?php echo esc_attr( (string) $ad['id'] ); ?>">
				<?php echo sponsor_manager_render_ad_markup( $ad, 'sidebar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
