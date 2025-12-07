<?php
/**
 * MMR Pro Analytics - Progress Analytics and Reporting
 *
 * @package MissingMediaRestorerPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MMR_Pro_Analytics
 */
class MMR_Pro_Analytics {

	/**
	 * Analytics data table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Initialize the pro analytics
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'mmr_analytics';

		// Register AJAX handlers
		add_action( 'wp_ajax_mmr_pro_get_analytics', array( $this, 'ajax_get_analytics' ) );
		add_action( 'wp_ajax_mmr_pro_generate_report', array( $this, 'ajax_generate_report' ) );
		add_action( 'wp_ajax_mmr_pro_track_progress', array( $this, 'ajax_track_progress' ) );
		add_action( 'wp_ajax_mmr_pro_export_analytics', array( $this, 'ajax_export_analytics' ) );

		// Initialize table on plugin activation
		add_action( 'admin_init', array( $this, 'create_analytics_table' ) );

		// Schedule daily analytics cleanup
		if ( ! wp_next_scheduled( 'mmr_pro_cleanup_analytics' ) ) {
			wp_schedule_event( time(), 'daily', 'mmr_pro_cleanup_analytics' );
		}
		add_action( 'mmr_pro_cleanup_analytics', array( $this, 'cleanup_old_analytics' ) );
	}

	/**
	 * Create analytics table
	 */
	public function create_analytics_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			session_id varchar(100) NOT NULL,
			user_id bigint(20) NOT NULL,
			action_type varchar(50) NOT NULL,
			action_data longtext,
			status varchar(20) NOT NULL DEFAULT 'pending',
			progress int(11) NOT NULL DEFAULT 0,
			total_items int(11) NOT NULL DEFAULT 0,
			processed_items int(11) NOT NULL DEFAULT 0,
			failed_items int(11) NOT NULL DEFAULT 0,
			start_time datetime DEFAULT NULL,
			end_time datetime DEFAULT NULL,
			duration float DEFAULT NULL,
			memory_usage bigint(20) DEFAULT NULL,
			peak_memory bigint(20) DEFAULT NULL,
			server_load varchar(50) DEFAULT NULL,
			error_message text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY user_id (user_id),
			KEY action_type (action_type),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Track operation progress
	 *
	 * @param string $session_id Unique session identifier
	 * @param string $action_type Type of action (scan, upload, match, restore)
	 * @param array  $data Action data
	 * @param array  $progress Progress information
	 * @return bool Success status
	 */
	public function track_progress( $session_id, $action_type, $data = array(), $progress = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => 'pending',
			'progress' => 0,
			'total_items' => 0,
			'processed_items' => 0,
			'failed_items' => 0,
			'error_message' => '',
		);

		$progress = wp_parse_args( $progress, $defaults );

		// Check if session exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE session_id = %s",
			$session_id
		) );

		$data_array = array(
			'user_id' => get_current_user_id(),
			'action_type' => $action_type,
			'action_data' => wp_json_encode( $data ),
			'status' => $progress['status'],
			'progress' => $progress['progress'],
			'total_items' => $progress['total_items'],
			'processed_items' => $progress['processed_items'],
			'failed_items' => $progress['failed_items'],
			'error_message' => $progress['error_message'],
			'memory_usage' => memory_get_usage( true ),
			'peak_memory' => memory_get_peak_usage( true ),
			'server_load' => $this->get_server_load(),
		);

		if ( $existing ) {
			// Update existing record
			$data_array['updated_at'] = current_time( 'mysql' );

			// Set end time if completed
			if ( in_array( $progress['status'], array( 'completed', 'failed', 'cancelled' ), true ) ) {
				$data_array['end_time'] = current_time( 'mysql' );

				// Calculate duration
				$start_time = $wpdb->get_var( $wpdb->prepare(
					"SELECT start_time FROM {$this->table_name} WHERE session_id = %s",
					$session_id
				) );

				if ( $start_time ) {
					$start = strtotime( $start_time );
					$end = strtotime( $data_array['end_time'] );
					$data_array['duration'] = $end - $start;
				}
			}

			$result = $wpdb->update( $this->table_name, $data_array, array( 'session_id' => $session_id ) );
		} else {
			// Insert new record
			$data_array['session_id'] = $session_id;
			$data_array['start_time'] = current_time( 'mysql' );

			$result = $wpdb->insert( $this->table_name, $data_array );
		}

		return false !== $result;
	}

	/**
	 * Get analytics data
	 *
	 * @param array $args Query arguments
	 * @return array Analytics data
	 */
	public function get_analytics( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id' => null,
			'action_type' => null,
			'status' => null,
			'date_from' => null,
			'date_to' => null,
			'limit' => 100,
			'offset' => 0,
			'orderby' => 'created_at',
			'order' => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$values = array();

		if ( $args['user_id'] ) {
			$where_clauses[] = 'user_id = %d';
			$values[] = $args['user_id'];
		}

		if ( $args['action_type'] ) {
			$where_clauses[] = 'action_type = %s';
			$values[] = $args['action_type'];
		}

		if ( $args['status'] ) {
			$where_clauses[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['date_from'] ) {
			$where_clauses[] = 'created_at >= %s';
			$values[] = $args['date_from'];
		}

		if ( $args['date_to'] ) {
			$where_clauses[] = 'created_at <= %s';
			$values[] = $args['date_to'];
		}

		$where_clause = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$sql = "SELECT * FROM {$this->table_name} {$where_clause} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
		$values[] = $args['limit'];
		$values[] = $args['offset'];

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		// Decode JSON data
		foreach ( $results as $result ) {
			$result->action_data = json_decode( $result->action_data, true );
		}

		return $results;
	}

	/**
	 * Get analytics summary statistics
	 *
	 * @param array $args Query arguments
	 * @return array Summary statistics
	 */
	public function get_analytics_summary( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'date_from' => null,
			'date_to' => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$values = array();

		if ( $args['date_from'] ) {
			$where_clauses[] = 'created_at >= %s';
			$values[] = $args['date_from'];
		}

		if ( $args['date_to'] ) {
			$where_clauses[] = 'created_at <= %s';
			$values[] = $args['date_to'];
		}

		$where_clause = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Get summary by action type
		$sql = "SELECT
			action_type,
			COUNT(*) as total_operations,
			SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
			SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
			SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
			AVG(duration) as avg_duration,
			SUM(processed_items) as total_processed,
			SUM(failed_items) as total_failed,
			AVG(progress) as avg_progress
			FROM {$this->table_name} {$where_clause}
			GROUP BY action_type";

		$by_action = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		// Get overall statistics
		$sql = "SELECT
			COUNT(*) as total_operations,
			SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
			SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
			SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
			AVG(duration) as avg_duration,
			SUM(processed_items) as total_processed,
			SUM(failed_items) as total_failed,
			AVG(progress) as avg_progress,
			MAX(peak_memory) as max_memory
			FROM {$this->table_name} {$where_clause}";

		$overall = $wpdb->get_row( $wpdb->prepare( $sql, $values ) );

		return array(
			'by_action_type' => $by_action,
			'overall' => $overall,
		);
	}

	/**
	 * Generate detailed report
	 *
	 * @param array $args Report arguments
	 * @return array Report data
	 */
	public function generate_report( $args = array() ) {
		$defaults = array(
			'report_type' => 'summary',
			'date_from' => date( 'Y-m-d', strtotime( '-30 days' ) ),
			'date_to' => date( 'Y-m-d' ),
			'user_id' => null,
			'format' => 'array',
		);

		$args = wp_parse_args( $args, $defaults );

		$report_data = array(
			'report_type' => $args['report_type'],
			'generated_at' => current_time( 'mysql' ),
			'period' => array(
				'from' => $args['date_from'],
				'to' => $args['date_to'],
			),
			'data' => array(),
		);

		switch ( $args['report_type'] ) {
			case 'summary':
				$report_data['data'] = $this->get_analytics_summary( $args );
				break;

			case 'detailed':
				$report_data['data']['analytics'] = $this->get_analytics( $args );
				$report_data['data']['summary'] = $this->get_analytics_summary( $args );
				break;

			case 'performance':
				$report_data['data'] = $this->get_performance_report( $args );
				break;

			case 'usage':
				$report_data['data'] = $this->get_usage_report( $args );
				break;
		}

		// Format output
		if ( 'json' === $args['format'] ) {
			return wp_json_encode( $report_data );
		} elseif ( 'csv' === $args['format'] ) {
			return $this->format_as_csv( $report_data );
		}

		return $report_data;
	}

	/**
	 * Get performance report
	 *
	 * @param array $args Report arguments
	 * @return array Performance data
	 */
	private function get_performance_report( $args ) {
		global $wpdb;

		$where_clauses = array();
		$values = array();

		if ( $args['date_from'] ) {
			$where_clauses[] = 'created_at >= %s';
			$values[] = $args['date_from'];
		}

		if ( $args['date_to'] ) {
			$where_clauses[] = 'created_at <= %s';
			$values[] = $args['date_to'];
		}

		$where_clause = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$sql = "SELECT
			action_type,
			AVG(duration) as avg_duration,
			MAX(duration) as max_duration,
			MIN(duration) as min_duration,
			AVG(memory_usage) as avg_memory,
			MAX(peak_memory) as max_memory,
			AVG(processed_items) as avg_items_per_op,
			SUM(processed_items) / SUM(duration) as items_per_second
			FROM {$this->table_name} {$where_clause}
			GROUP BY action_type";

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Get usage report
	 *
	 * @param array $args Report arguments
	 * @return array Usage data
	 */
	private function get_usage_report( $args ) {
		global $wpdb;

		$where_clauses = array();
		$values = array();

		if ( $args['date_from'] ) {
			$where_clauses[] = 'created_at >= %s';
			$values[] = $args['date_from'];
		}

		if ( $args['date_to'] ) {
			$where_clauses[] = 'created_at <= %s';
			$values[] = $args['date_to'];
		}

		$where_clause = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Usage by user
		$sql = "SELECT
			user_id,
			COUNT(*) as operations,
			SUM(processed_items) as total_processed,
			AVG(duration) as avg_duration
			FROM {$this->table_name} {$where_clause}
			GROUP BY user_id
			ORDER BY operations DESC
			LIMIT 10";

		$by_user = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		// Usage by date
		$sql = "SELECT
			DATE(created_at) as date,
			COUNT(*) as operations,
			SUM(processed_items) as total_processed
			FROM {$this->table_name} {$where_clause}
			GROUP BY DATE(created_at)
			ORDER BY date DESC
			LIMIT 30";

		$by_date = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		return array(
			'by_user' => $by_user,
			'by_date' => $by_date,
		);
	}

	/**
	 * Format report data as CSV
	 *
	 * @param array $report_data Report data
	 * @return string CSV formatted data
	 */
	private function format_as_csv( $report_data ) {
		$csv = '';
		$headers = array();

		// Add headers based on report type
		switch ( $report_data['report_type'] ) {
			case 'summary':
				$headers = array( 'Action Type', 'Total Operations', 'Completed', 'Failed', 'Pending', 'Avg Duration', 'Total Processed', 'Total Failed' );
				break;
			case 'performance':
				$headers = array( 'Action Type', 'Avg Duration', 'Max Duration', 'Min Duration', 'Avg Memory', 'Max Memory', 'Avg Items/Op', 'Items/Second' );
				break;
		}

		if ( ! empty( $headers ) ) {
			$csv .= implode( ',', $headers ) . "\n";

			// Add data rows
			foreach ( $report_data['data'] as $row ) {
				$values = array();
				foreach ( $headers as $header ) {
					$key = strtolower( str_replace( ' ', '_', $header ) );
					$values[] = isset( $row->$key ) ? $row->$key : '';
				}
				$csv .= implode( ',', $values ) . "\n";
			}
		}

		return $csv;
	}

	/**
	 * Get current server load
	 *
	 * @return string Server load information
	 */
	private function get_server_load() {
		if ( function_exists( 'sys_getloadavg' ) ) {
			$load = sys_getloadavg();
			return implode( ', ', $load );
		}

		// Fallback for Windows
		if ( function_exists( 'shell_exec' ) ) {
			$load = shell_exec( 'typeperf "\\Processor(_Total)\\% Processor Time" -sc 1' );
			if ( $load ) {
				preg_match( '/\d+\.\d+/', $load, $matches );
				return isset( $matches[0] ) ? $matches[0] . '%' : 'Unknown';
			}
		}

		return 'Unknown';
	}

	/**
	 * Clean up old analytics data
	 *
	 * @param int $days Number of days to keep data
	 */
	public function cleanup_old_analytics( $days = 90 ) {
		global $wpdb;

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE created_at < %s",
			$cutoff_date
		) );
	}

	/**
	 * AJAX handler for getting analytics
	 */
	public function ajax_get_analytics() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$args = array(
			'user_id' => isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : null,
			'action_type' => isset( $_GET['action_type'] ) ? sanitize_text_field( wp_unslash( $_GET['action_type'] ) ) : null,
			'status' => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null,
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : null,
			'date_to' => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : null,
			'limit' => isset( $_GET['limit'] ) ? intval( $_GET['limit'] ) : 100,
			'offset' => isset( $_GET['offset'] ) ? intval( $_GET['offset'] ) : 0,
		);

		$analytics = $this->get_analytics( $args );
		$summary = $this->get_analytics_summary( $args );

		wp_send_json_success( array(
			'analytics' => $analytics,
			'summary' => $summary,
		) );
	}

	/**
	 * AJAX handler for generating reports
	 */
	public function ajax_generate_report() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$args = array(
			'report_type' => isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : 'summary',
			'date_from' => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : date( 'Y-m-d', strtotime( '-30 days' ) ),
			'date_to' => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : date( 'Y-m-d' ),
			'format' => isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'array',
		);

		$report = $this->generate_report( $args );

		wp_send_json_success( $report );
	}

	/**
	 * AJAX handler for tracking progress
	 */
	public function ajax_track_progress() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$action_type = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		$data = isset( $_POST['data'] ) ? json_decode( wp_unslash( $_POST['data'] ), true ) : array();
		$progress = isset( $_POST['progress'] ) ? json_decode( wp_unslash( $_POST['progress'] ), true ) : array();

		if ( empty( $session_id ) || empty( $action_type ) ) {
			wp_send_json_error( array( 'message' => 'Session ID and action type are required' ) );
		}

		$result = $this->track_progress( $session_id, $action_type, $data, $progress );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Progress tracked successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to track progress' ) );
		}
	}

	/**
	 * AJAX handler for exporting analytics
	 */
	public function ajax_export_analytics() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$args = array(
			'report_type' => isset( $_POST['report_type'] ) ? sanitize_text_field( wp_unslash( $_POST['report_type'] ) ) : 'summary',
			'date_from' => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : date( 'Y-m-d', strtotime( '-30 days' ) ),
			'date_to' => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : date( 'Y-m-d' ),
			'format' => isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv',
		);

		$export_data = $this->generate_report( $args );

		// Set appropriate headers for file download
		$filename = 'mmr-analytics-' . $args['date_from'] . '-to-' . $args['date_to'] . '.' . $args['format'];

		header( 'Content-Type: ' . ( 'csv' === $args['format'] ? 'text/csv' : 'application/json' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );

		echo $export_data;
		exit;
	}
}

// Initialize the pro analytics
new MMR_Pro_Analytics();
