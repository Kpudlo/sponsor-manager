<?php
/**
 * Shared helpers for Sponsor Manager blocks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pick one ad from a pool using priority-weighted random selection.
 *
 * @param array $ads        List of ad arrays (must include id + priority).
 * @param int[] $exclude_ids Ad IDs to skip.
 * @return array|null
 */
function sponsor_manager_weighted_pick( array $ads, array $exclude_ids = array() ) {
	$pool = array_values(
		array_filter(
			$ads,
			function ( $ad ) use ( $exclude_ids ) {
				return ! in_array( (int) $ad['id'], $exclude_ids, true );
			}
		)
	);

	if ( empty( $pool ) ) {
		return null;
	}

	$total_weight = 0;
	foreach ( $pool as $ad ) {
		$total_weight += max( 1, (int) $ad['priority'] );
	}

	$roll = ( mt_rand() / mt_getrandmax() ) * $total_weight;
	foreach ( $pool as $ad ) {
		$roll -= max( 1, (int) $ad['priority'] );
		if ( $roll <= 0 ) {
			return $ad;
		}
	}

	return $pool[ count( $pool ) - 1 ];
}

/**
 * Pick multiple unique ads via weighted random selection.
 *
 * @param array $ads
 * @param int   $count
 * @param int[] $exclude_ids
 * @return array
 */
function sponsor_manager_weighted_pick_multiple( array $ads, $count, array $exclude_ids = array() ) {
	$picked      = array();
	$exclude_ids = array_map( 'intval', $exclude_ids );

	for ( $i = 0; $i < $count; $i++ ) {
		$ad = sponsor_manager_weighted_pick( $ads, $exclude_ids );
		if ( ! $ad ) {
			break;
		}
		$picked[]      = $ad;
		$exclude_ids[] = (int) $ad['id'];
	}

	return $picked;
}

/**
 * Render a single sponsor ad anchor/image.
 *
 * @param array  $ad
 * @param string $layout  grid|sidebar
 * @return string
 */
function sponsor_manager_render_ad_markup( array $ad, $layout = 'grid' ) {
	$classes = 'sidebar' === $layout
		? 'sponsor-manager-sidebar__item'
		: 'sponsor-manager-grid__item';

	ob_start();
	?>
	<a
		class="<?php echo esc_attr( $classes ); ?>"
		href="<?php echo esc_url( $ad['link'] ); ?>"
		target="_blank"
		rel="noopener noreferrer sponsored"
	>
		<?php if ( ! empty( $ad['image'] ) ) : ?>
			<img
				class="<?php echo esc_attr( $classes ); ?>-image"
				src="<?php echo esc_url( $ad['image'] ); ?>"
				alt="<?php echo esc_attr( $ad['title'] ); ?>"
				width="16"
				height="9"
				loading="lazy"
				decoding="async"
			/>
		<?php else : ?>
			<span class="<?php echo esc_attr( $classes ); ?>-fallback"><?php echo esc_html( $ad['title'] ); ?></span>
		<?php endif; ?>
	</a>
	<?php
	return ob_get_clean();
}

/**
 * CSS custom properties for ad card radius, border, and shadow.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function sponsor_manager_get_block_style_attribute( array $attributes ) {
	$use_global = ! array_key_exists( 'useGlobalStyles', $attributes ) || ! empty( $attributes['useGlobalStyles'] );
	if ( $use_global ) {
		return '';
	}

	$globals = function_exists( 'sponsor_manager_get_global_styles' )
		? sponsor_manager_get_global_styles()
		: sponsor_manager_get_default_styles();

	return sponsor_manager_styles_to_css(
		array(
			'border_radius'   => array_key_exists( 'borderRadius', $attributes ) ? $attributes['borderRadius'] : $globals['border_radius'],
			'border_width'    => array_key_exists( 'borderWidth', $attributes ) ? $attributes['borderWidth'] : $globals['border_width'],
			'border_color'    => $attributes['borderColor'] ?? $globals['border_color'],
			'show_shadow'     => array_key_exists( 'showShadow', $attributes ) ? ! empty( $attributes['showShadow'] ) : $globals['show_shadow'],
			'shadow_color'    => $attributes['shadowColor'] ?? $globals['shadow_color'],
			'shadow_offset_x' => array_key_exists( 'shadowOffsetX', $attributes ) ? $attributes['shadowOffsetX'] : $globals['shadow_offset_x'],
			'shadow_offset_y' => array_key_exists( 'shadowOffsetY', $attributes ) ? $attributes['shadowOffsetY'] : $globals['shadow_offset_y'],
			'shadow_blur'     => array_key_exists( 'shadowBlur', $attributes ) ? $attributes['shadowBlur'] : $globals['shadow_blur'],
			'shadow_spread'   => array_key_exists( 'shadowSpread', $attributes ) ? $attributes['shadowSpread'] : $globals['shadow_spread'],
		)
	);
}

/**
 * Strip ad data down to what the frontend rotation script needs.
 *
 * @param array $ads
 * @return array
 */
function sponsor_manager_prepare_ads_for_client( array $ads ) {
	return array_map(
		function ( $ad ) {
			return array(
				'id'       => (int) $ad['id'],
				'title'    => $ad['title'],
				'image'    => $ad['image'],
				'link'     => $ad['link'],
				'priority' => (int) $ad['priority'],
			);
		},
		$ads
	);
}
