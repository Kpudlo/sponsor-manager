<?php
/**
 * Stored global card styles.
 *
 * @package SponsorManager
 */

namespace SponsorManager\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SponsorManager\Constants;

/**
 * Option storage, sanitization, and CSS custom properties for ad cards.
 */
class Repository {

	/**
	 * Default border and shadow.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
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
	 * Theme palette colors for pickers and admin preview.
	 *
	 * @return array<int, array{name:string,slug:string,css:string,hex:string}>
	 */
	public static function palette() {
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
	 * @param mixed $color Raw color.
	 * @return string
	 */
	public static function sanitize_color( $color ) {
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
	 * @param string $color    Stored color.
	 * @param string $fallback CSS fallback.
	 * @return string
	 */
	public static function color_to_css( $color, $fallback = 'currentColor' ) {
		$color = self::sanitize_color( $color );
		return $color ? $color : $fallback;
	}

	/**
	 * Saved styles merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$saved = get_option( Constants::STYLES_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return self::sanitize( wp_parse_args( $saved, self::defaults() ) );
	}

	/**
	 * @param mixed $input Raw styles.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();

		return array(
			'border_radius'   => max( 0, min( 32, (int) ( $input['border_radius'] ?? $defaults['border_radius'] ) ) ),
			'border_width'    => max( 0, min( 8, (int) ( $input['border_width'] ?? $defaults['border_width'] ) ) ),
			'border_color'    => self::sanitize_color( $input['border_color'] ?? '' ),
			'show_shadow'     => ! empty( $input['show_shadow'] ),
			'shadow_color'    => self::sanitize_color( $input['shadow_color'] ?? '' ),
			'shadow_offset_x' => max( -40, min( 40, (int) ( $input['shadow_offset_x'] ?? $defaults['shadow_offset_x'] ) ) ),
			'shadow_offset_y' => max( -40, min( 40, (int) ( $input['shadow_offset_y'] ?? $defaults['shadow_offset_y'] ) ) ),
			'shadow_blur'     => max( 0, min( 60, (int) ( $input['shadow_blur'] ?? $defaults['shadow_blur'] ) ) ),
			'shadow_spread'   => max( -20, min( 40, (int) ( $input['shadow_spread'] ?? $defaults['shadow_spread'] ) ) ),
		);
	}

	/**
	 * @param array $styles      Style set.
	 * @param bool  $hover       Whether to nudge the shadow for hover.
	 * @param bool  $for_preview Resolve theme vars to hex for wp-admin.
	 * @return string
	 */
	public static function shadow_css( array $styles, $hover = false, $for_preview = false ) {
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
			? self::preview_color( $styles['shadow_color'] ?? '' )
			: self::color_to_css( $styles['shadow_color'] ?? '' );

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
	 * Resolve a theme preset to hex for the Styles tab preview.
	 *
	 * @param string $color Stored color.
	 * @return string
	 */
	public static function preview_color( $color ) {
		$color = self::sanitize_color( $color );
		if ( '' === $color ) {
			return 'currentColor';
		}

		foreach ( self::palette() as $item ) {
			if ( $color === $item['css'] && ! empty( $item['hex'] ) ) {
				return $item['hex'];
			}
		}

		return $color;
	}

	/**
	 * Inline palette custom properties for the admin preview.
	 *
	 * @return string
	 */
	public static function palette_css() {
		$parts = array();
		foreach ( self::palette() as $item ) {
			if ( empty( $item['slug'] ) || empty( $item['hex'] ) ) {
				continue;
			}
			$parts[] = '--wp--preset--color--' . $item['slug'] . ':' . $item['hex'];
		}

		return implode( ';', $parts );
	}

	/**
	 * CSS custom-property string for a style set.
	 *
	 * @param array $styles      Style set.
	 * @param bool  $for_preview Use hex for theme colors.
	 * @return string
	 */
	public static function to_css( array $styles, $for_preview = false ) {
		$styles = self::sanitize( $styles );

		$shift_x = (int) $styles['shadow_offset_x'] >= 0 ? -1 : 1;
		$shift_y = (int) $styles['shadow_offset_y'] >= 0 ? -1 : 1;

		$vars = array(
			'--sponsor-ad-radius'       => $styles['border_radius'] . 'px',
			'--sponsor-ad-border-width' => $styles['border_width'] . 'px',
			'--sponsor-ad-border-color' => $for_preview
				? self::preview_color( $styles['border_color'] )
				: self::color_to_css( $styles['border_color'] ),
			'--sponsor-ad-shadow'       => self::shadow_css( $styles, false, $for_preview ),
			'--sponsor-ad-shadow-hover' => self::shadow_css( $styles, true, $for_preview ),
			'--sponsor-ad-hover-shift'  => $styles['show_shadow'] ? sprintf( 'translate(%dpx, %dpx)', $shift_x, $shift_y ) : 'none',
		);

		$parts = array();
		foreach ( $vars as $property => $value ) {
			$parts[] = $property . ':' . $value;
		}

		return implode( ';', $parts );
	}

	/**
	 * Persist styles from the dashboard form.
	 *
	 * @param array $input Raw POST data.
	 */
	public static function save( array $input ) {
		update_option( Constants::STYLES_OPTION, self::sanitize( $input ) );
	}
}
