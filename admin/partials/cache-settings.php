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
            return __( 'No caching', 'gcal-tag-filter' );
        }
        if ( $seconds < 60 ) {
            return sprintf(
                /* translators: %d: number of seconds */
                _n( '%d second', '%d seconds', $seconds, 'gcal-tag-filter' ),
                $seconds
            );
        }
        $minutes = floor( $seconds / 60 );
        return sprintf(
            /* translators: %d: number of minutes */
            _n( '%d minute', '%d minutes', $minutes, 'gcal-tag-filter' ),
            $minutes
        );
    }
}

$cache = new GCal_Cache();
$cache_stats = $cache->get_cache_stats();
$current_duration = $cache->get_cache_duration();
?>

<div class="gcal-cache-settings">
    <form method="post" action="options.php">
        <?php settings_fields( 'gcal_tag_filter_options' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="cache_duration"><?php esc_html_e( 'Cache Duration', 'gcal-tag-filter' ); ?></label>
                </th>
                <td>
                    <input type="range" name="<?php echo esc_attr( GCal_Cache::OPTION_DURATION ); ?>"
                           id="cache_duration" min="0" max="3600" step="60"
                           value="<?php echo esc_attr( $current_duration ); ?>"
                           class="gcal-cache-slider" />
                    <span id="cache_duration_display"><?php echo esc_html( gcal_format_duration( $current_duration ) ); ?></span>

                    <p class="description">
                        <?php esc_html_e( 'How long to cache calendar events before refreshing from Google Calendar. Set to 0 for no caching.', 'gcal-tag-filter' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button( __( 'Save Cache Settings', 'gcal-tag-filter' ) ); ?>
    </form>

    <!-- Cache Statistics -->
    <div class="gcal-cache-stats">
        <h3><?php esc_html_e( 'Cache Statistics', 'gcal-tag-filter' ); ?></h3>

        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Cached Items', 'gcal-tag-filter' ); ?></th>
                <td>
                    <strong><?php echo esc_html( $cache_stats['cached_items'] ); ?></strong>
                    <?php esc_html_e( 'items', 'gcal-tag-filter' ); ?>
                </td>
            </tr>
            <?php if ( $cache_stats['last_cache_time'] ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Last Refresh', 'gcal-tag-filter' ); ?></th>
                    <td><?php echo esc_html( $cache_stats['last_cache_time'] ); ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e( 'Current Duration', 'gcal-tag-filter' ); ?></th>
                <td><?php echo esc_html( gcal_format_duration( $cache_stats['duration'] ) ); ?></td>
            </tr>
        </table>

        <button type="button" class="button button-secondary" id="gcal-clear-cache">
            <?php esc_html_e( 'Clear Cache Now', 'gcal-tag-filter' ); ?>
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
            return '<?php echo esc_js( __( 'No caching', 'gcal-tag-filter' ) ); ?>';
        }
        if (seconds < 60) {
            return seconds + ' <?php echo esc_js( __( 'seconds', 'gcal-tag-filter' ) ); ?>';
        }
        var minutes = Math.floor(seconds / 60);
        if (minutes === 1) {
            return '1 <?php echo esc_js( __( 'minute', 'gcal-tag-filter' ) ); ?>';
        }
        return minutes + ' <?php echo esc_js( __( 'minutes', 'gcal-tag-filter' ) ); ?>';
    }
});
</script>
