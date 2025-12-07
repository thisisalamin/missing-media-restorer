<?php
/**
 * MMR Pro Performance - Large-scale Operations Optimization
 *
 * @package MissingMediaRestorerPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MMR_Pro_Performance
 */
class MMR_Pro_Performance {

	/**
	 * Performance settings
	 *
	 * @var array
	 */
	private $settings = array(
		'max_execution_time' => 300, // 5 minutes
		'memory_limit' => '512M',
		'batch_size' => 50,
		'chunk_size' => 1024 * 1024, // 1MB
		'concurrent_processes' => 3,
		'cache_ttl' => 3600, // 1 hour
	);

	/**
	 * Verify nonce for AJAX requests
	 *
	 * @param string $nonce Nonce to verify
	 * @param string $action Action name
	 * @return bool True if nonce is valid
	 */
	private function verify_nonce( $nonce, $action = 'mmr_pro_performance' ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Check user capabilities
	 *
	 * @param string $capability Capability to check
	 * @return bool True if user has capability
	 */
	private function check_capability( $capability = 'manage_options' ) {
		return current_user_can( $capability );
	}

	/**
	 * Sanitize input data
	 *
	 * @param mixed $data Data to sanitize
	 * @param string $type Data type
	 * @return mixed Sanitized data
	 */
	private function sanitize_input( $data, $type = 'text' ) {
		switch ( $type ) {
			case 'filename':
				return sanitize_file_name( $data );
			case 'text':
				return sanitize_text_field( $data );
			case 'html':
				return wp_kses_post( $data );
			case 'integer':
				return intval( $data );
			case 'float':
				return floatval( $data );
			default:
				return sanitize_text_field( $data );
		}
	}

	/**
	 * Initialize performance optimization
	 */
	public function __construct() {
		$this->initialize_settings();
		$this->optimize_server_settings();
		$this->setup_caching();
	}

	/**
	 * Initialize performance settings
	 */
	private function initialize_settings() {
		// Get user-defined settings or use defaults
		$user_settings = get_option( 'mmr_pro_performance_settings', array() );
		$this->settings = wp_parse_args( $user_settings, $this->settings );

		// Apply settings
		$this->apply_memory_limit();
		$this->apply_execution_time_limit();
	}

	/**
	 * Optimize server settings for large operations
	 */
	private function optimize_server_settings() {
		// Increase memory limit if needed
		$current_limit = ini_get( 'memory_limit' );
		$required_limit = $this->settings['memory_limit'];

		if ( $this->convert_memory_to_bytes( $current_limit ) < $this->convert_memory_to_bytes( $required_limit ) ) {
			ini_set( 'memory_limit', $required_limit );
		}

		// Set maximum execution time
		ini_set( 'max_execution_time', $this->settings['max_execution_time'] );

		// Optimize other PHP settings for large file operations
		ini_set( 'max_input_time', $this->settings['max_execution_time'] );
		ini_set( 'max_input_vars', 3000 );
		ini_set( 'post_max_size', $this->convert_memory_to_bytes( '100M' ) );
		ini_set( 'upload_max_filesize', $this->convert_memory_to_bytes( '50M' ) );
	}

	/**
	 * Apply memory limit
	 */
	private function apply_memory_limit() {
		$memory_limit = $this->settings['memory_limit'];

		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( $memory_limit );
		}

		// Update WordPress memory constant
		if ( ! defined( 'WP_MEMORY_LIMIT' ) || $this->convert_memory_to_bytes( WP_MEMORY_LIMIT ) < $this->convert_memory_to_bytes( $memory_limit ) ) {
			define( 'WP_MEMORY_LIMIT', $memory_limit );
		}
	}

	/**
	 * Apply execution time limit
	 */
	private function apply_execution_time_limit() {
		$max_time = $this->settings['max_execution_time'];

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( $max_time );
		}
	}

	/**
	 * Set up caching system
	 */
	private function setup_caching() {
		// Create cache directory if it doesn't exist
		$cache_dir = WP_CONTENT_DIR . '/mmr-pro-cache/';
		if ( ! file_exists( $cache_dir ) ) {
			wp_mkdir_p( $cache_dir );
		}
	}

	/**
	 * Get cached data
	 *
	 * @param string $key Cache key
	 * @return mixed|false Cached data or false if not found
	 */
	public function get_cached_data( $key ) {
		$cache_file = $this->get_cache_file_path( $key );

		if ( ! file_exists( $cache_file ) ) {
			return false;
		}

		$cache_data = include $cache_file;

		// Check if cache is still valid
		if ( ! is_array( $cache_data ) || ! isset( $cache_data['timestamp'] ) ) {
			return false;
		}

		$cache_age = time() - $cache_data['timestamp'];
		if ( $cache_age > $this->settings['cache_ttl'] ) {
			$this->clear_cache( $key );
			return false;
		}

		return $cache_data['data'] ?? false;
	}

	/**
	 * Set cached data
	 *
	 * @param string $key Cache key
	 * @param mixed  $data Data to cache
	 * @param int    $ttl Time to live in seconds (optional)
	 * @return bool Success status
	 */
	public function set_cached_data( $key, $data, $ttl = null ) {
		$cache_file = $this->get_cache_file_path( $key );

		$cache_data = array(
			'timestamp' => time(),
			'data' => $data,
		);

		// Use custom TTL or default
		$cache_ttl = $ttl ?? $this->settings['cache_ttl'];

		$result = file_put_contents( $cache_file, '<?php return ' . var_export( $cache_data, true ) . '; ?>' );

		if ( $result === false ) {
			return false;
		}

		// Set cache expiration if custom TTL provided
		if ( $ttl !== $this->settings['cache_ttl'] ) {
			wp_schedule_single_event( time() + $ttl, 'mmr_pro_clear_cache', array( 'key' => $key ) );
		}

		return true;
	}

	/**
	 * Clear cache for specific key
	 *
	 * @param string $key Cache key
	 * @return bool Success status
	 */
	public function clear_cache( $key ) {
		$cache_file = $this->get_cache_file_path( $key );

		if ( file_exists( $cache_file ) ) {
			unlink( $cache_file );
		}

		// Clear scheduled expiration if exists
		wp_unschedule_event( 'mmr_pro_clear_cache', array( 'key' => $key ) );

		return true;
	}

	/**
	 * Clear all cache
	 *
	 * @return bool Success status
	 */
	public function clear_all_cache() {
		$cache_dir = WP_CONTENT_DIR . '/mmr-pro-cache/';

		if ( ! is_dir( $cache_dir ) ) {
			return false;
		}

		$files = glob( $cache_dir . '*.php' );
		$cleared = 0;

		foreach ( $files as $file ) {
			if ( unlink( $file ) ) {
				$cleared++;
			}
		}

		// Clear all scheduled cache clear events
		wp_clear_scheduled_hook( 'mmr_pro_clear_cache' );

		return $cleared;
	}

	/**
	 * Get cache file path
	 *
	 * @param string $key Cache key
	 * @return string Cache file path
	 */
	private function get_cache_file_path( $key ) {
		$safe_key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key );
		return WP_CONTENT_DIR . '/mmr-pro-cache/cache_' . $safe_key . '.php';
	}

	/**
	 * Process large file operations in batches
	 *
	 * @param array  $items Items to process
	 * @param callable $processor Processing function
	 * @param array  $options Processing options
	 * @return array Processing results
	 */
	public function process_in_batches( $items, $processor, $options = array() ) {
		$defaults = array(
			'batch_size' => $this->settings['batch_size'],
			'max_memory_usage' => $this->convert_memory_to_bytes( $this->settings['memory_limit'] ) * 0.8, // Use 80% of memory limit
			'progress_callback' => null,
		);

		$options = wp_parse_args( $options, $defaults );
		$total_items = count( $items );
		$batches = array_chunk( $items, $options['batch_size'] );

		$results = array(
			'total_items' => $total_items,
			'total_batches' => count( $batches ),
			'processed_items' => 0,
			'failed_items' => 0,
			'batches' => array(),
		);

		foreach ( $batches as $batch_index => $batch ) {
			// Check memory usage before processing batch
			$current_memory = memory_get_usage( true );

			if ( $current_memory > $options['max_memory_usage'] ) {
				// Wait for memory to be freed
				$this->wait_for_memory_release();
			}

			$batch_start_time = microtime( true );

			try {
				$batch_results = call_user_func( $processor, array( $batch, $batch_index, $options ) );

				if ( is_array( $batch_results ) ) {
					$results['processed_items'] += $batch_results['processed'] ?? 0;
					$results['failed_items'] += $batch_results['failed'] ?? 0;
					$results['batches'][] = array_merge( $batch_results, array(
						'batch_index' => $batch_index,
						'batch_size' => count( $batch ),
						'memory_before' => $current_memory,
						'memory_after' => memory_get_usage( true ),
						'execution_time' => microtime( true ) - $batch_start_time,
					) );
				}

				// Call progress callback if provided
				if ( is_callable( $options['progress_callback'] ) ) {
					call_user_func( $options['progress_callback'], array(
						'batch_index' => $batch_index,
						'total_batches' => $results['total_batches'],
						'processed_items' => $results['processed_items'],
						'total_items' => $total_items,
					) );
				}

				// Brief pause between batches to prevent server overload
				if ( $batch_index < $results['total_batches'] - 1 ) {
					usleep( 100000 ); // 0.1 seconds
				}

			} catch ( Exception $e ) {
				$results['failed_items'] += count( $batch );
				$results['batches'][] = array(
					'batch_index' => $batch_index,
					'error' => $e->getMessage(),
					'batch_size' => count( $batch ),
				);
			}

			// Check execution time limit
			$execution_time = microtime( true ) - $batch_start_time;
			if ( $execution_time > $this->settings['max_execution_time'] - 10 ) { // Leave 10 seconds buffer
				throw new Exception( 'Batch execution time exceeded limit' );
			}
		}

		return $results;
	}

	/**
	 * Wait for memory to be released
	 */
	private function wait_for_memory_release() {
		// Force garbage collection
		if ( function_exists( 'gc_collect_cycles' ) ) {
			gc_collect_cycles();
		} else {
			gc_collect_cycles();
		}

		// Brief pause to allow system to free memory
		usleep( 500000 ); // 0.5 seconds
	}

	/**
	 * Convert memory string to bytes
	 *
	 * @param string $memory_str Memory string (e.g., "256M")
	 * @return int Memory in bytes
	 */
	private function convert_memory_to_bytes( $memory_str ) {
		$memory_str = strtoupper( trim( $memory_str ) );
		$multiplier = 1;

		if ( strpos( $memory_str, 'G' ) !== false ) {
			$multiplier = 1024 * 1024 * 1024;
		} elseif ( strpos( $memory_str, 'M' ) !== false ) {
			$multiplier = 1024 * 1024;
		} elseif ( strpos( $memory_str, 'K' ) !== false ) {
			$multiplier = 1024;
		}

		return (int) ( (int) $memory_str ) * $multiplier;
	}

	/**
	 * Optimize file copying for large files
	 *
	 * @param string $source Source file path
	 * @param string $destination Destination file path
	 * @param array  $options Copy options
	 * @return bool Success status
	 */
	public function optimized_copy( $source, $destination, $options = array() ) {
		$defaults = array(
			'buffer_size' => 1024 * 1024, // 1MB buffer
			'progress_callback' => null,
		);

		$options = wp_parse_args( $options, $defaults );

		// Check if source file exists
		if ( ! file_exists( $source ) ) {
			return false;
		}

		$file_size = filesize( $source );
		$bytes_copied = 0;
		$start_time = microtime( true );

		// Create destination directory if it doesn't exist
		$dest_dir = dirname( $destination );
		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}

		// Open source and destination files
		$source_handle = fopen( $source, 'rb' );
		$dest_handle = fopen( $destination, 'wb' );

		if ( ! $source_handle || ! $dest_handle ) {
			return false;
		}

		try {
			while ( ! feof( $source_handle ) && $bytes_copied < $file_size ) {
				// Read chunk
				$chunk = fread( $source_handle, $options['buffer_size'] );

				if ( $chunk === false ) {
					break;
				}

				// Write chunk
				$bytes_written = fwrite( $dest_handle, $chunk );

				if ( $bytes_written === false ) {
					break;
				}

				$bytes_copied += $bytes_written;

				// Call progress callback if provided
				if ( is_callable( $options['progress_callback'] ) ) {
					call_user_func( $options['progress_callback'], array(
						'bytes_copied' => $bytes_copied,
						'total_bytes' => $file_size,
						'progress_percent' => ( $bytes_copied / $file_size ) * 100,
					) );
				}

				// Check execution time
				$execution_time = microtime( true ) - $start_time;
				if ( $execution_time > $this->settings['max_execution_time'] - 10 ) {
					break;
				}
			}

			// Verify file integrity
			$success = ( $bytes_copied === $file_size ) && filesize( $destination ) === $file_size;

		} catch ( Exception $e ) {
			$success = false;
		} finally {
			// Close file handles
			if ( $source_handle ) {
				fclose( $source_handle );
			}
			if ( $dest_handle ) {
				fclose( $dest_handle );
			}
		}

		return $success;
	}

	/**
	 * Get performance metrics
	 *
	 * @return array Performance metrics
	 */
	public function get_performance_metrics() {
		global $wpdb;

		return array(
			'memory_limit' => ini_get( 'memory_limit' ),
			'memory_usage' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size' => ini_get( 'post_max_size' ),
			'max_input_vars' => ini_get( 'max_input_vars' ),
			'wp_memory_limit' => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'Not defined',
			'db_queries' => $wpdb->num_queries,
			'db_query_time' => $wpdb->num_queries > 0 ? $wpdb->query_time : 0,
			'cache_stats' => $this->get_cache_stats(),
			'server_load' => $this->get_server_load(),
			'disk_space' => $this->get_disk_space(),
		);
	}

	/**
	 * Get cache statistics
	 *
	 * @return array Cache statistics
	 */
	private function get_cache_stats() {
		$cache_dir = WP_CONTENT_DIR . '/mmr-pro-cache/';

		if ( ! is_dir( $cache_dir ) ) {
			return array(
				'cache_dir_exists' => false,
				'cache_files' => 0,
				'total_size' => 0,
			);
		}

		$files = glob( $cache_dir . '*.php' );
		$total_size = 0;

		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$total_size += filesize( $file );
			}
		}

		return array(
			'cache_dir_exists' => true,
			'cache_files' => count( $files ),
			'total_size' => $total_size,
		);
	}

	/**
	 * Get server load information
	 *
	 * @return array Server load information
	 */
	private function get_server_load() {
		$load = array();

		if ( function_exists( 'sys_getloadavg' ) ) {
			$load_avg = sys_getloadavg();
			$load['1_min'] = $load_avg[0] ?? 0;
			$load['5_mins'] = $load_avg[1] ?? 0;
			$load['15_mins'] = $load_avg[2] ?? 0;
		}

		if ( function_exists( 'shell_exec' ) ) {
			$cpu_usage = shell_exec( 'ps aux | awk \'{usage+=$3} {print $1} END\' | sort -nr | tail -1' );
			$load['cpu_usage'] = trim( $cpu_usage );
		}

		return $load;
	}

	/**
	 * Get disk space information
	 *
	 * @return array Disk space information
	 */
	private function get_disk_space() {
		$upload_dir = wp_upload_dir();
		$total_space = disk_total_space( $upload_dir['basedir'] );
		$free_space = disk_free_space( $upload_dir['basedir'] );

		return array(
			'total_space' => $total_space,
			'free_space' => $free_space,
			'used_space' => $total_space - $free_space,
			'upload_dir' => $upload_dir['basedir'],
		);
	}

	/**
	 * Clean up temporary files and optimize storage
	 *
	 * @return array Cleanup results
	 */
	public function cleanup_temp_files() {
		$temp_dirs = array(
			WP_CONTENT_DIR . '/mmr-temp/',
			WP_CONTENT_DIR . '/mmr-pro-cache/',
			sys_get_temp_dir(),
		);

		$results = array(
			'cleaned_files' => 0,
			'freed_space' => 0,
			'errors' => array(),
		);

		foreach ( $temp_dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$files = $this->scan_directory( $dir );
			$dir_freed_space = 0;

			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					$file_size = filesize( $file );

					if ( unlink( $file ) ) {
						$results['cleaned_files']++;
						$dir_freed_space += $file_size;
					} else {
						$results['errors'][] = "Failed to delete: {$file}";
					}
				}
			}

			$results['freed_space'] += $dir_freed_space;
		}

		return $results;
	}

	/**
	 * Scan directory for cleanup
	 *
	 * @param string $directory Directory path
	 * @return array Files in directory
	 */
	private function scan_directory( $directory ) {
		$files = array();

		if ( ! is_dir( $directory ) ) {
			return $files;
		}

		$iterator = new DirectoryIterator( $directory );

		foreach ( $iterator as $fileinfo ) {
			if ( $fileinfo->isDot() ) {
				continue;
			}

			if ( $fileinfo->isFile() ) {
				$files[] = $fileinfo->getPathname();
			}
		}

		return $files;
	}

	/**
	 * Schedule automatic cleanup
	 */
	public function schedule_cleanup() {
		// Schedule daily cleanup
		if ( ! wp_next_scheduled( 'mmr_pro_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'mmr_pro_cleanup' );
		}

		add_action( 'mmr_pro_cleanup', array( $this, 'perform_automatic_cleanup' ) );
	}

	/**
	 * Perform automatic cleanup
	 */
	public function perform_automatic_cleanup() {
		$cleanup_results = $this->cleanup_temp_files();

		// Log cleanup results
		if ( $cleanup_results['cleaned_files'] > 0 ) {
			// Log successful cleanup
			error_log( "MMR Pro: Cleaned up {$cleanup_results['cleaned_files']} temporary files, freed {$cleanup_results['freed_space']} bytes" );
		}

		// Clear expired cache entries
		$this->clear_expired_cache();
	}

	/**
	 * Clear expired cache entries
	 */
	private function clear_expired_cache() {
		$cache_dir = WP_CONTENT_DIR . '/mmr-pro-cache/';

		if ( ! is_dir( $cache_dir ) ) {
			return;
		}

		$files = glob( $cache_dir . '*.php' );
		$current_time = time();
		$cleared = 0;

		foreach ( $files as $file ) {
			if ( ! is_file( $file ) ) {
				continue;
			}

			$cache_data = include $file;

			if ( is_array( $cache_data ) && isset( $cache_data['timestamp'] ) ) {
				$cache_age = $current_time - $cache_data['timestamp'];

				// Clear cache older than 24 hours
				if ( $cache_age > 86400 ) {
					if ( unlink( $file ) ) {
						$cleared++;
					}
				}
			}
		}

		if ( $cleared > 0 ) {
			error_log( "MMR Pro: Cleared {$cleared} expired cache entries" );
		}
	}
}

// Initialize performance optimization
global $mmr_pro_performance;
$mmr_pro_performance = new MMR_Pro_Performance();
