<?php
/**
 * Cache Settings Partial
 *
 * Template for cache management interface.
 *
 * @package GCal_Tag_Filter
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper function to format duration for display.
 */
if ( ! function_exists( 'gcal_format_duration' ) ) {
    /**
     * Format duration in seconds to human-readable string.
     *
     * @param int $seconds Duration in seconds.
     * @return string Formatted duration.
     */
    function gcal_format_duration( $seconds ) {
        if ( $seconds === 0 ) {
            return __( 'No caching', 'google-calendar-tag-filter' );
        }
        if ( $seconds < 60 ) {
            return sprintf(
                /* translators: %d: number of seconds */
                _n( '%d second', '%d seconds', $seconds, 'google-calendar-tag-filter' ),
                $seconds
            );
        }
        $minutes = floor( $seconds / 60 );
        return sprintf(
            /* translators: %d: number of minutes */
            _n( '%d minute', '%d minutes', $minutes, 'google-calendar-tag-filter' ),
            $minutes
        );
    }
}

$gcal_cache = new GCal_Cache();
$gcal_cache_stats = $gcal_cache->get_cache_stats();
$gcal_current_duration = $gcal_cache->get_cache_duration();
?>

<div class="gcal-cache-settings">
    <form method="post" action="options.php">
        <?php settings_fields( 'gcal_tag_filter_options' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cache_duration"><?php esc_html_e( 'Cache Duration', 'google-calendar-tag-filter' ); ?></label>
                </th>
                <td>
                    <input type="range" name="<?php echo esc_attr( GCal_Cache::OPTION_DURATION ); ?>"
                           id="cache_duration" min="0" max="3600" step="60"
                           value="<?php echo esc_attr( $gcal_current_duration ); ?>"
                           class="gcal-cache-slider" />
                    <span id="cache_duration_display"><?php echo esc_html( gcal_format_duration( $gcal_current_duration ) ); ?></span>

                    <p class="description">
                        <?php esc_html_e( 'How long to cache calendar events before refreshing from Google Calendar. Set to 0 for no caching.', 'google-calendar-tag-filter' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save Cache Settings', 'google-calendar-tag-filter' ) ); ?>
    </form>

    <!-- Cache Statistics -->
    <div class="gcal-cache-stats">
        <h3><?php esc_html_e( 'Cache Statistics', 'google-calendar-tag-filter' ); ?></h3>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Cached Items', 'google-calendar-tag-filter' ); ?></th>
                <td>
                    <strong><?php echo esc_html( $gcal_cache_stats['cached_items'] ); ?></strong>
                    <?php esc_html_e( 'items', 'google-calendar-tag-filter' ); ?>
                </td>
            </tr>
            <?php if ( $gcal_cache_stats['last_cache_time'] ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Last Refresh', 'google-calendar-tag-filter' ); ?></th>
                    <td><?php echo esc_html( $gcal_cache_stats['last_cache_time'] ); ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e( 'Current Duration', 'google-calendar-tag-filter' ); ?></th>
                <td><?php echo esc_html( gcal_format_duration( $gcal_cache_stats['duration'] ) ); ?></td>
            </tr>
        </table>

        <button type="button" class="button button-secondary" id="gcal-clear-cache">
            <?php esc_html_e( 'Clear Cache Now', 'google-calendar-tag-filter' ); ?>
        </button>
    </div>
</div>

<script type="text/javascript">
// Update cache duration display
jQuery(document).ready(function($) {
    $('#cache_duration').on('input', function() {
        var seconds = parseInt($(this).val());
        var display = formatDuration(seconds);
        $('#cache_duration_display').text(display);
    });

    function formatDuration(seconds) {
        if (seconds === 0) {
            return '<?php echo esc_js( __( 'No caching', 'google-calendar-tag-filter' ) ); ?>';
        }
        if (seconds < 60) {
            return seconds + ' <?php echo esc_js( __( 'seconds', 'google-calendar-tag-filter' ) ); ?>';
        }
        var minutes = Math.floor(seconds / 60);
        if (minutes === 1) {
            return '1 <?php echo esc_js( __( 'minute', 'google-calendar-tag-filter' ) ); ?>';
        }
        return minutes + ' <?php echo esc_js( __( 'minutes', 'google-calendar-tag-filter' ) ); ?>';
    }
});
</script>
