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

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'MMR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MMR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MMR_PLUGIN_VERSION', '1.0.0' );

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

	// Enqueue admin stylesheet
	wp_enqueue_style(
		'mmr-admin-css',
		MMR_PLUGIN_URL . 'assets/css/mmr-admin.css',
		array(),
		MMR_PLUGIN_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'mmr_enqueue_admin_assets' );

/**
 * Render the admin page HTML structure
 */
function mmr_render_admin_page() {
	?>
	<div class="wrap">
		<h1>Missing Media Restorer</h1>
		<p>Scan for missing WordPress media files and restore them in batches. This tool prevents timeouts by processing files in controlled batches.</p>

		<!-- Scan Area -->
		<div id="mmr-scan-area" class="mmr-section">
			<h2>Step 1: Scan for Missing Files</h2>
			<p>Click the button below to scan your WordPress media library and identify missing files. This will check all attachment entries in your database against physically existing files.</p>
			<button id="mmr-scan-btn" class="button button-primary">Start Scan</button>
			<div id="mmr-scan-results" style="display: none; margin-top: 20px;">
				<h3>Scan Results</h3>
				<div id="mmr-scan-output"></div>
			</div>
		</div>

		<!-- Upload Area -->
		<div id="mmr-upload-area" class="mmr-section" style="margin-top: 40px;">
			<h2>Step 2: Upload Backup Files</h2>
			<p>You can upload files in two ways:</p>
			<ol>
				<li><strong>Via Web Browser:</strong> Drag and drop or select files below</li>
				<li><strong>Via FTP/File System:</strong> Upload large files directly to <code>/wp-content/uploads/mmr-temp/</code> folder on your server, then click "Refresh Files List" below</li>
			</ol>

			<div style="margin-top: 15px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa; border-radius: 4px;">
				<p style="margin: 0; font-size: 13px; color: #0073aa;">
					<strong>💡 Tip:</strong> For large files (2GB+), upload directly to the server via FTP to avoid browser timeouts:<br>
					<code style="background: white; padding: 3px 6px; border-radius: 3px;">/wp-content/uploads/mmr-temp/</code>
				</p>
			</div>

			<div id="mmr-upload-dropzone" class="mmr-dropzone">
				<p>Drop files or folders here or click to browse</p>
				<input type="file" id="mmr-file-input" multiple webkitdirectory style="display: none;">
				<input type="file" id="mmr-files-input" multiple style="display: none;">
			</div>
			<div style="margin-top: 10px; text-align: center;">
				<button id="mmr-select-files-btn" class="button">Select Individual Files</button>
				<button id="mmr-select-folder-btn" class="button">Select Entire Folder</button>
				<button id="mmr-refresh-files-btn" class="button" style="background: #0073aa; color: white; border-color: #0073aa;">🔄 Refresh Files List</button>
			</div>
			<div id="mmr-upload-list" style="margin-top: 20px;"></div>
		</div>

		<!-- Progress Tracker -->
		<div id="mmr-progress-tracker" class="mmr-section" style="margin-top: 40px;">
			<h2>Step 3: Batch Restore</h2>
			<p>Restore missing files in controlled batches. This process will:</p>
			<ul>
				<li>Match your uploaded files with missing entries</li>
				<li>Create necessary year/month folders</li>
				<li>Move files to their correct locations</li>
				<li>Handle each batch safely without timeouts</li>
			</ul>
			<button id="mmr-restore-btn" class="button button-primary" disabled>Start Batch Restore</button>

			<div id="mmr-progress-container" style="display: none; margin-top: 20px;">
				<div class="mmr-progress-bar">
					<div id="mmr-progress-fill" class="mmr-progress-fill"></div>
				</div>
				<p id="mmr-progress-text">Processing...</p>
				<div id="mmr-restore-output" style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin-top: 15px; max-height: 400px; overflow-y: auto;"></div>
				<button id="mmr-clear-temp-btn" class="button" style="margin-top: 15px; display: none;">Clear Temporary Files</button>
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
						$thumb_filename = $size_data['file'];
						$thumb_path     = $dir_path . '/' . $thumb_filename;
						$thumb_full_path = $uploads_dir . '/' . $thumb_path;

						// Check if thumbnail exists
						if ( ! file_exists( $thumb_full_path ) ) {
							$missing_files[] = array(
								'id'        => $attachment_id,
								'filename'  => $thumb_filename,
								'path'      => $dir_path . '/',
								'full_path' => $thumb_path,
								'status'    => 'missing',
								'is_thumbnail' => true,
								'parent_file' => $filename,
								'size_name'   => $size_name,
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
		'missing' => $missing_files,
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
	$scan_results = mmr_scan_missing_files();
	$missing_files = $scan_results['missing'];
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
		$filename    = $missing_file['filename'];
		$target_path = $uploads_dir . '/' . $missing_file['full_path'];
		$target_dir  = dirname( $target_path );
		$status      = 'skipped';
		$message     = 'File not uploaded';
		$attachment_id = $missing_file['id'];
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
		$file_parts    = pathinfo( $filename );
		$basename      = $file_parts['filename']; // Without extension
		$extension     = isset( $file_parts['extension'] ) ? $file_parts['extension'] : '';

		// Copy the main file
		$source_file = $temp_upload_dir . $filename;
		$dest_file   = $target_dir . '/' . $filename;

		if ( copy( $source_file, $dest_file ) ) {
			$copy_success = true;
			$files_restored++;
			// Remove from temp after successful copy
			@unlink( $source_file );
		}

		// Look for and copy related thumbnail files
		// Pattern: basename-*x*.ext (e.g., image-300x300.jpg)
		if ( ! empty( $extension ) ) {
			$thumbnail_pattern = $basename . '-*.' . $extension;
			$thumbnails = glob( $temp_upload_dir . $thumbnail_pattern );

			if ( is_array( $thumbnails ) && ! empty( $thumbnails ) ) {
				foreach ( $thumbnails as $thumb_file ) {
					$thumb_name = basename( $thumb_file );
					$thumb_dest = $target_dir . '/' . $thumb_name;

					if ( copy( $thumb_file, $thumb_dest ) ) {
						$files_restored++;
						// Remove from temp after successful copy
						@unlink( $thumb_file );
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
			$counter++;
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
					if ( @unlink( $file ) ) {
						++$cleared_count;
					}
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
