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
                'view'   => 'list',     // Default to list view
                'period' => 'future',   // Default to future events
                'tags'   => '',         // Optional tags filter
            ),
            $atts,
            'gcal_embed'
        );

        // Validate and sanitize attributes
        $view   = $this->validate_view( $atts['view'] );
        $period = $this->validate_period( $atts['period'] );
        $tags   = $this->parse_tags( $atts['tags'] );

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

        // Fetch events
        $events = $this->calendar->get_events( $period, $tags );

        // Handle errors
        if ( is_wp_error( $events ) ) {
            return $this->display->render_error( $events->get_error_message() );
        }

        // Render appropriate view
        if ( $view === 'calendar' ) {
            return $this->display->render_calendar_view( $events, $period, $tags );
        } else {
            return $this->display->render_list_view( $events, $period, $tags );
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
     * @return string Validated period ('week', 'month', or 'future').
     */
    private function validate_period( $period ) {
        $period = strtolower( trim( $period ) );

        if ( in_array( $period, array( 'week', 'month', 'future' ), true ) ) {
            return $period;
        }

        // Default to future
        return 'future';
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
