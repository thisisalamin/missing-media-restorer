<?php
/**
 * MMR Pro Support - Priority Support Infrastructure
 *
 * @package MissingMediaRestorerPro
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MMR_Pro_Support
 */
class MMR_Pro_Support {

	/**
	 * Support tickets table name
	 *
	 * @var string
	 */
	private $tickets_table;

	/**
	 * Support knowledge base table name
	 *
	 * @var string
	 */
	private $kb_table;

	/**
	 * Initialize the pro support system
	 */
	public function __construct() {
		global $wpdb;
		$this->tickets_table = $wpdb->prefix . 'mmr_support_tickets';
		$this->kb_table = $wpdb->prefix . 'mmr_support_kb';

		// Register AJAX handlers
		add_action( 'wp_ajax_mmr_pro_create_ticket', array( $this, 'ajax_create_ticket' ) );
		add_action( 'wp_ajax_mmr_pro_get_tickets', array( $this, 'ajax_get_tickets' ) );
		add_action( 'wp_ajax_mmr_pro_update_ticket', array( $this, 'ajax_update_ticket' ) );
		add_action( 'wp_ajax_mmr_pro_search_kb', array( $this, 'ajax_search_kb' ) );
		add_action( 'wp_ajax_mmr_pro_get_kb_article', array( $this, 'ajax_get_kb_article' ) );
		add_action( 'wp_ajax_mmr_pro_system_info', array( $this, 'ajax_get_system_info' ) );
		add_action( 'wp_ajax_mmr_pro_diagnostic', array( $this, 'ajax_run_diagnostic' ) );

		// Initialize tables
		add_action( 'admin_init', array( $this, 'create_support_tables' ) );

		// Add admin menu items
		add_action( 'admin_menu', array( $this, 'add_support_menu' ) );
	}

	/**
	 * Create support tables
	 */
	public function create_support_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Support tickets table
		$sql_tickets = "CREATE TABLE {$this->tickets_table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			ticket_id varchar(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			subject text NOT NULL,
			message longtext NOT NULL,
			priority varchar(20) NOT NULL DEFAULT 'normal',
			status varchar(20) NOT NULL DEFAULT 'open',
			category varchar(50) NOT NULL DEFAULT 'general',
			attachments longtext,
			system_info longtext,
			admin_reply longtext,
			admin_reply_time datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY ticket_id (ticket_id),
			KEY user_id (user_id),
			KEY status (status),
			KEY priority (priority),
			KEY category (category),
			KEY created_at (created_at)
		) $charset_collate;";

		// Knowledge base table
		$sql_kb = "CREATE TABLE {$this->kb_table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			content longtext NOT NULL,
			category varchar(50) NOT NULL DEFAULT 'general',
			tags text,
			views int(11) NOT NULL DEFAULT 0,
			helpful int(11) NOT NULL DEFAULT 0,
			not_helpful int(11) NOT NULL DEFAULT 0,
			featured tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug),
			KEY category (category),
			KEY featured (featured),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_tickets );
		dbDelta( $sql_kb );

		// Insert default knowledge base articles if empty
		$this->insert_default_kb_articles();
	}

	/**
	 * Insert default knowledge base articles
	 */
	private function insert_default_kb_articles() {
		global $wpdb;

		$existing = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->kb_table}" );
		if ( $existing > 0 ) {
			return; // Articles already exist
		}

		$default_articles = array(
			array(
				'title' => 'Getting Started with Missing Media Restorer Pro',
				'slug' => 'getting-started',
				'content' => 'Welcome to Missing Media Restorer Pro! This comprehensive guide will help you understand how to use all the powerful features available in the Pro version.\n\n## Key Features\n\n1. **Smart Local Scanning**: Automatically scan local directories for missing media files\n2. **Advanced Matching Algorithms**: Multiple algorithms to find the best matches for your files\n3. **Bulk Upload & Restore**: Process multiple files simultaneously\n4. **Progress Analytics**: Detailed reports and analytics on your restoration activities\n5. **Priority Support**: Get help when you need it most\n\n## Quick Start\n\n1. Navigate to Media > Missing Media Restorer\n2. Click "Scan for Missing Media" to identify missing files\n3. Use the Pro Scanner to search local directories\n4. Upload and restore files using the advanced uploader\n5. Monitor progress with the analytics dashboard\n\nFor detailed tutorials, check out our other knowledge base articles.',
				'category' => 'getting-started',
				'tags' => 'tutorial,beginner,overview',
				'featured' => 1,
			),
			array(
				'title' => 'Troubleshooting Common Issues',
				'slug' => 'troubleshooting',
				'content' => 'This article covers common issues and their solutions when using Missing Media Restorer Pro.\n\n## Files Not Found During Scan\n\n**Problem**: The scan doesn\'t find files that should be missing.\n\n**Solution**:\n- Check that the files are actually missing from the uploads directory\n- Verify that the database entries still exist in wp_posts table\n- Ensure your WordPress installation has proper file permissions\n\n## Upload Failures\n\n**Problem**: Files fail to upload during restoration.\n\n**Solution**:\n- Check PHP upload limits in your server configuration\n- Verify directory permissions for wp-content/uploads\n- Ensure sufficient disk space is available\n- Check file size limits in WordPress settings\n\n## Memory Errors\n\n**Problem**: Running out of memory during large operations.\n\n**Solution**:\n- Increase PHP memory limit in wp-config.php: `define(\'WP_MEMORY_LIMIT\', \'512M\');`\n- Process files in smaller batches\n- Use the chunked upload feature for large files\n\n## Matching Accuracy Issues\n\n**Problem**: The wrong files are being matched.\n\n**Solution**:\n- Adjust the matching threshold in settings\n- Use multiple matching algorithms\n- Manually verify matches before restoration\n- Enable hash verification for critical files',
				'category' => 'troubleshooting',
				'tags' => 'errors,issues,solutions',
				'featured' => 1,
			),
			array(
				'title' => 'Advanced Matching Algorithms Explained',
				'slug' => 'matching-algorithms',
				'content' => 'Missing Media Restorer Pro uses multiple sophisticated algorithms to find the best matches for your missing media files.\n\n## Available Algorithms\n\n### 1. Exact Matching\n- **Description**: Matches files with identical filenames\n- **Accuracy**: 100% when found\n- **Use Case**: When you have the original filenames\n\n### 2. Fuzzy String Matching\n- **Description**: Uses Levenshtein distance to find similar filenames\n- **Accuracy**: 70-90%\n- **Use Case**: When filenames have minor variations\n\n### 3. Metadata Matching\n- **Description**: Compares file metadata (dimensions, creation date, etc.)\n- **Accuracy**: 60-80%\n- **Use Case**: When metadata is preserved\n\n### 4. File Size Matching\n- **Description**: Compares file sizes within a tolerance\n- **Accuracy**: 40-60%\n- **Use Case**: As a supporting evidence\n\n### 5. Hash Matching\n- **Description**: Compares MD5 hashes of files\n- **Accuracy**: 100% when found\n- **Use Case**: When you have hash information\n\n### 6. Pattern Matching\n- **Description**: Identifies patterns in filenames\n- **Accuracy**: 50-70%\n- **Use Case**: When files follow naming conventions\n\n## Best Practices\n\n1. **Use Multiple Algorithms**: Combine algorithms for better accuracy\n2. **Adjust Thresholds**: Fine-tune matching thresholds based on your needs\n3. **Verify Manually**: Always verify important matches\n4. **Start with Exact**: Begin with exact matching, then use fuzzy algorithms\n\n## Algorithm Weights\n\nYou can adjust the importance of each algorithm in the Pro settings:\n- Exact: 1.0 (highest)\n- Hash: 1.0 (highest)\n- Fuzzy: 0.8 (high)\n- Metadata: 0.6 (medium)\n- Size: 0.4 (low)\n- Pattern: 0.5 (medium-low)',
				'category' => 'features',
				'tags' => 'algorithms,matching,technical',
				'featured' => 0,
			),
		);

		foreach ( $default_articles as $article ) {
			$wpdb->insert( $this->kb_table, $article );
		}
	}

	/**
	 * Create support ticket
	 *
	 * @param array $ticket_data Ticket information
	 * @return array Result with ticket ID
	 */
	public function create_ticket( $ticket_data ) {
		global $wpdb;

		$ticket_id = 'MMR-' . strtoupper( wp_generate_password( 8, false ) );

		$data = array(
			'ticket_id' => $ticket_id,
			'user_id' => get_current_user_id(),
			'subject' => sanitize_text_field( $ticket_data['subject'] ),
			'message' => wp_kses_post( $ticket_data['message'] ),
			'priority' => in_array( $ticket_data['priority'], array( 'low', 'normal', 'high', 'urgent' ) ) ? $ticket_data['priority'] : 'normal',
			'category' => sanitize_text_field( $ticket_data['category'] ),
			'attachments' => isset( $ticket_data['attachments'] ) ? wp_json_encode( $ticket_data['attachments'] ) : '',
			'system_info' => isset( $ticket_data['include_system_info'] ) && $ticket_data['include_system_info'] ? wp_json_encode( $this->get_system_info() ) : '',
		);

		$result = $wpdb->insert( $this->tickets_table, $data );

		if ( $result ) {
			// Send notification email
			$this->send_ticket_notification( $ticket_id, $ticket_data, 'created' );

			return array(
				'success' => true,
				'ticket_id' => $ticket_id,
				'id' => $wpdb->insert_id,
			);
		}

		return array(
			'success' => false,
			'error' => 'Failed to create support ticket',
		);
	}

	/**
	 * Get support tickets for current user
	 *
	 * @param array $args Query arguments
	 * @return array Tickets
	 */
	public function get_tickets( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id' => get_current_user_id(),
			'status' => null,
			'limit' => 50,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array( 'user_id = %d' );
		$values = array( $args['user_id'] );

		if ( $args['status'] ) {
			$where_clauses[] = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = 'WHERE ' . implode( ' AND ', $where_clauses );

		$sql = "SELECT * FROM {$this->tickets_table} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$values[] = $args['limit'];
		$values[] = $args['offset'];

		$tickets = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		// Decode JSON fields
		foreach ( $tickets as $ticket ) {
			$ticket->attachments = json_decode( $ticket->attachments, true );
			$ticket->system_info = json_decode( $ticket->system_info, true );
		}

		return $tickets;
	}

	/**
	 * Update support ticket
	 *
	 * @param string $ticket_id Ticket ID
	 * @param array  $update_data Data to update
	 * @return bool Success status
	 */
	public function update_ticket( $ticket_id, $update_data ) {
		global $wpdb;

		$allowed_fields = array( 'status', 'priority', 'admin_reply', 'admin_reply_time' );
		$data = array();

		foreach ( $update_data as $field => $value ) {
			if ( in_array( $field, $allowed_fields, true ) ) {
				$data[ $field ] = $value;
			}
		}

		if ( empty( $data ) ) {
			return false;
		}

		$result = $wpdb->update( $this->tickets_table, $data, array( 'ticket_id' => $ticket_id ) );

		if ( $result && isset( $update_data['admin_reply'] ) ) {
			// Send notification email
			$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tickets_table} WHERE ticket_id = %s", $ticket_id ) );
			$this->send_ticket_notification( $ticket_id, $ticket, 'reply' );
		}

		return false !== $result;
	}

	/**
	 * Search knowledge base
	 *
	 * @param string $query Search query
	 * @param array  $args Search arguments
	 * @return array Search results
	 */
	public function search_kb( $query, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'category' => null,
			'limit' => 20,
		);

		$args = wp_parse_args( $args, $defaults );

		$where_clauses = array();
		$values = array();

		if ( $query ) {
			$where_clauses[] = '(title LIKE %s OR content LIKE %s OR tags LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $query ) . '%';
			$values[] = $search_term;
			$values[] = $search_term;
			$values[] = $search_term;
		}

		if ( $args['category'] ) {
			$where_clauses[] = 'category = %s';
			$values[] = $args['category'];
		}

		$where_clause = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$sql = "SELECT * FROM {$this->kb_table} {$where_clause} ORDER BY featured DESC, views DESC LIMIT %d";
		$values[] = $args['limit'];

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

		// Update view count
		foreach ( $results as $result ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$this->kb_table} SET views = views + 1 WHERE id = %d", $result->id ) );
		}

		return $results;
	}

	/**
	 * Get knowledge base article by slug
	 *
	 * @param string $slug Article slug
	 * @return object|null Article data
	 */
	public function get_kb_article( $slug ) {
		global $wpdb;

		$article = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->kb_table} WHERE slug = %s", $slug ) );

		if ( $article ) {
			// Update view count
			$wpdb->query( $wpdb->prepare( "UPDATE {$this->kb_table} SET views = views + 1 WHERE id = %d", $article->id ) );
		}

		return $article;
	}

	/**
	 * Get system information for support
	 *
	 * @return array System information
	 */
	public function get_system_info() {
		global $wpdb;

		$info = array(
			'site' => array(
				'url' => get_site_url(),
				'home' => get_home_url(),
				'wp_version' => get_bloginfo( 'version' ),
				'multisite' => is_multisite(),
				'language' => get_bloginfo( 'language' ),
			),
			'server' => array(
				'php_version' => PHP_VERSION,
				'mysql_version' => $wpdb->db_version(),
				'webserver' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
				'os' => PHP_OS,
				'memory_limit' => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
				'post_max_size' => ini_get( 'post_max_size' ),
			),
			'wordpress' => array(
				'active_plugins' => get_option( 'active_plugins' ),
				'current_theme' => get_option( 'stylesheet' ),
				'uploads_dir' => wp_upload_dir(),
				'debug_mode' => defined( 'WP_DEBUG' ) && WP_DEBUG,
			),
			'plugin' => array(
				'version' => defined( 'MMR_PRO_VERSION' ) ? MMR_PRO_VERSION : 'Unknown',
				'license_key' => get_option( 'mmr_pro_license_key', '' ),
				'settings' => get_option( 'mmr_pro_settings', array() ),
			),
		);

		return $info;
	}

	/**
	 * Run diagnostic tests
	 *
	 * @return array Diagnostic results
	 */
	public function run_diagnostic() {
		$results = array(
			'tests' => array(),
			'summary' => array(
				'passed' => 0,
				'failed' => 0,
				'warnings' => 0,
			),
		);

		// Test 1: File permissions
		$upload_dir = wp_upload_dir();
		$writable = is_writable( $upload_dir['basedir'] );
		$results['tests']['file_permissions'] = array(
			'name' => 'File Permissions',
			'status' => $writable ? 'passed' : 'failed',
			'message' => $writable ? 'Upload directory is writable' : 'Upload directory is not writable',
		);

		// Test 2: PHP memory limit
		$memory_limit = $this->parse_memory_limit( ini_get( 'memory_limit' ) );
		$memory_ok = $memory_limit >= 128 * 1024 * 1024; // 128MB minimum
		$results['tests']['memory_limit'] = array(
			'name' => 'PHP Memory Limit',
			'status' => $memory_ok ? 'passed' : 'warning',
			'message' => $memory_ok ? 'Memory limit is sufficient' : 'Memory limit may be too low for large operations',
		);

		// Test 3: Database connection
		global $wpdb;
		$db_test = $wpdb->get_var( 'SELECT 1' );
		$results['tests']['database'] = array(
			'name' => 'Database Connection',
			'status' => $db_test ? 'passed' : 'failed',
			'message' => $db_test ? 'Database connection is working' : 'Database connection failed',
		);

		// Test 4: Plugin tables
		$tables_exist = $wpdb->get_var( "SHOW TABLES LIKE '{$this->tickets_table}'" ) &&
		               $wpdb->get_var( "SHOW TABLES LIKE '{$this->kb_table}'" );
		$results['tests']['plugin_tables'] = array(
			'name' => 'Plugin Tables',
			'status' => $tables_exist ? 'passed' : 'failed',
			'message' => $tables_exist ? 'Plugin tables exist' : 'Plugin tables are missing',
		);

		// Test 5: WordPress functions
		$wp_functions_ok = function_exists( 'wp_handle_upload' ) && function_exists( 'wp_generate_password' );
		$results['tests']['wordpress_functions'] = array(
			'name' => 'WordPress Functions',
			'status' => $wp_functions_ok ? 'passed' : 'failed',
			'message' => $wp_functions_ok ? 'Required WordPress functions are available' : 'Some WordPress functions are missing',
		);

		// Count results
		foreach ( $results['tests'] as $test ) {
			switch ( $test['status'] ) {
				case 'passed':
					$results['summary']['passed']++;
					break;
				case 'failed':
					$results['summary']['failed']++;
					break;
				case 'warning':
					$results['summary']['warnings']++;
					break;
			}
		}

		return $results;
	}

	/**
	 * Parse memory limit string to bytes
	 *
	 * @param string $memory_limit Memory limit string
	 * @return int Memory limit in bytes
	 */
	private function parse_memory_limit( $memory_limit ) {
		$unit = strtolower( substr( $memory_limit, -1 ) );
		$value = (int) $memory_limit;

		switch ( $unit ) {
			case 'g':
				$value *= 1024;
				// Fall through
			case 'm':
				$value *= 1024;
				// Fall through
			case 'k':
				$value *= 1024;
		}

		return $value;
	}

	/**
	 * Send ticket notification email
	 *
	 * @param string $ticket_id Ticket ID
	 * @param array  $ticket_data Ticket data
	 * @param string $type Notification type
	 */
	private function send_ticket_notification( $ticket_id, $ticket_data, $type ) {
		$to = get_option( 'admin_email' );
		$subject = sprintf( '[Missing Media Restorer Pro] Support Ticket %s - %s', $ticket_id, ucfirst( $type ) );

		$message = "A support ticket has been {$type}:\n\n";
		$message .= "Ticket ID: {$ticket_id}\n";
		$message .= "Subject: {$ticket_data['subject']}\n";
		$message .= "Priority: {$ticket_data['priority']}\n";
		$message .= "Category: {$ticket_data['category']}\n\n";
		$message .= "Message:\n{$ticket_data['message']}\n\n";

		if ( 'created' === $type ) {
			$message .= "View and manage tickets: " . admin_url( 'admin.php?page=mmr-pro-support' ) . "\n";
		}

		wp_mail( $to, $subject, $message );
	}

	/**
	 * Add support menu items
	 */
	public function add_support_menu() {
		add_submenu_page(
			'missing-media-restorer',
			'Pro Support',
			'Pro Support',
			'manage_options',
			'mmr-pro-support',
			array( $this, 'render_support_page' )
		);
	}

	/**
	 * Render support page
	 */
	public function render_support_page() {
		?>
		<div class="wrap">
			<h1>Missing Media Restorer Pro - Priority Support</h1>

			<div id="mmr-pro-support-app">
				<!-- Support content will be rendered here by JavaScript -->
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler for creating tickets
	 */
	public function ajax_create_ticket() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$ticket_data = array(
			'subject' => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
			'message' => isset( $_POST['message'] ) ? wp_kses_post( wp_unslash( $_POST['message'] ) ) : '',
			'priority' => isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'normal',
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'general',
			'attachments' => isset( $_POST['attachments'] ) ? json_decode( wp_unslash( $_POST['attachments'] ), true ) : array(),
			'include_system_info' => isset( $_POST['include_system_info'] ) && rest_sanitize_boolean( $_POST['include_system_info'] ),
		);

		if ( empty( $ticket_data['subject'] ) || empty( $ticket_data['message'] ) ) {
			wp_send_json_error( array( 'message' => 'Subject and message are required' ) );
		}

		$result = $this->create_ticket( $ticket_data );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX handler for getting tickets
	 */
	public function ajax_get_tickets() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$args = array(
			'status' => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null,
			'limit' => isset( $_GET['limit'] ) ? intval( $_GET['limit'] ) : 50,
			'offset' => isset( $_GET['offset'] ) ? intval( $_GET['offset'] ) : 0,
		);

		$tickets = $this->get_tickets( $args );

		wp_send_json_success( array( 'tickets' => $tickets ) );
	}

	/**
	 * AJAX handler for updating tickets
	 */
	public function ajax_update_ticket() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$ticket_id = isset( $_POST['ticket_id'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_id'] ) ) : '';
		$update_data = array(
			'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : null,
			'priority' => isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : null,
		);

		if ( empty( $ticket_id ) ) {
			wp_send_json_error( array( 'message' => 'Ticket ID is required' ) );
		}

		$result = $this->update_ticket( $ticket_id, $update_data );

		if ( $result ) {
			wp_send_json_success( array( 'message' => 'Ticket updated successfully' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to update ticket' ) );
		}
	}

	/**
	 * AJAX handler for searching knowledge base
	 */
	public function ajax_search_kb() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		$query = isset( $_GET['query'] ) ? sanitize_text_field( wp_unslash( $_GET['query'] ) ) : '';
		$category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : null;

		$results = $this->search_kb( $query, array( 'category' => $category ) );

		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * AJAX handler for getting KB article
	 */
	public function ajax_get_kb_article() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Article slug is required' ) );
		}

		$article = $this->get_kb_article( $slug );

		if ( $article ) {
			wp_send_json_success( array( 'article' => $article ) );
		} else {
			wp_send_json_error( array( 'message' => 'Article not found' ) );
		}
	}

	/**
	 * AJAX handler for getting system info
	 */
	public function ajax_get_system_info() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$info = $this->get_system_info();

		wp_send_json_success( array( 'info' => $info ) );
	}

	/**
	 * AJAX handler for running diagnostic
	 */
	public function ajax_run_diagnostic() {
		// Security check
		check_ajax_referer( 'mmr_pro_ajax_nonce', 'nonce' );

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		$results = $this->run_diagnostic();

		wp_send_json_success( $results );
	}
}

// Initialize the pro support system
new MMR_Pro_Support();
