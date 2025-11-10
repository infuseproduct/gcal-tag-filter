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
     * @param string $period Period: 'week', 'month', 'year', or 'future'.
     * @param array  $tags   Optional. Array of tags to filter by.
     * @param int    $year   Optional. Specific year to fetch events for.
     * @param int    $month  Optional. Specific month to fetch events for (1-12).
     * @param int    $week   Optional. Specific week number.
     * @return array|WP_Error Array of events or WP_Error on failure.
     */
    public function get_events( $period, $tags = array(), $year = null, $month = null, $week = null ) {
        error_log( '=== GCal Get Events ===' );
        error_log( 'Period: ' . $period . ', Tags: ' . ( empty( $tags ) ? 'NONE' : implode( ',', $tags ) ) );

        // Allow bypassing cache with query parameter for debugging
        $bypass_cache = isset( $_GET['gcal_debug'] ) && $_GET['gcal_debug'] === '1';

        // Check cache first
        $cache_key = $this->cache->generate_key( $period, $tags, $year, $month, $week );
        $cached_events = $this->cache->get( $cache_key );

        if ( $cached_events !== false && ! $bypass_cache ) {
            error_log( 'Returning cached events: ' . count( $cached_events ) );
            return $cached_events;
        }

        error_log( $bypass_cache ? 'Cache bypassed via gcal_debug parameter' : 'Cache miss, fetching from API' );

        // Fetch from API
        $events = $this->fetch_events_from_api( $period, $year, $month, $week );

        if ( is_wp_error( $events ) ) {
            error_log( 'API fetch error: ' . $events->get_error_message() );
            // Output to browser console for debugging
            add_action( 'wp_footer', function() use ( $events ) {
                echo '<script>console.error("GCal API Error: ' . esc_js( $events->get_error_message() ) . '");</script>';
            } );
            return $events;
        }

        // Parse and process events
        $processed_events = $this->process_events( $events );
        error_log( 'Processed events: ' . count( $processed_events ) );

        // Output event count to browser console for debugging
        $count = count( $processed_events );
        add_action( 'wp_footer', function() use ( $count, $period ) {
            echo '<script>console.log("GCal: Fetched ' . intval( $count ) . ' events for period: ' . esc_js( $period ) . '");</script>';
        } );

        // Filter by tags if specified
        if ( ! empty( $tags ) ) {
            $processed_events = $this->filter_events_by_tags( $processed_events, $tags );
            error_log( 'After tag filter: ' . count( $processed_events ) . ' events' );
        } else {
            // When no tags specified, hide untagged and unknown-tag events from non-admins
            if ( ! current_user_can( 'manage_options' ) ) {
                $processed_events = array_filter(
                    $processed_events,
                    function ( $event ) {
                        // Show only events with valid tags
                        return ! empty( $event['tags'] );
                    }
                );
                error_log( 'After untagged/unknown filter (non-admin): ' . count( $processed_events ) . ' events' );
            }
        }

        // Cache the results
        $this->cache->set( $cache_key, $processed_events );

        return $processed_events;
    }

    /**
     * Fetch events from Google Calendar API.
     *
     * @param string $period Period: 'week', 'month', 'year', or 'future'.
     * @param int    $year   Optional. Specific year to fetch events for.
     * @param int    $month  Optional. Specific month to fetch events for (1-12).
     * @param int    $week   Optional. Specific week number.
     * @return array|WP_Error Array of events or WP_Error on failure.
     */
    private function fetch_events_from_api( $period, $year = null, $month = null, $week = null ) {
        error_log( '=== GCal Fetch Events ===' );

        $client = $this->oauth->get_authenticated_client();

        if ( ! $client ) {
            error_log( 'Client authentication failed' );
            return new WP_Error(
                'auth_failed',
                __( 'Not authenticated with Google Calendar. Please connect your account in the plugin settings.', 'gcal-tag-filter' )
            );
        }

        $calendar_id = $this->oauth->get_selected_calendar_id();
        error_log( 'Calendar ID: ' . ( $calendar_id ? $calendar_id : 'NONE' ) );

        if ( ! $calendar_id ) {
            error_log( 'No calendar selected' );
            return new WP_Error(
                'no_calendar',
                __( 'No calendar selected. Please select a calendar in the plugin settings.', 'gcal-tag-filter' )
            );
        }

        try {
            $service = new Google_Service_Calendar( $client );

            // Calculate time range based on period
            list( $time_min, $time_max ) = $this->get_time_range( $period, $year, $month, $week );
            error_log( 'Time range: ' . $time_min . ' to ' . ( $time_max ? $time_max : 'FUTURE' ) );

            // Output time range to browser console for debugging
            add_action( 'wp_footer', function() use ( $time_min, $time_max, $period, $year ) {
                echo '<script>console.log("PHP API call - period=' . esc_js( $period ) . ', year=' . ( $year ? $year : 'NULL' ) . ', timeMin=' . esc_js( $time_min ) . ', timeMax=' . esc_js( $time_max ? $time_max : 'NONE' ) . '");</script>';
            } );

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

            // Debug: Log first 3 event dates to console
            if ( count( $items ) > 0 ) {
                $sample_dates = array();
                for ( $i = 0; $i < min( 3, count( $items ) ); $i++ ) {
                    $event = $items[ $i ];
                    $start_obj = $event->getStart();
                    $start_time = ! empty( $start_obj->date ) ? $start_obj->date : $start_obj->dateTime;
                    $sample_dates[] = substr( $start_time, 0, 10 ) . ' - ' . $event->getSummary();
                }
                add_action( 'wp_footer', function() use ( $sample_dates ) {
                    echo '<script>console.log("Sample event dates: ' . esc_js( implode( ', ', $sample_dates ) ) . '");</script>';
                } );
            }

            return $items;
        } catch ( Exception $e ) {
            error_log( 'GCal API Error: ' . $e->getMessage() );

            return new WP_Error(
                'api_error',
                sprintf(
                    /* translators: %s: error message */
                    __( 'Failed to retrieve events from Google Calendar: %s', 'gcal-tag-filter' ),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Get time range for period.
     *
     * @param string $period Period: 'week', 'month', 'year', or 'future'.
     * @param int    $year   Optional. Specific year to fetch events for.
     * @param int    $month  Optional. Specific month to fetch events for (1-12).
     * @param int    $week   Optional. Specific week number.
     * @return array Array with timeMin and timeMax.
     */
    private function get_time_range( $period, $year = null, $month = null, $week = null ) {
        // Use provided year or current year
        $target_year = $year ? $year : (int) date( 'Y' );
        $target_month = $month ? $month : (int) date( 'n' );

        $now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );

        // If specific year/month provided, adjust the base date
        if ( $year ) {
            $now->setDate( $target_year, $target_month, 1 );
        }

        switch ( $period ) {
            case 'future':
                // Get all upcoming events from now through the next 3 years
                // (or until hitting the 100 event limit from the API)
                $start_time = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
                $start_time->setTime( 0, 0, 0 ); // Start of today
                $time_min = $start_time->format( DateTime::RFC3339 );

                // Set end to 3 years from now
                $end_time = clone $start_time;
                $end_time->modify( '+3 years' );
                $end_time->setTime( 23, 59, 59 );
                $time_max = $end_time->format( DateTime::RFC3339 );
                break;

            case 'week':
                // Calculate the Monday of the specified week
                if ( $year && $month && $week ) {
                    // Same logic as render_week_view()
                    $first_of_month = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
                    $first_of_month->setDate( $target_year, $target_month, 1 );
                    $first_day_weekday = (int) $first_of_month->format( 'N' );

                    error_log( 'GCal Calendar - Week calculation: year=' . $target_year . ', month=' . $target_month . ', week=' . $week . ', first_day_weekday=' . $first_day_weekday );

                    if ( $week === 1 && $first_day_weekday > 1 ) {
                        // Week 1 includes days before the first Monday
                        $start_of_week = clone $first_of_month;
                        $days_back = $first_day_weekday - 1;
                        $start_of_week->modify( '-' . $days_back . ' days' );
                        error_log( 'GCal Calendar - Week 1 special case: going back ' . $days_back . ' days' );
                    } else {
                        // Calculate from first of month
                        $start_of_week = clone $first_of_month;
                        $start_of_week->modify( '-' . ( $first_day_weekday - 1 ) . ' days' );
                        $start_of_week->modify( '+' . ( ( $week - 1 ) * 7 ) . ' days' );
                        error_log( 'GCal Calendar - Normal week calculation: week ' . $week );
                    }
                } else {
                    // Get current week (Monday)
                    $start_of_week = clone $now;
                    $day_of_week = (int) $start_of_week->format( 'N' );
                    $start_of_week->modify( '-' . ( $day_of_week - 1 ) . ' days' );
                    error_log( 'GCal Calendar - Using current week (no params provided)' );
                }

                $start_of_week->setTime( 0, 0, 0 );
                $time_min = $start_of_week->format( DateTime::RFC3339 );

                $end_of_week = clone $start_of_week;
                $end_of_week->modify( '+6 days' ); // Changed from +7 to +6 to get Sun 23:59:59
                $end_of_week->setTime( 23, 59, 59 );
                $time_max = $end_of_week->format( DateTime::RFC3339 );

                error_log( 'GCal Calendar - Week range: ' . $start_of_week->format('Y-m-d') . ' to ' . $end_of_week->format('Y-m-d') );
                break;

            case 'month':
                // Get start of month - use setDate for reliability
                $start_of_month = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
                $start_of_month->setDate( $target_year, $target_month, 1 );
                $start_of_month->setTime( 0, 0, 0 );
                $time_min = $start_of_month->format( DateTime::RFC3339 );

                // Get end of month - calculate last day
                $end_of_month = clone $start_of_month;
                $last_day = (int) $end_of_month->format( 't' ); // Days in month
                $end_of_month->setDate( $target_year, $target_month, $last_day );
                $end_of_month->setTime( 23, 59, 59 );
                $time_max = $end_of_month->format( DateTime::RFC3339 );
                break;

            case 'year':
            default:
                // Get start of year - use setDate instead of modify() for reliability
                $start_of_year = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
                $start_of_year->setDate( $target_year, 1, 1 );
                $start_of_year->setTime( 0, 0, 0 );
                $time_min = $start_of_year->format( DateTime::RFC3339 );

                // Get end of year
                $end_of_year = clone $start_of_year;
                $end_of_year->setDate( $target_year, 12, 31 );
                $end_of_year->setTime( 23, 59, 59 );
                $time_max = $end_of_year->format( DateTime::RFC3339 );
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

            // Extract tags from description (returns array with 'valid' and 'invalid' keys)
            $tag_result = $this->parser->extract_tags( $description );
            $valid_tags = isset( $tag_result['valid'] ) ? $tag_result['valid'] : array();
            $invalid_tags = isset( $tag_result['invalid'] ) ? $tag_result['invalid'] : array();

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

            // Determine event status
            $has_valid_tags = ! empty( $valid_tags );
            $has_invalid_tags = ! empty( $invalid_tags );
            $has_no_tags = empty( $valid_tags ) && empty( $invalid_tags );

            $processed[] = array(
                'id'               => $event->getId(),
                'title'            => $event->getSummary() ?? __( '(Untitled)', 'gcal-tag-filter' ),
                'description'      => $clean_description,
                'location'         => $location,
                'start'            => $start_time,
                'end'              => $end_time,
                'is_all_day'       => $is_all_day,
                'tags'             => $valid_tags,
                'invalid_tags'     => $invalid_tags,
                'is_untagged'      => $has_no_tags,
                'has_unknown_tags' => $has_invalid_tags && ! $has_valid_tags, // Only invalid tags, no valid ones
                'html_link'        => $event->getHtmlLink(),
                'map_link'         => $this->generate_map_link( $location ),
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

        // Check if current user is admin
        $is_admin = current_user_can( 'manage_options' );

        return array_filter(
            $events,
            function ( $event ) use ( $tags, $is_admin ) {
                // For events with unknown tags only (no valid tags): show to admins, hide from non-admins
                if ( ! empty( $event['has_unknown_tags'] ) ) {
                    return $is_admin;
                }

                // For untagged events: show to admins, hide from non-admins
                if ( empty( $event['tags'] ) ) {
                    return $is_admin;
                }

                // Check if event has ANY of the specified tags (OR logic)
                $event_tags = array_map( 'strtoupper', $event['tags'] );

                foreach ( $tags as $tag ) {
                    // Check for wildcard pattern (e.g., "MESSE*")
                    if ( strpos( $tag, '*' ) !== false ) {
                        // Convert wildcard to regex pattern
                        $pattern = '/^' . str_replace( '\*', '.*', preg_quote( $tag, '/' ) ) . '$/';

                        // Check if any event tag matches the wildcard pattern
                        foreach ( $event_tags as $event_tag ) {
                            if ( preg_match( $pattern, $event_tag ) ) {
                                return true;
                            }
                        }
                    } else {
                        // Exact match
                        if ( in_array( $tag, $event_tags, true ) ) {
                            return true;
                        }
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
