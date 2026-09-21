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
