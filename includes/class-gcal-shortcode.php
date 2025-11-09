<?php
/**
 * Shortcode Handler
 *
 * Handles the [gcal_embed] shortcode.
 *
 * @package GCal_Tag_Filter
 */

class GCal_Shortcode {

    /**
     * Calendar service instance.
     *
     * @var GCal_Calendar
     */
    private $calendar;

    /**
     * Display handler instance.
     *
     * @var GCal_Display
     */
    private $display;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->calendar = new GCal_Calendar();
        $this->display  = new GCal_Display();

        // Register shortcode
        add_shortcode( 'gcal_embed', array( $this, 'render_shortcode' ) );
    }

    /**
     * Render shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode( $atts ) {
        // Parse attributes with defaults
        $atts = shortcode_atts(
            array(
                'view'               => 'list',     // Default to list view
                'period'             => 'year',     // Default to year view
                'tags'               => '',         // Optional tags filter
                'show_categories'    => 'false',    // Show category sidebar
                'show_display_style' => 'false',    // Show display style toggle
                'hide_past'          => 'false',    // Hide past events in list view
            ),
            $atts,
            'gcal_embed'
        );

        // Validate and sanitize attributes
        $view               = $this->validate_view( $atts['view'] );
        $period             = $this->validate_period( $atts['period'] );
        $tags               = $this->parse_tags( $atts['tags'] );
        $show_categories    = filter_var( $atts['show_categories'], FILTER_VALIDATE_BOOLEAN );
        $show_display_style = filter_var( $atts['show_display_style'], FILTER_VALIDATE_BOOLEAN );
        $hide_past          = filter_var( $atts['hide_past'], FILTER_VALIDATE_BOOLEAN );

        // Check for URL parameter override (from view toggle)
        if ( isset( $_GET['gcal_view'] ) ) {
            $url_period = sanitize_text_field( $_GET['gcal_view'] );
            $validated_url_period = $this->validate_period( $url_period );
            if ( $validated_url_period ) {
                $period = $validated_url_period;
            }
        }

        // Check for URL parameter for display style toggle
        if ( isset( $_GET['gcal_display'] ) && $show_display_style ) {
            $url_view = sanitize_text_field( $_GET['gcal_display'] );
            $validated_url_view = $this->validate_view( $url_view );
            if ( $validated_url_view ) {
                $view = $validated_url_view;
            }
        }

        // Check for URL parameter for category filter
        $selected_category = '';
        if ( isset( $_GET['gcal_category'] ) ) {
            $selected_category = sanitize_text_field( $_GET['gcal_category'] );
        } elseif ( ! empty( $tags ) ) {
            // Pre-select the first tag if specified in shortcode
            $selected_category = $tags[0];
        }

        // Check if OAuth is configured
        $oauth = new GCal_OAuth();
        $is_auth = $oauth->is_authenticated();

        if ( ! $is_auth ) {
            return $this->display->render_error(
                __( 'Google Calendar non connecté. Veuillez contacter l\'administrateur du site.', 'gcal-tag-filter' )
            );
        }

        // Check if calendar is selected
        $calendar_id = $oauth->get_selected_calendar_id();

        if ( ! $calendar_id ) {
            return $this->display->render_error(
                __( 'Aucun calendrier sélectionné. Veuillez contacter l\'administrateur du site.', 'gcal-tag-filter' )
            );
        }

        // Read date parameters from URL
        $url_year  = isset( $_GET['gcal_year'] ) ? intval( $_GET['gcal_year'] ) : null;
        $url_month = isset( $_GET['gcal_month'] ) ? intval( $_GET['gcal_month'] ) : null;
        $url_week  = isset( $_GET['gcal_week'] ) ? intval( $_GET['gcal_week'] ) : null;

        // DEBUG: Add visible output to page
        $debug_output = '<!-- DEBUG: URL params - year=' . ( $url_year ? $url_year : 'NULL' ) . ', month=' . ( $url_month ? $url_month : 'NULL' ) . ', week=' . ( $url_week ? $url_week : 'NULL' ) . ', period=' . $period . ' -->';

        // Debug logging
        error_log( 'GCal Shortcode - URL params: year=' . ( $url_year ? $url_year : 'NULL' ) . ', month=' . ( $url_month ? $url_month : 'NULL' ) . ', week=' . ( $url_week ? $url_week : 'NULL' ) );
        error_log( 'GCal Shortcode - Period: ' . $period );

        // Output to browser console
        add_action( 'wp_footer', function() use ( $url_year, $url_month, $url_week, $period ) {
            echo '<script>console.log("PHP Shortcode params: year=' . ( $url_year ? $url_year : 'NULL' ) . ', month=' . ( $url_month ? $url_month : 'NULL' ) . ', week=' . ( $url_week ? $url_week : 'NULL' ) . ', period=' . esc_js( $period ) . '");</script>';
        } );

        // Fetch events
        $events = $this->calendar->get_events( $period, $tags, $url_year, $url_month, $url_week );

        // Debug events count
        $event_count = is_array( $events ) ? count( $events ) : 0;
        $is_error = is_wp_error( $events );
        error_log( 'GCal Shortcode - Fetched ' . $event_count . ' events' . ( $is_error ? ' (ERROR: ' . $events->get_error_message() . ')' : '' ) );

        add_action( 'wp_footer', function() use ( $events, $event_count, $is_error ) {
            if ( $is_error ) {
                echo '<script>console.error("PHP Shortcode ERROR: ' . esc_js( $events->get_error_message() ) . '");</script>';
            } else {
                echo '<script>console.log("PHP Shortcode fetched events: ' . esc_js( $event_count ) . '");</script>';
            }
        } );

        // Handle errors
        if ( is_wp_error( $events ) ) {
            return $this->display->render_error( $events->get_error_message() );
        }

        // Debug: Show visible warning if no events
        if ( empty( $events ) ) {
            error_log( 'GCal Shortcode - EMPTY EVENTS for period=' . $period . ', year=' . $url_year . ', month=' . $url_month . ', week=' . $url_week );
            $debug_output .= '<!-- DEBUG: EMPTY EVENTS ARRAY! -->';
        } else {
            $debug_output .= '<!-- DEBUG: Found ' . count( $events ) . ' events -->';
        }

        // Render appropriate view
        if ( $view === 'calendar' ) {
            return $debug_output . $this->display->render_calendar_view( $events, $period, $tags, $show_categories, $selected_category, $show_display_style, $view, $url_year, $url_month, $url_week );
        } else {
            return $debug_output . $this->display->render_list_view( $events, $period, $tags, $show_categories, $selected_category, $show_display_style, $view, $url_year, $url_month, $url_week, $hide_past );
        }
    }

    /**
     * Validate view parameter.
     *
     * @param string $view View value.
     * @return string Validated view ('calendar' or 'list').
     */
    private function validate_view( $view ) {
        $view = strtolower( trim( $view ) );

        if ( in_array( $view, array( 'calendar', 'list' ), true ) ) {
            return $view;
        }

        // Default to list view
        return 'list';
    }

    /**
     * Validate period parameter.
     *
     * @param string $period Period value.
     * @return string Validated period ('week', 'month', or 'year').
     */
    private function validate_period( $period ) {
        $period = strtolower( trim( $period ) );

        if ( in_array( $period, array( 'week', 'month', 'year' ), true ) ) {
            return $period;
        }

        // Default to year
        return 'year';
    }

    /**
     * Parse and validate tags parameter.
     *
     * @param string $tags Comma-separated tags.
     * @return array Array of validated tags.
     */
    private function parse_tags( $tags ) {
        if ( empty( $tags ) ) {
            return array();
        }

        // Split by comma and trim
        $tags_array = array_map( 'trim', explode( ',', $tags ) );

        // Remove empty values
        $tags_array = array_filter( $tags_array );

        // Validate against whitelist
        $parser = new GCal_Parser();
        $validated = array();

        foreach ( $tags_array as $tag ) {
            if ( $parser->is_valid_tag( $tag ) ) {
                $validated[] = strtoupper( $tag );
            } else {
                // Log invalid tag
                error_log( sprintf(
                    'GCal Shortcode: Invalid tag "%s" specified. Tag not in whitelist.',
                    $tag
                ) );
            }
        }

        return array_unique( $validated );
    }
}
