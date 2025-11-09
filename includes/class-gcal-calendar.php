<?php
/**
 * Google Calendar API Service
 *
 * Handles fetching events from Google Calendar API.
 *
 * @package GCal_Tag_Filter
 */

class GCal_Calendar {

    /**
     * OAuth handler instance.
     *
     * @var GCal_OAuth
     */
    private $oauth;

    /**
     * Parser instance.
     *
     * @var GCal_Parser
     */
    private $parser;

    /**
     * Cache handler instance.
     *
     * @var GCal_Cache
     */
    private $cache;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->oauth  = new GCal_OAuth();
        $this->parser = new GCal_Parser();
        $this->cache  = new GCal_Cache();
    }

    /**
     * Get events for a specific period.
     *
     * @param string $period Period: 'week', 'month', or 'future'.
     * @param array  $tags   Optional. Array of tags to filter by.
     * @return array|WP_Error Array of events or WP_Error on failure.
     */
    public function get_events( $period, $tags = array() ) {
        error_log( '=== GCal Get Events ===' );
        error_log( 'Period: ' . $period . ', Tags: ' . ( empty( $tags ) ? 'NONE' : implode( ',', $tags ) ) );

        // Check cache first
        $cache_key = $this->cache->generate_key( $period, $tags );
        $cached_events = $this->cache->get( $cache_key );

        if ( $cached_events !== false ) {
            error_log( 'Returning cached events: ' . count( $cached_events ) );
            return $cached_events;
        }

        error_log( 'Cache miss, fetching from API' );

        // Fetch from API
        $events = $this->fetch_events_from_api( $period );

        if ( is_wp_error( $events ) ) {
            error_log( 'API fetch error: ' . $events->get_error_message() );
            return $events;
        }

        // Parse and process events
        $processed_events = $this->process_events( $events );
        error_log( 'Processed events: ' . count( $processed_events ) );

        // Filter by tags if specified
        if ( ! empty( $tags ) ) {
            $processed_events = $this->filter_events_by_tags( $processed_events, $tags );
            error_log( 'After tag filter: ' . count( $processed_events ) . ' events' );
        }

        // Cache the results
        $this->cache->set( $cache_key, $processed_events );

        return $processed_events;
    }

    /**
     * Fetch events from Google Calendar API.
     *
     * @param string $period Period: 'week', 'month', or 'future'.
     * @return array|WP_Error Array of events or WP_Error on failure.
     */
    private function fetch_events_from_api( $period ) {
        error_log( '=== GCal Fetch Events ===' );

        $client = $this->oauth->get_authenticated_client();

        if ( ! $client ) {
            error_log( 'Client authentication failed' );
            return new WP_Error(
                'auth_failed',
                __( 'Not authenticated with Google Calendar. Please connect your account in plugin settings.', 'gcal-tag-filter' )
            );
        }

        $calendar_id = $this->oauth->get_selected_calendar_id();
        error_log( 'Calendar ID: ' . ( $calendar_id ? $calendar_id : 'NONE' ) );

        if ( ! $calendar_id ) {
            error_log( 'No calendar selected' );
            return new WP_Error(
                'no_calendar',
                __( 'No calendar selected. Please select a calendar in plugin settings.', 'gcal-tag-filter' )
            );
        }

        try {
            $service = new Google_Service_Calendar( $client );

            // Calculate time range based on period
            list( $time_min, $time_max ) = $this->get_time_range( $period );
            error_log( 'Time range: ' . $time_min . ' to ' . ( $time_max ? $time_max : 'FUTURE' ) );

            $params = array(
                'timeMin'      => $time_min,
                'maxResults'   => 100,
                'singleEvents' => true, // Expand recurring events
                'orderBy'      => 'startTime',
            );

            if ( $time_max ) {
                $params['timeMax'] = $time_max;
            }

            error_log( 'Calling Google Calendar API...' );
            $events = $service->events->listEvents( $calendar_id, $params );
            $items = $events->getItems();
            error_log( 'API returned ' . count( $items ) . ' events' );

            return $items;
        } catch ( Exception $e ) {
            error_log( 'GCal API Error: ' . $e->getMessage() );

            return new WP_Error(
                'api_error',
                sprintf(
                    /* translators: %s: error message */
                    __( 'Failed to fetch events from Google Calendar: %s', 'gcal-tag-filter' ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Get time range for period.
     *
     * @param string $period Period: 'week', 'month', or 'future'.
     * @return array Array with timeMin and timeMax.
     */
    private function get_time_range( $period ) {
        $now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

        switch ( $period ) {
            case 'week':
                $time_min = $now->format( DateTime::RFC3339 );
                $time_max = ( clone $now )->modify( '+7 days' )->format( DateTime::RFC3339 );
                break;

            case 'month':
                $time_min = $now->format( DateTime::RFC3339 );
                $time_max = ( clone $now )->modify( '+1 month' )->format( DateTime::RFC3339 );
                break;

            case 'future':
            default:
                $time_min = $now->format( DateTime::RFC3339 );
                $time_max = null; // No end date for future events
                break;
        }

        return array( $time_min, $time_max );
    }

    /**
     * Process events (parse tags, clean descriptions).
     *
     * @param array $events Array of Google Calendar events.
     * @return array Processed events.
     */
    private function process_events( $events ) {
        $processed = array();

        foreach ( $events as $event ) {
            $description = $event->getDescription() ?? '';

            // Extract tags from description
            $tags = $this->parser->extract_tags( $description );

            // Clean description (remove tags)
            $clean_description = $this->parser->strip_tags( $description );

            // Get start/end times
            $start = $event->getStart();
            $end   = $event->getEnd();

            // Handle all-day events
            $is_all_day = ! empty( $start->date );

            $start_time = $is_all_day ? $start->date : $start->dateTime;
            $end_time   = $is_all_day ? $end->date : $end->dateTime;

            // Get location
            $location = $event->getLocation() ?? '';

            $processed[] = array(
                'id'          => $event->getId(),
                'title'       => $event->getSummary() ?? __( '(No title)', 'gcal-tag-filter' ),
                'description' => $clean_description,
                'location'    => $location,
                'start'       => $start_time,
                'end'         => $end_time,
                'is_all_day'  => $is_all_day,
                'tags'        => $tags,
                'html_link'   => $event->getHtmlLink(),
                'map_link'    => $this->generate_map_link( $location ),
            );
        }

        return $processed;
    }

    /**
     * Filter events by tags.
     *
     * @param array $events Array of processed events.
     * @param array $tags   Array of tags to filter by.
     * @return array Filtered events.
     */
    private function filter_events_by_tags( $events, $tags ) {
        // Normalize tags to uppercase for comparison
        $tags = array_map( 'strtoupper', $tags );

        return array_filter(
            $events,
            function ( $event ) use ( $tags ) {
                if ( empty( $event['tags'] ) ) {
                    return false;
                }

                // Check if event has ANY of the specified tags (OR logic)
                $event_tags = array_map( 'strtoupper', $event['tags'] );

                foreach ( $tags as $tag ) {
                    if ( in_array( $tag, $event_tags, true ) ) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Generate Google Maps link from location text.
     *
     * @param string $location Location text.
     * @return string Map URL or empty string.
     */
    private function generate_map_link( $location ) {
        if ( empty( $location ) ) {
            return '';
        }

        return 'https://www.google.com/maps/search/' . rawurlencode( $location );
    }

    /**
     * Test connection to Google Calendar.
     *
     * @return array|WP_Error Test results or error.
     */
    public function test_connection() {
        $client = $this->oauth->get_authenticated_client();

        if ( ! $client ) {
            return new WP_Error(
                'auth_failed',
                __( 'Authentication failed. Please check your credentials.', 'gcal-tag-filter' )
            );
        }

        $calendar_id = $this->oauth->get_selected_calendar_id();

        if ( ! $calendar_id ) {
            return new WP_Error(
                'no_calendar',
                __( 'No calendar selected.', 'gcal-tag-filter' )
            );
        }

        try {
            $service = new Google_Service_Calendar( $client );

            // Try to fetch calendar info
            $calendar = $service->calendars->get( $calendar_id );

            // Try to fetch one event
            $events = $service->events->listEvents(
                $calendar_id,
                array( 'maxResults' => 1 )
            );

            return array(
                'success'      => true,
                'calendar'     => $calendar->getSummary(),
                'event_count'  => count( $events->getItems() ),
                'message'      => __( 'Connection successful!', 'gcal-tag-filter' ),
            );
        } catch ( Exception $e ) {
            error_log( 'GCal Connection Test Error: ' . $e->getMessage() );

            return new WP_Error(
                'connection_failed',
                sprintf(
                    /* translators: %s: error message */
                    __( 'Connection failed: %s', 'gcal-tag-filter' ),
                    $e->getMessage()
                )
            );
        }
    }
}
