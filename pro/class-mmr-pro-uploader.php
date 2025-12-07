<?php
/**
 * MMR Pro Uploader - Advanced Upload Features
 *
 * @package MissingMediaRestorerPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MMR_Pro_Uploader
 */
class MMR_Pro_Uploader {

	/**
	 * Supported MIME types for validation
	 *
	 * @var array
	 */
	private $allowed_mime_types = array();

	/**
	 * Initialize the pro uploader
	 */
	public function __construct() {
		$this->allowed_mime_types = get_allowed_mime_types();

		add_action( 'wp_ajax_mmr_pro_bulk_upload', array( $this, 'ajax_bulk_upload' ) );
		add_action( 'wp_ajax_mmr_pro_selective_upload', array( $this, 'ajax_selective_upload' ) );
		add_action( 'wp_ajax_mmr_pro_upload_progress', array( $this, 'ajax_upload_progress' ) );
		add_action( 'wp_ajax_mmr_pro_validate_file', array( $this, 'ajax_validate_file' ) );
		add_action( 'wp_ajax_mmr_pro_chunk_upload', array( $this, 'ajax_chunk_upload' ) );
	}

	/**
	 * Handle bulk upload of multiple files
	 *
	 * @param array $files Array of files to upload
	 * @param array $options Upload options
	 * @return array Upload results
	 */
	public function bulk_upload( $files, $options = array() ) {
		$defaults = array(
			'overwrite_existing' => false,
			'create_thumbnails' => true,
			'validate_mime' => true,
			'max_file_size' => wp_max_upload_size(),
			'batch_size' => 10, // Process files in batches to avoid timeouts
		);

		$options = wp_parse_args( $options, $defaults );

		$results = array(
			'uploaded' => array(),
			'failed' => array(),
			'skipped' => array(),
			'total_processed' => 0,
			'total_size' => 0,
			'upload_time' => 0,
		);

		$start_time = microtime( true );
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/mmr-temp/';

		// Create temp directory if it doesn't exist
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		// Process files in batches
		$batches = array_chunk( $files, $options['batch_size'] );

		foreach ( $batches as $batch ) {
			foreach ( $batch as $file ) {
				$results['total_processed']++;

				$upload_result = $this->process_single_file( $file, $temp_dir, $options );

				if ( $upload_result['success'] ) {
					$results['uploaded'][] = $upload_result['data'];
					$results['total_size'] += $upload_result['data']['size'];
				} else {
					if ( $upload_result['skipped'] ) {
						$results['skipped'][] = $upload_result['data'];
					} else {
						$results['failed'][] = $upload_result['data'];
					}
				}
			}

			// Brief pause to prevent server overload
			if ( count( $batches ) > 1 ) {
				usleep( 100000 ); // 0.1 second pause
			}
		}

		$results['upload_time'] = round( microtime( true ) - $start_time, 2 );

		return $results;
	}

	/**
	 * Process a single file upload
	 *
	 * @param array  $file File data
	 * @param string $temp_dir Temporary directory path
	 * @param array  $options Upload options
	 * @return array Processing result
	 */
	private function process_single_file( $file, $temp_dir, $options ) {
		$result = array(
			'success' => false,
			'skipped' => false,
			'data' => array(),
		);

		// Validate file
		if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$result['data'] = array(
				'name' => isset( $file['name'] ) ? $file['name'] : 'Unknown',
				'error' => 'Invalid file upload',
			);
			return $result;
		}

		// Check file size
		if ( $file['size'] > $options['max_file_size'] ) {
			$result['data'] = array(
				'name' => $file['name'],
				'error' => 'File size exceeds maximum allowed limit',
				'size' => $file['size'],
				'max_allowed' => $options['max_file_size'],
			);
			return $result;
		}

		// Validate MIME type if enabled
		if ( $options['validate_mime'] ) {
			$filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
			if ( ! $filetype['ext'] || ! $filetype['type'] ) {
				$result['data'] = array(
					'name' => $file['name'],
					'error' => 'Invalid file type or extension',
				);
				return $result;
			}
		}

		$filename = sanitize_file_name( $file['name'] );
		$destination = $temp_dir . $filename;

		// Handle filename conflicts
		if ( ! $options['overwrite_existing'] && file_exists( $destination ) ) {
			$counter = 1;
			$original_filename = $filename;
			$file_info = pathinfo( $original_filename );

			while ( file_exists( $destination ) ) {
				$new_filename = $file_info['filename'] . '_' . $counter . '.' . $file_info['extension'];
				$destination = $temp_dir . $new_filename;
				$filename = $new_filename;
				$counter++;
			}
		}

		// Move file to temp directory
		if ( move_uploaded_file( $file['tmp_name'], $destination ) ) {
			$file_info = pathinfo( $filename );

			$result['success'] = true;
			$result['data'] = array(
				'name' => $filename,
				'original_name' => $file['name'],
				'size' => $file['size'],
				'type' => $file['type'],
				'extension' => $file_info['extension'],
				'temp_path' => $destination,
				'upload_time' => time(),
			);

			// Create thumbnails for images if enabled
			if ( $options['create_thumbnails'] && $this->is_image_file( $file_info['extension'] ) ) {
				$this->create_thumbnail( $destination, $temp_dir );
			}
		} else {
			$result['data'] = array(
				'name' => $file['name'],
				'error' => 'Failed to move file to temp directory',
			);
		}

		return $result;
	}

	/**
	 * Handle selective upload based on matching files
	 *
	 * @param array $matching_files Array of files to upload selectively
	 * @param array $options Upload options
	 * @return array Upload results
	 */
	public function selective_upload( $matching_files, $options = array() ) {
		$defaults = array(
			'overwrite_existing' => false,
			'create_thumbnails' => true,
			'validate_mime' => true,
			'max_file_size' => wp_max_upload_size(),
		);

		$options = wp_parse_args( $options, $defaults );

		$results = array(
			'uploaded' => array(),
			'failed' => array(),
			'skipped' => array(),
			'total_processed' => 0,
			'total_size' => 0,
			'upload_time' => 0,
		);

		$start_time = microtime( true );
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/mmr-temp/';

		// Create temp directory if it doesn't exist
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		foreach ( $matching_files as $file_data ) {
			$results['total_processed']++;

			$local_path = $file_data['local_path'];
			$filename = $file_data['filename'];

			// Check if local file exists
			if ( ! file_exists( $local_path ) ) {
				$results['failed'][] = array(
					'name' => $filename,
					'error' => 'Local file not found: ' . $local_path,
				);
				continue;
			}

			// Check if file is readable
			if ( ! is_readable( $local_path ) ) {
				$results['failed'][] = array(
					'name' => $filename,
					'error' => 'Local file not readable: ' . $local_path,
				);
				continue;
			}

			$filesize = filesize( $local_path );

			// Check file size
			if ( $filesize > $options['max_file_size'] ) {
				$results['failed'][] = array(
					'name' => $filename,
					'error' => 'File size exceeds maximum allowed limit',
					'size' => $filesize,
					'max_allowed' => $options['max_file_size'],
				);
				continue;
			}

			$destination = $temp_dir . $filename;

			// Handle filename conflicts
			if ( ! $options['overwrite_existing'] && file_exists( $destination ) ) {
				$counter = 1;
				$original_filename = $filename;
				$file_info = pathinfo( $original_filename );

				while ( file_exists( $destination ) ) {
					$new_filename = $file_info['filename'] . '_' . $counter . '.' . $file_info['extension'];
					$destination = $temp_dir . $new_filename;
					$filename = $new_filename;
					$counter++;
				}
			}

			// Copy file to temp directory
			if ( copy( $local_path, $destination ) ) {
				$file_info = pathinfo( $filename );

				$results['uploaded'][] = array(
					'name' => $filename,
					'original_name' => $file_data['filename'],
					'local_path' => $local_path,
					'size' => $filesize,
					'extension' => $file_info['extension'],
					'temp_path' => $destination,
					'missing_data' => $file_data['missing_data'],
				);

				$results['total_size'] += $filesize;

				// Create thumbnails for images if enabled
				if ( $options['create_thumbnails'] && $this->is_image_file( $file_info['extension'] ) ) {
					$this->create_thumbnail( $destination, $temp_dir );
				}
			} else {
				$results['failed'][] = array(
					'name' => $filename,
					'error' => 'Failed to copy file to temp directory',
				);
			}
		}

		$results['upload_time'] = round( microtime( true ) - $start_time, 2 );

		return $results;
	}

	/**
	 * Check if file is an image
	 *
	 * @param string $extension File extension
	 * @return bool True if image file
	 */
	private function is_image_file( $extension ) {
		$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'ico' );
		return in_array( strtolower( $extension ), $image_extensions, true );
	}

	/**
	 * Create thumbnail for image file
	 *
	 * @param string $image_path Path to image file
	 * @param string $thumb_dir Directory to save thumbnail
	 * @return bool True on success
	 */
	private function create_thumbnail( $image_path, $thumb_dir ) {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return false;
		}

		$image_editor = wp_get_image_editor( $image_path );

		if ( is_wp_error( $image_editor ) ) {
			return false;
		}

		$file_info = pathinfo( $image_path );
		$thumb_name = $file_info['filename'] . '_thumb.' . $file_info['extension'];
		$thumb_path = $thumb_dir . $thumb_name;

		$image_editor->resize( 150, 150, true );
		$result = $image_editor->save( $thumb_path );

		return ! is_wp_error( $result );
	}

	/**
	 * Handle chunked file upload for large files
	 *
	 * @param array $chunk_data Chunk data
	 * @return array Upload result
	 */
	public function handle_chunk_upload( $chunk_data ) {
		$results = array(
			'success' => false,
			'chunk_received' => false,
			'upload_complete' => false,
			'temp_path' => '',
			'error' => '',
		);

		$upload_dir = wp_upload_dir();
		$chunk_dir = $upload_dir['basedir'] . '/mmr-chunks/';
		$temp_dir = $upload_dir['basedir'] . '/mmr-temp/';

		// Create directories if they don't exist
		if ( ! file_exists( $chunk_dir ) ) {
			wp_mkdir_p( $chunk_dir );
		}
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		$file_id = sanitize_text_field( $chunk_data['file_id'] );
		$chunk_index = intval( $chunk_data['chunk_index'] );
		$total_chunks = intval( $chunk_data['total_chunks'] );
		$file_name = sanitize_file_name( $chunk_data['file_name'] );

		// Save chunk
		$chunk_path = $chunk_dir . $file_id . '_chunk_' . $chunk_index;

		if ( isset( $_FILES['chunk']['tmp_name'] ) && move_uploaded_file( $_FILES['chunk']['tmp_name'], $chunk_path ) ) {
			$results['chunk_received'] = true;

			// Check if all chunks have been received
			$received_chunks = glob( $chunk_dir . $file_id . '_chunk_*' );

			if ( count( $received_chunks ) === $total_chunks ) {
				// Combine chunks
				$temp_file_path = $temp_dir . $file_name;
				$temp_file = fopen( $temp_file_path, 'wb' );

				if ( $temp_file ) {
					for ( $i = 0; $i < $total_chunks; $i++ ) {
						$chunk_path = $chunk_dir . $file_id . '_chunk_' . $i;
						$chunk_data = file_get_contents( $chunk_path );
						fwrite( $temp_file, $chunk_data );
						unlink( $chunk_path ); // Remove chunk after combining
					}

					fclose( $temp_file );

					$results['upload_complete'] = true;
					$results['temp_path'] = $temp_file_path;
					$results['success'] = true;
				} else {
					$results['error'] = 'Failed to create combined file';
				}
			}
		} else {
			$results['error'] = 'Failed to save chunk';
		}

		return $results;
	}

	/**
	 * AJAX handler for bulk upload
	 */
	public function ajax_bulk_upload() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Check if files were uploaded
		if ( ! isset( $_FILES['files'] ) || empty( $_FILES['files'] ) ) {
			wp_send_json_error( array( 'message' => 'No files provided' ) );
		}

		$files = isset( $_FILES['files'] ) ? wp_unslash( $_FILES['files'] ) : array();
		$file_array = array();

		// Parse upload options
		$options = array(
			'overwrite_existing' => isset( $_POST['overwrite_existing'] ) && rest_sanitize_boolean( $_POST['overwrite_existing'] ),
			'create_thumbnails' => isset( $_POST['create_thumbnails'] ) && rest_sanitize_boolean( $_POST['create_thumbnails'] ),
			'validate_mime' => ! ( isset( $_POST['skip_mime_validation'] ) && rest_sanitize_boolean( $_POST['skip_mime_validation'] ) ),
			'batch_size' => isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10,
		);

		// Convert $_FILES structure to array of files
		$file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

		for ( $i = 0; $i < $file_count; $i++ ) {
			$file_array[] = array(
				'name' => is_array( $files['name'] ) ? $files['name'][ $i ] : $files['name'],
				'tmp_name' => is_array( $files['tmp_name'] ) ? $files['tmp_name'][ $i ] : $files['tmp_name'],
				'type' => is_array( $files['type'] ) ? $files['type'][ $i ] : $files['type'],
				'error' => is_array( $files['error'] ) ? $files['error'][ $i ] : $files['error'],
				'size' => is_array( $files['size'] ) ? $files['size'][ $i ] : $files['size'],
			);
		}

		// Perform bulk upload
		$results = $this->bulk_upload( $file_array, $options );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for selective upload
	 */
	public function ajax_selective_upload() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$matching_files = isset( $_POST['matching_files'] ) ? json_decode( wp_unslash( $_POST['matching_files'] ), true ) : array();

		if ( empty( $matching_files ) ) {
			wp_send_json_error( array( 'message' => 'No matching files provided' ) );
		}

		// Parse upload options
		$options = array(
			'overwrite_existing' => isset( $_POST['overwrite_existing'] ) && rest_sanitize_boolean( $_POST['overwrite_existing'] ),
			'create_thumbnails' => isset( $_POST['create_thumbnails'] ) && rest_sanitize_boolean( $_POST['create_thumbnails'] ),
			'validate_mime' => ! ( isset( $_POST['skip_mime_validation'] ) && rest_sanitize_boolean( $_POST['skip_mime_validation'] ) ),
		);

		// Perform selective upload
		$results = $this->selective_upload( $matching_files, $options );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for upload progress
	 */
	public function ajax_upload_progress() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$upload_id = isset( $_POST['upload_id'] ) ? sanitize_text_field( wp_unslash( $_POST['upload_id'] ) ) : '';

		if ( empty( $upload_id ) ) {
			wp_send_json_error( array( 'message' => 'Upload ID is required' ) );
		}

		// Get progress from transient
		$progress = get_transient( 'mmr_upload_progress_' . $upload_id );

		if ( $progress === false ) {
			wp_send_json_error( array( 'message' => 'Upload progress not found' ) );
		}

		wp_send_json_success( $progress );
	}

	/**
	 * AJAX handler for file validation
	 */
	public function ajax_validate_file() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$file_data = isset( $_POST['file_data'] ) ? json_decode( wp_unslash( $_POST['file_data'] ), true ) : array();

		if ( empty( $file_data ) ) {
			wp_send_json_error( array( 'message' => 'File data is required' ) );
		}

		$validation_result = $this->validate_file( $file_data );

		wp_send_json_success( $validation_result );
	}

	/**
	 * Validate file before upload
	 *
	 * @param array $file_data File data
	 * @return array Validation result
	 */
	private function validate_file( $file_data ) {
		$result = array(
			'valid' => true,
			'errors' => array(),
			'warnings' => array(),
		);

		// Check file size
		$max_size = wp_max_upload_size();
		if ( isset( $file_data['size'] ) && $file_data['size'] > $max_size ) {
			$result['valid'] = false;
			$result['errors'][] = 'File size exceeds maximum allowed limit of ' . size_format( $max_size );
		}

		// Check file extension
		if ( isset( $file_data['name'] ) ) {
			$filetype = wp_check_filetype_and_ext( '', $file_data['name'] );
			if ( ! $filetype['ext'] || ! $filetype['type'] ) {
				$result['valid'] = false;
				$result['errors'][] = 'Invalid file type or extension';
			}
		}

		// Additional validations can be added here

		return $result;
	}

	/**
	 * AJAX handler for chunked upload
	 */
	public function ajax_chunk_upload() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		if ( ! isset( $_FILES['chunk'] ) ) {
			wp_send_json_error( array( 'message' => 'No chunk data provided' ) );
		}

		$chunk_data = array(
			'file_id' => isset( $_POST['file_id'] ) ? sanitize_text_field( wp_unslash( $_POST['file_id'] ) ) : '',
			'chunk_index' => isset( $_POST['chunk_index'] ) ? intval( $_POST['chunk_index'] ) : 0,
			'total_chunks' => isset( $_POST['total_chunks'] ) ? intval( $_POST['total_chunks'] ) : 1,
			'file_name' => isset( $_POST['file_name'] ) ? sanitize_file_name( wp_unslash( $_POST['file_name'] ) ) : '',
		);

		$result = $this->handle_chunk_upload( $chunk_data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
}

// Initialize the pro uploader
new MMR_Pro_Uploader();
