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
	 * Initialize the pro uploader
	 */
	public function __construct() {
		add_action( 'wp_ajax_mmr_pro_bulk_upload', array( $this, 'ajax_bulk_upload' ) );
		add_action( 'wp_ajax_mmr_pro_selective_upload', array( $this, 'ajax_selective_upload' ) );
	}

	/**
	 * Handle bulk upload of multiple files
	 *
	 * @param array $files Array of files to upload
	 * @return array Upload results
	 */
	public function bulk_upload( $files ) {
		$results = array(
			'uploaded' => array(),
			'failed' => array(),
			'total_processed' => 0,
		);

		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/mmr-temp/';

		// Create temp directory if it doesn't exist
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		foreach ( $files as $file ) {
			$results['total_processed']++;

			// Validate file
			if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				$results['failed'][] = array(
					'name' => isset( $file['name'] ) ? $file['name'] : 'Unknown',
					'error' => 'Invalid file upload',
				);
				continue;
			}

			$filename = sanitize_file_name( $file['name'] );
			$destination = $temp_dir . $filename;

			// Handle filename conflicts
			$counter = 1;
			$original_filename = $filename;
			while ( file_exists( $destination ) ) {
				$file_info = pathinfo( $original_filename );
				$new_filename = $file_info['filename'] . '_' . $counter . '.' . $file_info['extension'];
				$destination = $temp_dir . $new_filename;
				$filename = $new_filename;
				$counter++;
			}

			if ( move_uploaded_file( $file['tmp_name'], $destination ) ) {
				$results['uploaded'][] = array(
					'name' => $filename,
					'original_name' => $file['name'],
					'size' => $file['size'],
				);
			} else {
				$results['failed'][] = array(
					'name' => $file['name'],
					'error' => 'Failed to move file to temp directory',
				);
			}
		}

		return $results;
	}

	/**
	 * Handle selective upload based on matching files
	 *
	 * @param array $matching_files Array of files to upload selectively
	 * @return array Upload results
	 */
	public function selective_upload( $matching_files ) {
		$results = array(
			'uploaded' => array(),
			'failed' => array(),
			'skipped' => array(),
			'total_processed' => 0,
		);

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

			$destination = $temp_dir . $filename;

			// Handle filename conflicts
			$counter = 1;
			$original_filename = $filename;
			while ( file_exists( $destination ) ) {
				$file_info = pathinfo( $original_filename );
				$new_filename = $file_info['filename'] . '_' . $counter . '.' . $file_info['extension'];
				$destination = $temp_dir . $new_filename;
				$filename = $new_filename;
				$counter++;
			}

			if ( copy( $local_path, $destination ) ) {
				$results['uploaded'][] = array(
					'name' => $filename,
					'local_path' => $local_path,
					'size' => filesize( $local_path ),
				);
			} else {
				$results['failed'][] = array(
					'name' => $filename,
					'error' => 'Failed to copy file to temp directory',
				);
			}
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

		// Convert $_FILES structure to array of files
		$file_count = is_array( $files['name'] ) ? count( $files['name'] ) : 1;

		for ( $i = 0; $i < $file_count; $i++ ) {
			$file_array[] = array(
				'name' => is_array( $files['name'] ) ? $files['name'][ $i ] : $files['name'],
				'tmp_name' => is_array( $files['tmp_name'] ) ? $files['tmp_name'][ $i ] : $files['tmp_name'],
				'error' => is_array( $files['error'] ) ? $files['error'][ $i ] : $files['error'],
				'size' => is_array( $files['size'] ) ? $files['size'][ $i ] : $files['size'],
			);
		}

		// Perform bulk upload
		$results = $this->bulk_upload( $file_array );

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

		// Perform selective upload
		$results = $this->selective_upload( $matching_files );

		wp_send_json_success( $results );
	}
}

// Initialize the pro uploader
new MMR_Pro_Uploader();
