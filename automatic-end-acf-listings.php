<?php
/**
 * Plugin Name: Automatic End for ACF Listings
 * Description: Automatically updates listing status from 'active' to 'ended' based on the ACF Auction Start Date.
 * Version: 1.0.0
 * Author: John Lim
 * Text Domain: auto-end-acf-listings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Auto_End_ACF_Listings {

	/**
	 * Option name for the execution time.
	 */
	const OPTION_NAME = 'aeal_execution_time';

	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'aeal_daily_event';

	/**
	 * Instance of the class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Admin Settings
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Cron Scheduling
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'update_cron_schedule' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'execute_automation' ) );

		// Activation/Deactivation
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // Add settings link to plugin list
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add Settings Page under Settings menu.
	 */
	public function add_settings_page() {
		add_options_page(
			'Auto End Listings',
			'Auto End Listings',
			'manage_options',
			'auto-end-listings',
			array( $this, 'render_settings_page' )
		);
	}

    /**
     * Add settings link to plugin page
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="options-general.php?page=auto-end-listings">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

	/**
	 * Register the setting.
	 */
	public function register_settings() {
		register_setting( 'aeal_settings_group', self::OPTION_NAME, array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '00:00'
        ) );

		add_settings_section(
			'aeal_main_section',
			'Schedule Settings',
			null,
			'auto-end-listings'
		);

		add_settings_field(
			self::OPTION_NAME,
			'Daily Execution Time',
			array( $this, 'render_time_field' ),
			'auto-end-listings',
			'aeal_main_section'
		);
	}

	/**
	 * Render the time input field.
	 */
	public function render_time_field() {
		$value = get_option( self::OPTION_NAME, '00:00' );
		?>
		<input type="time" name="<?php echo esc_attr( self::OPTION_NAME ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<p class="description">Select the time of day to run the automation (Server Time).</p>
		<?php
	}

	/**
	 * Render the settings page HTML.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>Automatic End for ACF Listings Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'aeal_settings_group' );
				do_settings_sections( 'auto-end-listings' );
				submit_button();
				?>
			</form>
            <hr>
            <h2>Debug Info</h2>
            <p><strong>Current Server Time:</strong> <?php echo current_time( 'Y-m-d H:i:s' ); ?></p>
            <p><strong>Next Scheduled Run:</strong> 
                <?php 
                $next_run = wp_next_scheduled( self::CRON_HOOK );
                echo $next_run ? get_date_from_gmt( date( 'Y-m-d H:i:s', $next_run ), 'Y-m-d H:i:s' ) : 'Not scheduled';
                ?>
            </p>
		</div>
		<?php
	}

	/**
	 * Update the cron schedule when the setting is changed.
	 */
	public function update_cron_schedule( $old_value, $value, $option ) {
		// Clear existing schedule
		wp_clear_scheduled_hook( self::CRON_HOOK );

		if ( ! empty( $value ) ) {
            // Use WordPress Timezone settings to ensure accuracy
            $timezone = wp_timezone();
            
            // Create a DateTime object for "Today" at the specified time in the Site's Timezone
            $date = new DateTime( 'now', $timezone );
            $time_parts = explode( ':', $value );
            if ( count( $time_parts ) === 2 ) {
                $date->setTime( (int) $time_parts[0], (int) $time_parts[1], 0 );
            }

            // If this time has already passed properly in UTC, schedule for tomorrow
            if ( $date->getTimestamp() <= time() ) {
                $date->modify( '+1 day' );
            }

			// wp_schedule_event expects a UTC timestamp
			wp_schedule_event( $date->getTimestamp(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Execute the automation logic.
	 */
	public function execute_automation() {
		$today = date( 'Ymd' ); // ACF Date format Ymd

        // Log start (optional, using error_log for simplicity)
        error_log( "AEAL: Starting automation for date $today" );

		$args = array(
			'post_type'      => 'listing',
			'posts_per_page' => -1,
            'fields'         => 'ids', // Performance: just get IDs
			'tax_query'      => array(
				array(
					'taxonomy' => 'listing-status',
					'field'    => 'slug',
					'terms'    => 'active',
				),
			),
			'meta_query'     => array(
				array(
					'key'     => 'auction_start_date',
					'value'   => $today,
					'compare' => '=',
                    'type'    => 'DATE'
				),
			),
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				// Remove 'active'
				wp_remove_object_terms( $post_id, 'active', 'listing-status' );
				// Add 'ended'
				wp_set_object_terms( $post_id, 'ended', 'listing-status', true );
                
                error_log( "AEAL: Updated Post ID $post_id to 'ended'." );
			}
		}
        
        error_log( "AEAL: Automation complete. Processed " . $query->post_count . " listings." );
	}

	/**
	 * Activation hook.
	 */
	public function activate() {
		// Trigger a schedule update with the current setting
		$current_time = get_option( self::OPTION_NAME, '00:00' );
		$this->update_cron_schedule( '', $current_time, self::OPTION_NAME );
	}

	/**
	 * Deactivation hook.
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}

// Initialize the plugin
Auto_End_ACF_Listings::get_instance();
