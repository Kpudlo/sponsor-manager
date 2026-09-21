<?php
/**
 * Self-update from GitHub Releases.
 *
 * Uses the bundled plugin-update-checker library so WordPress shows a normal
 * update notice when a newer GitHub release is published.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', 'sponsor_manager_init_update_checker' );
function sponsor_manager_init_update_checker() {
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
		SPONSOR_MANAGER_PATH . 'sponsor-manager.php',
		'sponsor-manager'
	);

	$update_checker->setBranch( 'master' );

	// Install the built zip attached to each GitHub Release, not the source tarball.
	$api = $update_checker->getVcsApi();
	if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
		$api->enableReleaseAssets( '/sponsor-manager\.zip($|[?&#])/i' );
	}
}
