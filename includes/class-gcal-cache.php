<?php
/**
 * Cache Management
 *
 * Handles caching of Google Calendar events using WordPress transients.
 *
 * @package GCal_Tag_Filter
 */

class GCal_Cache {

    /**
     * Cache key prefix.
     */
    const CACHE_PREFIX = 'gcal_tag_filter_';

    /**
     * Default cache duration in seconds (1 minute).
     */
    const DEFAULT_DURATION = 60;

    /**
     * Option name for cache duration setting.
     */
    const OPTION_DURATION = 'gcal_tag_filter_cache_duration';

    /**
     * Get cached events.
     *
     * @param string $key Cache key.
     * @return mixed|false Cached data or false if not found/expired.
     */
    public function get( $key ) {
        // Don't return cache if caching is disabled
        if ( $this->get_cache_duration() === 0 ) {
            return false;
        }

        $cache_key = $this->get_cache_key( $key );
        return get_transient( $cache_key );
    }

    /**
     * Set cached events.
     *
     * @param string $key  Cache key.
     * @param mixed  $data Data to cache.
     * @return bool True on success, false on failure.
     */
    public function set( $key, $data ) {
        $duration = $this->get_cache_duration();

        // Don't cache if duration is 0
        if ( $duration === 0 ) {
            return false;
        }

        $cache_key = $this->get_cache_key( $key );
        return set_transient( $cache_key, $data, $duration );
    }

    /**
     * Delete cached events.
     *
     * @param string $key Cache key.
     * @return bool True on success, false on failure.
     */
    public function delete( $key ) {
        $cache_key = $this->get_cache_key( $key );
        return delete_transient( $cache_key );
    }

    /**
     * Generate cache key based on parameters.
     *
     * @param string $period Period: 'week', 'month', or 'year'.
     * @param array  $tags   Optional. Array of tags.
     * @param int    $year   Optional. Specific year.
     * @param int    $month  Optional. Specific month (1-12).
     * @param int    $week   Optional. Specific week number.
     * @return string Cache key.
     */
    public function generate_key( $period, $tags = array(), $year = null, $month = null, $week = null ) {
        $oauth       = new GCal_OAuth();
        $calendar_id = $oauth->get_selected_calendar_id();

        // Sort tags for consistent cache keys
        sort( $tags );

        // Add week/month/year to cache key to handle different time ranges
        $date_key = '';
        if ( $period === 'week' ) {
            if ( $year && $month ) {
                $date_key = sprintf( '%d-W%02d', $year, $week ? $week : 1 );
            } else {
                $now = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
                $day_of_week = (int) $now->format( 'N' );
                $monday = clone $now;
                $monday->modify( '-' . ( $day_of_week - 1 ) . ' days' );
                $date_key = $monday->format( 'Y-m-d' );
            }
        } elseif ( $period === 'month' ) {
            if ( $year && $month ) {
                $date_key = sprintf( '%d-%02d', $year, $month );
            } else {
                $date_key = date( 'Y-m' );
            }
        } elseif ( $period === 'year' ) {
            if ( $year ) {
                $date_key = (string) $year;
            } else {
                $date_key = date( 'Y' );
            }
        }

        // Create a unique key
        $key_parts = array(
            $calendar_id,
            $period,
            $date_key,
            implode( '_', $tags ),
        );

        return md5( implode( '|', $key_parts ) );
    }

    /**
     * Get full cache key with prefix.
     *
     * @param string $key Short cache key.
     * @return string Full cache key.
     */
    private function get_cache_key( $key ) {
        return self::CACHE_PREFIX . $key;
    }

    /**
     * Get cache duration in seconds.
     *
     * @return int Cache duration.
     */
    public function get_cache_duration() {
        $duration = get_option( self::OPTION_DURATION, self::DEFAULT_DURATION );

        // Ensure it's a positive integer
        $duration = absint( $duration );

        // Max 1 hour (3600 seconds)
        if ( $duration > 3600 ) {
            $duration = 3600;
        }

        // Min 0 seconds (no cache)
        if ( $duration < 0 ) {
            $duration = 0;
        }

        return $duration;
    }

    /**
     * Set cache duration.
     *
     * @param int $duration Duration in seconds (0-3600).
     * @return bool True on success, false on failure.
     */
    public function set_cache_duration( $duration ) {
        $duration = absint( $duration );

        // Enforce limits
        if ( $duration > 3600 ) {
            $duration = 3600;
        }

        return update_option( self::OPTION_DURATION, $duration );
    }

    /**
     * Clear all plugin caches.
     *
     * @return int Number of caches cleared.
     */
    public static function clear_all_cache() {
        global $wpdb;

        // Delete all transients with our prefix
        $pattern = '_transient_' . self::CACHE_PREFIX . '%';

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $pattern,
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );

        return $deleted;
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache stats.
     */
    public function get_cache_stats() {
        global $wpdb;

        // Count cached items
        $pattern = '_transient_' . self::CACHE_PREFIX . '%';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );

        // Get last cache time (most recent transient)
        $last_cache = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 ORDER BY option_id DESC
                 LIMIT 1",
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );

        $last_cache_time = null;
        if ( $last_cache ) {
            $last_cache_time = date_i18n(
                get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                $last_cache - $this->get_cache_duration()
            );
        }

        return array(
            'cached_items'    => absint( $count ),
            'last_cache_time' => $last_cache_time,
            'duration'        => $this->get_cache_duration(),
        );
    }
}
