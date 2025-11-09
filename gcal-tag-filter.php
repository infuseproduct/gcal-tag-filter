<?php
/**
 * Plugin Name: Google Calendar Tag Filter
 * Plugin URI: https://github.com/ccfhk/ccfhk-calendar-wp-plugin
 * Description: Embeds Google Calendar events with tag-based filtering capabilities using OAuth 2.0 authentication
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: CCFHK
 * Author URI: https://ccfhk.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gcal-tag-filter
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Current plugin version.
 */
define( 'GCAL_TAG_FILTER_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'GCAL_TAG_FILTER_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'GCAL_TAG_FILTER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Composer autoloader.
 */
require_once GCAL_TAG_FILTER_PATH . 'vendor/autoload.php';

/**
 * Core plugin classes.
 */
require_once GCAL_TAG_FILTER_PATH . 'includes/class-gcal-oauth.php';
require_once GCAL_TAG_FILTER_PATH . 'includes/class-gcal-calendar.php';
require_once GCAL_TAG_FILTER_PATH . 'includes/class-gcal-parser.php';
require_once GCAL_TAG_FILTER_PATH . 'includes/class-gcal-cache.php';
require_once GCAL_TAG_FILTER_PATH . 'includes/class-gcal-categories.php';
require_once GCAL_TAG_FILTER_PATH . 'includes/class-gcal-shortcode.php';

/**
 * Admin classes.
 */
if ( is_admin() ) {
    require_once GCAL_TAG_FILTER_PATH . 'admin/class-gcal-admin.php';
}

/**
 * Public display classes.
 */
require_once GCAL_TAG_FILTER_PATH . 'public/class-gcal-display.php';

/**
 * Activation hook.
 */
function gcal_tag_filter_activate() {
    // Create default options
    add_option( 'gcal_tag_filter_cache_duration', 60 ); // 1 minute default
    add_option( 'gcal_tag_filter_categories', array() );

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'gcal_tag_filter_activate' );

/**
 * Deactivation hook.
 */
function gcal_tag_filter_deactivate() {
    // Clear all transients
    GCal_Cache::clear_all_cache();

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'gcal_tag_filter_deactivate' );

/**
 * Load plugin textdomain for translations.
 */
function gcal_tag_filter_load_textdomain() {
    load_plugin_textdomain(
        'gcal-tag-filter',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}
add_action( 'plugins_loaded', 'gcal_tag_filter_load_textdomain' );

/**
 * Initialize the plugin.
 */
function gcal_tag_filter_init() {
    // Initialize admin interface
    if ( is_admin() ) {
        new GCal_Admin();
    }

    // Initialize shortcode handler
    new GCal_Shortcode();
}
add_action( 'init', 'gcal_tag_filter_init' );

/**
 * Enqueue frontend styles and scripts.
 */
function gcal_tag_filter_enqueue_scripts() {
    // Only enqueue if shortcode is present on the page
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'gcal_embed' ) ) {
        // Styles
        wp_enqueue_style(
            'gcal-calendar-view',
            GCAL_TAG_FILTER_URL . 'public/css/calendar-view.css',
            array(),
            GCAL_TAG_FILTER_VERSION
        );

        wp_enqueue_style(
            'gcal-list-view',
            GCAL_TAG_FILTER_URL . 'public/css/list-view.css',
            array(),
            GCAL_TAG_FILTER_VERSION
        );

        wp_enqueue_style(
            'gcal-event-modal',
            GCAL_TAG_FILTER_URL . 'public/css/event-modal.css',
            array(),
            GCAL_TAG_FILTER_VERSION
        );

        wp_enqueue_style(
            'gcal-category-sidebar',
            GCAL_TAG_FILTER_URL . 'public/css/category-sidebar.css',
            array(),
            GCAL_TAG_FILTER_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'gcal-timezone-handler',
            GCAL_TAG_FILTER_URL . 'public/js/timezone-handler.js',
            array(),
            GCAL_TAG_FILTER_VERSION,
            true
        );

        wp_enqueue_script(
            'gcal-event-modal',
            GCAL_TAG_FILTER_URL . 'public/js/event-modal.js',
            array(),
            GCAL_TAG_FILTER_VERSION,
            true
        );

        wp_enqueue_script(
            'gcal-calendar-navigation',
            GCAL_TAG_FILTER_URL . 'public/js/calendar-navigation.js',
            array( 'gcal-timezone-handler' ),
            GCAL_TAG_FILTER_VERSION,
            true
        );

        wp_enqueue_script(
            'gcal-category-filter',
            GCAL_TAG_FILTER_URL . 'public/js/category-filter.js',
            array(),
            GCAL_TAG_FILTER_VERSION,
            true
        );

        wp_enqueue_script(
            'gcal-contrast-handler',
            GCAL_TAG_FILTER_URL . 'public/js/contrast-handler.js',
            array(),
            GCAL_TAG_FILTER_VERSION,
            true
        );

        wp_enqueue_script(
            'gcal-year-view',
            GCAL_TAG_FILTER_URL . 'public/js/year-view.js',
            array(),
            GCAL_TAG_FILTER_VERSION,
            true
        );

        // Localize script with necessary data
        wp_localize_script(
            'gcal-calendar-navigation',
            'gcalData',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'gcal-ajax-nonce' ),
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'gcal_tag_filter_enqueue_scripts' );

/**
 * AJAX handler to fetch events for a specific month/week.
 */
function gcal_ajax_fetch_events() {
    // Verify nonce
    check_ajax_referer( 'gcal-ajax-nonce', 'nonce' );

    // Get parameters
    $year  = isset( $_POST['year'] ) ? intval( $_POST['year'] ) : date( 'Y' );
    $month = isset( $_POST['month'] ) ? intval( $_POST['month'] ) : date( 'm' );

    // Create a custom date range for this specific month
    $start = new DateTime();
    $start->setDate( $year, $month, 1 );
    $start->setTime( 0, 0, 0 );
    $start->setTimezone( new DateTimeZone( 'UTC' ) );

    $end = clone $start;
    $end->modify( 'last day of this month' );
    $end->setTime( 23, 59, 59 );

    // Fetch events from API
    $calendar = new GCal_Calendar();
    $oauth    = new GCal_OAuth();

    try {
        $client      = $oauth->get_authenticated_client();
        $calendar_id = $oauth->get_selected_calendar_id();

        if ( ! $client || ! $calendar_id ) {
            wp_send_json_error( array( 'message' => 'Not authenticated' ) );
            return;
        }

        $service = new Google_Service_Calendar( $client );
        $params  = array(
            'timeMin'      => $start->format( DateTime::RFC3339 ),
            'timeMax'      => $end->format( DateTime::RFC3339 ),
            'maxResults'   => 100,
            'singleEvents' => true,
            'orderBy'      => 'startTime',
        );

        $events = $service->events->listEvents( $calendar_id, $params );
        $items  = $events->getItems();

        // Process events (same logic as GCal_Calendar::process_events)
        $parser    = new GCal_Parser();
        $processed = array();

        foreach ( $items as $event ) {
            $description = $event->getDescription() ?? '';

            // Extract and validate tags
            $tag_result   = $parser->extract_tags( $description );
            $valid_tags   = isset( $tag_result['valid'] ) ? $tag_result['valid'] : array();
            $invalid_tags = isset( $tag_result['invalid'] ) ? $tag_result['invalid'] : array();

            // Clean description
            $clean_description = $parser->strip_tags( $description );

            // Get start/end times
            $start_obj = $event->getStart();
            $end_obj   = $event->getEnd();

            $is_all_day = ! empty( $start_obj->date );
            $start_time = $is_all_day ? $start_obj->date : $start_obj->dateTime;
            $end_time   = $is_all_day ? $end_obj->date : $end_obj->dateTime;

            $has_no_tags      = empty( $valid_tags ) && empty( $invalid_tags );
            $has_invalid_tags = ! empty( $invalid_tags );
            $has_valid_tags   = ! empty( $valid_tags );

            $processed[] = array(
                'id'               => $event->getId(),
                'title'            => $event->getSummary() ?? '(Sans titre)',
                'description'      => $clean_description,
                'location'         => $event->getLocation() ?? '',
                'start'            => $start_time,
                'end'              => $end_time,
                'is_all_day'       => $is_all_day,
                'tags'             => $valid_tags,
                'invalid_tags'     => $invalid_tags,
                'is_untagged'      => $has_no_tags,
                'has_unknown_tags' => $has_invalid_tags && ! $has_valid_tags,
            );
        }

        // Filter for non-admins (same logic as class-gcal-calendar.php)
        if ( ! current_user_can( 'manage_options' ) ) {
            $processed = array_filter(
                $processed,
                function ( $event ) {
                    return ! empty( $event['tags'] );
                }
            );
        }

        // Prepare for JS (match the format from class-gcal-display.php)
        $js_events = array();

        foreach ( $processed as $event ) {
            $js_events[] = array(
                'id'          => $event['id'],
                'title'       => $event['title'],
                'start'       => $event['start'],
                'end'         => $event['end'],
                'isAllDay'    => $event['is_all_day'],
                'description' => $event['description'],
                'location'    => $event['location'],
                'tags'        => $event['tags'],
                'invalidTags' => $event['invalid_tags'],
            );
        }

        wp_send_json_success( array(
            'events' => array_values( $js_events ),
            'count'  => count( $js_events ),
        ) );

    } catch ( Exception $e ) {
        wp_send_json_error( array( 'message' => $e->getMessage() ) );
    }
}
add_action( 'wp_ajax_gcal_fetch_events', 'gcal_ajax_fetch_events' );
add_action( 'wp_ajax_nopriv_gcal_fetch_events', 'gcal_ajax_fetch_events' );
