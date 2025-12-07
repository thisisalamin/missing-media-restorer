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
	 * Supported file extensions for media files
	 *
	 * @var array
	 */
	private $supported_extensions = array(
		'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'ico',
		'mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac',
		'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv',
		'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
		'zip', 'rar', '7z', 'tar', 'gz'
	);

	/**
	 * Initialize the pro scanner
	 */
	public function __construct() {
		add_action( 'wp_ajax_mmr_pro_scan_local_directory', array( $this, 'ajax_scan_local_directory' ) );
		add_action( 'wp_ajax_mmr_pro_deep_scan', array( $this, 'ajax_deep_scan' ) );
		add_action( 'wp_ajax_mmr_pro_fuzzy_match', array( $this, 'ajax_fuzzy_match' ) );
	}

	/**
	 * Scan local directory for missing files
	 *
	 * @param string $directory_path The local directory path to scan
	 * @param array  $options Scan options
	 * @return array Results of the scan
	 */
	public function scan_local_directory( $directory_path, $options = array() ) {
		$defaults = array(
			'recursive' => true,
			'fuzzy_matching' => false,
			'file_size_limit' => 50 * 1024 * 1024, // 50MB default
			'include_extensions' => $this->supported_extensions,
			'exclude_patterns' => array( 'node_modules', '.git', '__MACOSX', 'Thumbs.db' ),
		);

		$options = wp_parse_args( $options, $defaults );

		$results = array(
			'scanned_files' => 0,
			'matching_files' => array(),
			'potential_matches' => array(),
			'skipped_files' => array(),
			'scan_size' => 0,
			'scan_time' => 0,
			'errors' => array(),
		);

		$start_time = microtime( true );

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
			$missing_lookup[ strtolower( $file['filename'] ) ] = $file;
		}

		// Scan directory
		if ( $options['recursive'] ) {
			$this->scan_directory_recursive( $directory_path, $missing_lookup, $results, $options );
		} else {
			$this->scan_directory_single( $directory_path, $missing_lookup, $results, $options );
		}

		// Perform fuzzy matching if enabled
		if ( $options['fuzzy_matching'] && ! empty( $results['scanned_files'] ) ) {
			$this->perform_fuzzy_matching( $missing_files, $results, $options );
		}

		$results['scan_time'] = round( microtime( true ) - $start_time, 2 );

		return $results;
	}

	/**
	 * Recursively scan directory
	 *
	 * @param string $directory Directory to scan
	 * @param array  $missing_lookup Lookup array of missing files
	 * @param array  $results Results array (passed by reference)
	 * @param array  $options Scan options
	 */
	private function scan_directory_recursive( $directory, $missing_lookup, &$results, $options ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$this->process_file( $file, $missing_lookup, $results, $options );
			}
		}
	}

	/**
	 * Scan single directory (non-recursive)
	 *
	 * @param string $directory Directory to scan
	 * @param array  $missing_lookup Lookup array of missing files
	 * @param array  $results Results array (passed by reference)
	 * @param array  $options Scan options
	 */
	private function scan_directory_single( $directory, $missing_lookup, &$results, $options ) {
		$iterator = new DirectoryIterator( $directory );

		foreach ( $iterator as $fileinfo ) {
			if ( $fileinfo->isDot() || ! $fileinfo->isFile() ) {
				continue;
			}

			$this->process_file( $fileinfo, $missing_lookup, $results, $options );
		}
	}

	/**
	 * Process individual file during scanning
	 *
	 * @param SplFileInfo $file File object
	 * @param array       $missing_lookup Lookup array of missing files
	 * @param array       $results Results array (passed by reference)
	 * @param array       $options Scan options
	 */
	private function process_file( $file, $missing_lookup, &$results, $options ) {
		$filename = $file->getFilename();
		$filepath = $file->getPathname();
		$filesize = $file->getSize();
		$file_extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		// Check if file should be skipped
		if ( $this->should_skip_file( $filename, $filepath, $filesize, $file_extension, $options ) ) {
			$results['skipped_files'][] = array(
				'filename' => $filename,
				'path' => $filepath,
				'reason' => 'Extension not supported or file too large',
			);
			return;
		}

		$results['scanned_files']++;
		$results['scan_size'] += $filesize;

		// Check for exact match (case-insensitive)
		$filename_lower = strtolower( $filename );
		if ( isset( $missing_lookup[ $filename_lower ] ) ) {
			$results['matching_files'][] = array(
				'filename' => $filename,
				'local_path' => $filepath,
				'size' => $filesize,
				'modified' => $file->getMTime(),
				'missing_data' => $missing_lookup[ $filename_lower ],
				'match_type' => 'exact',
			);
		}
	}

	/**
	 * Check if file should be skipped during scan
	 *
	 * @param string $filename File name
	 * @param string $filepath File path
	 * @param int    $filesize File size
	 * @param string $extension File extension
	 * @param array  $options Scan options
	 * @return bool True if file should be skipped
	 */
	private function should_skip_file( $filename, $filepath, $filesize, $extension, $options ) {
		// Check file size limit
		if ( $filesize > $options['file_size_limit'] ) {
			return true;
		}

		// Check extension
		if ( ! in_array( $extension, $options['include_extensions'], true ) ) {
			return true;
		}

		// Check exclude patterns
		foreach ( $options['exclude_patterns'] as $pattern ) {
			if ( strpos( $filepath, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Perform fuzzy matching for potential matches
	 *
	 * @param array $missing_files Array of missing files
	 * @param array $results Scan results (passed by reference)
	 * @param array $options Scan options
	 */
	private function perform_fuzzy_matching( $missing_files, &$results, $options ) {
		// This is a simplified fuzzy matching algorithm
		// In a real implementation, you might use more sophisticated algorithms

		foreach ( $missing_files as $missing_file ) {
			$missing_name = $missing_file['filename'];
			$missing_base = pathinfo( $missing_name, PATHINFO_FILENAME );
			$missing_ext = strtolower( pathinfo( $missing_name, PATHINFO_EXTENSION ) );

			// Skip if we already found an exact match
			$found_exact = false;
			foreach ( $results['matching_files'] as $match ) {
				if ( strtolower( $match['filename'] ) === strtolower( $missing_name ) ) {
					$found_exact = true;
					break;
				}
			}

			if ( $found_exact ) {
				continue;
			}

			// Look for potential matches
			foreach ( $results['scanned_files_info'] ?? array() as $scanned_file ) {
				$scanned_name = $scanned_file['filename'];
				$scanned_base = pathinfo( $scanned_name, PATHINFO_FILENAME );
				$scanned_ext = strtolower( pathinfo( $scanned_name, PATHINFO_EXTENSION ) );

				// Check for similar names with different extensions
				if ( $missing_ext !== $scanned_ext && strtolower( $missing_base ) === strtolower( $scanned_base ) ) {
					$similarity = $this->calculate_similarity( $missing_base, $scanned_base );

					if ( $similarity > 0.8 ) {
						$results['potential_matches'][] = array(
							'missing_file' => $missing_file,
							'potential_match' => $scanned_file,
							'similarity' => $similarity,
							'match_reason' => 'Similar filename, different extension',
						);
					}
				}
			}
		}
	}

	/**
	 * Calculate similarity between two strings
	 *
	 * @param string $str1 First string
	 * @param string $str2 Second string
	 * @return float Similarity score (0-1)
	 */
	private function calculate_similarity( $str1, $str2 ) {
		// Use Levenshtein distance for similarity calculation
		$distance = levenshtein( strtolower( $str1 ), strtolower( $str2 ) );
		$max_length = max( strlen( $str1 ), strlen( $str2 ) );

		if ( $max_length === 0 ) {
			return 1.0;
		}

		return 1.0 - ( $distance / $max_length );
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

		// Parse scan options
		$options = array(
			'recursive' => isset( $_POST['recursive'] ) && rest_sanitize_boolean( $_POST['recursive'] ),
			'fuzzy_matching' => isset( $_POST['fuzzy_matching'] ) && rest_sanitize_boolean( $_POST['fuzzy_matching'] ),
			'file_size_limit' => isset( $_POST['file_size_limit'] ) ? intval( $_POST['file_size_limit'] ) : 50 * 1024 * 1024,
		);

		// Perform the scan
		$results = $this->scan_local_directory( $directory_path, $options );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for deep scan with extended options
	 */
	public function ajax_deep_scan() {
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

		// Parse deep scan options
		$options = array(
			'recursive' => true,
			'fuzzy_matching' => true,
			'file_size_limit' => isset( $_POST['file_size_limit'] ) ? intval( $_POST['file_size_limit'] ) : 100 * 1024 * 1024,
			'include_extensions' => isset( $_POST['include_extensions'] ) ? array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['include_extensions'] ) ) ) ) : $this->supported_extensions,
			'exclude_patterns' => isset( $_POST['exclude_patterns'] ) ? array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['exclude_patterns'] ) ) ) ) : array( 'node_modules', '.git', '__MACOSX', 'Thumbs.db' ),
		);

		// Perform the deep scan
		$results = $this->scan_local_directory( $directory_path, $options );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for fuzzy matching only
	 */
	public function ajax_fuzzy_match() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$scanned_files = isset( $_POST['scanned_files'] ) ? json_decode( wp_unslash( $_POST['scanned_files'] ), true ) : array();

		if ( empty( $scanned_files ) ) {
			wp_send_json_error( array( 'message' => 'No scanned files provided' ) );
		}

		// Get missing files from transient
		$missing_files = get_transient( 'mmr_missing_files' );
		if ( ! $missing_files ) {
			wp_send_json_error( array( 'message' => 'No missing files data found. Please run a scan first.' ) );
		}

		$results = array(
			'potential_matches' => array(),
		);

		$options = array(
			'similarity_threshold' => isset( $_POST['similarity_threshold'] ) ? floatval( $_POST['similarity_threshold'] ) : 0.7,
		);

		// Perform fuzzy matching
		$this->perform_fuzzy_matching( $missing_files, $results, $options );

		wp_send_json_success( $results );
	}
}

// Initialize the pro scanner
new MMR_Pro_Scanner();
