<?php
/**
 * GitHub Releases updater.
 *
 * @package SponsorManager
 */

namespace SponsorManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a normal Plugins-screen update when a newer GitHub release is published.
 */
class Updater {

	/**
	 * Load plugin-update-checker on admin_init.
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'init' ) );
	}

	/**
	 * Build the update checker against GitHub release zips.
	 */
	public static function init() {
		$loader = SPONSOR_MANAGER_PATH . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $loader ) ) {
			return;
		}

		require_once $loader;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/Kpudlo/sponsor-manager/',
			SPONSOR_MANAGER_FILE,
			'sponsor-manager'
		);

		$update_checker->setBranch( 'master' );

		$api = $update_checker->getVcsApi();
		if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/sponsor-manager\.zip($|[?&#])/i' );
		}
	}
}
