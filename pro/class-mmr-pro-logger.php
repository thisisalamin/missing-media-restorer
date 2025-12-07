<?php
/**
 * MMR Pro Logger - Comprehensive Error Handling and Logging
 *
 * @package MissingMediaRestorerPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MMR_Pro_Logger
 */
class MMR_Pro_Logger {

	/**
	 * Verify nonce for AJAX requests
	 *
	 * @param string $nonce Nonce to verify
	 * @param string $action Action name
	 * @return bool True if nonce is valid
	 */
	private function verify_nonce( $nonce, $action = 'mmr_pro_logger' ) {
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
			default:
				return sanitize_text_field( $data );
		}
	}

	/**
	 * Log levels
	 *
	 * @var array
	 */
	private $log_levels = array(
		'emergency' => 0,
		'alert'     => 1,
		'critical'  => 2,
		'error'     => 3,
		'warning'   => 4,
		'notice'    => 5,
		'info'      => 6,
		'debug'     => 7,
	);

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Maximum log file size in bytes
	 *
	 * @var int
	 */
	private $max_log_size = 5 * 1024 * 1024; // 5MB

	/**
	 * Initialize the logger
	 */
	public function __construct() {
		$this->log_file = WP_CONTENT_DIR . '/mmr-pro-logs/mmr-pro-' . date( 'Y-m-d' ) . '.log';

		// Ensure log directory exists
		$log_dir = dirname( $this->log_file );
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Set up error handlers
		set_error_handler( array( $this, 'handle_error' ) );
		set_exception_handler( array( $this, 'handle_exception' ) );
		register_shutdown_function( array( $this, 'handle_shutdown' ) );
	}

	/**
	 * Log a message with specified level
	 *
	 * @param string $level Log level
	 * @param string $message Log message
	 * @param array  $context Additional context
	 * @return bool Success status
	 */
	public function log( $level, $message, $context = array() ) {
		if ( ! isset( $this->log_levels[ $level ] ) ) {
			$level = 'info';
		}

		$timestamp = current_time( 'mysql' );
		$user_id = get_current_user_id();
		$session_id = $this->get_session_id();

		$log_entry = array(
			'timestamp' => $timestamp,
			'level' => $level,
			'level_numeric' => $this->log_levels[ $level ],
			'message' => $message,
			'context' => $context,
			'user_id' => $user_id,
			'session_id' => $session_id,
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '',
			'request_method' => isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '',
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'ip_address' => $this->get_client_ip(),
			'memory_usage' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
		);

		return $this->write_log( $log_entry );
	}

	/**
	 * Emergency level logging
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public function emergency( $message, $context = array() ) {
		return $this->log( 'emergency', $message, $context );
	}

	/**
	 * Alert level logging
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public function alert( $message, $context = array() ) {
		return $this->log( 'alert', $message, $context );
	}

	/**
	 * Critical level logging
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public function critical( $message, $context = array() ) {
		return $this->log( 'critical', $message, $context );
	}

	/**
	 * Error level logging
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public function error( $message, $context = array() ) {
		return $this->log( 'error', $message, $context );
	}

	/**
	 * Warning level logging
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public function warning( $message, $context = array() ) {
		return $this->log( 'warning', $message, $context );
	}

	/**
	 * Notice level logging
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public function notice( $message, $context = array() ) {
		return $this->log( 'notice', $message, $context );
	}

	/**
	 * Info level logging
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public function info( $message, $context = array() ) {
		return $this->log( 'info', $message, $context );
	}

	/**
	 * Debug level logging
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context
	 */
	public function debug( $message, $context = array() ) {
		return $this->log( 'debug', $message, $context );
	}

	/**
	 * Log operation start
	 *
	 * @param string $operation Operation name
	 * @param array  $context Additional context
	 */
	public function log_operation_start( $operation, $context = array() ) {
		return $this->info( "Operation started: {$operation}", array_merge( $context, array( 'operation' => $operation, 'status' => 'started' ) ) );
	}

	/**
	 * Log operation completion
	 *
	 * @param string $operation Operation name
	 * @param array  $results Operation results
	 * @param array  $context Additional context
	 */
	public function log_operation_complete( $operation, $results = array(), $context = array() ) {
		return $this->info( "Operation completed: {$operation}", array_merge( $context, array( 'operation' => $operation, 'status' => 'completed', 'results' => $results ) ) );
	}

	/**
	 * Log operation failure
	 *
	 * @param string $operation Operation name
	 * @param string $error Error message
	 * @param array  $context Additional context
	 */
	public function log_operation_failure( $operation, $error, $context = array() ) {
		return $this->error( "Operation failed: {$operation}", array_merge( $context, array( 'operation' => $operation, 'status' => 'failed', 'error' => $error ) ) );
	}

	/**
	 * Log file operation
	 *
	 * @param string $operation File operation type
	 * @param string $file_path File path
	 * @param bool  $success Success status
	 * @param array  $context Additional context
	 */
	public function log_file_operation( $operation, $file_path, $success, $context = array() ) {
		$level = $success ? 'info' : 'error';
		$message = $success ? "File {$operation}: {$file_path}" : "File {$operation} failed: {$file_path}";

		return $this->log( $level, $message, array_merge( $context, array( 'file_operation' => $operation, 'file_path' => $file_path, 'success' => $success ) ) );
	}

	/**
	 * Log database operation
	 *
	 * @param string $operation Database operation type
	 * @param string $query SQL query (sanitized)
	 * @param bool  $success Success status
	 * @param array  $context Additional context
	 */
	public function log_database_operation( $operation, $query, $success, $context = array() ) {
		$level = $success ? 'debug' : 'error';
		$message = $success ? "Database {$operation}" : "Database {$operation} failed";

		return $this->log( $level, $message, array_merge( $context, array( 'database_operation' => $operation, 'query' => $query, 'success' => $success ) ) );
	}

	/**
	 * Log security event
	 *
	 * @param string $event Security event type
	 * @param array  $context Additional context
	 */
	public function log_security_event( $event, $context = array() ) {
		return $this->warning( "Security event: {$event}", array_merge( $context, array( 'security_event' => $event ) ) );
	}

	/**
	 * Log performance metrics
	 *
	 * @param string $operation Operation name
	 * @param float  $duration Operation duration in seconds
	 * @param array  $metrics Performance metrics
	 * @param array  $context Additional context
	 */
	public function log_performance( $operation, $duration, $metrics = array(), $context = array() ) {
		return $this->info( "Performance: {$operation}", array_merge( $context, array(
			'operation' => $operation,
			'duration' => $duration,
			'metrics' => $metrics,
			'memory_usage' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
		) ) );
	}

	/**
	 * Write log entry to file
	 *
	 * @param array $log_entry Log entry data
	 * @return bool Success status
	 */
	private function write_log( $log_entry ) {
		$log_line = $this->format_log_entry( $log_entry );

		// Check file size and rotate if necessary
		if ( file_exists( $this->log_file ) && filesize( $this->log_file ) > $this->max_log_size ) {
			$this->rotate_log();
		}

		// Write to log file
		$result = file_put_contents( $this->log_file, $log_line . PHP_EOL, FILE_APPEND | LOCK_EX );

		if ( $result === false ) {
			// Fallback to error log if file write fails
			error_log( "MMR Pro Logger: Failed to write to log file: {$this->log_file}" );
			return false;
		}

		return true;
	}

	/**
	 * Format log entry as JSON string
	 *
	 * @param array $log_entry Log entry data
	 * @return string Formatted log line
	 */
	private function format_log_entry( $log_entry ) {
		return json_encode( $log_entry );
	}

	/**
	 * Rotate log file when it gets too large
	 */
	private function rotate_log() {
		$log_dir = dirname( $this->log_file );
		$timestamp = date( 'Y-m-d-H-i-s' );

		// Create backup of current log
		$backup_file = $log_dir . '/mmr-pro-' . $timestamp . '.log.backup';
		if ( file_exists( $this->log_file ) ) {
			rename( $this->log_file, $backup_file );
		}
	}

	/**
	 * Get current session ID
	 *
	 * @return string Session ID
	 */
	private function get_session_id() {
		if ( ! isset( $_SESSION['mmr_pro_session_id'] ) ) {
			$_SESSION['mmr_pro_session_id'] = wp_generate_password( 16, false );
		}

		return $_SESSION['mmr_pro_session_id'];
	}

	/**
	 * Get client IP address
	 *
	 * @return string Client IP
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );
		$server_vars = array();

		// Safely get server variables
		if ( isset( $_SERVER ) && is_array( $_SERVER ) ) {
			foreach ( $ip_keys as $key ) {
				if ( isset( $_SERVER[ $key ] ) ) {
					$server_vars[ $key ] = $_SERVER[ $key ];
				}
			}
		}

		foreach ( $ip_keys as $key ) {
			if ( isset( $server_vars[ $key ] ) ) {
				foreach ( array_map( 'trim', explode( ',', $server_vars[ $key ] ) ) as $ip ) {
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return isset( $server_vars['REMOTE_ADDR'] ) ? $server_vars['REMOTE_ADDR'] : 'unknown';
	}

	/**
	 * Handle PHP errors
	 *
	 * @param int    $errno Error number
	 * @param string $errstr Error string
	 * @param string $errfile Error file
	 * @param int    $errline Error line
	 */
	public function handle_error( $errno, $errstr, $errfile, $errline ) {
		// Don't log suppressed errors
		if ( ! ( error_reporting() & $errno ) ) {
			return;
		}

		$error_types = array(
			E_ERROR             => 'Fatal Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parse Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING     => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING  => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING     => 'User Warning',
			E_USER_NOTICE      => 'User Notice',
			E_STRICT           => 'Strict Notice',
			E_RECOVERABLE_ERROR => 'Recoverable Error',
			E_DEPRECATED       => 'Deprecated',
		);

		$error_type = $error_types[ $errno ] ?? 'Unknown Error';

		$this->error( "PHP Error: {$error_type}", array(
			'errno' => $errno,
			'errstr' => $errstr,
			'errfile' => $errfile,
			'errline' => $errline,
			'error_type' => $error_type,
		) );
	}

	/**
	 * Handle uncaught exceptions
	 *
	 * @param Throwable $exception The exception
	 */
	public function handle_exception( $exception ) {
		$this->error( "Uncaught Exception", array(
			'exception_type' => get_class( $exception ),
			'message' => $exception->getMessage(),
			'code' => $exception->getCode(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString(),
		) );
	}

	/**
	 * Handle fatal errors on shutdown
	 */
	public function handle_shutdown() {
		$error = error_get_last();

		if ( $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			$this->error( "Fatal Error on Shutdown", array(
				'type' => $error['type'],
				'message' => $error['message'],
				'file' => $error['file'],
				'line' => $error['line'],
			) );
		}
	}

	/**
	 * Get recent log entries
	 *
	 * @param int    $limit Number of entries to return
	 * @param string $level Filter by log level
	 * @return array Recent log entries
	 */
	public function get_recent_logs( $limit = 100, $level = null ) {
		if ( ! file_exists( $this->log_file ) ) {
			return array();
		}

		$lines = file( $this->log_file );
		if ( $lines === false ) {
			return array();
		}

		$logs = array();
		$line_count = count( $lines );

		// Start from the end of the file
		for ( $i = $line_count - 1; $i >= 0 && count( $logs ) < $limit; $i-- ) {
			$line = $lines[ $i ];

			if ( trim( $line ) === '' ) {
				continue;
			}

			$log_entry = json_decode( $line, true );
			if ( $log_entry === null ) {
				continue;
			}

			// Filter by level if specified
			if ( $level && $log_entry['level'] !== $level ) {
				continue;
			}

			$logs[] = $log_entry;
		}

		return array_reverse( $logs );
	}

	/**
	 * Clear old log files
	 *
	 * @param int $days Number of days to keep
	 */
	public function clear_old_logs( $days = 30 ) {
		$log_dir = dirname( $this->log_file );
		$cutoff_time = time() - ( $days * 24 * 60 * 60 );

		if ( ! is_dir( $log_dir ) ) {
			return false;
		}

		$files = glob( $log_dir . '*.log*' );
		$cleared = 0;

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff_time ) {
				if ( unlink( $file ) ) {
					$cleared++;
				}
			}
		}

		$this->info( "Log cleanup completed", array(
			'days_kept' => $days,
			'files_cleared' => $cleared,
		) );

		return $cleared;
	}

	/**
	 * Get log statistics
	 *
	 * @param int $days Number of days to analyze
	 * @return array Log statistics
	 */
	public function get_log_statistics( $days = 7 ) {
		$logs = $this->get_recent_logs( 10000 ); // Get large sample

		$stats = array(
			'total_entries' => count( $logs ),
			'by_level' => array(),
			'by_operation' => array(),
			'by_user' => array(),
			'error_rate' => 0,
			'avg_memory_usage' => 0,
			'peak_memory' => 0,
		);

		$cutoff_time = time() - ( $days * 24 * 60 * 60 );
		$total_memory = 0;
		$error_count = 0;
		$memory_sum = 0;
		$memory_count = 0;

		foreach ( $logs as $log ) {
			// Filter by date
			$log_time = strtotime( $log['timestamp'] );
			if ( $log_time < $cutoff_time ) {
				continue;
			}

			// Count by level
			$level = $log['level'] ?? 'info';
			if ( ! isset( $stats['by_level'][ $level ] ) ) {
				$stats['by_level'][ $level ] = 0;
			}
			$stats['by_level'][ $level ]++;

			// Count by operation
			if ( isset( $log['operation'] ) ) {
				$operation = $log['operation'];
				if ( ! isset( $stats['by_operation'][ $operation ] ) ) {
					$stats['by_operation'][ $operation ] = 0;
				}
				$stats['by_operation'][ $operation ]++;
			}

			// Count by user
			if ( isset( $log['user_id'] ) && $log['user_id'] > 0 ) {
				$user_id = $log['user_id'];
				if ( ! isset( $stats['by_user'][ $user_id ] ) ) {
					$stats['by_user'][ $user_id ] = 0;
				}
				$stats['by_user'][ $user_id ]++;
			}

			// Calculate error rate
			if ( in_array( $level, array( 'error', 'critical', 'emergency', 'alert' ) ) ) {
				$error_count++;
			}

			// Calculate memory statistics
			if ( isset( $log['memory_usage'] ) ) {
				$memory_sum += $log['memory_usage'];
				$memory_count++;
			}

			if ( isset( $log['peak_memory'] ) ) {
				$stats['peak_memory'] = max( $stats['peak_memory'], $log['peak_memory'] );
			}
		}

		$stats['error_rate'] = $stats['total_entries'] > 0 ? ( $error_count / $stats['total_entries'] ) * 100 : 0;
		$stats['avg_memory_usage'] = $memory_count > 0 ? $memory_sum / $memory_count : 0;

		return $stats;
	}

	/**
	 * Export logs to file
	 *
	 * @param string $format Export format (json, csv)
	 * @param array  $filters Export filters
	 * @return string|false Export data or false on failure
	 */
	public function export_logs( $format = 'json', $filters = array() ) {
		$logs = $this->get_recent_logs( 10000 );

		// Apply filters
		if ( ! empty( $filters ) ) {
			$filtered_logs = array_filter( $logs, function( $log ) use ( $filters ) {
				foreach ( $filters as $key => $value ) {
					if ( isset( $log[ $key ] ) && $log[ $key ] !== $value ) {
						return false;
					}
				}
				return true;
			} );
		} else {
			$filtered_logs = $logs;
		}

		switch ( $format ) {
			case 'csv':
				return $this->export_logs_csv( $filtered_logs );

			case 'json':
			default:
				return json_encode( $filtered_logs, JSON_PRETTY_PRINT );
		}
	}

	/**
	 * Export logs as CSV
	 *
	 * @param array $logs Log entries
	 * @return string CSV formatted logs
	 */
	private function export_logs_csv( $logs ) {
		if ( empty( $logs ) ) {
			return '';
		}

		$csv = "Timestamp,Level,Message,User ID,Session ID,Operation,File Path,Memory Usage,Peak Memory\n";

		foreach ( $logs as $log ) {
			$timestamp = $log['timestamp'] ?? '';
			$level = $log['level'] ?? '';
			$message = str_replace( '"', '""', $log['message'] ?? '' );
			$user_id = $log['user_id'] ?? '';
			$session_id = $log['session_id'] ?? '';
			$operation = $log['operation'] ?? '';
			$file_path = isset( $log['context']['file_path'] ) ? str_replace( '"', '""', $log['context']['file_path'] ) : '';
			$memory_usage = isset( $log['memory_usage'] ) ? $log['memory_usage'] : '';
			$peak_memory = isset( $log['peak_memory'] ) ? $log['peak_memory'] : '';

			$csv .= "\"{$timestamp}\",\"{$level}\",\"{$message}\",\"{$user_id}\",\"{$session_id}\",\"{$operation}\",\"{$file_path}\",\"{$memory_usage}\",\"{$peak_memory}\"\n";
		}

		return $csv;
	}
}

// Initialize the logger
global $mmr_pro_logger;
$mmr_pro_logger = new MMR_Pro_Logger();
