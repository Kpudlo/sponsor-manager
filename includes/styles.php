<?php
/**
 * Global ad styles — stored as an option and applied to every block by default.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPONSOR_MANAGER_STYLES_OPTION', 'sponsor_manager_global_styles' );

/**
 * Default look for grid/sidebar ad cards.
 *
 * @return array
 */
function sponsor_manager_get_default_styles() {
	return array(
		'border_radius'   => 6,
		'border_width'    => 2,
		'border_color'    => '',
		'show_shadow'     => true,
		'shadow_color'    => '',
		'shadow_offset_x' => 2,
		'shadow_offset_y' => 2,
		'shadow_blur'     => 0,
		'shadow_spread'   => 0,
	);
}

/**
 * Theme palette colors (theme + custom + default presets).
 *
 * @return array<int, array{name:string,slug:string,css:string,hex:string}>
 */
function sponsor_manager_get_theme_palette() {
	$palette = array();

	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return $palette;
	}

	$groups = wp_get_global_settings( array( 'color', 'palette' ) );
	if ( ! is_array( $groups ) ) {
		return $palette;
	}

	$seen = array();
	foreach ( $groups as $origin ) {
		if ( ! is_array( $origin ) ) {
			continue;
		}

		// Flat list vs origin-grouped (theme/default/custom).
		$colors = isset( $origin['color'] ) || isset( $origin['slug'] ) ? array( $origin ) : $origin;
		foreach ( $colors as $color ) {
			if ( ! is_array( $color ) || empty( $color['slug'] ) || empty( $color['color'] ) ) {
				continue;
			}

			$slug = sanitize_title( $color['slug'] );
			if ( ! $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$palette[]     = array(
				'name' => isset( $color['name'] ) ? (string) $color['name'] : $slug,
				'slug' => $slug,
				'css'  => 'var(--wp--preset--color--' . $slug . ')',
				'hex'  => (string) $color['color'],
			);
		}
	}

	return $palette;
}

/**
 * Normalize a stored color to a safe CSS value.
 *
 * Accepts hex, rgba(), theme preset vars, and Gutenberg's var:preset|color|slug.
 *
 * @param mixed $color
 * @return string
 */
function sponsor_manager_sanitize_color_value( $color ) {
	$color = trim( (string) $color );
	if ( '' === $color ) {
		return '';
	}

	if ( preg_match( '/^var:preset\|color\|([a-z0-9\-]+)$/i', $color, $matches ) ) {
		return 'var(--wp--preset--color--' . sanitize_title( $matches[1] ) . ')';
	}

	if ( preg_match( '/^var\(--wp--preset--color--[a-z0-9\-]+\)$/i', $color ) ) {
		return $color;
	}

	if ( sanitize_hex_color( $color ) ) {
		return $color;
	}

	if ( preg_match( '/^#([0-9a-fA-F]{4}|[0-9a-fA-F]{8})$/', $color ) ) {
		return $color;
	}

	if ( preg_match( '/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+(?:\s*,\s*[\d.]+)?\s*\)$/', $color ) ) {
		return $color;
	}

	return '';
}

/**
 * @param string $color
 * @param string $fallback
 * @return string
 */
function sponsor_manager_color_to_css( $color, $fallback = 'currentColor' ) {
	$color = sponsor_manager_sanitize_color_value( $color );
	return $color ? $color : $fallback;
}

/**
 * Sanitized global styles merged with defaults.
 *
 * @return array
 */
function sponsor_manager_get_global_styles() {
	$saved = get_option( SPONSOR_MANAGER_STYLES_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return sponsor_manager_sanitize_styles( wp_parse_args( $saved, sponsor_manager_get_default_styles() ) );
}

/**
 * @param array $input
 * @return array
 */
function sponsor_manager_sanitize_styles( $input ) {
	$defaults = sponsor_manager_get_default_styles();
	$input    = is_array( $input ) ? $input : array();

	return array(
		'border_radius'   => max( 0, min( 32, (int) ( $input['border_radius'] ?? $defaults['border_radius'] ) ) ),
		'border_width'    => max( 0, min( 8, (int) ( $input['border_width'] ?? $defaults['border_width'] ) ) ),
		'border_color'    => sponsor_manager_sanitize_color_value( $input['border_color'] ?? '' ),
		'show_shadow'     => ! empty( $input['show_shadow'] ),
		'shadow_color'    => sponsor_manager_sanitize_color_value( $input['shadow_color'] ?? '' ),
		'shadow_offset_x' => max( -40, min( 40, (int) ( $input['shadow_offset_x'] ?? $defaults['shadow_offset_x'] ) ) ),
		'shadow_offset_y' => max( -40, min( 40, (int) ( $input['shadow_offset_y'] ?? $defaults['shadow_offset_y'] ) ) ),
		'shadow_blur'     => max( 0, min( 60, (int) ( $input['shadow_blur'] ?? $defaults['shadow_blur'] ) ) ),
		'shadow_spread'   => max( -20, min( 40, (int) ( $input['shadow_spread'] ?? $defaults['shadow_spread'] ) ) ),
	);
}

/**
 * Build the CSS custom-property string for a style set.
 *
 * @param array $styles
 * @return string
 */
function sponsor_manager_build_shadow_css( array $styles, $hover = false, $for_preview = false ) {
	if ( empty( $styles['show_shadow'] ) ) {
		return 'none';
	}

	$x = (int) $styles['shadow_offset_x'];
	$y = (int) $styles['shadow_offset_y'];
	if ( $hover ) {
		$x += ( $x >= 0 ) ? 1 : -1;
		$y += ( $y >= 0 ) ? 1 : -1;
	}

	$color = $for_preview
		? sponsor_manager_preview_color( $styles['shadow_color'] ?? '' )
		: sponsor_manager_color_to_css( $styles['shadow_color'] ?? '' );

	return sprintf(
		'%dpx %dpx %dpx %dpx %s',
		$x,
		$y,
		(int) $styles['shadow_blur'],
		(int) $styles['shadow_spread'],
		$color
	);
}

/**
 * Resolve a stored color to a concrete CSS value for the admin preview.
 * Theme preset vars are not available in wp-admin, so use the palette hex.
 *
 * @param string $color
 * @return string
 */
function sponsor_manager_preview_color( $color ) {
	$color = sponsor_manager_sanitize_color_value( $color );
	if ( '' === $color ) {
		return 'currentColor';
	}

	foreach ( sponsor_manager_get_theme_palette() as $item ) {
		if ( $color === $item['css'] && ! empty( $item['hex'] ) ) {
			return $item['hex'];
		}
	}

	return $color;
}

/**
 * Theme palette custom properties for the Styles tab preview.
 *
 * @return string
 */
function sponsor_manager_theme_palette_css() {
	$parts = array();
	foreach ( sponsor_manager_get_theme_palette() as $item ) {
		if ( empty( $item['slug'] ) || empty( $item['hex'] ) ) {
			continue;
		}
		$parts[] = '--wp--preset--color--' . $item['slug'] . ':' . $item['hex'];
	}

	return implode( ';', $parts );
}

function sponsor_manager_styles_to_css( array $styles, $for_preview = false ) {
	$styles = sponsor_manager_sanitize_styles( $styles );

	$shift_x = (int) $styles['shadow_offset_x'] >= 0 ? -1 : 1;
	$shift_y = (int) $styles['shadow_offset_y'] >= 0 ? -1 : 1;

	$vars = array(
		'--sponsor-ad-radius'       => $styles['border_radius'] . 'px',
		'--sponsor-ad-border-width' => $styles['border_width'] . 'px',
		'--sponsor-ad-border-color' => $for_preview
			? sponsor_manager_preview_color( $styles['border_color'] )
			: sponsor_manager_color_to_css( $styles['border_color'] ),
		'--sponsor-ad-shadow'       => sponsor_manager_build_shadow_css( $styles, false, $for_preview ),
		'--sponsor-ad-shadow-hover' => sponsor_manager_build_shadow_css( $styles, true, $for_preview ),
		'--sponsor-ad-hover-shift'  => $styles['show_shadow'] ? sprintf( 'translate(%dpx, %dpx)', $shift_x, $shift_y ) : 'none',
	);

	$parts = array();
	foreach ( $vars as $property => $value ) {
		$parts[] = $property . ':' . $value;
	}

	return implode( ';', $parts );
}

add_action( 'wp_enqueue_scripts', 'sponsor_manager_enqueue_global_styles', 20 );
add_action( 'enqueue_block_editor_assets', 'sponsor_manager_enqueue_global_styles', 20 );
function sponsor_manager_enqueue_global_styles() {
	$css = ':root{' . sponsor_manager_styles_to_css( sponsor_manager_get_global_styles() ) . ';}';

	wp_register_style( 'sponsor-manager-global', false, array(), '1.2.0' );
	wp_enqueue_style( 'sponsor-manager-global' );
	wp_add_inline_style( 'sponsor-manager-global', $css );
}

add_action( 'admin_init', 'sponsor_manager_handle_styles_save' );
function sponsor_manager_handle_styles_save() {
	if ( ! isset( $_POST['sponsor_manager_save_styles'] ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to manage sponsor styles.', 'sponsor-manager' ) );
	}

	check_admin_referer( 'sponsor_manager_save_styles' );

	$input = isset( $_POST['sponsor_styles'] ) ? wp_unslash( $_POST['sponsor_styles'] ) : array();
	update_option( SPONSOR_MANAGER_STYLES_OPTION, sponsor_manager_sanitize_styles( $input ) );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'              => 'sponsor_manager',
				'tab'               => 'styles',
				'settings-updated'  => 'true',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

add_action( 'admin_enqueue_scripts', 'sponsor_manager_enqueue_styles_tab_assets' );
function sponsor_manager_enqueue_styles_tab_assets( $hook ) {
	if ( 'toplevel_page_sponsor_manager' !== $hook ) {
		return;
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	if ( 'styles' !== $tab ) {
		return;
	}

	$style_path = SPONSOR_MANAGER_PATH . 'build/sponsor-grid/style-index.css';
	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'sponsor-manager-grid',
			SPONSOR_MANAGER_URL . 'build/sponsor-grid/style-index.css',
			array(),
			'1.2.0'
		);
	}

	sponsor_manager_enqueue_global_styles();
}

function sponsor_manager_render_styles_tab() {
	$styles = sponsor_manager_get_global_styles();
	if ( isset( $_GET['settings-updated'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Global styles saved.', 'sponsor-manager' ) . '</p></div>';
	}
	?>
	<style>
		.sponsor-manager-styles {
			display: grid;
			grid-template-columns: minmax(0, 1fr) minmax(260px, 340px);
			gap: 40px;
			align-items: start;
			margin-top: 20px;
			max-width: 1100px;
		}
		.sponsor-manager-styles__form { min-width: 0; }
		.sponsor-manager-styles .form-table { width: 100%; margin-top: 0; }
		.sponsor-manager-styles .form-table th { width: 150px; padding-right: 16px; }
		.sponsor-manager-styles .form-table td { min-width: 0; overflow: visible; }
		.sponsor-manager-styles .form-table tr { overflow: visible; }
		.sponsor-manager-styles__preview { min-width: 0; position: sticky; top: 46px; }
		.sponsor-manager-styles__preview-card {
			margin-top: 16px;
			color: #1d2327;
		}
		.sponsor-manager-styles__preview-card .sponsor-manager-grid__item,
		.sponsor-manager-styles__preview-card .sponsor-manager-grid__item:hover,
		.sponsor-manager-styles__preview-card .sponsor-manager-grid__item:focus {
			color: inherit;
			background: #fff;
			text-decoration: none;
			outline: none;
		}
		.sponsor-manager-color { position: relative; max-width: 280px; }
		.sponsor-manager-color__toggle {
			display: flex;
			align-items: center;
			gap: 8px;
			width: 100%;
			min-height: 36px;
			padding: 4px 8px;
			background: #fff;
			border: 1px solid #8c8f94;
			border-radius: 4px;
			cursor: pointer;
			text-align: left;
		}
		.sponsor-manager-color__toggle:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
		.sponsor-manager-color__chip {
			width: 22px;
			height: 22px;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			background: currentColor;
			flex: 0 0 auto;
		}
		.sponsor-manager-color__label { flex: 1 1 auto; }
		.sponsor-manager-color__chevron { color: #1d2327; }
		.sponsor-manager-color__menu {
			position: absolute;
			z-index: 20;
			top: calc(100% + 4px);
			left: 0;
			right: 0;
			max-height: 240px;
			overflow: auto;
			margin: 0;
			padding: 4px 0;
			background: #fff;
			border: 1px solid #c3c4c7;
			border-radius: 4px;
			box-shadow: 0 2px 8px rgba(0,0,0,.12);
		}
		.sponsor-manager-color__menu[hidden] { display: none; }
		.sponsor-manager-color__option {
			display: flex;
			align-items: center;
			gap: 8px;
			width: 100%;
			padding: 6px 10px;
			background: none;
			border: 0;
			cursor: pointer;
			text-align: left;
		}
		.sponsor-manager-color__option:hover,
		.sponsor-manager-color__option.is-selected { background: #f0f0f1; }
		.sponsor-manager-color__custom { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; }
		.sponsor-manager-color__custom[hidden] { display: none; }
		.sponsor-manager-color__custom input[type="text"] { width: 120px; max-width: 100%; }
		.sponsor-manager-shadow-grid {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 10px 16px;
			max-width: 360px;
			margin-top: 12px;
		}
		.sponsor-manager-shadow-grid label { display: block; font-weight: 600; margin-bottom: 4px; }
		.sponsor-manager-shadow-fields[hidden] { display: none; }
		@media screen and (max-width: 960px) {
			.sponsor-manager-styles { grid-template-columns: 1fr; }
			.sponsor-manager-styles__preview { position: static; }
		}
	</style>
	<div class="sponsor-manager-styles">
		<form class="sponsor-manager-styles__form" method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=sponsor_manager&tab=styles' ) ); ?>">
			<?php wp_nonce_field( 'sponsor_manager_save_styles' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sponsor-style-radius"><?php esc_html_e( 'Border radius', 'sponsor-manager' ); ?></label></th>
					<td>
						<input name="sponsor_styles[border_radius]" id="sponsor-style-radius" type="number" min="0" max="32" value="<?php echo esc_attr( (string) $styles['border_radius'] ); ?>" class="small-text" />
						<span class="description">px</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sponsor-style-width"><?php esc_html_e( 'Border width', 'sponsor-manager' ); ?></label></th>
					<td>
						<input name="sponsor_styles[border_width]" id="sponsor-style-width" type="number" min="0" max="8" value="<?php echo esc_attr( (string) $styles['border_width'] ); ?>" class="small-text" />
						<span class="description">px</span>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Border color', 'sponsor-manager' ); ?></th>
					<td>
						<?php sponsor_manager_render_color_control( 'sponsor-style-border-color', 'sponsor_styles[border_color]', $styles['border_color'] ); ?>
						<p class="description"><?php esc_html_e( 'Theme colors show a swatch. Choose Custom to enter your own, or Inherit for the current text color.', 'sponsor-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Drop shadow', 'sponsor-manager' ); ?></th>
					<td>
						<label>
							<input name="sponsor_styles[show_shadow]" id="sponsor-style-shadow" type="checkbox" value="1" <?php checked( $styles['show_shadow'] ); ?> />
							<?php esc_html_e( 'Show a drop shadow on ad cards', 'sponsor-manager' ); ?>
						</label>
						<div class="sponsor-manager-shadow-fields" id="sponsor-style-shadow-fields" <?php echo $styles['show_shadow'] ? '' : 'hidden'; ?>>
							<div class="sponsor-manager-shadow-grid">
								<div>
									<label for="sponsor-style-shadow-x"><?php esc_html_e( 'Horizontal offset', 'sponsor-manager' ); ?></label>
									<input name="sponsor_styles[shadow_offset_x]" id="sponsor-style-shadow-x" type="number" min="-40" max="40" value="<?php echo esc_attr( (string) $styles['shadow_offset_x'] ); ?>" class="small-text" />
									<span class="description">px</span>
								</div>
								<div>
									<label for="sponsor-style-shadow-y"><?php esc_html_e( 'Vertical offset', 'sponsor-manager' ); ?></label>
									<input name="sponsor_styles[shadow_offset_y]" id="sponsor-style-shadow-y" type="number" min="-40" max="40" value="<?php echo esc_attr( (string) $styles['shadow_offset_y'] ); ?>" class="small-text" />
									<span class="description">px</span>
								</div>
								<div>
									<label for="sponsor-style-shadow-blur"><?php esc_html_e( 'Blur', 'sponsor-manager' ); ?></label>
									<input name="sponsor_styles[shadow_blur]" id="sponsor-style-shadow-blur" type="number" min="0" max="60" value="<?php echo esc_attr( (string) $styles['shadow_blur'] ); ?>" class="small-text" />
									<span class="description">px</span>
								</div>
								<div>
									<label for="sponsor-style-shadow-spread"><?php esc_html_e( 'Spread', 'sponsor-manager' ); ?></label>
									<input name="sponsor_styles[shadow_spread]" id="sponsor-style-shadow-spread" type="number" min="-20" max="40" value="<?php echo esc_attr( (string) $styles['shadow_spread'] ); ?>" class="small-text" />
									<span class="description">px</span>
								</div>
							</div>
							<p style="margin:16px 0 8px;"><strong><?php esc_html_e( 'Shadow color', 'sponsor-manager' ); ?></strong></p>
							<?php sponsor_manager_render_color_control( 'sponsor-style-shadow-color', 'sponsor_styles[shadow_color]', $styles['shadow_color'] ); ?>
						</div>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save styles', 'sponsor-manager' ), 'primary', 'sponsor_manager_save_styles' ); ?>
		</form>

		<div class="sponsor-manager-styles__preview">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Preview', 'sponsor-manager' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These styles apply to every Sponsor Grid and Sponsor Sidebar block, unless a block opts out and sets its own look.', 'sponsor-manager' ); ?></p>
			<div id="sponsor-manager-style-preview" class="sponsor-manager-styles__preview-card" style="<?php echo esc_attr( trim( sponsor_manager_theme_palette_css() . ';' . sponsor_manager_styles_to_css( $styles, true ), ';' ) ); ?>">
				<a href="#" class="sponsor-manager-grid__item" onclick="return false;">
					<span class="sponsor-manager-grid__item-fallback"><?php esc_html_e( 'Sample sponsor ad', 'sponsor-manager' ); ?></span>
				</a>
			</div>
		</div>
	</div>
	<script>
	(function () {
		var preview = document.getElementById('sponsor-manager-style-preview');
		if (!preview) {
			return;
		}

		var radius = document.getElementById('sponsor-style-radius');
		var width = document.getElementById('sponsor-style-width');
		var shadow = document.getElementById('sponsor-style-shadow');
		var shadowFields = document.getElementById('sponsor-style-shadow-fields');
		var offsetX = document.getElementById('sponsor-style-shadow-x');
		var offsetY = document.getElementById('sponsor-style-shadow-y');
		var blur = document.getElementById('sponsor-style-shadow-blur');
		var spread = document.getElementById('sponsor-style-shadow-spread');

		function resolveColor(id) {
			var input = document.getElementById(id);
			if (!input || !input.value) {
				return 'currentColor';
			}
			var value = input.value.trim();
			var control = input.closest('[data-color-control]');
			if (!control) {
				return value;
			}
			var selected = control.querySelector('.sponsor-manager-color__option.is-selected');
			if (!selected) {
				return value;
			}
			var stored = selected.getAttribute('data-color') || '';
			if (stored === '' ) {
				return 'currentColor';
			}
			if (stored === '__custom') {
				return value || 'currentColor';
			}
			return selected.getAttribute('data-hex') || value;
		}

		function buildShadow(isHover) {
			if (!shadow.checked) {
				return 'none';
			}
			var x = parseInt(offsetX.value, 10) || 0;
			var y = parseInt(offsetY.value, 10) || 0;
			if (isHover) {
				x += x >= 0 ? 1 : -1;
				y += y >= 0 ? 1 : -1;
			}
			return x + 'px ' + y + 'px ' + (parseInt(blur.value, 10) || 0) + 'px ' + (parseInt(spread.value, 10) || 0) + 'px ' + resolveColor('sponsor-style-shadow-color');
		}

		function updatePreview() {
			var x = parseInt(offsetX.value, 10) || 0;
			var y = parseInt(offsetY.value, 10) || 0;
			var radiusValue = (radius.value || 0) + 'px';
			var widthValue = (width.value || 0) + 'px';
			var borderColor = resolveColor('sponsor-style-border-color');
			var shadowValue = buildShadow(false);
			var item = preview.querySelector('.sponsor-manager-grid__item');

			preview.style.setProperty('--sponsor-ad-radius', radiusValue);
			preview.style.setProperty('--sponsor-ad-border-width', widthValue);
			preview.style.setProperty('--sponsor-ad-border-color', borderColor);
			preview.style.setProperty('--sponsor-ad-shadow', shadowValue);
			preview.style.setProperty('--sponsor-ad-shadow-hover', buildShadow(true));
			preview.style.setProperty('--sponsor-ad-hover-shift', shadow.checked ? 'translate(' + (x >= 0 ? -1 : 1) + 'px, ' + (y >= 0 ? -1 : 1) + 'px)' : 'none');

			if (item) {
				item.style.borderRadius = radiusValue;
				item.style.borderWidth = widthValue;
				item.style.borderStyle = 'solid';
				item.style.borderColor = borderColor;
				item.style.boxShadow = shadowValue;
			}

			if (shadowFields) {
				shadowFields.hidden = !shadow.checked;
			}
		}

		function bindColorControl(control) {
			var valueInput = control.querySelector('input[type="hidden"]');
			var toggle = control.querySelector('.sponsor-manager-color__toggle');
			var menu = control.querySelector('.sponsor-manager-color__menu');
			var options = control.querySelectorAll('.sponsor-manager-color__option');
			var hexInput = control.querySelector('.sponsor-manager-color__hex');
			var picker = control.querySelector('.sponsor-manager-color__picker');
			var custom = control.querySelector('.sponsor-manager-color__custom');
			var chip = control.querySelector('.sponsor-manager-color__toggle .sponsor-manager-color__chip');
			var label = control.querySelector('.sponsor-manager-color__label');

			function expandHex(value) {
				if (value && value.length === 4 && value.indexOf('#') === 0) {
					return '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];
				}
				return value;
			}

			function findOption(value, mode) {
				var key = mode === 'custom' || (value && value.indexOf('#') === 0) ? '__custom' : value;
				return Array.prototype.find.call(options, function (option) {
					return option.getAttribute('data-color') === key;
				}) || options[0];
			}

			function setColor(value, mode) {
				var option = findOption(value, mode);
				var isCustom = option && option.getAttribute('data-color') === '__custom';
				valueInput.value = isCustom ? value : (option ? option.getAttribute('data-color') : '');
				options.forEach(function (item) {
					item.classList.toggle('is-selected', item === option);
				});
				if (label && option) {
					label.textContent = option.getAttribute('data-label') || option.textContent;
				}
				if (chip) {
					chip.style.background = isCustom
						? (value || '#000000')
						: (option.getAttribute('data-hex') || 'currentColor');
				}
				if (custom) {
					custom.hidden = !isCustom;
				}
				if (hexInput && mode !== 'hex' && isCustom) {
					hexInput.value = value.indexOf('#') === 0 ? value : '';
				}
				if (picker && value && value.indexOf('#') === 0) {
					picker.value = expandHex(value);
				}
				updatePreview();
			}

			function closeMenu() {
				if (menu) {
					menu.hidden = true;
				}
				if (toggle) {
					toggle.setAttribute('aria-expanded', 'false');
				}
			}

			if (toggle && menu) {
				toggle.addEventListener('click', function () {
					var open = menu.hidden;
					document.querySelectorAll('.sponsor-manager-color__menu').forEach(function (other) {
						other.hidden = true;
					});
					menu.hidden = !open;
					toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				});
			}

			options.forEach(function (option) {
				option.addEventListener('click', function () {
					var next = option.getAttribute('data-color') || '';
					if (next === '__custom') {
						setColor(hexInput && hexInput.value ? hexInput.value : (picker ? picker.value : '#000000'), 'custom');
					} else {
						setColor(next);
					}
					closeMenu();
				});
			});

			if (hexInput) {
				hexInput.addEventListener('input', function () {
					var value = hexInput.value.trim();
					if (!value || /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(value)) {
						setColor(value, 'hex');
					}
				});
			}

			if (picker) {
				picker.addEventListener('input', function () {
					setColor(picker.value, 'custom');
				});
			}

			document.addEventListener('click', function (event) {
				if (!control.contains(event.target)) {
					closeMenu();
				}
			});

			setColor(valueInput.value, valueInput.value.indexOf('#') === 0 ? 'custom' : '');
		}

		document.querySelectorAll('[data-color-control]').forEach(bindColorControl);

		[radius, width, shadow, offsetX, offsetY, blur, spread].forEach(function (field) {
			if (field) {
				field.addEventListener('input', updatePreview);
				field.addEventListener('change', updatePreview);
			}
		});
	})();
	</script>
	<?php
}

/**
 * Theme/custom color dropdown.
 *
 * @param string $id    Hidden input id.
 * @param string $name  Hidden input name.
 * @param string $value Stored color (hex or CSS variable).
 */
function sponsor_manager_render_color_control( $id, $name, $value ) {
	$value     = sponsor_manager_sanitize_color_value( $value );
	$palette   = sponsor_manager_get_theme_palette();
	$is_custom = (bool) $value && ! preg_match( '/^var\(--wp--preset--color--/', $value );
	$hex       = ( $value && '#' === $value[0] ) ? $value : '';
	$picker    = $hex ? $hex : '#000000';
	if ( 4 === strlen( $picker ) ) {
		$picker = '#' . $picker[1] . $picker[1] . $picker[2] . $picker[2] . $picker[3] . $picker[3];
	}

	$label = __( 'Inherit text color', 'sponsor-manager' );
	$chip  = 'currentColor';
	if ( $is_custom ) {
		$label = __( 'Custom color…', 'sponsor-manager' );
		$chip  = $hex ? $hex : '#000000';
	} else {
		foreach ( $palette as $item ) {
			if ( $value === $item['css'] ) {
				$label = $item['name'];
				$chip  = $item['hex'];
				break;
			}
		}
	}
	?>
	<div class="sponsor-manager-color" data-color-control>
		<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<button type="button" class="sponsor-manager-color__toggle" aria-expanded="false" aria-haspopup="listbox">
			<span class="sponsor-manager-color__chip" style="background:<?php echo esc_attr( $chip ); ?>"></span>
			<span class="sponsor-manager-color__label"><?php echo esc_html( $label ); ?></span>
			<span class="sponsor-manager-color__chevron" aria-hidden="true">▾</span>
		</button>
		<div class="sponsor-manager-color__menu" hidden role="listbox">
			<button type="button" class="sponsor-manager-color__option<?php echo '' === $value ? ' is-selected' : ''; ?>" data-color="" data-hex="currentColor" data-label="<?php esc_attr_e( 'Inherit text color', 'sponsor-manager' ); ?>">
				<span class="sponsor-manager-color__chip" style="background:currentColor"></span>
				<?php esc_html_e( 'Inherit text color', 'sponsor-manager' ); ?>
			</button>
			<?php foreach ( $palette as $item ) : ?>
				<button
					type="button"
					class="sponsor-manager-color__option<?php echo $value === $item['css'] ? ' is-selected' : ''; ?>"
					data-color="<?php echo esc_attr( $item['css'] ); ?>"
					data-hex="<?php echo esc_attr( $item['hex'] ); ?>"
					data-label="<?php echo esc_attr( $item['name'] ); ?>"
				>
					<span class="sponsor-manager-color__chip" style="background:<?php echo esc_attr( $item['hex'] ); ?>"></span>
					<?php echo esc_html( $item['name'] ); ?>
				</button>
			<?php endforeach; ?>
			<button type="button" class="sponsor-manager-color__option<?php echo $is_custom ? ' is-selected' : ''; ?>" data-color="__custom" data-hex="#000000" data-label="<?php esc_attr_e( 'Custom color…', 'sponsor-manager' ); ?>">
				<span class="sponsor-manager-color__chip" style="background:conic-gradient(red, yellow, lime, aqua, blue, magenta, red)"></span>
				<?php esc_html_e( 'Custom color…', 'sponsor-manager' ); ?>
			</button>
		</div>
		<div class="sponsor-manager-color__custom" <?php echo $is_custom ? '' : 'hidden'; ?>>
			<input type="text" class="sponsor-manager-color__hex" value="<?php echo esc_attr( $hex ); ?>" placeholder="#000000" aria-label="<?php esc_attr_e( 'Custom color', 'sponsor-manager' ); ?>" />
			<input type="color" class="sponsor-manager-color__picker" value="<?php echo esc_attr( $picker ); ?>" aria-label="<?php esc_attr_e( 'Color picker', 'sponsor-manager' ); ?>" />
		</div>
	</div>
	<?php
}
