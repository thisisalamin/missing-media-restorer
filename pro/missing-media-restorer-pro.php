<?php
/**
 * Missing Media Restorer Pro - Pro Features
 *
 * @package MissingMediaRestorerPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define pro constants.
define( 'MMR_PRO_VERSION', '1.0.0' );
define( 'MMR_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMR_PRO_URL', plugin_dir_url( __FILE__ ) );

// Include pro features.
require_once MMR_PRO_DIR . 'class-mmr-pro-scanner.php';
require_once MMR_PRO_DIR . 'class-mmr-pro-uploader.php';

/**
 * Initialize Pro features
 */
function mmr_pro_init() {
	// Add pro features to admin interface
	add_action( 'admin_enqueue_scripts', 'mmr_pro_enqueue_assets' );
	add_filter( 'mmr_is_pro_active', '__return_true' );
	add_filter( 'mmr_pro_features', 'mmr_pro_get_features' );
}
add_action( 'plugins_loaded', 'mmr_pro_init' );

/**
 * Enqueue pro-specific assets
 */
function mmr_pro_enqueue_assets( $hook ) {
	if ( 'toplevel_page_missing-media-restorer' !== $hook ) {
		return;
	}

	// Enqueue pro-specific CSS
	wp_enqueue_style(
		'mmr-pro-css',
		MMR_PRO_URL . 'assets/css/mmr-pro.css',
		array( 'mmr-admin-css' ),
		MMR_PRO_VERSION
	);

	// Enqueue pro-specific JS
	wp_enqueue_script(
		'mmr-pro-js',
		MMR_PRO_URL . 'assets/js/mmr-pro.js',
		array( 'mmr-admin-js' ),
		MMR_PRO_VERSION,
		true
	);

	// Localize pro script
	wp_localize_script(
		'mmr-pro-js',
		'mmrProConfig',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'mmr_pro_ajax_nonce' ),
		)
	);
}

/**
 * Get pro features list
 */
function mmr_pro_get_features() {
	return array(
		'smart_local_scan' => array(
			'title'       => 'Smart Local Scan & Selective Upload',
			'description' => 'Scan local directories for files that are missing on the server and upload only the matching files automatically.',
			'icon'        => 'dashicons-search',
			'enabled'     => true,
		),
		'bulk_upload' => array(
			'title'       => 'Bulk Upload Support',
			'description' => 'Upload thousands of files at once with drag & drop folders.',
			'icon'        => 'dashicons-cloud-upload',
			'enabled'     => true,
		),
		'advanced_matching' => array(
			'title'       => 'Advanced Matching',
			'description' => 'Smart filename matching and duplicate detection.',
			'icon'        => 'dashicons-admin-tools',
			'enabled'     => true,
		),
		'progress_analytics' => array(
			'title'       => 'Progress Analytics',
			'description' => 'Detailed reports and restoration statistics.',
			'icon'        => 'dashicons-chart-line',
			'enabled'     => true,
		),
		'priority_support' => array(
			'title'       => 'Priority Support',
			'description' => 'Get help from our expert support team.',
			'icon'        => 'dashicons-shield',
			'enabled'     => true,
		),
	);
}

/**
 * Check if pro is active
 */
function mmr_is_pro_active() {
	return apply_filters( 'mmr_is_pro_active', false );
}

/**
 * Get pro features
 */
function mmr_get_pro_features() {
	return apply_filters( 'mmr_pro_features', array() );
}
