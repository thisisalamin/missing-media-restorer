<?php
/**
 * MMR Pro Scanner - Smart Local Directory Scanning
 *
 * @package MissingMediaRestorerPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MMR_Pro_Scanner
 */
class MMR_Pro_Scanner {

	/**
	 * Initialize the pro scanner
	 */
	public function __construct() {
		add_action( 'wp_ajax_mmr_pro_scan_local_directory', array( $this, 'ajax_scan_local_directory' ) );
	}

	/**
	 * Scan local directory for missing files
	 *
	 * @param string $directory_path The local directory path to scan
	 * @return array Results of the scan
	 */
	public function scan_local_directory( $directory_path ) {
		$results = array(
			'scanned_files' => 0,
			'matching_files' => array(),
			'missing_files' => array(),
			'errors' => array(),
		);

		// Validate directory
		if ( ! is_dir( $directory_path ) ) {
			$results['errors'][] = 'Directory does not exist: ' . $directory_path;
			return $results;
		}

		if ( ! is_readable( $directory_path ) ) {
			$results['errors'][] = 'Directory is not readable: ' . $directory_path;
			return $results;
		}

		// Get missing files from transient
		$missing_files = get_transient( 'mmr_missing_files' );
		if ( ! $missing_files ) {
			$results['errors'][] = 'No missing files data found. Please run a scan first.';
			return $results;
		}

		// Create lookup array for faster matching
		$missing_lookup = array();
		foreach ( $missing_files as $file ) {
			$missing_lookup[ $file['filename'] ] = $file;
		}

		// Scan directory recursively
		$this->scan_directory_recursive( $directory_path, $missing_lookup, $results );

		return $results;
	}

	/**
	 * Recursively scan directory
	 *
	 * @param string $directory Directory to scan
	 * @param array  $missing_lookup Lookup array of missing files
	 * @param array  $results Results array (passed by reference)
	 */
	private function scan_directory_recursive( $directory, $missing_lookup, &$results ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$filename = $file->getFilename();
				$filepath = $file->getPathname();

				$results['scanned_files']++;

				// Check if this file matches any missing file
				if ( isset( $missing_lookup[ $filename ] ) ) {
					$results['matching_files'][] = array(
						'filename' => $filename,
						'local_path' => $filepath,
						'missing_data' => $missing_lookup[ $filename ],
					);
				}
			}
		}
	}

	/**
	 * AJAX handler for local directory scan
	 */
	public function ajax_scan_local_directory() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$directory_path = isset( $_POST['directory_path'] ) ? sanitize_text_field( wp_unslash( $_POST['directory_path'] ) ) : '';

		if ( empty( $directory_path ) ) {
			wp_send_json_error( array( 'message' => 'Directory path is required' ) );
		}

		// Perform the scan
		$results = $this->scan_local_directory( $directory_path );

		wp_send_json_success( $results );
	}
}

// Initialize the pro scanner
new MMR_Pro_Scanner();
