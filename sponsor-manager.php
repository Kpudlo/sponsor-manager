<?php
/**
 * Plugin Name: Sponsor Manager
 * Description: Booking, scheduling, and sellable-inventory system for sponsor placements (sidebar, mega menu, landing pages, single posts/pages, targetable by category). Owns the sponsor_ad post type and sponsor-placement taxonomy (see acf-json/); category targeting uses core's "category" taxonomy and is attached at runtime, not part of that JSON.
 * Author: Bemis Tech
 * Author URI: https://bemistech.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPONSOR_MANAGER_LEGACY_PAUSE_META', 'expired' ); // Repurposed legacy field: manual pause override.

/* -------------------------------------------------------------------------
 * Schema: category targeting + local ACF fields (additive; never touches
 * the existing DB-stored "Ad Fields" group).
 *
 * The Sponsor Booking field group, the sponsor_ad post type ("Ads"), and the
 * sponsor-placement taxonomy ("Ad Placements") all live in acf-json/ as ACF
 * Local JSON — not a acf_add_local_field_group() array for the fields, and
 * not bare register_post_type()/register_taxonomy() calls for the other
 * two. Both sponsor_ad and sponsor-placement were previously DB-only ACF items
 * (Custom Fields → Post Types / Taxonomies) with no owning plugin;
 * post_type_69934f2a6c7af.json and taxonomy_699350d5b4842.json now mirror
 * those same records by key, so this plugin is their source of truth going
 * forward. Editing any of the three from wp-admin still writes straight
 * back to its JSON file, and the live site picks up all three automatically
 * — or via Custom Fields → Tools → Import — just by deploying this plugin
 * folder, no manual field/post-type/taxonomy recreation required.
 *
 * sponsor-placement's own object_type is just sponsor_ad — it does NOT include
 * "category". Category targeting is core's own "category" taxonomy,
 * attached to sponsor_ad at runtime below, deliberately kept out of this
 * JSON: category isn't a taxonomy this plugin defines, just one it reuses.
 * ---------------------------------------------------------------------- */

add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = __DIR__ . '/acf-json';
	return $paths;
} );

add_filter( 'acf/settings/save_json', function ( $path ) {
	return __DIR__ . '/acf-json';
} );


add_action( 'init', 'sponsor_manager_ensure_placement_terms', 21 );
function sponsor_manager_ensure_placement_terms() {
	if ( get_option( 'sponsor_manager_terms_seeded_v1' ) ) {
		return;
	}
	if ( ! taxonomy_exists( 'sponsor-placement' ) ) {
		return;
	}

	$properties = array(
		'WDW'   => 'wdw',
		'DLNT'  => 'dlnt',
		'UPNT'  => 'upnt',
		'DCLNT' => 'dclnt',
	);
	$zones = array(
		'Single Post' => 'single-post',
		'Single Page' => 'single-page',
	);

	foreach ( $zones as $zone_label => $zone_slug ) {
		foreach ( $properties as $prop_label => $prop_slug ) {
			$slug = $zone_slug . '-' . $prop_slug;
			if ( ! term_exists( $slug, 'sponsor-placement' ) ) {
				wp_insert_term( $zone_label . ' - ' . $prop_label, 'sponsor-placement', array( 'slug' => $slug ) );
			}
		}
	}

	update_option( 'sponsor_manager_terms_seeded_v1', 1 );
}

// Cosmetic relabel only — the legacy "expired" checkbox becomes the manual pause switch.
// We never touch the stored field-group option itself, just how it's displayed.
add_filter( 'acf/load_field/name=expired', function ( $field ) {
	$field['label']        = 'Paused (manual override)';
	$field['instructions'] = 'Check to pull this ad from rotation immediately, regardless of its scheduled dates.';
	return $field;
} );

/* -------------------------------------------------------------------------
 * Status engine
 * ---------------------------------------------------------------------- */

function sponsor_manager_compute_status( $post_id ) {
	if ( get_post_status( $post_id ) !== 'publish' ) {
		return 'draft';
	}
	if ( (bool) get_post_meta( $post_id, SPONSOR_MANAGER_LEGACY_PAUSE_META, true ) ) {
		return 'paused';
	}

	$today = current_time( 'Ymd' );
	$start = get_post_meta( $post_id, 'sponsor_start_date', true );
	$end   = get_post_meta( $post_id, 'sponsor_end_date', true );

	if ( $start && $today < $start ) {
		return 'scheduled';
	}
	if ( $end && $today > $end ) {
		return 'expired';
	}
	return 'active';
}

function sponsor_manager_refresh_status( $post_id ) {
	$status = sponsor_manager_compute_status( $post_id );
	update_post_meta( $post_id, '_sponsor_ad_status', $status );
	return $status;
}
add_action( 'save_post_sponsor_ad', 'sponsor_manager_refresh_status' );

add_action( 'sponsor_manager_daily_status_check', 'sponsor_manager_run_daily_status_check' );
function sponsor_manager_run_daily_status_check() {
	$ads = get_posts( array(
		'post_type'      => 'sponsor_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	foreach ( $ads as $ad_id ) {
		sponsor_manager_refresh_status( $ad_id );
	}
}

register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( 'sponsor_manager_daily_status_check' ) ) {
		wp_schedule_event( time(), 'daily', 'sponsor_manager_daily_status_check' );
	}
	update_option( 'sponsor_manager_flush_rewrite_rules', 1 );
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'sponsor_manager_daily_status_check' );
	flush_rewrite_rules();
} );
// Safety net in case the plugin was placed on disk without going through activation (e.g. Studio site copy).
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'sponsor_manager_daily_status_check' ) ) {
		wp_schedule_event( time(), 'daily', 'sponsor_manager_daily_status_check' );
	}
} );

add_action( 'init', function () {
	if ( get_option( 'sponsor_manager_flush_rewrite_rules' ) ) {
		flush_rewrite_rules();
		delete_option( 'sponsor_manager_flush_rewrite_rules' );
	}
}, 999 );


function sponsor_manager_get_ads( $placement_slug, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'category_id' => 0, // 0 = auto-detect from the current queried post; array|int to override.
		'limit'       => 0, // 0 = return every eligible sponsor (stacked list); N = pick top N by priority.
	) );

	if ( ! $args['category_id'] ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post && ! empty( $queried->ID ) ) {
			// Single post/page: target by the categories that post itself belongs to.
			$cats = get_the_category( $queried->ID );
			if ( $cats ) {
				$args['category_id'] = wp_list_pluck( $cats, 'term_id' );
			}
		} elseif ( $queried instanceof WP_Term && 'category' === $queried->taxonomy ) {

			$args['category_id'] = array( $queried->term_id );
		}
	}
	$context_categories = array_map( 'intval', (array) $args['category_id'] );

	$query = new WP_Query( array(
		'post_type'      => 'sponsor_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy' => 'sponsor-placement',
				'field'    => 'slug',
				'terms'    => $placement_slug,
			),
		),
	) );

	$today    = current_time( 'Ymd' );
	$eligible = array();

	foreach ( $query->posts as $post ) {
		if ( (bool) get_post_meta( $post->ID, SPONSOR_MANAGER_LEGACY_PAUSE_META, true ) ) {
			continue; // paused
		}

		$start = get_post_meta( $post->ID, 'sponsor_start_date', true );
		$end   = get_post_meta( $post->ID, 'sponsor_end_date', true );
		if ( $start && $today < $start ) {
			continue; // scheduled, not live yet
		}
		if ( $end && $today > $end ) {
			continue; // past its end date
		}

		$ad_categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'ids' ) );
		if ( ! empty( $ad_categories ) && ! array_intersect( $ad_categories, $context_categories ) ) {
			continue; // targeted at other categories only
		}

		$priority = (int) get_post_meta( $post->ID, 'sponsor_priority', true );
		$priority = $priority > 0 ? $priority : 10;

		$link = get_post_meta( $post->ID, 'ad_link', true );
		if ( empty( $link ) ) {
			$link = get_post_meta( $post->ID, 'link', true );
		}

		$img_url = get_the_post_thumbnail_url( $post->ID, 'full' );
		// Known-broken local attachment reference for the COP ad; carried over from the legacy renderer.
		if ( 1345437 === $post->ID || empty( $img_url ) || false !== strpos( (string) $img_url, 'dinosaur_pins_website_ad' ) ) {
			if ( 1345437 === $post->ID ) {
				$img_url = site_url( '/wp-content/uploads/2026/06/cop_ad_2026-standard.jpg' );
			}
		}

		$eligible[] = array(
			'id'       => $post->ID,
			'title'    => get_the_title( $post->ID ),
			'image'    => $img_url,
			'link'     => $link ? $link : '#',
			'priority' => $priority,
		);
	}

	if ( empty( $eligible ) ) {
		return array();
	}

	// Order by priority (desc), randomized within same-priority ties for fair rotation.
	$tiers = array();
	foreach ( $eligible as $ad ) {
		$tiers[ $ad['priority'] ][] = $ad;
	}
	krsort( $tiers );
	$ordered = array();
	foreach ( $tiers as $tier ) {
		shuffle( $tier );
		foreach ( $tier as $ad ) {
			$ordered[] = $ad;
		}
	}

	if ( $args['limit'] > 0 ) {
		$ordered = array_slice( $ordered, 0, $args['limit'] );
	}

	return $ordered;
}

/* -------------------------------------------------------------------------
 * Admin: list table columns
 * ---------------------------------------------------------------------- */

add_filter( 'manage_sponsor_ad_posts_columns', function ( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['sponsor_name']  = 'Sponsor';
			$new['placements']    = 'Placement(s)';
			$new['categories']    = 'Category Target';
			$new['status']        = 'Status';
			$new['sponsor_dates'] = 'Runs';
		}
	}
	return $new;
} );

add_action( 'manage_sponsor_ad_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'sponsor_name':
			echo esc_html( get_post_meta( $post_id, 'sponsor_name', true ) ?: '—' );
			break;

		case 'placements':
			$terms = get_the_terms( $post_id, 'sponsor-placement' );
			echo ( $terms && ! is_wp_error( $terms ) )
				? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) )
				: '<em>none assigned</em>';
			break;

		case 'categories':
			$terms = get_the_terms( $post_id, 'category' );
			echo ( $terms && ! is_wp_error( $terms ) )
				? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) )
				: 'All categories';
			break;

		case 'status':
			$status = sponsor_manager_refresh_status( $post_id );
			$colors = array(
				'active'    => '#1a7f37',
				'scheduled' => '#9a6700',
				'expired'   => '#cf222e',
				'paused'    => '#57606a',
				'draft'     => '#57606a',
			);
			$color = $colors[ $status ] ?? '#57606a';
			printf(
				'<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:%1$s;color:#fff;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;">%2$s</span>',
				esc_attr( $color ),
				esc_html( $status )
			);
			break;

		case 'sponsor_dates':
			$start = get_post_meta( $post_id, 'sponsor_start_date', true );
			$end   = get_post_meta( $post_id, 'sponsor_end_date', true );
			$fmt   = function ( $d ) {
				return $d ? date_i18n( 'M j, Y', strtotime( $d ) ) : '—';
			};
			echo esc_html( $fmt( $start ) . ' – ' . $fmt( $end ) );
			break;
	}
}, 10, 2 );

add_filter( 'manage_edit-sponsor_ad_sortable_columns', function ( $columns ) {
	$columns['sponsor_dates'] = 'sponsor_end_date';
	return $columns;
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'sponsor_ad' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( 'sponsor_end_date' === $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', 'sponsor_end_date' );
		$query->set( 'orderby', 'meta_value' );
	}
} );

/* -------------------------------------------------------------------------
 * Admin: dashboard widget — what needs attention
 * ---------------------------------------------------------------------- */

add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'sponsor_manager_alerts', 'Sponsor Ads — Needs Attention', 'sponsor_manager_render_dashboard_widget' );
} );

function sponsor_manager_render_dashboard_widget() {
	$ads = get_posts( array(
		'post_type'      => 'sponsor_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	) );

	$today    = current_time( 'Ymd' );
	$soon     = gmdate( 'Ymd', strtotime( '+7 days' ) );
	$expiring = array();
	$expired  = array();

	foreach ( $ads as $ad ) {
		$end = get_post_meta( $ad->ID, 'sponsor_end_date', true );
		if ( ! $end ) {
			continue;
		}
		if ( $end < $today ) {
			$expired[] = $ad;
		} elseif ( $end <= $soon ) {
			$expiring[] = $ad;
		}
	}

	if ( ! $expiring && ! $expired ) {
		echo '<p>All sponsor placements are current — nothing expiring in the next 7 days.</p>';
		return;
	}

	if ( $expired ) {
		echo '<p><strong style="color:#cf222e;">Expired but still published:</strong></p><ul style="margin-left:1em;list-style:disc;">';
		foreach ( $expired as $ad ) {
			printf(
				'<li><a href="%s">%s</a> — ended %s</li>',
				esc_url( get_edit_post_link( $ad->ID ) ),
				esc_html( get_post_meta( $ad->ID, 'sponsor_name', true ) ?: get_the_title( $ad ) ),
				esc_html( date_i18n( 'M j, Y', strtotime( get_post_meta( $ad->ID, 'sponsor_end_date', true ) ) ) )
			);
		}
		echo '</ul>';
	}

	if ( $expiring ) {
		echo '<p><strong style="color:#9a6700;">Expiring within 7 days:</strong></p><ul style="margin-left:1em;list-style:disc;">';
		foreach ( $expiring as $ad ) {
			printf(
				'<li><a href="%s">%s</a> — ends %s</li>',
				esc_url( get_edit_post_link( $ad->ID ) ),
				esc_html( get_post_meta( $ad->ID, 'sponsor_name', true ) ?: get_the_title( $ad ) ),
				esc_html( date_i18n( 'M j, Y', strtotime( get_post_meta( $ad->ID, 'sponsor_end_date', true ) ) ) )
			);
		}
		echo '</ul>';
	}
}

/* -------------------------------------------------------------------------
 * Admin menu: a custom "Sponsor Manager" top-level page (with its own
 * Dashboard / How to Use tabs) that the sponsor_ad post type nests under
 * (see admin_menu_parent in acf-json/post_type_sponsor_ad.json), instead of
 * the post type generating its own top-level menu whose click target would
 * just be the raw post list.
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_menu_page(
		'Sponsor Manager',
		'Sponsor Manager',
		'edit_posts',
		'sponsor-manager',
		'sponsor_manager_render_dashboard_page',
		'dashicons-screenoptions'
	);

	// Relabel the menu's own first submenu entry (defaults to the parent's title) to "Dashboard".
	// Position 0 pins it first — core's own _add_post_type_submenus() (wp-includes/post.php)
	// separately adds an "All Sponsor Ads" entry here for the sponsor_ad post type, since its
	// show_in_menu is reparented to this slug (see admin_menu_parent in the CPT's ACF JSON).
	add_submenu_page(
		'sponsor-manager',
		'Sponsor Manager — Dashboard',
		'Dashboard',
		'edit_posts',
		'sponsor-manager',
		'sponsor_manager_render_dashboard_page',
		0
	);

	add_submenu_page(
		'sponsor-manager',
		'Add New Sponsor Ad',
		'Add New',
		'edit_posts',
		'post-new.php?post_type=sponsor_ad'
	);

	add_submenu_page(
		'sponsor-manager',
		'Sponsor Placements',
		'Placements',
		'manage_categories',
		'edit-tags.php?taxonomy=sponsor-placement&post_type=sponsor_ad'
	);
} );

function sponsor_manager_get_stats() {
	$counts    = wp_count_posts( 'sponsor_ad' );
	$total_ads = 0;
	foreach ( array( 'publish', 'draft', 'pending', 'future', 'private' ) as $status ) {
		if ( isset( $counts->$status ) ) {
			$total_ads += (int) $counts->$status;
		}
	}

	$published_ids = get_posts( array(
		'post_type'      => 'sponsor_ad',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	$active_ads = 0;
	foreach ( $published_ids as $ad_id ) {
		if ( 'active' === sponsor_manager_compute_status( $ad_id ) ) {
			$active_ads++;
		}
	}

	$placement_count = wp_count_terms( array( 'taxonomy' => 'sponsor-placement', 'hide_empty' => false ) );

	return array(
		'active_ads' => $active_ads,
		'total_ads'  => $total_ads,
		'placements' => is_wp_error( $placement_count ) ? 0 : (int) $placement_count,
	);
}

function sponsor_manager_render_dashboard_page() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
	if ( ! in_array( $tab, array( 'dashboard', 'how-to-use' ), true ) ) {
		$tab = 'dashboard';
	}
	$base_url = admin_url( 'admin.php?page=sponsor-manager' );
	?>
	<div class="wrap">
		<h1>Sponsor Manager</h1>

		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( $base_url ); ?>" class="nav-tab <?php echo 'dashboard' === $tab ? 'nav-tab-active' : ''; ?>">Dashboard</a>
			<a href="<?php echo esc_url( add_query_arg( 'tab', 'how-to-use', $base_url ) ); ?>" class="nav-tab <?php echo 'how-to-use' === $tab ? 'nav-tab-active' : ''; ?>">How to Use</a>
		</h2>

		<?php if ( 'how-to-use' === $tab ) : ?>
			<?php sponsor_manager_render_howto_tab(); ?>
		<?php else : ?>
			<?php sponsor_manager_render_dashboard_tab(); ?>
		<?php endif; ?>
	</div>
	<?php
}

function sponsor_manager_render_dashboard_tab() {
	$stats = sponsor_manager_get_stats();
	?>
	<p style="margin-top:1em;">
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=sponsor_ad' ) ); ?>" class="button button-primary">Add New Sponsor Ad</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=sponsor_ad' ) ); ?>" class="button">All Sponsor Ads</a>
		<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=sponsor-placement&post_type=sponsor_ad' ) ); ?>" class="button">Manage Placements</a>
	</p>

	<div style="display:flex;gap:16px;flex-wrap:wrap;margin:1.5em 0;">
		<?php
		$cards = array(
			array( 'label' => 'Active Ads', 'value' => $stats['active_ads'], 'color' => '#1a7f37' ),
			array( 'label' => 'Total Ads', 'value' => $stats['total_ads'], 'color' => '#2271b1' ),
			array( 'label' => 'Placements', 'value' => $stats['placements'], 'color' => '#9a6700' ),
		);
		foreach ( $cards as $card ) :
			?>
			<div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid <?php echo esc_attr( $card['color'] ); ?>;border-radius:4px;padding:16px 24px;min-width:160px;">
				<div style="font-size:32px;font-weight:600;line-height:1.2;"><?php echo esc_html( $card['value'] ); ?></div>
				<div style="color:#50575e;"><?php echo esc_html( $card['label'] ); ?></div>
			</div>
			<?php
		endforeach;
		?>
	</div>

	<?php sponsor_manager_render_inventory_table(); ?>
	<?php
}

function sponsor_manager_render_howto_tab() {
	?>
	<div style="margin-top:1em;max-width:800px;">
		<p>Booking, scheduling, and sellable-inventory system for sponsor placements (sidebar, mega menu, landing pages, single posts/pages) — targetable by category.</p>

		<h3>1. Placements</h3>
		<p>Every sponsor ad is assigned one or more <strong>Placements</strong> (the <code>sponsor-placement</code> taxonomy) — these describe <em>where</em> an ad can appear (e.g. sidebar, mega menu, a specific property/zone combination). A placement is what your theme/template code queries for when it asks "what ad(s) should show here?"</p>

		<h3>2. Category targeting</h3>
		<p>Optionally assign an ad to one or more of the site's regular <strong>Categories</strong>. If an ad has no category selected, it's eligible everywhere. If it has categories selected, it only shows on posts/pages/archives in those categories.</p>

		<h3>3. Scheduling &amp; status</h3>
		<p>Each ad has a start date, end date, and priority. Its status is computed automatically and shown as a colored badge on the All Sponsor Ads list:</p>
		<ul style="list-style:disc;margin-left:2em;">
			<li><strong>Active</strong> — published, within its date range, not paused</li>
			<li><strong>Scheduled</strong> — start date is in the future</li>
			<li><strong>Expired</strong> — end date has passed</li>
			<li><strong>Paused</strong> — the "Paused (manual override)" checkbox is checked, pulling it from rotation immediately regardless of dates</li>
			<li><strong>Draft</strong> — not published</li>
		</ul>
		<p>A daily scheduled task keeps every ad's status current even if nobody edits it.</p>

		<h3>4. Priority &amp; rotation</h3>
		<p>When more than one ad is eligible for the same placement/category, higher <strong>Priority</strong> ads are shown first; ads that share the same priority rotate randomly so no single sponsor is stuck at the bottom.</p>

		<h3>5. Ad Inventory</h3>
		<p>The Dashboard tab's inventory table shows, per placement, who's currently booked and whether the slot is open to sell.</p>

		<h3>For developers</h3>
		<p>Templates and theme code pull ads with <code>sponsor_manager_get_ads( $placement_slug, $args )</code>, which returns an ordered array of eligible ads (title, image, link, priority) for the given placement, auto-detecting category context from the current queried post/archive unless a <code>category_id</code> is passed explicitly.</p>
	</div>
	<?php
}

function sponsor_manager_render_inventory_table() {
	$terms = get_terms( array( 'taxonomy' => 'sponsor-placement', 'hide_empty' => false ) );
	$today = current_time( 'Ymd' );

	echo '<h2>Ad Inventory</h2><p>What\'s booked and what\'s open to sell, by placement.</p>';
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Placement</th><th>Current / Upcoming Bookings</th><th>Availability</th>';
	echo '</tr></thead><tbody>';

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		echo '<tr><td colspan="3">No sponsor-placement terms found.</td></tr>';
	} else {
		foreach ( $terms as $term ) {
			$query = new WP_Query( array(
				'post_type'      => 'sponsor_ad',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'tax_query'      => array(
					array( 'taxonomy' => 'sponsor-placement', 'field' => 'term_id', 'terms' => $term->term_id ),
				),
			) );

			$rows          = array();
			$latest_end    = null;
			$has_open_ended = false;

			foreach ( $query->posts as $post ) {
				if ( (bool) get_post_meta( $post->ID, SPONSOR_MANAGER_LEGACY_PAUSE_META, true ) ) {
					continue; // paused, not occupying the slot
				}
				$end = get_post_meta( $post->ID, 'sponsor_end_date', true );
				if ( $end && $end < $today ) {
					continue; // already expired, not occupying the slot
				}
				$sponsor = get_post_meta( $post->ID, 'sponsor_name', true ) ?: get_the_title( $post );
				$rows[]  = sprintf(
					'<a href="%s">%s</a>%s',
					esc_url( get_edit_post_link( $post->ID ) ),
					esc_html( $sponsor ),
					$end ? ' (through ' . esc_html( date_i18n( 'M j, Y', strtotime( $end ) ) ) . ')' : ' (no end date)'
				);
				if ( ! $end ) {
					$has_open_ended = true;
				} elseif ( ! $latest_end || $end > $latest_end ) {
					$latest_end = $end;
				}
			}

			if ( $has_open_ended ) {
				$availability = '<strong style="color:#cf222e;">Booked (indefinite)</strong>';
			} elseif ( $latest_end ) {
				$availability = '<strong style="color:#9a6700;">Booked through ' . esc_html( date_i18n( 'M j, Y', strtotime( $latest_end ) ) ) . '</strong>';
			} else {
				$availability = '<strong style="color:#1a7f37;">OPEN — available to sell</strong>';
			}

			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $term->name ),
				$rows ? implode( '<br>', $rows ) : '<em>none</em>',
				$availability
			);
		}
	}

	echo '</tbody></table>';
}
