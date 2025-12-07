<?php
/**
 * Plugin Name: Missing Media Restorer
 * Description: Restores missing WordPress media files in batches to prevent timeouts and server overload
 * Version: 1.0.0
 * Author: Crafely Development
 * License: GPL v2 or later
 * Text Domain: missing-media-restorer
 * Domain Path: /languages
 *
 * @package MissingMediaRestorer
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'MMR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MMR_PLUGIN_VERSION', '1.0.4' );

/**
 * Check if pro version is available
 *
 * @return bool True if pro version is available
 */
function mmr_is_pro_available() {
	return file_exists( MMR_PLUGIN_DIR . '/pro/missing-media-restorer-pro.php' );
}

/**
 * Get pro version status for display
 *
 * @return array Pro status information
 */
function mmr_get_pro_status() {
	$is_pro = mmr_is_pro_available();

	return array(
		'is_pro'      => $is_pro,
		'button_text' => $is_pro ? __( 'Pro', 'missing-media-restorer' ) : __( 'Upgrade to Pro', 'missing-media-restorer' ),
		'button_icon' => $is_pro ? 'star-filled' : 'star-filled',
		'badge_text'  => $is_pro ? __( 'PRO', 'missing-media-restorer' ) : '',
	);
}

/**
 * Plugin activation hook
 */
function mmr_plugin_activation() {
	// Create temp upload directory
	$upload_dir = wp_upload_dir();
	$temp_dir   = $upload_dir['basedir'] . '/mmr-temp/';

	if ( ! file_exists( $temp_dir ) ) {
		wp_mkdir_p( $temp_dir );
	}

	// Load pro features if available
	if ( mmr_is_pro_available() ) {
		require_once MMR_PLUGIN_DIR . 'pro/missing-media-restorer-pro.php';
	}
}
register_activation_hook( __FILE__, 'mmr_plugin_activation' );

/**
 * Plugin deactivation hook
 */
function mmr_plugin_deactivation() {
	// Clear temporary data
	delete_transient( 'mmr_missing_files' );

	// Optionally clean up temp directory (commented for safety)
	// $upload_dir = wp_upload_dir();
	// $temp_dir = $upload_dir['basedir'] . '/mmr-temp/';
	// if ( file_exists( $temp_dir ) ) {
	// array_map( 'unlink', glob( $temp_dir . '*' ) );
	// rmdir( $temp_dir );
	// }
}
register_deactivation_hook( __FILE__, 'mmr_plugin_deactivation' );

/**
 * Register admin menu for Media Restorer
 */
function mmr_register_admin_menu() {
	// Main menu page
	add_menu_page(
		'Media Restorer',           // Page title
		'Media Restorer',           // Menu title
		'manage_options',           // Capability required
		'missing-media-restorer',   // Menu slug
		'mmr_render_admin_page',    // Function to output page content
		'dashicons-format-image',   // Icon
		25                          // Position
	);

	// Add pro submenu pages if pro is available
	if ( mmr_is_pro_available() ) {
		add_submenu_page(
			'missing-media-restorer',      // Parent slug
			'Pro Scanner',               // Page title
			'Scanner',                  // Menu title
			'manage_options',            // Capability
			'mmr-pro-scanner',          // Menu slug
			'mmr_render_pro_scanner_page' // Callback function
		);

		add_submenu_page(
			'missing-media-restorer',      // Parent slug
			'Pro Uploader',              // Page title
			'Uploader',                  // Menu title
			'manage_options',            // Capability
			'mmr-pro-uploader',          // Menu slug
			'mmr_render_pro_uploader_page' // Callback function
		);

		add_submenu_page(
			'missing-media-restorer',      // Parent slug
			'Pro Matcher',               // Page title
			'Matcher',                   // Menu title
			'manage_options',            // Capability
			'mmr-pro-matcher',           // Menu slug
			'mmr_render_pro_matcher_page'  // Callback function
		);

		add_submenu_page(
			'missing-media-restorer',      // Parent slug
			'Pro Analytics',             // Page title
			'Analytics',                 // Menu title
			'manage_options',            // Capability
			'mmr-pro-analytics',         // Menu slug
			'mmr_render_pro_analytics_page' // Callback function
		);

		add_submenu_page(
			'missing-media-restorer',      // Parent slug
			'Pro Support',              // Page title
			'Support',                   // Menu title
			'manage_options',            // Capability
			'mmr-pro-support',           // Menu slug
			'mmr_render_pro_support_page'  // Callback function
		);
	}
}
add_action( 'admin_menu', 'mmr_register_admin_menu' );

/**
 * Enqueue admin scripts and styles for the plugin page
 */
function mmr_enqueue_admin_assets( $hook ) {
	// Only enqueue on our plugin's admin pages
	$valid_hooks = array(
		'toplevel_page_missing-media-restorer',
		'media-restorer_page_mmr-pro-scanner',
		'media-restorer_page_mmr-pro-uploader',
		'media-restorer_page_mmr-pro-matcher',
		'media-restorer_page_mmr-pro-analytics',
		'media-restorer_page_mmr-pro-support',
	);

	if ( ! in_array( $hook, $valid_hooks, true ) ) {
		return;
	}

	// Enqueue the main admin JavaScript
	wp_enqueue_script(
		'mmr-admin-js',
		MMR_PLUGIN_URL . 'assets/js/mmr-admin.js',
		array(),
		MMR_PLUGIN_VERSION,
		true
	);

	// Localize script with AJAX URL and nonce
	wp_localize_script(
		'mmr-admin-js',
		'mmrConfig',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'mmr_ajax_nonce' ),
		)
	);

	// Enqueue the main admin CSS (includes Tailwind + custom styles)
	wp_enqueue_style(
		'mmr-admin-css',
		MMR_PLUGIN_URL . 'assets/css/mmr-admin.css',
		array(),
		MMR_PLUGIN_VERSION
	);

	// Enqueue pro assets if pro version is available
	if ( mmr_is_pro_available() ) {
		wp_enqueue_style(
			'mmr-pro-css',
			MMR_PLUGIN_URL . 'pro/assets/css/mmr-pro.css',
			array( 'mmr-admin-css' ),
			MMR_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'mmr-pro-js',
			MMR_PLUGIN_URL . 'pro/assets/js/mmr-pro.js',
			array( 'mmr-admin-js' ),
			MMR_PLUGIN_VERSION,
			true
		);
	}

	// Enqueue Dashicons for admin icons
	wp_enqueue_style( 'dashicons' );
}
add_action( 'admin_enqueue_scripts', 'mmr_enqueue_admin_assets' );

/**
 * Render the admin page HTML structure
 */
function mmr_render_admin_page() {
	$pro_status = mmr_get_pro_status();
	?>
	<div class="wrap">
		<div class="mmr-admin max-w-[88rem] mx-auto mt-8 mb-10 px-4">
			<header class="flex flex-col gap-4 mb-4">
				<div class="flex items-center justify-between gap-4">
					<div class="flex items-center gap-3">
						<div class="h-9 w-9 rounded-xl mmr-header-icon flex items-center justify-center shadow-sm">
							<span class="dashicons dashicons-format-image text-base"></span>
						</div>
						<div>
							<h1 class="text-xl font-semibold tracking-tight text-slate-900">
								<?php esc_html_e( 'Missing Media Restorer', 'missing-media-restorer' ); ?>
								<?php if ( $pro_status['is_pro'] ) : ?>
									<span class="mmr-pro-badge"><?php echo esc_html( $pro_status['badge_text'] ); ?></span>
								<?php endif; ?>
							</h1>
							<p class="text-sm font-medium text-slate-500">
								<?php esc_html_e( 'Minimal recovery workspace for lost media files.', 'missing-media-restorer' ); ?>
							</p>
						</div>
					</div>
					<div class="sm:flex items-center gap-3 text-sm text-slate-500">
						<span class="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1">
							<span class="dashicons dashicons-shield text-[13px] text-emerald-600"></span>
							<?php esc_html_e( 'Safe batch restore', 'missing-media-restorer' ); ?>
						</span>
						<span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-1">
							<span class="dashicons dashicons-shield-alt text-[13px] text-blue-600"></span>
							<?php esc_html_e( 'No database changes', 'missing-media-restorer' ); ?>
						</span>
					</div>
				</div>
				<nav class="mt-2 flex items-center justify-between border border-slate-200 rounded-xl bg-white bg-opacity-70 backdrop-blur px-3 py-2 shadow-sm">
					<ol class="flex items-center gap-2 text-sm text-slate-500 font-medium">
						<li class="flex items-center gap-2" data-step="1">
							<button type="button" class="mmr-step-chip mmr-step-chip-active inline-flex items-center gap-2 rounded-full px-2 py-1 bg-slate-900 text-slate-50">
								<span class="flex h-4 w-4 mmr-step-active-icon items-center justify-center rounded-full border border-slate-700 text-[10px]">1</span>
								<span><?php esc_html_e( 'Scan', 'missing-media-restorer' ); ?></span>
							</button>
						</li>
						<li class="flex items-center gap-2" data-step="2">
							<div class="h-px w-5 bg-slate-200"></div>
							<button type="button" class="mmr-step-chip mmr-step-chip-inactive inline-flex items-center gap-2 rounded-full px-2 py-1 text-slate-500">
								<span class="flex h-4 w-4 mmr-step-inactive-icon items-center justify-center rounded-full border border-slate-300 text-[10px]">2</span>
								<span><?php esc_html_e( 'Upload', 'missing-media-restorer' ); ?></span>
							</button>
						</li>
						<li class="flex items-center gap-2" data-step="3">
							<div class="h-px w-5 bg-slate-200"></div>
							<button type="button" class="mmr-step-chip mmr-step-chip-inactive inline-flex items-center gap-2 rounded-full px-2 py-1 text-slate-500">
								<span class="flex h-4 w-4 mmr-step-inactive-icon items-center justify-center rounded-full border border-slate-300 text-[10px]">3</span>
								<span><?php esc_html_e( 'Match', 'missing-media-restorer' ); ?></span>
							</button>
						</li>
						<li class="flex items-center gap-2" data-step="4">
							<div class="h-px w-5 bg-slate-200"></div>
							<button type="button" class="mmr-step-chip mmr-step-chip-inactive inline-flex items-center gap-2 rounded-full px-2 py-1 text-slate-500">
								<span class="flex h-4 w-4 mmr-step-inactive-icon items-center justify-center rounded-full border border-slate-300 text-[10px]">4</span>
								<span><?php esc_html_e( 'Restore', 'missing-media-restorer' ); ?></span>
							</button>
						</li>
					</ol>
					<div class="flex items-center gap-2 text-sm text-slate-400">
						<button id="mmr-new-scan-btn" type="button" class="mmr-btn-primary inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-medium">
							<span class="dashicons dashicons-search text-[13px]"></span>
							<span><?php esc_html_e( 'Run Scan', 'missing-media-restorer' ); ?></span>
						</button>
						<button id="mmr-btn-pro" type="button" class="mmr-btn-pro inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-medium <?php echo $pro_status['is_pro'] ? 'mmr-pro-active' : ''; ?>">
							<span class="dashicons dashicons-<?php echo esc_attr( $pro_status['button_icon'] ); ?> text-[13px]"></span>
							<span><?php echo esc_html( $pro_status['button_text'] ); ?></span>
						</button>
					</div>
				</nav>
			</header>

			<!-- Step 1: Scan -->
			<section id="mmr-step-1" class="mmr-step-container mmr-step-active">
				<div class="flex flex-col gap-4">
				<!-- Main Scan Panel -->
				<div>
					<div id="mmr-scan-results" class="mt-3 hidden px-4 py-4 rounded-2xl border border-slate-200 mmr-section-bg shadow-sm">
						<div id="mmr-scan-output" class="text-sm text-slate-700"></div>
					</div>
				</div>
				<!-- Scanning progress bar for a calm, centred progress indicator (indeterminate while scanning) -->
				<div id="mmr-scan-progress-container" class="hidden mt-4">
					<div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
						<div id="mmr-scan-progress-fill" class="h-full w-0 mmr-scan-progress-fill rounded-full transition-all"></div>
					</div>
					<p id="mmr-scan-progress-text" class="text-sm text-slate-500 mt-2">Preparing scan…</p>
				</div>
				<!-- How it Works Grid -->
				<div class="grid gap-4 md:grid-cols-3 text-sm">
					<div class="mmr-how-works-card">
						<div class="mb-3 flex items-start gap-3">
							<span class="mmr-how-works-icon-blue inline-flex h-8 w-8 items-center justify-center rounded-lg mt-0.5 shrink-0">
								<span class="dashicons dashicons-yes text-[16px]"></span>
							</span>
							<div>
								<p class="font-bold text-slate-900 mb-1"><?php esc_html_e( 'Check Attachments', 'missing-media-restorer' ); ?></p>
								<p class="text-slate-600 leading-relaxed"><?php esc_html_e( 'Examines all media library entries and their metadata to build a complete inventory.', 'missing-media-restorer' ); ?></p>
							</div>
						</div>
					</div>
					<div class="mmr-how-works-card">
						<div class="mb-3 flex items-start gap-3">
							<span class="mmr-how-works-icon-amber inline-flex h-8 w-8 items-center justify-center rounded-lg mt-0.5 shrink-0">
								<span class="dashicons dashicons-search text-[16px]"></span>
							</span>
							<div>
								<p class="font-bold text-slate-900 mb-1"><?php esc_html_e( 'Find Missing Files', 'missing-media-restorer' ); ?></p>
								<p class="text-slate-600 leading-relaxed"><?php esc_html_e( 'Locates files referenced in metadata but missing from the uploads folder.', 'missing-media-restorer' ); ?></p>
							</div>
						</div>
					</div>
					<div class="mmr-how-works-card">
						<div class="mb-3 flex items-start gap-3">
							<span class="mmr-how-works-icon-green inline-flex h-8 w-8 items-center justify-center rounded-lg mt-0.5 shrink-0">
								<span class="dashicons dashicons-format-image text-[16px]"></span>
							</span>
							<div>
								<p class="font-bold text-slate-900 mb-1"><?php esc_html_e( 'Include Thumbnails', 'missing-media-restorer' ); ?></p>
								<p class="text-slate-600 leading-relaxed"><?php esc_html_e( 'Also checks for missing thumbnail variations and image sizes.', 'missing-media-restorer' ); ?></p>
							</div>
						</div>
				</div>
			</div>					<!-- Key Features -->
				<div class="mmr-key-features-box">
					<h3 class="text-sm font-bold text-slate-900 mb-4"><?php esc_html_e( 'How it works', 'missing-media-restorer' ); ?></h3>
					<div class="grid gap-4 md:grid-cols-2">
						<div class="mmr-workflow-card">
							<div class="flex items-start gap-3">
								<span class="mmr-workflow-icon mmr-workflow-icon-blue">
									<span class="dashicons dashicons-search"></span>
								</span>
								<div>
									<p class="mmr-workflow-title"><?php esc_html_e( 'Scan Library', 'missing-media-restorer' ); ?></p>
									<p class="mmr-workflow-description"><?php esc_html_e( 'Quickly identify missing media files in your WordPress uploads.', 'missing-media-restorer' ); ?></p>
								</div>
							</div>
						</div>
						<div class="mmr-workflow-card">
							<div class="flex items-start gap-3">
								<span class="mmr-workflow-icon mmr-workflow-icon-amber">
									<span class="dashicons dashicons-cloud-upload"></span>
								</span>
								<div>
									<p class="mmr-workflow-title"><?php esc_html_e( 'Upload Backups', 'missing-media-restorer' ); ?></p>
									<p class="mmr-workflow-description"><?php esc_html_e( 'Drag & drop or FTP your backup files to the temp folder.', 'missing-media-restorer' ); ?></p>
								</div>
							</div>
						</div>
						<div class="mmr-workflow-card">
							<div class="flex items-start gap-3">
								<span class="mmr-workflow-icon mmr-workflow-icon-emerald">
									<span class="dashicons dashicons-yes"></span>
								</span>
								<div>
									<p class="mmr-workflow-title"><?php esc_html_e( 'Smart Matching', 'missing-media-restorer' ); ?></p>
									<p class="mmr-workflow-description"><?php esc_html_e( 'Automatically match backup files with missing entries.', 'missing-media-restorer' ); ?></p>
								</div>
							</div>
						</div>
						<div class="mmr-workflow-card">
							<div class="flex items-start gap-3">
								<span class="mmr-workflow-icon mmr-workflow-icon-cyan">
									<span class="dashicons dashicons-image-rotate"></span>
								</span>
								<div>
									<p class="mmr-workflow-title"><?php esc_html_e( 'Batch Restore', 'missing-media-restorer' ); ?></p>
									<p class="mmr-workflow-description"><?php esc_html_e( 'Restore files safely in small batches to avoid timeouts.', 'missing-media-restorer' ); ?></p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>			<!-- Step 2: Upload -->
			<section id="mmr-step-2" class="mmr-step-container hidden">
				<div class="grid gap-4 md:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
					<div class="rounded-2xl border border-slate-200 mmr-section-bg shadow-sm">
						<div class="border-b border-slate-100 px-4 py-3">
							<h2 class="text-sm font-semibold tracking-tight text-slate-900"><?php esc_html_e( 'Bring your backup media', 'missing-media-restorer' ); ?></h2>
							<p class="mt-0.5 text-sm text-slate-500"><?php esc_html_e( 'Upload only the files you want to restore—no bulky UI, just a focused drop area.', 'missing-media-restorer' ); ?></p>
						</div>
						<div class="px-4 py-4 flex flex-col gap-3">
							<div id="mmr-upload-dropzone" class="group flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 bg-opacity-60 px-4 py-6 text-center text-sm text-slate-500 cursor-pointer transition">
								<span class="mb-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-sm text-slate-400">
									<span class="dashicons dashicons-cloud-upload text-[16px]"></span>
								</span>
								<p class="font-medium text-slate-700"><?php esc_html_e( 'Drop media here', 'missing-media-restorer' ); ?></p>
								<p class="text-sm text-slate-500"><?php esc_html_e( 'We keep the layout calm while files upload in the background.', 'missing-media-restorer' ); ?></p>
								<p class="mt-2 text-[10px] uppercase tracking-wide text-slate-400"><?php esc_html_e( 'Or use the buttons below', 'missing-media-restorer' ); ?></p>
								<input type="file" id="mmr-file-input" multiple webkitdirectory class="hidden">
								<input type="file" id="mmr-files-input" multiple class="hidden">
							</div>
							<div class="flex flex-wrap items-center gap-2 text-sm">
								<button id="mmr-select-files-btn" type="button" class="mmr-btn-ghost inline-flex items-center gap-1 rounded-full px-3 py-1.5">
									<span class="dashicons dashicons-media-default text-[13px]"></span>
									<span><?php esc_html_e( 'Pick files', 'missing-media-restorer' ); ?></span>
								</button>
								<button id="mmr-select-folder-btn" type="button" class="mmr-btn-ghost inline-flex items-center gap-1 rounded-full px-3 py-1.5">
									<span class="dashicons dashicons-category text-[13px]"></span>
									<span><?php esc_html_e( 'Pick folder', 'missing-media-restorer' ); ?></span>
								</button>
								<button id="mmr-refresh-files-btn" type="button" class="mmr-btn-ghost inline-flex items-center gap-1 rounded-full px-3 py-1.5">
									<span class="dashicons dashicons-update text-[13px]"></span>
									<span><?php esc_html_e( 'Check FTP uploads', 'missing-media-restorer' ); ?></span>
								</button>
							</div>
							<div id="mmr-upload-list" class="mt-2 text-sm text-slate-600"></div>
						</div>
					</div>
					<div class="flex flex-col gap-3">
						<div class="rounded-2xl border border-slate-200 mmr-section-bg p-4 shadow-sm text-sm text-slate-600">
							<p class="mb-2 font-medium text-slate-800"><?php esc_html_e( 'Prefer FTP?', 'missing-media-restorer' ); ?></p>
							<p class="mb-1 leading-relaxed"><?php esc_html_e( 'Upload your backup set directly to the temp folder, then refresh.', 'missing-media-restorer' ); ?></p>
							<p class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-[10px] font-mono text-slate-700">
								<span class="dashicons dashicons-admin-network text-[13px]"></span>
								<span>/wp-content/uploads/mmr-temp/</span>
							</p>
						</div>
						<div class="rounded-2xl border mmr-success-box p-4 text-sm text-emerald-700 hidden" id="mmr-upload-hint">
							<p class="mb-1 font-medium"><?php esc_html_e( 'Files ready to match', 'missing-media-restorer' ); ?></p>
							<p class="mb-2"><?php esc_html_e( 'When you are happy with the list, move on to matching.', 'missing-media-restorer' ); ?></p>
							<button id="mmr-continue-match-btn" type="button" class="mmr-btn-primary inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-medium">
								<span><?php esc_html_e( 'Continue to match', 'missing-media-restorer' ); ?></span>
								<span class="dashicons dashicons-arrow-right-alt2 text-[13px]"></span>
							</button>
						</div>
					</div>
				</div>
			</section>

			<!-- Step 3: Match & Review -->
			<section id="mmr-step-3" class="mmr-step-container hidden">
				<div class="rounded-2xl border border-slate-200 mmr-section-bg shadow-sm">
					<div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
						<div class="flex items-center gap-2">
							<span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-600 text-xs">
								<span class="dashicons dashicons-yes text-[13px]"></span>
							</span>
							<div>
								<h2 class="text-sm font-semibold tracking-tight text-slate-900"><?php esc_html_e( 'Quick match overview', 'missing-media-restorer' ); ?></h2>
								<p class="mt-0.5 text-sm text-slate-500"><?php esc_html_e( 'We compare filenames to your missing list—no noisy controls, just a clean summary.', 'missing-media-restorer' ); ?></p>
							</div>
						</div>
						<div class="flex items-center gap-2 text-sm text-slate-400">
							<button id="mmr-back-to-upload-btn" type="button" class="mmr-btn-ghost inline-flex items-center gap-1 rounded-full px-3 py-1.5">
								<span class="dashicons dashicons-arrow-left-alt2 text-[13px]"></span>
								<span><?php esc_html_e( 'Adjust uploads', 'missing-media-restorer' ); ?></span>
							</button>
						</div>
					</div>
					<div class="px-4 py-4">
						<div id="mmr-match-summary" class="text-sm text-slate-700"></div>
						<div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
							<p class="text-sm text-slate-500"><?php esc_html_e( 'Only matched files will be processed in the next step.', 'missing-media-restorer' ); ?></p>
							<button id="mmr-continue-restore-btn" type="button" class="mmr-btn-primary inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-medium">
								<span><?php esc_html_e( 'Continue to restore', 'missing-media-restorer' ); ?></span>
								<span class="dashicons dashicons-arrow-right-alt2 text-[13px]"></span>
							</button>
						</div>
					</div>
				</div>
			</section>

			<!-- Step 4: Restore -->
			<section id="mmr-step-4" class="mmr-step-container hidden">
				<div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
					<div class="rounded-2xl border border-slate-200 mmr-section-bg shadow-sm flex flex-col">
						<div class="border-b border-slate-100 px-4 py-3">
							<div class="flex items-center gap-2">
								<span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-cyan-100 text-cyan-600 text-xs">
									<span class="dashicons dashicons-image-rotate text-[13px]"></span>
								</span>
								<div>
									<h2 class="text-sm font-semibold tracking-tight text-slate-900"><?php esc_html_e( 'Restore in calm batches', 'missing-media-restorer' ); ?></h2>
									<p class="mt-0.5 text-sm text-slate-500"><?php esc_html_e( 'We restore files in small controlled groups to keep your dashboard responsive.', 'missing-media-restorer' ); ?></p>
								</div>
							</div>
						</div>
						<div class="px-4 py-4 flex flex-col gap-3">
							<ul class="list-disc list-inside space-y-1 text-sm text-slate-600">
								<li><?php esc_html_e( 'Original folder structure is respected using your attachment metadata.', 'missing-media-restorer' ); ?></li>
								<li><?php esc_html_e( 'Thumbnails are regenerated so the media library stays consistent.', 'missing-media-restorer' ); ?></li>
								<li><?php esc_html_e( 'You can clear temporary uploads when you are done.', 'missing-media-restorer' ); ?></li>
							</ul>
							<div class="mt-2">
								<button id="mmr-restore-btn" type="button" class="mmr-btn-primary inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium">
									<span class="dashicons dashicons-image-rotate text-[13px]"></span>
									<span><?php esc_html_e( 'Start restore', 'missing-media-restorer' ); ?></span>
								</button>
							</div>
							<div class="mt-3 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] text-slate-500">
								<span class="dashicons dashicons-clock text-[13px]"></span>
								<span><?php esc_html_e( 'You can leave this tab open in the background.', 'missing-media-restorer' ); ?></span>
							</div>
						</div>
					</div>
					<div class="rounded-2xl bg-white border border-slate-200 mmr-console-bg text-slate-50 shadow-sm flex flex-col">
						<div class="border-b border-slate-800 border-opacity-60 px-4 py-3 flex items-center justify-between text-sm">
							<span class="font-medium text-slate-100"><?php esc_html_e( 'Batch progress', 'missing-media-restorer' ); ?></span>
							<button id="mmr-clear-temp-btn" type="button" class="hidden rounded-full border border-slate-700 px-2 py-1 text-[10px] font-medium text-slate-200 hover:bg-slate-800">
								<span class="dashicons dashicons-trash text-[12px]"></span>
								<span><?php esc_html_e( 'Clear temp files', 'missing-media-restorer' ); ?></span>
							</button>
						</div>
						<div id="mmr-progress-container" class="flex flex-col gap-3 px-4 py-4">
							<div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-800 bg-opacity-80">
								<div id="mmr-progress-fill" class="h-full w-0 mmr-progress-fill rounded-full transition-all"></div>
							</div>
							<p id="mmr-progress-text" class="text-sm text-slate-300"><?php esc_html_e( 'Waiting to start…', 'missing-media-restorer' ); ?></p>
							<div id="mmr-restore-output" class="mt-2 max-h-64 overflow-auto rounded-xl bg-slate-950 bg-opacity-60 p-3 text-sm font-mono leading-relaxed text-slate-200"></div>
						</div>
					</div>
				</div>
			</section>
		</div>
	</div>



	<!-- Pro Features Modal -->
	<div id="mmr-pro-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 backdrop-blur-sm">
		<div class="flex min-h-screen items-center justify-center p-4">
			<div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
				<div class="relative">
					<!-- Header -->
					<div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
						<div class="flex items-center gap-3">
							<div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-500">
								<span class="dashicons dashicons-star-filled text-white text-lg"></span>
							</div>
							<div>
								<h3 class="text-lg font-semibold text-slate-900">
									<?php echo $pro_status['is_pro'] ? esc_html__( 'Pro Features Active', 'missing-media-restorer' ) : esc_html__( 'Upgrade to Pro', 'missing-media-restorer' ); ?>
								</h3>
								<p class="text-sm text-slate-500">
									<?php echo $pro_status['is_pro'] ? esc_html__( 'All premium features unlocked', 'missing-media-restorer' ) : esc_html__( 'Unlock advanced features', 'missing-media-restorer' ); ?>
								</p>
							</div>
						</div>
						<button id="mmr-close-pro-modal" type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600">
							<span class="dashicons dashicons-no text-lg"></span>
						</button>
					</div>

					<!-- Content -->
					<div class="px-6 py-6">
						<?php if ( $pro_status['is_pro'] ) : ?>
							<!-- Pro Active Content -->
							<div class="space-y-4">
								<div class="flex items-start gap-3">
									<span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-600 mt-0.5">
										<span class="dashicons dashicons-yes text-sm"></span>
									</span>
									<div>
										<p class="font-medium text-slate-900"><?php esc_html_e( 'Smart Directory Scanner', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'Scan local directories for missing media files', 'missing-media-restorer' ); ?></p>
									</div>
								</div>

								<div class="flex items-start gap-3">
									<span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-600 mt-0.5">
										<span class="dashicons dashicons-cloud-upload text-sm"></span>
									</span>
									<div>
										<p class="font-medium text-slate-900"><?php esc_html_e( 'Bulk Upload Support', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'Upload thousands of files at once with drag & drop folders', 'missing-media-restorer' ); ?></p>
									</div>
								</div>

								<div class="flex items-start gap-3">
									<span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-600 mt-0.5">
										<span class="dashicons dashicons-admin-tools text-sm"></span>
									</span>
									<div>
										<p class="font-medium text-slate-900"><?php esc_html_e( 'Advanced Matching', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'Smart filename matching and duplicate detection', 'missing-media-restorer' ); ?></p>
									</div>
								</div>

								<div class="flex items-start gap-3">
									<span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-600 mt-0.5">
										<span class="dashicons dashicons-chart-line text-sm"></span>
									</span>
									<div>
										<p class="font-medium text-slate-900"><?php esc_html_e( 'Progress Analytics', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'Detailed reports and restoration statistics', 'missing-media-restorer' ); ?></p>
									</div>
								</div>
							</div>

							<div class="mt-6 rounded-xl bg-green-50 p-4">
								<div class="flex items-center justify-center">
									<div class="text-center">
										<span class="mmr-pro-badge"><?php echo esc_html( $pro_status['badge_text'] ); ?></span>
										<p class="text-sm text-slate-600 mt-2"><?php esc_html_e( 'Your Pro license is active and all features are available.', 'missing-media-restorer' ); ?></p>
									</div>
								</div>
							</div>
						<?php else : ?>
							<!-- Pro Upgrade Content -->
							<div class="space-y-4">
								<div class="flex items-start gap-3">
									<span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-blue-600 mt-0.5">
										<span class="dashicons dashicons-cloud-upload text-sm"></span>
									</span>
									<div>
										<p class="font-medium text-slate-900"><?php esc_html_e( 'Bulk Upload Support', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'Upload thousands of files at once with drag & drop folders', 'missing-media-restorer' ); ?></p>
									</div>
								</div>

								<div class="flex items-start gap-3">
									<span class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100 text-green-600 mt-0.5">
										<span class="dashicons dashicons-admin-tools text-sm"></span>
									</span>
									<div>
										<p class="font-medium text-slate-900"><?php esc_html_e( 'Advanced Matching', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'Smart filename matching and duplicate detection', 'missing-media-restorer' ); ?></p>
									</div>
								</div>

								<div class="flex items-start gap-3">
									<span class="flex h-6 w-6 items-center justify-center rounded-full bg-purple-100 text-purple-600 mt-0.5">
										<span class="dashicons dashicons-chart-line text-sm"></span>
									</span>
									<div>
										<p class="font-medium text-slate-900"><?php esc_html_e( 'Progress Analytics', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'Detailed reports and restoration statistics', 'missing-media-restorer' ); ?></p>
									</div>
								</div>

								<div class="flex items-start gap-3">
									<span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-amber-600 mt-0.5">
										<span class="dashicons dashicons-shield text-sm"></span>
									</span>
									<div>
										<p class="font-medium text-slate-900"><?php esc_html_e( 'Priority Support', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'Get help from our expert support team', 'missing-media-restorer' ); ?></p>
									</div>
								</div>
							</div>

							<div class="mt-6 rounded-xl bg-blue-50 p-4">
								<div class="flex items-center justify-between">
									<div>
										<p class="font-semibold text-slate-900"><?php esc_html_e( 'Starting at $29/year', 'missing-media-restorer' ); ?></p>
										<p class="text-sm text-slate-600"><?php esc_html_e( 'One-time payment, lifetime updates', 'missing-media-restorer' ); ?></p>
									</div>
									<a href="#" target="_blank" class="inline-flex items-center gap-2 rounded-full bg-blue-500 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600 hover:text-white transition-all whitespace-nowrap">
										<span><?php esc_html_e( 'Coming Soon!', 'missing-media-restorer' ); ?></span>
										<span class="dashicons dashicons-arrow-right-alt text-sm"></span>
									</a>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php
}

/**
 * Get list of files in the temporary upload directory
 *
 * @return array List of uploaded files
 */
function mmr_get_temp_files() {
	$upload_dir      = wp_upload_dir();
	$temp_upload_dir = $upload_dir['basedir'] . '/mmr-temp/';
	$files           = array();

	if ( file_exists( $temp_upload_dir ) ) {
		$file_list = glob( $temp_upload_dir . '*' );

		if ( is_array( $file_list ) ) {
			foreach ( $file_list as $file ) {
				if ( is_file( $file ) ) {
					$files[] = array(
						'name' => basename( $file ),
						'size' => filesize( $file ),
					);
				}
			}
		}
	}

	return $files;
}

/**
 * AJAX Handler: Get available uploaded files
 *
 * @since 1.0.0
 */
function mmr_ajax_get_available_files() {
	// Security check
	check_ajax_referer( 'mmr_ajax_nonce', 'nonce' );

	// Verify user capability
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}

	$available_files = mmr_get_temp_files();

	wp_send_json_success(
		array(
			'files_count' => count( $available_files ),
			'files'       => $available_files,
		)
	);
}
add_action( 'wp_ajax_mmr_get_available_files', 'mmr_ajax_get_available_files' );

/**
 * Get the WordPress uploads directory path
 *
 * @return string The uploads directory path
 */
function mmr_get_uploads_dir() {
	$upload_dir = wp_upload_dir();
	return $upload_dir['basedir'];
}

/**
 * Scan all WordPress attachments and identify missing files
 *
 * @return array Array of missing files with their metadata
 */
function mmr_scan_missing_files() {
	$args = array(
		'post_type'      => 'attachment',
		'posts_per_page' => -1,
		'post_status'    => 'inherit',
	);

	$attachments    = get_posts( $args );
	$missing_files  = array();
	$existing_files = array();
	$uploads_dir    = mmr_get_uploads_dir();

	foreach ( $attachments as $attachment ) {
		$attachment_id = $attachment->ID;
		// Get the relative path from metadata
		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );

		if ( ! $attached_file ) {
			continue;
		}

		// Full file path
		$file_path = $uploads_dir . '/' . $attached_file;
		$filename  = basename( $attached_file );

		// Extract directory path (year/month)
		$dir_path = dirname( $attached_file );

		// Check if main file exists
		if ( ! file_exists( $file_path ) ) {
			$missing_files[] = array(
				'id'        => $attachment_id,
				'filename'  => $filename,
				'path'      => $dir_path . '/',
				'full_path' => $attached_file,
				'status'    => 'missing',
			);
		} else {
			$existing_files[] = array(
				'id'        => $attachment_id,
				'filename'  => $filename,
				'path'      => $dir_path . '/',
				'full_path' => $attached_file,
				'status'    => 'exists',
			);
		}

		// Also check for missing thumbnails
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata ) && is_array( $metadata ) ) {
			// Check image sizes (thumbnails)
			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					if ( ! empty( $size_data['file'] ) ) {
						$thumb_filename  = $size_data['file'];
						$thumb_path      = $dir_path . '/' . $thumb_filename;
						$thumb_full_path = $uploads_dir . '/' . $thumb_path;

						// Check if thumbnail exists
						if ( ! file_exists( $thumb_full_path ) ) {
							$missing_files[] = array(
								'id'           => $attachment_id,
								'filename'     => $thumb_filename,
								'path'         => $dir_path . '/',
								'full_path'    => $thumb_path,
								'status'       => 'missing',
								'is_thumbnail' => true,
								'parent_file'  => $filename,
								'size_name'    => $size_name,
							);
						}
					}
				}
			}
		}
	}

	// Store missing files in transient for batch processing
	set_transient( 'mmr_missing_files', $missing_files, HOUR_IN_SECONDS * 24 );

	return array(
		'missing'  => $missing_files,
		'existing' => $existing_files,
	);
}

/**
 * AJAX Handler: Scan for missing files
 *
 * @since 1.0.0
 */
function mmr_ajax_scan_missing_files() {
	// Security check
	check_ajax_referer( 'mmr_ajax_nonce', 'nonce' );

	// Verify user capability
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}

	// Perform the scan
	$scan_results   = mmr_scan_missing_files();
	$missing_files  = $scan_results['missing'];
	$existing_files = $scan_results['existing'];

	wp_send_json_success(
		array(
			'missing_count'  => count( $missing_files ),
			'existing_count' => count( $existing_files ),
			'total_count'    => count( $missing_files ) + count( $existing_files ),
			'missing_files'  => $missing_files,
			'existing_files' => $existing_files,
			'message'        => 'Scan completed. Found ' . count( $missing_files ) . ' missing and ' . count( $existing_files ) . ' existing attachment entries.',
		)
	);
}
add_action( 'wp_ajax_mmr_scan_missing_files', 'mmr_ajax_scan_missing_files' );

/**
 * Regenerate attachment metadata and thumbnails
 *
 * @param int $attachment_id The attachment ID
 * @return bool True if successful
 */
function mmr_regenerate_attachment_metadata( $attachment_id ) {
	// Load required files for image processing
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	// Get the attachment file path
	$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( ! $file ) {
		return false;
	}

	// Generate new metadata
	$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
	if ( is_wp_error( $metadata ) || empty( $metadata ) ) {
		return false;
	}

	// Update the attachment metadata
	update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );

	return true;
}

/**
 * Match uploaded files with missing files and restore them
 *
 * @param int $batch_number The batch number to process
 * @param int $batch_size The number of files to process per batch
 * @return array Result of batch processing
 */
function mmr_process_batch_restore( $batch_number, $batch_size ) {
	$uploads_dir   = mmr_get_uploads_dir();
	$missing_files = get_transient( 'mmr_missing_files' );

	if ( ! $missing_files ) {
		return array(
			'files_processed' => 0,
			'processed_files' => array(),
			'is_complete'     => true,
			'message'         => 'No missing files to restore',
		);
	}

	// Calculate start and end indices for this batch
	$start_index = ( $batch_number - 1 ) * $batch_size;
	$end_index   = $start_index + $batch_size;
	$batch_files = array_slice( $missing_files, $start_index, $batch_size );

	$processed_files = array();
	$uploaded_dir    = wp_upload_dir();
	$temp_upload_dir = $uploaded_dir['basedir'] . '/mmr-temp/';

	// Get list of available files in temp directory for efficient matching
	$available_temp_files = mmr_get_temp_files();
	$available_filenames  = wp_list_pluck( $available_temp_files, 'name' );

	// Process each file in this batch
	foreach ( $batch_files as $missing_file ) {
		$filename       = $missing_file['filename'];
		$target_path    = $uploads_dir . '/' . $missing_file['full_path'];
		$target_dir     = dirname( $target_path );
		$status         = 'skipped';
		$message        = 'File not uploaded';
		$attachment_id  = $missing_file['id'];
		$files_restored = 0;

		// Check if this is a thumbnail - if so, skip (handled with main file)
		if ( ! empty( $missing_file['is_thumbnail'] ) ) {
			$processed_files[] = array(
				'filename' => $filename,
				'status'   => 'skipped',
				'path'     => $missing_file['full_path'],
				'message'  => 'Thumbnail (restored with main file)',
			);
			continue;
		}

		// Check if main file is available in /mmr-temp/
		$file_is_available = in_array( $filename, $available_filenames, true );

		// SKIP if main file is not available
		if ( ! $file_is_available ) {
			$processed_files[] = array(
				'filename' => $filename,
				'status'   => $status,
				'path'     => $missing_file['full_path'],
				'message'  => $message,
			);
			continue;
		}

		// File is available - restore it and its thumbnails
		$copy_success = false;

		// Create target directory if it doesn't exist
		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		// Get file info to find related thumbnails
		$file_parts = pathinfo( $filename );
		$basename   = $file_parts['filename']; // Without extension
		$extension  = isset( $file_parts['extension'] ) ? $file_parts['extension'] : '';

		// Copy the main file
		$source_file = $temp_upload_dir . $filename;
		$dest_file   = $target_dir . '/' . $filename;

		if ( copy( $source_file, $dest_file ) ) {
			$copy_success = true;
			++$files_restored;
			// Remove from temp after successful copy.
			wp_delete_file( $source_file );
		}

		// Look for and copy related thumbnail files
		// Pattern: basename-*x*.ext (e.g., image-300x300.jpg)
		if ( ! empty( $extension ) ) {
			$thumbnail_pattern = $basename . '-*.' . $extension;
			$thumbnails        = glob( $temp_upload_dir . $thumbnail_pattern );

			if ( is_array( $thumbnails ) && ! empty( $thumbnails ) ) {
				foreach ( $thumbnails as $thumb_file ) {
					$thumb_name = basename( $thumb_file );
					$thumb_dest = $target_dir . '/' . $thumb_name;

					if ( copy( $thumb_file, $thumb_dest ) ) {
						++$files_restored;
					// Remove from temp after successful copy.
					wp_delete_file( $thumb_file );
					}
				}
			}
		}

		if ( $copy_success ) {
			$status  = 'restored';
			$message = 'Successfully restored ' . $files_restored . ' file(s)';

			// Regenerate attachment metadata and thumbnails
			if ( mmr_regenerate_attachment_metadata( $attachment_id ) ) {
				$message .= ' (metadata updated)';
			}
		} else {
			$status  = 'failed';
			$message = 'Failed to copy file';
		}

		$processed_files[] = array(
			'filename' => $filename,
			'status'   => $status,
			'path'     => $missing_file['full_path'],
			'message'  => $message,
		);
	}
	$total_files = count( $missing_files );
	$is_complete = $end_index >= $total_files;

	return array(
		'files_processed' => count( $processed_files ),
		'processed_files' => $processed_files,
		'is_complete'     => $is_complete,
		'total_processed' => $end_index,
		'total_files'     => $total_files,
	);
}

/**
 * AJAX Handler: Process batch restore
 *
 * @since 1.0.0
 */
function mmr_ajax_batch_restore() {
	// Security check
	check_ajax_referer( 'mmr_ajax_nonce', 'nonce' );

	// Verify user capability
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}

	// Get batch parameters from request
	$batch_number = isset( $_POST['batch'] ) ? intval( $_POST['batch'] ) : 1;
	$batch_size   = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 20;

	// Process the batch
	$result = mmr_process_batch_restore( $batch_number, $batch_size );

	wp_send_json_success(
		array(
			'batch'           => $batch_number,
			'files_processed' => $result['files_processed'],
			'processed_files' => $result['processed_files'],
			'is_complete'     => $result['is_complete'],
			'total_processed' => $result['total_processed'],
			'total_files'     => $result['total_files'],
			'message'         => 'Batch ' . $batch_number . ' completed',
		)
	);
}
add_action( 'wp_ajax_mmr_batch_restore', 'mmr_ajax_batch_restore' );

/**
 * AJAX Handler: Upload files to temporary directory
 *
 * @since 1.0.0
 */
function mmr_ajax_upload_files() {
	// Security check
	check_ajax_referer( 'mmr_ajax_nonce', 'nonce' );

	// Verify user capability
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}

	// Check if files were uploaded
	if ( ! isset( $_FILES['files'] ) || empty( $_FILES['files'] ) ) {
		wp_send_json_error( array( 'message' => 'No files provided. Please select files to upload.' ) );
	}

	// Get upload directory info
	$uploaded_dir = wp_upload_dir();
	if ( $uploaded_dir['error'] ) {
		wp_send_json_error( array( 'message' => 'WordPress upload directory error: ' . $uploaded_dir['error'] ) );
	}

	$temp_upload_dir = $uploaded_dir['basedir'] . '/mmr-temp/';

	// Create temp directory if it doesn't exist
	if ( ! file_exists( $temp_upload_dir ) ) {
		if ( ! wp_mkdir_p( $temp_upload_dir ) ) {
			wp_send_json_error( array( 'message' => 'Failed to create temporary upload directory: ' . $temp_upload_dir ) );
		}
	}

	// Check if temp directory is writable
	if ( ! is_writable( $temp_upload_dir ) ) {
		wp_send_json_error( array( 'message' => 'Temporary upload directory is not writable: ' . $temp_upload_dir ) );
	}

	// @var array $_FILES - PHP Superglobal
	$files          = isset( $_FILES['files'] ) ? wp_unslash( $_FILES['files'] ) : array();
	$uploaded_files = array();
	$failed_files   = array();

	// Handle single file or multiple files
	$file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

	for ( $i = 0; $i < $file_count; $i++ ) {
		$name     = is_array( $files['name'] ) ? $files['name'][ $i ] : $files['name'];
		$tmp_name = is_array( $files['tmp_name'] ) ? $files['tmp_name'][ $i ] : $files['tmp_name'];
		$error    = is_array( $files['error'] ) ? $files['error'][ $i ] : $files['error'];
		$size     = is_array( $files['size'] ) ? $files['size'][ $i ] : $files['size'];

		// Skip empty uploads
		if ( empty( $name ) || $error === UPLOAD_ERR_NO_FILE ) {
			continue;
		}

		// Check for upload errors
		if ( $error !== UPLOAD_ERR_OK ) {
			$error_messages = array(
				UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize directive',
				UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE directive',
				UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
				UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
				UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
				UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension',
			);

			$error_message = isset( $error_messages[ $error ] ) ? $error_messages[ $error ] : 'Unknown upload error: ' . $error;

			$failed_files[] = array(
				'name'  => $name,
				'error' => $error_message,
			);
			continue;
		}

		// Validate file type (basic security)
		$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'mp4', 'mp3', 'zip', 'txt' );
		$file_ext      = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_ext, $allowed_types, true ) ) {
			$failed_files[] = array(
				'name'  => $name,
				'error' => 'File type not allowed: ' . $file_ext,
			);
			continue;
		}

		// Sanitize filename
		$filename = sanitize_file_name( $name );

		// If filename is empty after sanitization, use a fallback
		if ( empty( $filename ) ) {
			$filename = 'file_' . time() . '_' . $i . '.' . $file_ext;
		}

		// Check for file size (max 50MB per file)
		if ( $size > 50 * 1024 * 1024 ) {
			$failed_files[] = array(
				'name'  => $name,
				'error' => 'File too large (max 50MB): ' . round( $size / 1024 / 1024, 2 ) . 'MB',
			);
			continue;
		}

		// Move file to temp directory
		$destination = $temp_upload_dir . $filename;

		// Handle filename conflicts
		$counter = 1;
		while ( file_exists( $destination ) ) {
			$name_parts  = pathinfo( $filename );
			$new_name    = $name_parts['filename'] . '_' . $counter . '.' . $name_parts['extension'];
			$destination = $temp_upload_dir . $new_name;
			$filename    = $new_name;
			++$counter;
		}

		if ( move_uploaded_file( $tmp_name, $destination ) ) {
			$uploaded_files[] = array(
				'name'     => $filename,
				'original' => $name,
				'size'     => $size,
			);
		} else {
			$failed_files[] = array(
				'name'  => $name,
				'error' => 'Failed to move uploaded file to destination',
			);
		}
	}

	// Prepare response
	$response = array(
		'uploaded_count' => count( $uploaded_files ),
		'failed_count'   => count( $failed_files ),
		'uploaded_files' => $uploaded_files,
		'failed_files'   => $failed_files,
		'temp_dir'       => $temp_upload_dir,
		'message'        => count( $uploaded_files ) . ' file(s) uploaded successfully',
	);

	if ( count( $failed_files ) > 0 ) {
		$response['message'] .= '. ' . count( $failed_files ) . ' file(s) failed to upload.';
	}

	wp_send_json_success( $response );
}
add_action( 'wp_ajax_mmr_upload_files', 'mmr_ajax_upload_files' );

/**
 * AJAX Handler: Clear temporary files
 *
 * @since 1.0.0
 */
function mmr_ajax_clear_temp_files() {
	// Security check
	check_ajax_referer( 'mmr_ajax_nonce', 'nonce' );

	// Verify user capability
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
	}

	$uploaded_dir    = wp_upload_dir();
	$temp_upload_dir = $uploaded_dir['basedir'] . '/mmr-temp/';

	$cleared_count = 0;

	if ( file_exists( $temp_upload_dir ) ) {
		$files = glob( $temp_upload_dir . '*' );

		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
					++$cleared_count;
				}
			}
		}
	}

	wp_send_json_success(
		array(
			'cleared_count' => $cleared_count,
			'message'       => $cleared_count . ' temporary file(s) cleared',
		)
	);
}
add_action( 'wp_ajax_mmr_clear_temp_files', 'mmr_ajax_clear_temp_files' );

/**
 * Render Pro Scanner page
 */
function mmr_render_pro_scanner_page() {
	if ( ! mmr_is_pro_available() ) {
		wp_die( 'Pro version not available' );
	}
	?>
	<script>jQuery('body').addClass('mmr-pro-scanner');</script>
	<div class="wrap">
		<div class="mmr-admin max-w-[88rem] mx-auto mt-8 mb-10 px-4">
			<header class="flex flex-col gap-4 mb-4">
				<div class="flex items-center justify-between gap-4">
					<div class="flex items-center gap-3">
						<div class="h-9 w-9 rounded-xl mmr-header-icon flex items-center justify-center shadow-sm">
							<span class="dashicons dashicons-search text-base"></span>
						</div>
						<div>
							<h1 class="text-xl font-semibold tracking-tight text-slate-900">
								<?php esc_html_e( 'Pro Scanner', 'missing-media-restorer' ); ?>
								<span class="mmr-pro-badge">PRO</span>
							</h1>
							<p class="text-sm font-medium text-slate-500">
								<?php esc_html_e( 'Advanced directory scanning for missing media files.', 'missing-media-restorer' ); ?>
							</p>
						</div>
					</div>
				</div>
			</header>

			<div class="mmr-pro-scanner-container">
				<!-- Pro Scanner Interface -->
				<div class="grid gap-6 md:grid-cols-2 mb-6">
					<!-- Quick Scan -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Quick Scan', 'missing-media-restorer' ); ?></h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Directory Path', 'missing-media-restorer' ); ?></label>
								<input type="text" id="mmr-pro-scan-directory" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="/path/to/your/media" value="<?php echo esc_attr( wp_upload_dir()['basedir'] ); ?>">
							</div>
							<div class="flex items-center gap-4">
								<label class="flex items-center gap-2">
									<input type="checkbox" id="mmr-pro-recursive-scan" class="rounded text-blue-600">
									<span class="text-sm text-slate-700"><?php esc_html_e( 'Recursive scan', 'missing-media-restorer' ); ?></span>
								</label>
								<label class="flex items-center gap-2">
									<input type="checkbox" id="mmr-pro-fuzzy-matching" class="rounded text-blue-600">
									<span class="text-sm text-slate-700"><?php esc_html_e( 'Fuzzy matching', 'missing-media-restorer' ); ?></span>
								</label>
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'File Size Limit (MB)', 'missing-media-restorer' ); ?></label>
								<input type="number" id="mmr-pro-file-size-limit" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="50" min="1" max="500">
							</div>
							<button id="mmr-pro-scan-button" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e( 'Start Scan', 'missing-media-restorer' ); ?>
							</button>
						</div>
					</div>

					<!-- Deep Scan -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Deep Scan', 'missing-media-restorer' ); ?></h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Directory Path', 'missing-media-restorer' ); ?></label>
								<input type="text" id="mmr-pro-deep-scan-directory" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="/path/to/scan">
							</div>
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Include Extensions', 'missing-media-restorer' ); ?></label>
									<input type="text" id="mmr-pro-include-extensions" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="jpg,png,gif,pdf" value="jpg,jpeg,png,gif,pdf,doc,docx">
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Exclude Patterns', 'missing-media-restorer' ); ?></label>
									<input type="text" id="mmr-pro-exclude-patterns" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="cache,temp,*.tmp">
								</div>
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'File Size Limit (MB)', 'missing-media-restorer' ); ?></label>
								<input type="number" id="mmr-pro-deep-file-size-limit" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="100" min="1" max="1000">
							</div>
							<button id="mmr-pro-deep-scan-button" class="w-full bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
								<span class="dashicons dashicons-search"></span>
								<?php esc_html_e( 'Start Deep Scan', 'missing-media-restorer' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Fuzzy Matching -->
				<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-6">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Fuzzy Matching', 'missing-media-restorer' ); ?></h3>
					<div class="flex items-center gap-4">
						<div>
							<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Similarity Threshold', 'missing-media-restorer' ); ?></label>
							<input type="range" id="mmr-pro-fuzzy-threshold" class="w-48" min="0.1" max="1.0" step="0.1" value="0.7">
							<span id="mmr-pro-threshold-value" class="ml-2 text-sm text-slate-600">0.7</span>
						</div>
						<button id="mmr-pro-fuzzy-match-button" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
							<span class="dashicons dashicons-admin-tools"></span>
							<?php esc_html_e( 'Run Fuzzy Match', 'missing-media-restorer' ); ?>
						</button>
					</div>
				</div>

				<!-- Results Container -->
				<div id="mmr-pro-scan-results" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"></div>
				<div id="mmr-pro-deep-scan-results" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"></div>
				<div id="mmr-pro-fuzzy-match-results" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"></div>

				<!-- Progress Bar -->
				<div id="mmr-pro-scan-progress" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<div class="flex items-center gap-3">
						<div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
						<span class="text-sm text-slate-600"><?php esc_html_e( 'Scanning...', 'missing-media-restorer' ); ?></span>
					</div>
					<div class="mt-4 h-2 bg-slate-200 rounded-full overflow-hidden">
						<div id="mmr-pro-scan-progress-fill" class="h-full bg-blue-600 rounded-full transition-all duration-300" style="width: 0%"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render Pro Uploader page
 */
function mmr_render_pro_uploader_page() {
	if ( ! mmr_is_pro_available() ) {
		wp_die( 'Pro version not available' );
	}
	?>
	<script>jQuery('body').addClass('mmr-pro-uploader');</script>
	<div class="wrap">
		<div class="mmr-admin max-w-[88rem] mx-auto mt-8 mb-10 px-4">
			<header class="flex flex-col gap-4 mb-4">
				<div class="flex items-center justify-between gap-4">
					<div class="flex items-center gap-3">
						<div class="h-9 w-9 rounded-xl mmr-header-icon flex items-center justify-center shadow-sm">
							<span class="dashicons dashicons-cloud-upload text-base"></span>
						</div>
						<div>
							<h1 class="text-xl font-semibold tracking-tight text-slate-900">
								<?php esc_html_e( 'Pro Uploader', 'missing-media-restorer' ); ?>
								<span class="mmr-pro-badge">PRO</span>
							</h1>
							<p class="text-sm font-medium text-slate-500">
								<?php esc_html_e( 'Bulk upload and manage media files.', 'missing-media-restorer' ); ?>
							</p>
						</div>
					</div>
				</div>
			</header>

			<div class="mmr-pro-uploader-container">
				<!-- Pro Uploader Interface -->
				<div class="grid gap-6 md:grid-cols-3 mb-6">
					<!-- File Upload -->
					<div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Bulk File Upload', 'missing-media-restorer' ); ?></h3>

						<!-- Drop Zone -->
						<div id="mmr-pro-drop-zone" class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:border-blue-400 transition-colors cursor-pointer">
							<div class="flex flex-col items-center gap-3">
								<span class="dashicons dashicons-cloud-upload text-4xl text-slate-400"></span>
								<p class="text-lg font-medium text-slate-700"><?php esc_html_e( 'Drop files here or click to browse', 'missing-media-restorer' ); ?></p>
								<p class="text-sm text-slate-500"><?php esc_html_e( 'Supports drag & drop folders and multiple files', 'missing-media-restorer' ); ?></p>
							</div>
							<input type="file" id="mmr-pro-upload-files" multiple webkitdirectory class="hidden">
						</div>

						<!-- Upload Options -->
						<div class="mt-6 space-y-4">
							<div class="grid grid-cols-2 gap-4">
								<label class="flex items-center gap-2">
									<input type="checkbox" id="mmr-pro-overwrite-existing" class="rounded text-blue-600">
									<span class="text-sm text-slate-700"><?php esc_html_e( 'Overwrite existing', 'missing-media-restorer' ); ?></span>
								</label>
								<label class="flex items-center gap-2">
									<input type="checkbox" id="mmr-pro-create-thumbnails" class="rounded text-blue-600" checked>
									<span class="text-sm text-slate-700"><?php esc_html_e( 'Create thumbnails', 'missing-media-restorer' ); ?></span>
								</label>
							</div>

							<div class="flex items-center gap-4">
								<label class="text-sm font-medium text-slate-700"><?php esc_html_e( 'Batch Size:', 'missing-media-restorer' ); ?></label>
								<select id="mmr-pro-batch-size" class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
									<option value="10">10 files</option>
									<option value="25" selected>25 files</option>
									<option value="50">50 files</option>
									<option value="100">100 files</option>
								</select>
								<label class="flex items-center gap-2 ml-4">
									<input type="checkbox" id="mmr-pro-enable-chunked-upload" class="rounded text-blue-600">
									<span class="text-sm text-slate-700"><?php esc_html_e( 'Chunked upload for large files', 'missing-media-restorer' ); ?></span>
								</label>
							</div>
						</div>

						<!-- Action Buttons -->
						<div class="mt-6 flex gap-3">
							<button id="mmr-pro-select-files" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
								<span class="dashicons dashicons-media-default"></span>
								<?php esc_html_e( 'Select Files', 'missing-media-restorer' ); ?>
							</button>
							<button id="mmr-pro-bulk-upload-button" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
								<span class="dashicons dashicons-cloud-upload"></span>
								<?php esc_html_e( 'Upload All', 'missing-media-restorer' ); ?>
							</button>
						</div>
					</div>

					<!-- File List -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'File Queue', 'missing-media-restorer' ); ?></h3>
						<div id="mmr-pro-file-list" class="space-y-2 max-h-96 overflow-y-auto">
							<p class="text-sm text-slate-500 text-center py-8"><?php esc_html_e( 'No files selected', 'missing-media-restorer' ); ?></p>
						</div>
						<div class="mt-4 pt-4 border-t border-slate-200">
							<div class="flex justify-between items-center">
								<span class="text-sm text-slate-600"><?php esc_html_e( 'Total Size:', 'missing-media-restorer' ); ?></span>
								<span id="mmr-pro-total-size" class="text-sm font-medium text-slate-900">0 MB</span>
							</div>
						</div>
					</div>
				</div>

				<!-- Results Container -->
				<div id="mmr-pro-upload-results" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"></div>

				<!-- Progress Container -->
				<div id="mmr-pro-upload-progress" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Upload Progress', 'missing-media-restorer' ); ?></h3>
					<div class="space-y-4">
						<div class="h-3 bg-slate-200 rounded-full overflow-hidden">
							<div id="mmr-pro-upload-progress-fill" class="h-full bg-green-600 rounded-full transition-all duration-300" style="width: 0%"></div>
						</div>
						<div class="flex justify-between items-center text-sm">
							<span id="mmr-pro-upload-status"><?php esc_html_e( 'Preparing upload...', 'missing-media-restorer' ); ?></span>
							<span id="mmr-pro-upload-percentage">0%</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render Pro Matcher page
 */
function mmr_render_pro_matcher_page() {
	if ( ! mmr_is_pro_available() ) {
		wp_die( 'Pro version not available' );
	}
	?>
	<script>jQuery('body').addClass('mmr-pro-matcher');</script>
	<div class="wrap">
		<div class="mmr-admin max-w-[88rem] mx-auto mt-8 mb-10 px-4">
			<header class="flex flex-col gap-4 mb-4">
				<div class="flex items-center justify-between gap-4">
					<div class="flex items-center gap-3">
						<div class="h-9 w-9 rounded-xl mmr-header-icon flex items-center justify-center shadow-sm">
							<span class="dashicons dashicons-admin-tools text-base"></span>
						</div>
						<div>
							<h1 class="text-xl font-semibold tracking-tight text-slate-900">
								<?php esc_html_e( 'Pro Matcher', 'missing-media-restorer' ); ?>
								<span class="mmr-pro-badge">PRO</span>
							</h1>
							<p class="text-sm font-medium text-slate-500">
								<?php esc_html_e( 'Advanced file matching and verification.', 'missing-media-restorer' ); ?>
							</p>
						</div>
					</div>
				</div>
			</header>

			<div class="mmr-pro-matcher-container">
				<!-- Pro Matcher Interface -->
				<div class="grid gap-6 md:grid-cols-2 mb-6">
					<!-- Matching Configuration -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Matching Configuration', 'missing-media-restorer' ); ?></h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Matching Algorithms', 'missing-media-restorer' ); ?></label>
								<div class="space-y-2">
									<label class="flex items-center gap-2">
										<input type="checkbox" id="mmr-pro-exact-match" class="rounded text-blue-600" checked>
										<span class="text-sm text-slate-700"><?php esc_html_e( 'Exact Filename Match', 'missing-media-restorer' ); ?></span>
									</label>
									<label class="flex items-center gap-2">
										<input type="checkbox" id="mmr-pro-fuzzy-match" class="rounded text-blue-600">
										<span class="text-sm text-slate-700"><?php esc_html_e( 'Fuzzy Filename Match', 'missing-media-restorer' ); ?></span>
									</label>
									<label class="flex items-center gap-2">
										<input type="checkbox" id="mmr-pro-metadata-match" class="rounded text-blue-600">
										<span class="text-sm text-slate-700"><?php esc_html_e( 'Metadata Match', 'missing-media-restorer' ); ?></span>
									</label>
									<label class="flex items-center gap-2">
										<input type="checkbox" id="mmr-pro-size-match" class="rounded text-blue-600">
										<span class="text-sm text-slate-700"><?php esc_html_e( 'File Size Match', 'missing-media-restorer' ); ?></span>
									</label>
								</div>
							</div>

							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Algorithm Weights', 'missing-media-restorer' ); ?></label>
								<div class="space-y-3">
									<div>
										<div class="flex justify-between items-center mb-1">
											<span class="text-sm text-slate-600"><?php esc_html_e( 'Exact Match', 'missing-media-restorer' ); ?></span>
											<span id="mmr-pro-exact-weight" class="text-sm font-medium text-slate-900">100%</span>
										</div>
										<input type="range" id="mmr-pro-exact-weight-slider" class="w-full" min="0" max="100" value="100">
									</div>
									<div>
										<div class="flex justify-between items-center mb-1">
											<span class="text-sm text-slate-600"><?php esc_html_e( 'Fuzzy Match', 'missing-media-restorer' ); ?></span>
											<span id="mmr-pro-fuzzy-weight" class="text-sm font-medium text-slate-900">70%</span>
										</div>
										<input type="range" id="mmr-pro-fuzzy-weight-slider" class="w-full" min="0" max="100" value="70">
									</div>
									<div>
										<div class="flex justify-between items-center mb-1">
											<span class="text-sm text-slate-600"><?php esc_html_e( 'Metadata Match', 'missing-media-restorer' ); ?></span>
											<span id="mmr-pro-metadata-weight" class="text-sm font-medium text-slate-900">50%</span>
										</div>
										<input type="range" id="mmr-pro-metadata-weight-slider" class="w-full" min="0" max="100" value="50">
									</div>
									<div>
										<div class="flex justify-between items-center mb-1">
											<span class="text-sm text-slate-600"><?php esc_html_e( 'Size Match', 'missing-media-restorer' ); ?></span>
											<span id="mmr-pro-size-weight" class="text-sm font-medium text-slate-900">30%</span>
										</div>
										<input type="range" id="mmr-pro-size-weight-slider" class="w-full" min="0" max="100" value="30">
									</div>
								</div>
							</div>

							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Match Threshold', 'missing-media-restorer' ); ?></label>
									<input type="number" id="mmr-pro-match-threshold" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="80" min="1" max="100">
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Max Matches', 'missing-media-restorer' ); ?></label>
									<input type="number" id="mmr-pro-max-matches" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="10" min="1" max="100">
								</div>
							</div>

							<button id="mmr-pro-run-matcher" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
								<span class="dashicons dashicons-admin-tools"></span>
								<?php esc_html_e( 'Run Matcher', 'missing-media-restorer' ); ?>
							</button>
						</div>
					</div>

					<!-- Match Verification -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Match Verification', 'missing-media-restorer' ); ?></h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Missing File Path', 'missing-media-restorer' ); ?></label>
								<input type="text" id="mmr-pro-missing-file-path" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="/path/to/missing/file.jpg">
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Potential Match Path', 'missing-media-restorer' ); ?></label>
								<input type="text" id="mmr-pro-match-file-path" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="/path/to/potential/match.jpg">
							</div>
							<div class="grid grid-cols-2 gap-4">
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Similarity Score', 'missing-media-restorer' ); ?></label>
									<input type="number" id="mmr-pro-similarity-score" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="0" min="0" max="100" step="0.1" readonly>
								</div>
								<div>
									<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Confidence Level', 'missing-media-restorer' ); ?></label>
									<select id="mmr-pro-confidence-level" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
										<option value="low"><?php esc_html_e( 'Low', 'missing-media-restorer' ); ?></option>
										<option value="medium"><?php esc_html_e( 'Medium', 'missing-media-restorer' ); ?></option>
										<option value="high"><?php esc_html_e( 'High', 'missing-media-restorer' ); ?></option>
									</select>
								</div>
							</div>
							<div class="flex gap-3">
								<button id="mmr-pro-verify-match" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e( 'Verify Match', 'missing-media-restorer' ); ?>
								</button>
								<button id="mmr-pro-accept-match" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Accept Match', 'missing-media-restorer' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Results Container -->
				<div id="mmr-pro-match-results" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Match Results', 'missing-media-restorer' ); ?></h3>
					<div id="mmr-pro-match-results-content"></div>
				</div>

				<!-- Verification Results -->
				<div id="mmr-pro-verification-results" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Verification Results', 'missing-media-restorer' ); ?></h3>
					<div id="mmr-pro-verification-content"></div>
				</div>

				<!-- Progress Container -->
				<div id="mmr-pro-match-progress" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Matching Progress', 'missing-media-restorer' ); ?></h3>
					<div class="space-y-4">
						<div class="h-3 bg-slate-200 rounded-full overflow-hidden">
							<div id="mmr-pro-match-progress-fill" class="h-full bg-blue-600 rounded-full transition-all duration-300" style="width: 0%"></div>
						</div>
						<div class="flex justify-between items-center text-sm">
							<span id="mmr-pro-match-status"><?php esc_html_e( 'Preparing match...', 'missing-media-restorer' ); ?></span>
							<span id="mmr-pro-match-percentage">0%</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render Pro Analytics page
 */
function mmr_render_pro_analytics_page() {
	if ( ! mmr_is_pro_available() ) {
		wp_die( 'Pro version not available' );
	}
	?>
	<script>jQuery('body').addClass('mmr-pro-analytics');</script>
	<div class="wrap">
		<div class="mmr-admin max-w-[88rem] mx-auto mt-8 mb-10 px-4">
			<header class="flex flex-col gap-4 mb-4">
				<div class="flex items-center justify-between gap-4">
					<div class="flex items-center gap-3">
						<div class="h-9 w-9 rounded-xl mmr-header-icon flex items-center justify-center shadow-sm">
							<span class="dashicons dashicons-chart-line text-base"></span>
						</div>
						<div>
							<h1 class="text-xl font-semibold tracking-tight text-slate-900">
								<?php esc_html_e( 'Pro Analytics', 'missing-media-restorer' ); ?>
								<span class="mmr-pro-badge">PRO</span>
							</h1>
							<p class="text-sm font-medium text-slate-500">
								<?php esc_html_e( 'Detailed reports and restoration statistics.', 'missing-media-restorer' ); ?>
							</p>
						</div>
					</div>
				</div>
			</header>

			<div class="mmr-pro-analytics-container">
				<!-- Pro Analytics Interface -->
				<div class="grid gap-6 md:grid-cols-3 mb-6">
					<!-- Statistics Cards -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<div class="flex items-center justify-between mb-2">
							<h3 class="text-sm font-medium text-slate-600"><?php esc_html_e( 'Total Files Processed', 'missing-media-restorer' ); ?></h3>
							<span class="dashicons dashicons-media-default text-blue-500"></span>
						</div>
						<p id="mmr-pro-total-files" class="text-2xl font-bold text-slate-900">0</p>
						<p class="text-xs text-slate-500 mt-1"><?php esc_html_e( 'All time', 'missing-media-restorer' ); ?></p>
					</div>

					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<div class="flex items-center justify-between mb-2">
							<h3 class="text-sm font-medium text-slate-600"><?php esc_html_e( 'Successful Restores', 'missing-media-restorer' ); ?></h3>
							<span class="dashicons dashicons-yes text-green-500"></span>
						</div>
						<p id="mmr-pro-successful-restore" class="text-2xl font-bold text-slate-900">0</p>
						<p class="text-xs text-slate-500 mt-1"><?php esc_html_e( 'All time', 'missing-media-restorer' ); ?></p>
					</div>

					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<div class="flex items-center justify-between mb-2">
							<h3 class="text-sm font-medium text-slate-600"><?php esc_html_e( 'Success Rate', 'missing-media-restorer' ); ?></h3>
							<span class="dashicons dashicons-chart-line text-purple-500"></span>
						</div>
						<p id="mmr-pro-success-rate" class="text-2xl font-bold text-slate-900">0%</p>
						<p class="text-xs text-slate-500 mt-1"><?php esc_html_e( 'Last 30 days', 'missing-media-restorer' ); ?></p>
					</div>
				</div>

				<!-- Charts Section -->
				<div class="grid gap-6 md:grid-cols-2 mb-6">
					<!-- Restoration Activity Chart -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Restoration Activity', 'missing-media-restorer' ); ?></h3>
						<div class="mb-4">
							<select id="mmr-pro-activity-period" class="px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
								<option value="7"><?php esc_html_e( 'Last 7 days', 'missing-media-restorer' ); ?></option>
								<option value="30" selected><?php esc_html_e( 'Last 30 days', 'missing-media-restorer' ); ?></option>
								<option value="90"><?php esc_html_e( 'Last 90 days', 'missing-media-restorer' ); ?></option>
								<option value="365"><?php esc_html_e( 'Last year', 'missing-media-restorer' ); ?></option>
							</select>
						</div>
						<div id="mmr-pro-activity-chart" class="h-64 flex items-center justify-center bg-slate-50 rounded-lg">
							<p class="text-sm text-slate-500"><?php esc_html_e( 'Chart will be displayed here', 'missing-media-restorer' ); ?></p>
						</div>
					</div>

					<!-- File Type Distribution -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'File Type Distribution', 'missing-media-restorer' ); ?></h3>
						<div id="mmr-pro-file-type-chart" class="h-64 flex items-center justify-center bg-slate-50 rounded-lg">
							<p class="text-sm text-slate-500"><?php esc_html_e( 'Chart will be displayed here', 'missing-media-restorer' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Reports Section -->
				<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-6">
					<div class="flex items-center justify-between mb-4">
						<h3 class="text-lg font-semibold text-slate-900"><?php esc_html_e( 'Reports & Export', 'missing-media-restorer' ); ?></h3>
						<div class="flex gap-2">
							<button id="mmr-pro-generate-report" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Generate Report', 'missing-media-restorer' ); ?>
							</button>
							<button id="mmr-pro-export-data" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export Data', 'missing-media-restorer' ); ?>
							</button>
						</div>
					</div>

					<div class="grid gap-4 md:grid-cols-2">
						<div>
							<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Report Type', 'missing-media-restorer' ); ?></label>
							<select id="mmr-pro-report-type" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
								<option value="summary"><?php esc_html_e( 'Summary Report', 'missing-media-restorer' ); ?></option>
								<option value="detailed"><?php esc_html_e( 'Detailed Report', 'missing-media-restorer' ); ?></option>
								<option value="errors"><?php esc_html_e( 'Error Report', 'missing-media-restorer' ); ?></option>
								<option value="performance"><?php esc_html_e( 'Performance Report', 'missing-media-restorer' ); ?></option>
							</select>
						</div>
						<div>
							<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Export Format', 'missing-media-restorer' ); ?></label>
							<select id="mmr-pro-export-format" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
								<option value="csv">CSV</option>
								<option value="json">JSON</option>
								<option value="pdf">PDF</option>
								<option value="xlsx">Excel</option>
							</select>
						</div>
					</div>

					<div class="mt-4">
						<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Date Range', 'missing-media-restorer' ); ?></label>
						<div class="grid gap-4 md:grid-cols-2">
							<div>
								<label class="block text-xs text-slate-500 mb-1"><?php esc_html_e( 'From', 'missing-media-restorer' ); ?></label>
								<input type="date" id="mmr-pro-date-from" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
							</div>
							<div>
								<label class="block text-xs text-slate-500 mb-1"><?php esc_html_e( 'To', 'missing-media-restorer' ); ?></label>
								<input type="date" id="mmr-pro-date-to" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
							</div>
						</div>
					</div>
				</div>

				<!-- Recent Activity -->
				<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Recent Activity', 'missing-media-restorer' ); ?></h3>
					<div id="mmr-pro-recent-activity" class="space-y-3">
						<div class="text-center py-8 text-sm text-slate-500">
							<p><?php esc_html_e( 'No recent activity to display', 'missing-media-restorer' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Results Container -->
				<div id="mmr-pro-analytics-results" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Analytics Results', 'missing-media-restorer' ); ?></h3>
					<div id="mmr-pro-analytics-content"></div>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render Pro Support page
 */
function mmr_render_pro_support_page() {
	if ( ! mmr_is_pro_available() ) {
		wp_die( 'Pro version not available' );
	}
	?>
	<script>jQuery('body').addClass('mmr-pro-support');</script>
	<div class="wrap">
		<div class="mmr-admin max-w-[88rem] mx-auto mt-8 mb-10 px-4">
			<header class="flex flex-col gap-4 mb-4">
				<div class="flex items-center justify-between gap-4">
					<div class="flex items-center gap-3">
						<div class="h-9 w-9 rounded-xl mmr-header-icon flex items-center justify-center shadow-sm">
							<span class="dashicons dashicons-sos text-base"></span>
						</div>
						<div>
							<h1 class="text-xl font-semibold tracking-tight text-slate-900">
								<?php esc_html_e( 'Pro Support', 'missing-media-restorer' ); ?>
								<span class="mmr-pro-badge">PRO</span>
							</h1>
							<p class="text-sm font-medium text-slate-500">
								<?php esc_html_e( 'Priority support and knowledge base.', 'missing-media-restorer' ); ?>
							</p>
						</div>
					</div>
				</div>
			</header>

			<div class="mmr-pro-support-container">
				<!-- Pro Support Interface -->
				<div class="grid gap-6 md:grid-cols-2 mb-6">
					<!-- Support Tickets -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Support Tickets', 'missing-media-restorer' ); ?></h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Ticket Subject', 'missing-media-restorer' ); ?></label>
								<input type="text" id="mmr-pro-ticket-subject" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="<?php esc_attr_e( 'Enter ticket subject', 'missing-media-restorer' ); ?>">
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Category', 'missing-media-restorer' ); ?></label>
								<select id="mmr-pro-ticket-category" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
									<option value="general"><?php esc_html_e( 'General Inquiry', 'missing-media-restorer' ); ?></option>
									<option value="technical"><?php esc_html_e( 'Technical Issue', 'missing-media-restorer' ); ?></option>
									<option value="feature"><?php esc_html_e( 'Feature Request', 'missing-media-restorer' ); ?></option>
									<option value="bug"><?php esc_html_e( 'Bug Report', 'missing-media-restorer' ); ?></option>
								</select>
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Priority', 'missing-media-restorer' ); ?></label>
								<select id="mmr-pro-ticket-priority" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
									<option value="low"><?php esc_html_e( 'Low', 'missing-media-restorer' ); ?></option>
									<option value="medium"><?php esc_html_e( 'Medium', 'missing-media-restorer' ); ?></option>
									<option value="high"><?php esc_html_e( 'High', 'missing-media-restorer' ); ?></option>
									<option value="urgent"><?php esc_html_e( 'Urgent', 'missing-media-restorer' ); ?></option>
								</select>
							</div>
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Description', 'missing-media-restorer' ); ?></label>
								<textarea id="mmr-pro-ticket-description" rows="4" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="<?php esc_attr_e( 'Describe your issue or question', 'missing-media-restorer' ); ?>"></textarea>
							</div>
							<button id="mmr-pro-submit-ticket" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
								<span class="dashicons dashicons-email-alt"></span>
								<?php esc_html_e( 'Submit Ticket', 'missing-media-restorer' ); ?>
							</button>
						</div>
					</div>

					<!-- Knowledge Base -->
					<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
						<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Knowledge Base', 'missing-media-restorer' ); ?></h3>
						<div class="space-y-4">
							<div>
								<label class="block text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Search Articles', 'missing-media-restorer' ); ?></label>
								<div class="relative">
									<input type="text" id="mmr-pro-kb-search" class="w-full px-3 py-2 pr-10 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="<?php esc_attr_e( 'Search knowledge base...', 'missing-media-restorer' ); ?>">
									<button id="mmr-pro-kb-search-btn" class="absolute right-2 top-2.5 text-slate-400 hover:text-slate-600">
										<span class="dashicons dashicons-search"></span>
									</button>
								</div>
							</div>
							<div id="mmr-pro-kb-categories" class="space-y-2">
								<h4 class="text-sm font-medium text-slate-700"><?php esc_html_e( 'Popular Categories', 'missing-media-restorer' ); ?></h4>
								<div class="space-y-1">
									<a href="#" class="block text-sm text-blue-600 hover:text-blue-800"><?php esc_html_e( 'Getting Started', 'missing-media-restorer' ); ?></a>
									<a href="#" class="block text-sm text-blue-600 hover:text-blue-800"><?php esc_html_e( 'Troubleshooting', 'missing-media-restorer' ); ?></a>
									<a href="#" class="block text-sm text-blue-600 hover:text-blue-800"><?php esc_html_e( 'Advanced Features', 'missing-media-restorer' ); ?></a>
									<a href="#" class="block text-sm text-blue-600 hover:text-blue-800"><?php esc_html_e( 'Best Practices', 'missing-media-restorer' ); ?></a>
								</div>
							</div>
							<div id="mmr-pro-kb-results" class="hidden space-y-2">
								<h4 class="text-sm font-medium text-slate-700"><?php esc_html_e( 'Search Results', 'missing-media-restorer' ); ?></h4>
								<div id="mmr-pro-kb-results-list" class="space-y-2"></div>
							</div>
						</div>
					</div>
				</div>

				<!-- Diagnostic Tools -->
				<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-6">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Diagnostic Tools', 'missing-media-restorer' ); ?></h3>
					<div class="grid gap-4 md:grid-cols-3">
						<div>
							<h4 class="text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'System Check', 'missing-media-restorer' ); ?></h4>
							<button id="mmr-pro-system-check" class="w-full bg-slate-600 text-white px-4 py-2 rounded-lg hover:bg-slate-700 transition-colors text-sm">
								<span class="dashicons dashicons-hammer"></span>
								<?php esc_html_e( 'Run System Check', 'missing-media-restorer' ); ?>
							</button>
						</div>
						<div>
							<h4 class="text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'File Permissions', 'missing-media-restorer' ); ?></h4>
							<button id="mmr-pro-permissions-check" class="w-full bg-slate-600 text-white px-4 py-2 rounded-lg hover:bg-slate-700 transition-colors text-sm">
								<span class="dashicons dashicons-lock"></span>
								<?php esc_html_e( 'Check Permissions', 'missing-media-restorer' ); ?>
							</button>
						</div>
						<div>
							<h4 class="text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Export Logs', 'missing-media-restorer' ); ?></h4>
							<button id="mmr-pro-export-logs" class="w-full bg-slate-600 text-white px-4 py-2 rounded-lg hover:bg-slate-700 transition-colors text-sm">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export System Logs', 'missing-media-restorer' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Recent Tickets -->
				<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm mb-6">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Recent Tickets', 'missing-media-restorer' ); ?></h3>
					<div id="mmr-pro-recent-tickets" class="space-y-3">
						<div class="text-center py-8 text-sm text-slate-500">
							<p><?php esc_html_e( 'No recent tickets to display', 'missing-media-restorer' ); ?></p>
						</div>
					</div>
				</div>

				<!-- System Information -->
				<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'System Information', 'missing-media-restorer' ); ?></h3>
					<div id="mmr-pro-system-info" class="grid gap-4 md:grid-cols-2">
						<div>
							<h4 class="text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'WordPress Environment', 'missing-media-restorer' ); ?></h4>
							<div class="space-y-1 text-sm">
								<div class="flex justify-between">
									<span class="text-slate-600"><?php esc_html_e( 'Version:', 'missing-media-restorer' ); ?></span>
									<span id="mmr-pro-wp-version" class="text-slate-900">-</span>
								</div>
								<div class="flex justify-between">
									<span class="text-slate-600"><?php esc_html_e( 'Multisite:', 'missing-media-restorer' ); ?></span>
									<span id="mmr-pro-multisite" class="text-slate-900">-</span>
								</div>
								<div class="flex justify-between">
									<span class="text-slate-600"><?php esc_html_e( 'Memory Limit:', 'missing-media-restorer' ); ?></span>
									<span id="mmr-pro-memory-limit" class="text-slate-900">-</span>
								</div>
							</div>
						</div>
						<div>
							<h4 class="text-sm font-medium text-slate-700 mb-2"><?php esc_html_e( 'Plugin Information', 'missing-media-restorer' ); ?></h4>
							<div class="space-y-1 text-sm">
								<div class="flex justify-between">
									<span class="text-slate-600"><?php esc_html_e( 'Version:', 'missing-media-restorer' ); ?></span>
									<span id="mmr-pro-plugin-version" class="text-slate-900"><?php echo esc_html( MMR_PLUGIN_VERSION ); ?></span>
								</div>
								<div class="flex justify-between">
									<span class="text-slate-600"><?php esc_html_e( 'Pro Status:', 'missing-media-restorer' ); ?></span>
									<span id="mmr-pro-pro-status" class="text-slate-900"><?php echo mmr_is_pro_available() ? esc_html__( 'Active', 'missing-media-restorer' ) : esc_html__( 'Inactive', 'missing-media-restorer' ); ?></span>
								</div>
								<div class="flex justify-between">
									<span class="text-slate-600"><?php esc_html_e( 'License Key:', 'missing-media-restorer' ); ?></span>
									<span id="mmr-pro-license-key" class="text-slate-900">-</span>
								</div>
							</div>
						</div>
					</div>
					<div class="mt-4">
						<button id="mmr-pro-refresh-system-info" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'Refresh System Info', 'missing-media-restorer' ); ?>
						</button>
					</div>
				</div>

				<!-- Results Container -->
				<div id="mmr-pro-support-results" class="hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
					<h3 class="text-lg font-semibold text-slate-900 mb-4"><?php esc_html_e( 'Support Results', 'missing-media-restorer' ); ?></h3>
					<div id="mmr-pro-support-content"></div>
				</div>
			</div>
		</div>
	</div>
	<?php
}
