<?php
/**
 * MMR Pro Matcher - Advanced Matching Algorithms
 *
 * @package MissingMediaRestorerPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MMR_Pro_Matcher
 */
class MMR_Pro_Matcher {

	/**
	 * Matching algorithms available
	 *
	 * @var array
	 */
	private $algorithms = array(
		'exact' => 'Exact filename match',
		'fuzzy' => 'Fuzzy string matching',
		'meta' => 'Metadata-based matching',
		'size' => 'File size-based matching',
		'hash' => 'File hash matching',
		'pattern' => 'Pattern-based matching',
	);

	/**
	 * Initialize the pro matcher
	 */
	public function __construct() {
		add_action( 'wp_ajax_mmr_pro_advanced_match', array( $this, 'ajax_advanced_match' ) );
		add_action( 'wp_ajax_mmr_pro_batch_match', array( $this, 'ajax_batch_match' ) );
		add_action( 'wp_ajax_mmr_pro_verify_match', array( $this, 'ajax_verify_match' ) );
	}

	/**
	 * Perform advanced matching between missing files and available files
	 *
	 * @param array $missing_files Array of missing files
	 * @param array $available_files Array of available files
	 * @param array $options Matching options
	 * @return array Matching results
	 */
	public function advanced_match( $missing_files, $available_files, $options = array() ) {
		$defaults = array(
			'algorithms' => array( 'exact', 'fuzzy', 'meta', 'size' ),
			'threshold' => 0.7,
			'weight_exact' => 1.0,
			'weight_fuzzy' => 0.8,
			'weight_meta' => 0.6,
			'weight_size' => 0.4,
			'weight_hash' => 1.0,
			'weight_pattern' => 0.5,
			'max_matches' => 5,
		);

		$options = wp_parse_args( $options, $defaults );

		$results = array(
			'matches' => array(),
			'unmatched' => array(),
			'statistics' => array(
				'total_missing' => count( $missing_files ),
				'total_available' => count( $available_files ),
				'matches_found' => 0,
				'high_confidence' => 0,
				'medium_confidence' => 0,
				'low_confidence' => 0,
			),
		);

		// Pre-process available files for faster matching
		$processed_available = $this->pre_process_available_files( $available_files );

		foreach ( $missing_files as $missing_file ) {
			$matches = $this->find_matches_for_file( $missing_file, $processed_available, $options );

			if ( ! empty( $matches ) ) {
				$results['matches'][] = array(
					'missing_file' => $missing_file,
					'matches' => $matches,
					'best_match' => $matches[0], // First match is the best match
				);
				$results['statistics']['matches_found']++;

				// Categorize by confidence
				$confidence = $matches[0]['confidence'];
				if ( $confidence >= 0.9 ) {
					$results['statistics']['high_confidence']++;
				} elseif ( $confidence >= 0.7 ) {
					$results['statistics']['medium_confidence']++;
				} else {
					$results['statistics']['low_confidence']++;
				}
			} else {
				$results['unmatched'][] = $missing_file;
			}
		}

		return $results;
	}

	/**
	 * Pre-process available files for faster matching
	 *
	 * @param array $available_files Array of available files
	 * @return array Processed files
	 */
	private function pre_process_available_files( $available_files ) {
		$processed = array();

		foreach ( $available_files as $file ) {
			$processed_file = array(
				'filename' => $file['filename'],
				'path' => isset( $file['path'] ) ? $file['path'] : $file['local_path'],
				'size' => isset( $file['size'] ) ? $file['size'] : filesize( $file['path'] ),
				'extension' => strtolower( pathinfo( $file['filename'], PATHINFO_EXTENSION ) ),
				'basename' => pathinfo( $file['filename'], PATHINFO_FILENAME ),
				'metadata' => isset( $file['metadata'] ) ? $file['metadata'] : array(),
			);

			// Generate file hash if not present
			if ( ! isset( $file['hash'] ) && isset( $file['path'] ) && file_exists( $file['path'] ) ) {
				$processed_file['hash'] = md5_file( $file['path'] );
			} else {
				$processed_file['hash'] = isset( $file['hash'] ) ? $file['hash'] : '';
			}

			// Extract patterns from filename
			$processed_file['patterns'] = $this->extract_filename_patterns( $file['filename'] );

			$processed[] = $processed_file;
		}

		return $processed;
	}

	/**
	 * Find matches for a specific missing file
	 *
	 * @param array $missing_file Missing file data
	 * @param array $available_files Pre-processed available files
	 * @param array $options Matching options
	 * @return array Array of matches
	 */
	private function find_matches_for_file( $missing_file, $available_files, $options ) {
		$matches = array();
		$missing_basename = pathinfo( $missing_file['filename'], PATHINFO_FILENAME );
		$missing_extension = strtolower( pathinfo( $missing_file['filename'], PATHINFO_EXTENSION ) );
		$missing_size = isset( $missing_file['size'] ) ? $missing_file['size'] : 0;

		foreach ( $available_files as $available_file ) {
			$score = 0;
			$match_details = array();

			// Exact filename matching
			if ( in_array( 'exact', $options['algorithms'], true ) ) {
				$exact_score = $this->exact_match( $missing_file['filename'], $available_file['filename'] );
				if ( $exact_score > 0 ) {
					$score += $exact_score * $options['weight_exact'];
					$match_details[] = 'Exact match: ' . round( $exact_score * 100, 1 ) . '%';
				}
			}

			// Fuzzy string matching
			if ( in_array( 'fuzzy', $options['algorithms'], true ) ) {
				$fuzzy_score = $this->fuzzy_match( $missing_basename, $available_file['basename'] );
				if ( $fuzzy_score > $options['threshold'] ) {
					$score += $fuzzy_score * $options['weight_fuzzy'];
					$match_details[] = 'Fuzzy match: ' . round( $fuzzy_score * 100, 1 ) . '%';
				}
			}

			// Metadata matching
			if ( in_array( 'meta', $options['algorithms'], true ) && ! empty( $missing_file['metadata'] ) ) {
				$meta_score = $this->metadata_match( $missing_file['metadata'], $available_file['metadata'] );
				if ( $meta_score > 0 ) {
					$score += $meta_score * $options['weight_meta'];
					$match_details[] = 'Metadata match: ' . round( $meta_score * 100, 1 ) . '%';
				}
			}

			// File size matching
			if ( in_array( 'size', $options['algorithms'], true ) && $missing_size > 0 ) {
				$size_score = $this->size_match( $missing_size, $available_file['size'] );
				if ( $size_score > 0.5 ) { // Only count if reasonably close
					$score += $size_score * $options['weight_size'];
					$match_details[] = 'Size match: ' . round( $size_score * 100, 1 ) . '%';
				}
			}

			// Hash matching
			if ( in_array( 'hash', $options['algorithms'], true ) && ! empty( $missing_file['hash'] ) && ! empty( $available_file['hash'] ) ) {
				$hash_score = $this->hash_match( $missing_file['hash'], $available_file['hash'] );
				if ( $hash_score > 0 ) {
					$score += $hash_score * $options['weight_hash'];
					$match_details[] = 'Hash match: ' . round( $hash_score * 100, 1 ) . '%';
				}
			}

			// Pattern matching
			if ( in_array( 'pattern', $options['algorithms'], true ) ) {
				$pattern_score = $this->pattern_match( $missing_file['filename'], $available_file['patterns'] );
				if ( $pattern_score > 0 ) {
					$score += $pattern_score * $options['weight_pattern'];
					$match_details[] = 'Pattern match: ' . round( $pattern_score * 100, 1 ) . '%';
				}
			}

			// Calculate confidence score
			$max_possible_score = array_sum( array_intersect_key( $options, array(
				'weight_exact' => 1,
				'weight_fuzzy' => 1,
				'weight_meta' => 1,
				'weight_size' => 1,
				'weight_hash' => 1,
				'weight_pattern' => 1,
			) ) );

			$confidence = $max_possible_score > 0 ? $score / $max_possible_score : 0;

			// Only include matches above threshold
			if ( $confidence >= $options['threshold'] ) {
				$matches[] = array(
					'file' => $available_file,
					'score' => $score,
					'confidence' => $confidence,
					'details' => $match_details,
				);
			}
		}

		// Sort matches by confidence (highest first)
		usort( $matches, function( $a, $b ) {
			return $b['confidence'] <=> $a['confidence'];
		} );

		// Limit number of matches
		return array_slice( $matches, 0, $options['max_matches'] );
	}

	/**
	 * Exact filename matching
	 *
	 * @param string $filename1 First filename
	 * @param string $filename2 Second filename
	 * @return float Match score (0-1)
	 */
	private function exact_match( $filename1, $filename2 ) {
		return strtolower( $filename1 ) === strtolower( $filename2 ) ? 1.0 : 0.0;
	}

	/**
	 * Fuzzy string matching using Levenshtein distance
	 *
	 * @param string $str1 First string
	 * @param string $str2 Second string
	 * @return float Similarity score (0-1)
	 */
	private function fuzzy_match( $str1, $str2 ) {
		$str1_lower = strtolower( $str1 );
		$str2_lower = strtolower( $str2 );

		$distance = levenshtein( $str1_lower, $str2_lower );
		$max_length = max( strlen( $str1_lower ), strlen( $str2_lower ) );

		if ( $max_length === 0 ) {
			return 1.0;
		}

		return 1.0 - ( $distance / $max_length );
	}

	/**
	 * Metadata matching
	 *
	 * @param array $metadata1 First metadata
	 * @param array $metadata2 Second metadata
	 * @return float Match score (0-1)
	 */
	private function metadata_match( $metadata1, $metadata2 ) {
		if ( empty( $metadata1 ) || empty( $metadata2 ) ) {
			return 0.0;
		}

		$matching_fields = 0;
		$total_fields = 0;

		$comparable_fields = array( 'title', 'description', 'caption', 'alt', 'width', 'height', 'created_timestamp' );

		foreach ( $comparable_fields as $field ) {
			if ( isset( $metadata1[ $field ] ) && isset( $metadata2[ $field ] ) ) {
				$total_fields++;
				if ( $metadata1[ $field ] === $metadata2[ $field ] ) {
					$matching_fields++;
				} elseif ( is_string( $metadata1[ $field ] ) && is_string( $metadata2[ $field ] ) ) {
					// For string fields, use fuzzy matching
					$similarity = $this->fuzzy_match( $metadata1[ $field ], $metadata2[ $field ] );
					if ( $similarity > 0.8 ) {
						$matching_fields++;
					}
				}
			}
		}

		return $total_fields > 0 ? $matching_fields / $total_fields : 0.0;
	}

	/**
	 * File size matching
	 *
	 * @param int $size1 First file size
	 * @param int $size2 Second file size
	 * @return float Match score (0-1)
	 */
	private function size_match( $size1, $size2 ) {
		if ( $size1 <= 0 || $size2 <= 0 ) {
			return 0.0;
		}

		$diff = abs( $size1 - $size2 );
		$max_size = max( $size1, $size2 );

		// Allow for small differences (up to 5%)
		$threshold = $max_size * 0.05;

		if ( $diff <= $threshold ) {
			return 1.0;
		}

		// Gradual decrease in score for larger differences
		$score = 1.0 - ( $diff / $max_size );
		return max( 0.0, $score );
	}

	/**
	 * File hash matching
	 *
	 * @param string $hash1 First file hash
	 * @param string $hash2 Second file hash
	 * @return float Match score (0-1)
	 */
	private function hash_match( $hash1, $hash2 ) {
		return strtolower( $hash1 ) === strtolower( $hash2 ) ? 1.0 : 0.0;
	}

	/**
	 * Pattern matching based on filename patterns
	 *
	 * @param string $filename Filename to match
	 * @param array  $patterns Patterns from available file
	 * @return float Match score (0-1)
	 */
	private function pattern_match( $filename, $patterns ) {
		if ( empty( $patterns ) ) {
			return 0.0;
		}

		$matching_patterns = 0;
		$total_patterns = count( $patterns );

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $filename ) ) {
				$matching_patterns++;
			}
		}

		return $total_patterns > 0 ? $matching_patterns / $total_patterns : 0.0;
	}

	/**
	 * Extract patterns from filename for pattern matching
	 *
	 * @param string $filename Filename
	 * @return array Array of regex patterns
	 */
	private function extract_filename_patterns( $filename ) {
		$patterns = array();
		$basename = pathinfo( $filename, PATHINFO_FILENAME );
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		// Date patterns (YYYY-MM-DD, YYYYMMDD, etc.)
		if ( preg_match( '/(\d{4}[-_]?\d{2}[-_]?\d{2})/', $basename, $date_matches ) ) {
			$patterns[] = '/\d{4}[-_]?\d{2}[-_]?\d{2}/';
		}

		// Sequential number patterns
		if ( preg_match( '/(\d+)$/', $basename, $num_matches ) ) {
			$patterns[] = '/\d+$/';
		}

		// Common prefixes/suffixes
		$common_parts = array( 'img', 'image', 'photo', 'pic', 'dsc', 'img_', 'photo_', 'pic_' );
		foreach ( $common_parts as $part ) {
			if ( stripos( $basename, $part ) === 0 ) {
				$patterns[] = '/^' . preg_quote( $part, '/' ) . '/i';
			}
			if ( stripos( $basename, $part ) !== false ) {
				$patterns[] = '/' . preg_quote( $part, '/' ) . '/i';
			}
		}

		// File extension pattern
		if ( ! empty( $extension ) ) {
			$patterns[] = '/\.' . preg_quote( $extension, '/' ) . '$/i';
		}

		return $patterns;
	}

	/**
	 * Batch match multiple files
	 *
	 * @param array $batch_data Batch matching data
	 * @return array Batch matching results
	 */
	public function batch_match( $batch_data ) {
		$results = array(
			'batches' => array(),
			'total_processed' => 0,
			'total_matches' => 0,
			'processing_time' => 0,
		);

		$start_time = microtime( true );
		$batch_size = isset( $batch_data['batch_size'] ) ? intval( $batch_data['batch_size'] ) : 50;
		$missing_files = $batch_data['missing_files'];
		$available_files = $batch_data['available_files'];
		$options = $batch_data['options'];

		// Process in batches
		$batches = array_chunk( $missing_files, $batch_size );

		foreach ( $batches as $batch_index => $batch ) {
			$batch_results = $this->advanced_match( $batch, $available_files, $options );

			$results['batches'][] = array(
				'batch_index' => $batch_index,
				'processed' => count( $batch ),
				'matches' => count( $batch_results['matches'] ),
				'results' => $batch_results,
			);

			$results['total_processed'] += count( $batch );
			$results['total_matches'] += count( $batch_results['matches'] );

			// Brief pause to prevent server overload
			if ( count( $batches ) > 1 ) {
				usleep( 50000 ); // 0.05 second pause
			}
		}

		$results['processing_time'] = round( microtime( true ) - $start_time, 2 );

		return $results;
	}

	/**
	 * Verify a match by comparing file hashes
	 *
	 * @param string $missing_file_path Path to missing file
	 * @param string $candidate_file_path Path to candidate file
	 * @return array Verification result
	 */
	public function verify_match( $missing_file_path, $candidate_file_path ) {
		$result = array(
			'verified' => false,
			'hash_match' => false,
			'size_match' => false,
			'error' => '',
		);

		if ( ! file_exists( $missing_file_path ) ) {
			$result['error'] = 'Missing file does not exist';
			return $result;
		}

		if ( ! file_exists( $candidate_file_path ) ) {
			$result['error'] = 'Candidate file does not exist';
			return $result;
		}

		// Compare file sizes
		$missing_size = filesize( $missing_file_path );
		$candidate_size = filesize( $candidate_file_path );

		if ( $missing_size === $candidate_size ) {
			$result['size_match'] = true;
		}

		// Compare file hashes
		$missing_hash = md5_file( $missing_file_path );
		$candidate_hash = md5_file( $candidate_file_path );

		if ( $missing_hash === $candidate_hash ) {
			$result['hash_match'] = true;
			$result['verified'] = true;
		}

		return $result;
	}

	/**
	 * AJAX handler for advanced matching
	 */
	public function ajax_advanced_match() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$missing_files = isset( $_POST['missing_files'] ) ? json_decode( wp_unslash( $_POST['missing_files'] ), true ) : array();
		$available_files = isset( $_POST['available_files'] ) ? json_decode( wp_unslash( $_POST['available_files'] ), true ) : array();

		if ( empty( $missing_files ) || empty( $available_files ) ) {
			wp_send_json_error( array( 'message' => 'Missing files or available files data is required' ) );
		}

		// Parse matching options
		$options = array(
			'algorithms' => isset( $_POST['algorithms'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['algorithms'] ) ) : array( 'exact', 'fuzzy', 'meta', 'size' ),
			'threshold' => isset( $_POST['threshold'] ) ? floatval( $_POST['threshold'] ) : 0.7,
			'max_matches' => isset( $_POST['max_matches'] ) ? intval( $_POST['max_matches'] ) : 5,
		);

		// Perform advanced matching
		$results = $this->advanced_match( $missing_files, $available_files, $options );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for batch matching
	 */
	public function ajax_batch_match() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$batch_data = isset( $_POST['batch_data'] ) ? json_decode( wp_unslash( $_POST['batch_data'] ), true ) : array();

		if ( empty( $batch_data ) ) {
			wp_send_json_error( array( 'message' => 'Batch data is required' ) );
		}

		// Perform batch matching
		$results = $this->batch_match( $batch_data );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX handler for match verification
	 */
	public function ajax_verify_match() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$missing_file_path = isset( $_POST['missing_file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['missing_file_path'] ) ) : '';
		$candidate_file_path = isset( $_POST['candidate_file_path'] ) ? sanitize_text_field( wp_unslash( $_POST['candidate_file_path'] ) ) : '';

		if ( empty( $missing_file_path ) || empty( $candidate_file_path ) ) {
			wp_send_json_error( array( 'message' => 'Both file paths are required for verification' ) );
		}

		// Verify match
		$result = $this->verify_match( $missing_file_path, $candidate_file_path );

		wp_send_json_success( $result );
	}
}

// Initialize the pro matcher
new MMR_Pro_Matcher();
