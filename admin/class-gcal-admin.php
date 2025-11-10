<?php
/**
 * Admin Interface
 *
 * Handles the plugin settings page in WordPress admin.
 *
 * @package GCal_Tag_Filter
 */

class GCal_Admin {

    /**
     * OAuth handler instance.
     *
     * @var GCal_OAuth
     */
    private $oauth;

    /**
     * Cache handler instance.
     *
     * @var GCal_Cache
     */
    private $cache;

    /**
     * Calendar handler instance.
     *
     * @var GCal_Calendar
     */
    private $calendar;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->oauth    = new GCal_OAuth();
        $this->cache    = new GCal_Cache();
        $this->calendar = new GCal_Calendar();

        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Handle OAuth callback
        add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );

        // Handle AJAX requests
        add_action( 'wp_ajax_gcal_save_credentials', array( $this, 'ajax_save_credentials' ) );
        add_action( 'wp_ajax_gcal_disconnect', array( $this, 'ajax_disconnect' ) );
        add_action( 'wp_ajax_gcal_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_gcal_clear_cache', array( $this, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_gcal_add_category', array( $this, 'ajax_add_category' ) );
        add_action( 'wp_ajax_gcal_update_category', array( $this, 'ajax_update_category' ) );
        add_action( 'wp_ajax_gcal_delete_category', array( $this, 'ajax_delete_category' ) );
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Google Calendar Tag Filter', 'google-calendar-tag-filter' ),
            __( 'Calendar Filter', 'google-calendar-tag-filter' ),
            'manage_options',
            'google-calendar-tag-filter-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings.
     *
     * NOTE: Client ID and Secret are NOT registered here because they are saved via AJAX.
     * Registering them would cause WordPress to delete them when saving other settings
     * (like calendar selection) because they're not present in those forms.
     */
    public function register_settings() {
        register_setting(
            'gcal_tag_filter_options',
            GCal_OAuth::OPTION_CALENDAR_ID,
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            )
        );
        register_setting(
            'gcal_tag_filter_options',
            GCal_Cache::OPTION_DURATION,
            array(
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            )
        );
        register_setting(
            'gcal_tag_filter_options',
            GCal_Categories::OPTION_CATEGORIES,
            array(
                'type' => 'array',
                'sanitize_callback' => array( $this, 'sanitize_categories' ),
            )
        );
    }

    /**
     * Sanitize categories array.
     *
     * @param array $categories Categories to sanitize.
     * @return array Sanitized categories.
     */
    public function sanitize_categories( $categories ) {
        if ( ! is_array( $categories ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $categories as $category ) {
            if ( is_array( $category ) ) {
                $sanitized[] = array(
                    'slug' => isset( $category['slug'] ) ? sanitize_key( $category['slug'] ) : '',
                    'name' => isset( $category['name'] ) ? sanitize_text_field( $category['name'] ) : '',
                    'color' => isset( $category['color'] ) ? sanitize_hex_color( $category['color'] ) : '',
                );
            }
        }

        return $sanitized;
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our settings page
        if ( 'settings_page_google-calendar-tag-filter-settings' !== $hook ) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'gcal-admin',
            GCAL_TAG_FILTER_URL . 'admin/css/admin.css',
            array( 'wp-color-picker' ),
            GCAL_TAG_FILTER_VERSION
        );

        // Scripts
        wp_enqueue_script(
            'gcal-admin',
            GCAL_TAG_FILTER_URL . 'admin/js/admin.js',
            array( 'jquery', 'wp-color-picker' ),
            GCAL_TAG_FILTER_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'gcal-admin',
            'gcalAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'gcal-admin-nonce' ),
                'strings' => array(
                    'confirmDelete'     => __( 'Are you sure you want to delete this category?', 'google-calendar-tag-filter' ),
                    'confirmDisconnect' => __( 'Are you sure you want to disconnect your Google Calendar?', 'google-calendar-tag-filter' ),
                    'confirmClearCache' => __( 'Are you sure you want to clear the cache?', 'google-calendar-tag-filter' ),
                ),
            )
        );
    }

    /**
     * Handle OAuth callback.
     */
    public function handle_oauth_callback() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google, verified by user capability check
        if ( ! isset( $_GET['gcal_oauth_callback'] ) || ! isset( $_GET['code'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'google-calendar-tag-filter' ) );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from Google, verified by user capability check
        $code = sanitize_text_field( wp_unslash( $_GET['code'] ) );

        if ( $this->oauth->handle_callback( $code ) ) {
            add_settings_error(
                'gcal_tag_filter_messages',
                'gcal_oauth_success',
                __( 'Successfully connected to Google Calendar!', 'google-calendar-tag-filter' ),
                'success'
            );
        } else {
            add_settings_error(
                'gcal_tag_filter_messages',
                'gcal_oauth_error',
                __( 'Failed to connect to Google Calendar. Please try again.', 'google-calendar-tag-filter' ),
                'error'
            );
        }

        // Redirect to settings page
        wp_safe_redirect( admin_url( 'options-general.php?page=google-calendar-tag-filter-settings' ) );
        exit;
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check for settings errors
        settings_errors( 'gcal_tag_filter_messages' );

        $is_authenticated = $this->oauth->is_authenticated();
        $selected_calendar = $this->oauth->get_selected_calendar_id();
        $cache_stats = $this->cache->get_cache_stats();
        $categories = GCal_Categories::get_categories();

        // Debug info
        $client_id = get_option( GCal_OAuth::OPTION_CLIENT_ID );
        $client_secret = get_option( GCal_OAuth::OPTION_CLIENT_SECRET );
        $access_token = get_option( GCal_OAuth::OPTION_ACCESS_TOKEN );
        $calendars = false;
        if ( $is_authenticated ) {
            $calendars = $this->oauth->get_calendar_list();
        }

        ?>
        <script>
        console.log('=== GCal Admin Debug ===');
        console.log('Is Authenticated:', <?php echo $is_authenticated ? 'true' : 'false'; ?>);
        console.log('Client ID exists:', <?php echo ! empty( $client_id ) ? 'true' : 'false'; ?>);
        console.log('Client Secret exists:', <?php echo ! empty( $client_secret ) ? 'true' : 'false'; ?>);
        console.log('Access Token exists:', <?php echo ! empty( $access_token ) ? 'true' : 'false'; ?>);
        console.log('Selected Calendar:', <?php echo wp_json_encode( $selected_calendar ); ?>);
        console.log('Calendars retrieved:', <?php echo $calendars !== false ? 'true' : 'false'; ?>);
        <?php if ( $calendars !== false ) : ?>
        console.log('Calendar count:', <?php echo count( $calendars ); ?>);
        <?php endif; ?>
        </script>

        <div class="wrap">
            <h1>
                <?php echo esc_html( get_admin_page_title() ); ?>
                <span style="font-size: 0.5em; font-weight: normal; color: #666; margin-left: 10px;">
                    v<?php echo esc_html( GCAL_TAG_FILTER_VERSION ); ?>
                </span>
            </h1>

            <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
            <div class="notice notice-info">
                <p><strong>Debug Info:</strong></p>
                <ul style="font-family: monospace; font-size: 11px;">
                    <li>Authenticated: <?php echo $is_authenticated ? 'YES' : 'NO'; ?></li>
                    <li>Client ID: <?php echo ! empty( $client_id ) ? 'EXISTS' : 'MISSING'; ?></li>
                    <li>Client Secret: <?php echo ! empty( $client_secret ) ? 'EXISTS' : 'MISSING'; ?></li>
                    <li>Access Token: <?php echo ! empty( $access_token ) ? 'EXISTS' : 'MISSING'; ?></li>
                    <li>Calendar ID: <?php echo $selected_calendar ? esc_html( $selected_calendar ) : 'NONE'; ?></li>
                    <li>Calendars Retrieved: <?php echo $calendars !== false ? 'YES (' . count( $calendars ) . ')' : 'NO'; ?></li>
                </ul>
            </div>
            <?php endif; ?>

            <div class="gcal-admin-container">
                <!-- OAuth Connection Section -->
                <div class="gcal-admin-section">
                    <h2><?php esc_html_e( 'Google Calendar Connection', 'google-calendar-tag-filter' ); ?></h2>

                    <?php if ( $is_authenticated ) : ?>
                        <div class="gcal-connection-status gcal-connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e( 'Connected', 'google-calendar-tag-filter' ); ?>
                        </div>

                        <?php if ( $selected_calendar ) : ?>
                            <p>
                                <?php
                                /* translators: %s: Calendar name */
                                printf( esc_html__( 'Selected Calendar: <strong>%s</strong>', 'google-calendar-tag-filter' ), esc_html( $selected_calendar ) );
                                ?>
                            </p>
                        <?php endif; ?>

                        <div class="gcal-actions" style="margin: 20px 0;">
                            <button type="button" class="button" id="gcal-refresh-calendars" style="margin-right: 10px;">
                                <?php esc_html_e( 'Refresh Calendar List', 'google-calendar-tag-filter' ); ?>
                            </button>
                            <button type="button" class="button" id="gcal-test-connection" style="margin-right: 10px;">
                                <?php esc_html_e( 'Test Connection', 'google-calendar-tag-filter' ); ?>
                            </button>
                            <button type="button" class="button gcal-button-danger" id="gcal-disconnect">
                                <?php esc_html_e( 'Disconnect', 'google-calendar-tag-filter' ); ?>
                            </button>
                        </div>

                        <?php
                        $calendars = $this->oauth->get_calendar_list();
                        if ( $calendars !== false && ! empty( $calendars ) ) :
                            ?>
                            <form method="post" action="options.php">
                                <?php settings_fields( 'gcal_tag_filter_options' ); ?>

                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="calendar_id"><?php esc_html_e( 'Select Calendar', 'google-calendar-tag-filter' ); ?></label>
                                        </th>
                                        <td>
                                            <select name="<?php echo esc_attr( GCal_OAuth::OPTION_CALENDAR_ID ); ?>" id="calendar_id" class="regular-text">
                                                <option value=""><?php esc_html_e( '-- Select a calendar --', 'google-calendar-tag-filter' ); ?></option>
                                                <?php foreach ( $calendars as $calendar ) : ?>
                                                    <option value="<?php echo esc_attr( $calendar['id'] ); ?>"
                                                        <?php selected( $selected_calendar, $calendar['id'] ); ?>>
                                                        <?php echo esc_html( $calendar['summary'] ); ?>
                                                        <?php echo $calendar['primary'] ? '(' . esc_html__( 'Primary', 'google-calendar-tag-filter' ) . ')' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">
                                                <?php esc_html_e( 'Choose which calendar to display events from.', 'google-calendar-tag-filter' ); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                                <?php submit_button( __( 'Save Calendar Selection', 'google-calendar-tag-filter' ) ); ?>
                            </form>
                        <?php else : ?>
                            <div class="notice notice-warning inline">
                                <p><?php esc_html_e( 'Unable to retrieve calendar list. Click "Test Connection" to diagnose the issue.', 'google-calendar-tag-filter' ); ?></p>
                            </div>
                        <?php endif; ?>

                    <?php else : ?>
                        <div class="gcal-connection-status gcal-disconnected">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php esc_html_e( 'Not Connected', 'google-calendar-tag-filter' ); ?>
                        </div>

                        <p><?php esc_html_e( 'Enter your Google OAuth credentials to connect to Google Calendar.', 'google-calendar-tag-filter' ); ?></p>

                        <form id="gcal-credentials-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="client_id"><?php esc_html_e( 'Client ID', 'google-calendar-tag-filter' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="client_id" id="client_id" class="regular-text" required />
                                        <p class="description">
                                            <?php
                                            printf(
                                                /* translators: %s: link to Google Cloud Console */
                                                esc_html__( 'Get this from your %s.', 'google-calendar-tag-filter' ),
                                                '<a href="https://console.cloud.google.com/apis/credentials" target="_blank">' . esc_html__( 'Google Cloud Console', 'google-calendar-tag-filter' ) . '</a>'
                                            );
                                            ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="client_secret"><?php esc_html_e( 'Client Secret', 'google-calendar-tag-filter' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="password" name="client_secret" id="client_secret" class="regular-text" required />
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php esc_html_e( 'Redirect URI', 'google-calendar-tag-filter' ); ?>
                                    </th>
                                    <td>
                                        <code><?php echo esc_html( $this->oauth->get_redirect_uri() ); ?></code>
                                        <p class="description">
                                            <?php esc_html_e( 'Use this as the authorized redirect URI in your Google Cloud Console OAuth credentials.', 'google-calendar-tag-filter' ); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <button type="submit" class="button button-primary">
                                <?php esc_html_e( 'Save and Connect with Google', 'google-calendar-tag-filter' ); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Category Management Section -->
                <div class="gcal-admin-section">
                    <h2><?php esc_html_e( 'Category Whitelist', 'google-calendar-tag-filter' ); ?></h2>
                    <p><?php esc_html_e( 'Manage the categories that can be used to tag events. Only whitelisted categories will be recognized.', 'google-calendar-tag-filter' ); ?></p>

                    <?php require_once GCAL_TAG_FILTER_PATH . 'admin/partials/category-manager.php'; ?>
                </div>

                <!-- Cache Settings Section -->
                <div class="gcal-admin-section">
                    <h2><?php esc_html_e( 'Cache Settings', 'google-calendar-tag-filter' ); ?></h2>

                    <?php require_once GCAL_TAG_FILTER_PATH . 'admin/partials/cache-settings.php'; ?>
                </div>

                <!-- Shortcode Documentation Section -->
                <div class="gcal-admin-section">
                    <h2><?php esc_html_e( 'Shortcode Usage', 'google-calendar-tag-filter' ); ?></h2>
                    <p><?php esc_html_e( 'Use the [gcal_embed] shortcode to display events on any page or post.', 'google-calendar-tag-filter' ); ?></p>

                    <h3><?php esc_html_e( 'Parameters', 'google-calendar-tag-filter' ); ?></h3>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Parameter', 'google-calendar-tag-filter' ); ?></th>
                                <th><?php esc_html_e( 'Options', 'google-calendar-tag-filter' ); ?></th>
                                <th><?php esc_html_e( 'Default', 'google-calendar-tag-filter' ); ?></th>
                                <th><?php esc_html_e( 'Description', 'google-calendar-tag-filter' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>view</code></td>
                                <td><code>calendar</code>, <code>list</code></td>
                                <td><code>list</code></td>
                                <td><?php esc_html_e( 'Display format: calendar grid or vertical list', 'google-calendar-tag-filter' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>period</code></td>
                                <td><code>week</code>, <code>month</code>, <code>year</code>, <code>future</code></td>
                                <td><code>year</code></td>
                                <td><?php esc_html_e( 'Time range to display. "future" shows all upcoming events (up to 100) and hides period navigation.', 'google-calendar-tag-filter' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>tags</code></td>
                                <td><?php esc_html_e( 'Category IDs (comma-separated)', 'google-calendar-tag-filter' ); ?></td>
                                <td><?php esc_html_e( 'Empty (all events)', 'google-calendar-tag-filter' ); ?></td>
                                <td><?php esc_html_e( 'Filter by categories. Supports wildcards (e.g., "MESSE*" matches all tags starting with MESSE).', 'google-calendar-tag-filter' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>show_categories</code></td>
                                <td><code>true</code>, <code>false</code></td>
                                <td><code>false</code></td>
                                <td><?php esc_html_e( 'Show category filter sidebar with interactive buttons', 'google-calendar-tag-filter' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>show_display_style</code></td>
                                <td><code>true</code>, <code>false</code></td>
                                <td><code>false</code></td>
                                <td><?php esc_html_e( 'Show calendar/list view toggle in sidebar', 'google-calendar-tag-filter' ); ?></td>
                            </tr>
                            <tr>
                                <td><code>hide_past</code></td>
                                <td><code>true</code>, <code>false</code></td>
                                <td><code>false</code></td>
                                <td><?php esc_html_e( 'Hide past events in list view (useful with year/month/week periods)', 'google-calendar-tag-filter' ); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="margin-top: 24px;"><?php esc_html_e( 'Examples', 'google-calendar-tag-filter' ); ?></h3>

                    <div style="background: #f5f5f5; padding: 16px; border-radius: 4px; margin-bottom: 12px;">
                        <strong><?php esc_html_e( 'Basic Calendar View (Current Month)', 'google-calendar-tag-filter' ); ?></strong>
                        <pre style="background: white; padding: 12px; margin-top: 8px; overflow-x: auto;"><code>[gcal_embed view="calendar" period="month"]</code></pre>
                    </div>

                    <div style="background: #f5f5f5; padding: 16px; border-radius: 4px; margin-bottom: 12px;">
                        <strong><?php esc_html_e( 'List of All Upcoming Events', 'google-calendar-tag-filter' ); ?></strong>
                        <pre style="background: white; padding: 12px; margin-top: 8px; overflow-x: auto;"><code>[gcal_embed view="list" period="future"]</code></pre>
                        <p style="margin: 8px 0 0 0; font-size: 13px; color: #666;">
                            <?php esc_html_e( 'Shows all future events (up to 100 from Google API). Period navigation is automatically hidden.', 'google-calendar-tag-filter' ); ?>
                        </p>
                    </div>

                    <div style="background: #f5f5f5; padding: 16px; border-radius: 4px; margin-bottom: 12px;">
                        <strong><?php esc_html_e( 'Filter by Specific Category', 'google-calendar-tag-filter' ); ?></strong>
                        <pre style="background: white; padding: 12px; margin-top: 8px; overflow-x: auto;"><code>[gcal_embed tags="WORKSHOP" view="list" period="year"]</code></pre>
                    </div>

                    <div style="background: #f5f5f5; padding: 16px; border-radius: 4px; margin-bottom: 12px;">
                        <strong><?php esc_html_e( 'Filter by Wildcard Pattern', 'google-calendar-tag-filter' ); ?></strong>
                        <pre style="background: white; padding: 12px; margin-top: 8px; overflow-x: auto;"><code>[gcal_embed tags="MESSE*" view="list" period="future"]</code></pre>
                        <p style="margin: 8px 0 0 0; font-size: 13px; color: #666;">
                            <?php esc_html_e( 'Matches all tags starting with "MESSE" (e.g., MESSE-MUI-WO, MESSE-AUTRE). Wildcards use asterisk (*) to match patterns.', 'google-calendar-tag-filter' ); ?>
                        </p>
                    </div>

                    <div style="background: #f5f5f5; padding: 16px; border-radius: 4px; margin-bottom: 12px;">
                        <strong><?php esc_html_e( 'Interactive Calendar with Sidebar', 'google-calendar-tag-filter' ); ?></strong>
                        <pre style="background: white; padding: 12px; margin-top: 8px; overflow-x: auto;"><code>[gcal_embed view="calendar" period="month" show_categories="true" show_display_style="true"]</code></pre>
                        <p style="margin: 8px 0 0 0; font-size: 13px; color: #666;">
                            <?php esc_html_e( 'Includes category filter buttons and view toggle (calendar/list) in the sidebar.', 'google-calendar-tag-filter' ); ?>
                        </p>
                    </div>

                    <div style="background: #f5f5f5; padding: 16px; border-radius: 4px; margin-bottom: 12px;">
                        <strong><?php esc_html_e( 'Hide Past Events (List View)', 'google-calendar-tag-filter' ); ?></strong>
                        <pre style="background: white; padding: 12px; margin-top: 8px; overflow-x: auto;"><code>[gcal_embed view="list" period="year" hide_past="true"]</code></pre>
                        <p style="margin: 8px 0 0 0; font-size: 13px; color: #666;">
                            <?php esc_html_e( 'Shows only upcoming events from today through the end of the year.', 'google-calendar-tag-filter' ); ?>
                        </p>
                    </div>

                    <h3 style="margin-top: 24px;"><?php esc_html_e( 'Wildcard Pattern Rules', 'google-calendar-tag-filter' ); ?></h3>
                    <ul style="margin-left: 20px;">
                        <li><?php esc_html_e( 'Use asterisk (*) to match zero or more characters', 'google-calendar-tag-filter' ); ?></li>
                        <li><code>MESSE*</code> - <?php esc_html_e( 'Matches all tags starting with MESSE', 'google-calendar-tag-filter' ); ?></li>
                        <li><code>*-WO</code> - <?php esc_html_e( 'Matches all tags ending with -WO', 'google-calendar-tag-filter' ); ?></li>
                        <li><code>MESSE*,REUNION*</code> - <?php esc_html_e( 'Matches tags starting with MESSE or REUNION (OR logic)', 'google-calendar-tag-filter' ); ?></li>
                        <li><?php esc_html_e( 'Wildcards bypass the category whitelist (no need to pre-define patterns)', 'google-calendar-tag-filter' ); ?></li>
                        <li><?php esc_html_e( 'Only alphanumeric characters, hyphens (-), underscores (_), and asterisk (*) allowed', 'google-calendar-tag-filter' ); ?></li>
                    </ul>

                    <h3 style="margin-top: 24px;"><?php esc_html_e( 'URL Parameters', 'google-calendar-tag-filter' ); ?></h3>
                    <p><?php esc_html_e( 'Users can override shortcode settings using URL parameters:', 'google-calendar-tag-filter' ); ?></p>
                    <ul style="margin-left: 20px;">
                        <li><code>?gcal_view=week</code> - <?php esc_html_e( 'Switch to week view', 'google-calendar-tag-filter' ); ?></li>
                        <li><code>?gcal_view=month</code> - <?php esc_html_e( 'Switch to month view', 'google-calendar-tag-filter' ); ?></li>
                        <li><code>?gcal_view=year</code> - <?php esc_html_e( 'Switch to year view', 'google-calendar-tag-filter' ); ?></li>
                        <li><code>?gcal_display=calendar</code> - <?php esc_html_e( 'Switch to calendar display', 'google-calendar-tag-filter' ); ?></li>
                        <li><code>?gcal_display=list</code> - <?php esc_html_e( 'Switch to list display', 'google-calendar-tag-filter' ); ?></li>
                        <li><code>?gcal_category=WORKSHOP</code> - <?php esc_html_e( 'Filter by specific category', 'google-calendar-tag-filter' ); ?></li>
                    </ul>

                    <p style="margin-top: 16px;">
                        <strong><?php esc_html_e( 'For complete documentation, see:', 'google-calendar-tag-filter' ); ?></strong>
                        <code>/docs/SHORTCODE-REFERENCE.md</code>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save OAuth credentials.
     */
    public function ajax_save_credentials() {
        check_ajax_referer( 'gcal-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'google-calendar-tag-filter' ) ) );
        }

        $client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
        $client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            wp_send_json_error( array( 'message' => __( 'Client ID and Secret are required.', 'google-calendar-tag-filter' ) ) );
        }

        if ( $this->oauth->save_credentials( $client_id, $client_secret ) ) {
            $auth_url = $this->oauth->get_auth_url();

            if ( empty( $auth_url ) ) {
                wp_send_json_error( array(
                    'message' => __( 'Credentials saved but failed to generate Google authorization URL. Please check your credentials and try again.', 'google-calendar-tag-filter' )
                ) );
            }

            wp_send_json_success( array(
                'message'  => __( 'Credentials saved. Redirecting to Google...', 'google-calendar-tag-filter' ),
                'auth_url' => $auth_url,
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to save credentials. Please check that they are valid.', 'google-calendar-tag-filter' ) ) );
        }
    }

    /**
     * AJAX: Disconnect OAuth.
     */
    public function ajax_disconnect() {
        check_ajax_referer( 'gcal-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'google-calendar-tag-filter' ) ) );
        }

        $this->oauth->disconnect();
        GCal_Cache::clear_all_cache();

        wp_send_json_success( array(
            'message' => __( 'Disconnected successfully.', 'google-calendar-tag-filter' ),
        ) );
    }

    /**
     * AJAX: Test connection.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'gcal-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'google-calendar-tag-filter' ) ) );
        }

        $result = $this->calendar->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( $result );
        }
    }

    /**
     * AJAX: Clear cache.
     */
    public function ajax_clear_cache() {
        check_ajax_referer( 'gcal-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'google-calendar-tag-filter' ) ) );
        }

        $deleted = GCal_Cache::clear_all_cache();

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %d: number of cache entries cleared */
                __( 'Cache cleared! %d entries removed.', 'google-calendar-tag-filter' ),
                $deleted
            ),
        ) );
    }

    /**
     * AJAX: Add category.
     */
    public function ajax_add_category() {
        check_ajax_referer( 'gcal-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'google-calendar-tag-filter' ) ) );
        }

        $id = isset( $_POST['id'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['id'] ) ) ) : '';
        $display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
        $color = isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#4285F4';

        $result = GCal_Categories::add_category( $id, $display_name, $color );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array(
                'message' => __( 'Category added successfully.', 'google-calendar-tag-filter' ),
            ) );
        }
    }

    /**
     * AJAX: Update category.
     */
    public function ajax_update_category() {
        check_ajax_referer( 'gcal-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'google-calendar-tag-filter' ) ) );
        }

        $id = isset( $_POST['id'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['id'] ) ) ) : '';
        $display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
        $color = isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#4285F4';

        $result = GCal_Categories::update_category( $id, $display_name, $color );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array(
                'message' => __( 'Category updated successfully.', 'google-calendar-tag-filter' ),
            ) );
        }
    }

    /**
     * AJAX: Delete category.
     */
    public function ajax_delete_category() {
        check_ajax_referer( 'gcal-admin-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'google-calendar-tag-filter' ) ) );
        }

        $id = isset( $_POST['id'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['id'] ) ) ) : '';

        $result = GCal_Categories::delete_category( $id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array(
                'message' => __( 'Category deleted successfully.', 'google-calendar-tag-filter' ),
            ) );
        }
    }
}
