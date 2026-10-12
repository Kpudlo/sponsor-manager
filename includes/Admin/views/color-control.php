<?php
/**
 * Theme/custom color dropdown used on the Styles tab.
 *
 * @package SponsorManager
 *
 * @var string $id        Hidden input id.
 * @var string $name      Hidden input name.
 * @var string $value     Stored color.
 * @var bool   $is_custom Whether the value is a custom hex.
 * @var string $hex       Hex value when custom.
 * @var string $picker    Color-input value.
 * @var string $label     Toggle label.
 * @var string $chip      Chip background.
 * @var array  $palette   Theme palette entries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		<?php foreach ( $palette as $sponsor_manager_swatch ) : ?>
			<button
				type="button"
				class="sponsor-manager-color__option<?php echo $value === $sponsor_manager_swatch['css'] ? ' is-selected' : ''; ?>"
				data-color="<?php echo esc_attr( $sponsor_manager_swatch['css'] ); ?>"
				data-hex="<?php echo esc_attr( $sponsor_manager_swatch['hex'] ); ?>"
				data-label="<?php echo esc_attr( $sponsor_manager_swatch['name'] ); ?>"
			>
				<span class="sponsor-manager-color__chip" style="background:<?php echo esc_attr( $sponsor_manager_swatch['hex'] ); ?>"></span>
				<?php echo esc_html( $sponsor_manager_swatch['name'] ); ?>
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
