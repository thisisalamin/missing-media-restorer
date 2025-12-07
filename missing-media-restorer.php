<?php
/**
 * Plugin Name: Missing Media Restorer
 * Description: Restores missing WordPress media files in batches to prevent timeouts and server overload
 * Plugin URI: https://github.com/thisisalamin/missing-media-restorer
 * Version: 1.0.0
 * Author: REVENTOR
 * Author URI: https://reventor.eu
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
define( 'MMR_PLUGIN_VERSION', '1.0.3' );

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
	add_menu_page(
		'Media Restorer',           // Page title
		'Media Restorer',           // Menu title
		'manage_options',           // Capability required
		'missing-media-restorer',   // Menu slug
		'mmr_render_admin_page',    // Function to output page content
		'dashicons-format-image',   // Icon
		25                          // Position
	);
}
add_action( 'admin_menu', 'mmr_register_admin_menu' );

/**
 * Enqueue admin scripts and styles for the plugin page
 */
function mmr_enqueue_admin_assets( $hook ) {
	// Only enqueue on our plugin's admin page
	if ( 'toplevel_page_missing-media-restorer' !== $hook ) {
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

	// Enqueue Dashicons for admin icons
	wp_enqueue_style( 'dashicons' );
}
add_action( 'admin_enqueue_scripts', 'mmr_enqueue_admin_assets' );

/**
 * Render the admin page HTML structure
 */
function mmr_render_admin_page() {
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
						<button id="mmr-btn-pro" type="button" class="mmr-btn-pro inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-sm font-medium">
							<span class="dashicons dashicons-star-filled text-[13px]"></span>
							<span><?php esc_html_e( 'Upgrade to Pro', 'missing-media-restorer' ); ?></span>
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
								<h3 class="text-lg font-semibold text-slate-900"><?php esc_html_e( 'Upgrade to Pro', 'missing-media-restorer' ); ?></h3>
								<p class="text-sm text-slate-500"><?php esc_html_e( 'Unlock advanced features', 'missing-media-restorer' ); ?></p>
							</div>
						</div>
						<button id="mmr-close-pro-modal" type="button" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600">
							<span class="dashicons dashicons-no text-lg"></span>
						</button>
					</div>

					<!-- Content -->
					<div class="px-6 py-6">
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
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php
}

/**
 * Sanitize files array from $_FILES
 *
 * @param array $files The files array to sanitize
 * @return array The sanitized files array
 */
function mmr_sanitize_files_array( $files ) {
	if ( ! is_array( $files ) ) {
		return array();
	}

	$sanitized = array();
	foreach ( $files as $key => $value ) {
		if ( is_array( $value ) ) {
			$sanitized[ $key ] = array();
			foreach ( $value as $index => $item ) {
				$sanitized[ $key ][ $index ] = is_string( $item ) ? sanitize_text_field( $item ) : $item;
			}
		} else {
			$sanitized[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}
	}

	return $sanitized;
}

/**
 * Get list of files in the temporary upload directory
 *
 * @return array List of uploaded files
 */
function mmr_get_temp_files() {
	// Initialize WP_Filesystem
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	$upload_dir      = wp_upload_dir();
	$temp_upload_dir = $upload_dir['basedir'] . '/mmr-temp/';
	$files           = array();

	if ( $wp_filesystem->exists( $temp_upload_dir ) ) {
		$file_list = $wp_filesystem->dirlist( $temp_upload_dir );

		if ( is_array( $file_list ) ) {
			foreach ( $file_list as $filename => $fileinfo ) {
				if ( 'f' === $fileinfo['type'] ) { // It's a file
					$files[] = array(
						'name' => $filename,
						'size' => $fileinfo['size'],
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

		// Initialize WP_Filesystem if not already initialized
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Check if main file exists
		if ( ! $wp_filesystem->exists( $file_path ) ) {
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
						if ( ! $wp_filesystem->exists( $thumb_full_path ) ) {
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

		// Initialize WP_Filesystem if not already initialized
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Create target directory if it doesn't exist
		if ( ! $wp_filesystem->exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		// Get file info to find related thumbnails
		$file_parts = pathinfo( $filename );
		$basename   = $file_parts['filename']; // Without extension
		$extension  = isset( $file_parts['extension'] ) ? $file_parts['extension'] : '';

		// Copy the main file
		$source_file = $temp_upload_dir . $filename;
		$dest_file   = $target_dir . '/' . $filename;

		if ( $wp_filesystem->copy( $source_file, $dest_file ) ) {
			$copy_success = true;
			++$files_restored;
			// Remove from temp after successful copy.
			$wp_filesystem->delete( $source_file );
		}

		// Look for and copy related thumbnail files
		// Pattern: basename-*x*.ext (e.g., image-300x300.jpg)
		if ( ! empty( $extension ) ) {
			$thumbnail_pattern = $basename . '-*.' . $extension;
			$temp_files        = $wp_filesystem->dirlist( $temp_upload_dir );
			$thumbnails        = array();

			if ( is_array( $temp_files ) ) {
				foreach ( $temp_files as $temp_filename => $fileinfo ) {
					if ( 'f' === $fileinfo['type'] && preg_match( '/^' . preg_quote( $basename, '/' ) . '-.*\.' . preg_quote( $extension, '/' ) . '$/', $temp_filename ) ) {
						$thumbnails[] = $temp_upload_dir . $temp_filename;
					}
				}
			}

			if ( ! empty( $thumbnails ) ) {
				foreach ( $thumbnails as $thumb_file ) {
					$thumb_name = basename( $thumb_file );
					$thumb_dest = $target_dir . '/' . $thumb_name;

					if ( $wp_filesystem->copy( $thumb_file, $thumb_dest ) ) {
						++$files_restored;
						// Remove from temp after successful copy.
						$wp_filesystem->delete( $thumb_file );
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
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below with wp_unslash and mmr_sanitize_files_array
	if ( ! isset( $_FILES['files'] ) || empty( $_FILES['files'] ) ) {
		// phpcs:enable
		wp_send_json_error( array( 'message' => 'No files provided. Please select files to upload.' ) );
	}
	// phpcs:enable

	// Initialize WP_Filesystem
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	// Get upload directory info
	$uploaded_dir = wp_upload_dir();
	if ( $uploaded_dir['error'] ) {
		wp_send_json_error( array( 'message' => 'WordPress upload directory error: ' . $uploaded_dir['error'] ) );
	}

	$temp_upload_dir = $uploaded_dir['basedir'] . '/mmr-temp/';

	// Create temp directory if it doesn't exist
	if ( ! $wp_filesystem->exists( $temp_upload_dir ) ) {
		if ( ! wp_mkdir_p( $temp_upload_dir ) ) {
			wp_send_json_error( array( 'message' => 'Failed to create temporary upload directory: ' . $temp_upload_dir ) );
		}
	}

	// Check if temp directory is writable using WP_Filesystem
	if ( ! $wp_filesystem->is_writable( $temp_upload_dir ) ) {
		wp_send_json_error( array( 'message' => 'Temporary upload directory is not writable: ' . $temp_upload_dir ) );
	}

	// Sanitize $_FILES input
	// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately with wp_unslash and mmr_sanitize_files_array
	$files = isset( $_FILES['files'] ) ? wp_unslash( $_FILES['files'] ) : array();
	// phpcs:enable
	$files          = mmr_sanitize_files_array( $files );
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
		while ( $wp_filesystem->exists( $destination ) ) {
			$name_parts  = pathinfo( $filename );
			$new_name    = $name_parts['filename'] . '_' . $counter . '.' . $name_parts['extension'];
			$destination = $temp_upload_dir . $new_name;
			$filename    = $new_name;
			++$counter;
		}

		// Use WP_Filesystem to move the uploaded file
		if ( $wp_filesystem->move( $tmp_name, $destination ) ) {
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

	// Initialize WP_Filesystem
	global $wp_filesystem;
	if ( ! $wp_filesystem ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	$uploaded_dir    = wp_upload_dir();
	$temp_upload_dir = $uploaded_dir['basedir'] . '/mmr-temp/';

	$cleared_count = 0;

	if ( $wp_filesystem->exists( $temp_upload_dir ) ) {
		$files = $wp_filesystem->dirlist( $temp_upload_dir );

		if ( is_array( $files ) ) {
			foreach ( $files as $filename => $fileinfo ) {
				if ( 'f' === $fileinfo['type'] ) { // It's a file
					$file_path = $temp_upload_dir . $filename;
					$wp_filesystem->delete( $file_path );
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
