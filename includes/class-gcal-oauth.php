<?php
/**
 * OAuth 2.0 Authentication Handler
 *
 * Handles Google Calendar OAuth 2.0 authentication flow.
 *
 * @package GCal_Tag_Filter
 */

class GCal_OAuth {

    /**
     * Google Client instance.
     *
     * @var Google_Client
     */
    private $client;

    /**
     * OAuth scope for read-only calendar access.
     */
    const OAUTH_SCOPE = Google_Service_Calendar::CALENDAR_READONLY;

    /**
     * Option names for storing credentials.
     */
    const OPTION_CLIENT_ID     = 'gcal_tag_filter_client_id';
    const OPTION_CLIENT_SECRET = 'gcal_tag_filter_client_secret';
    const OPTION_ACCESS_TOKEN  = 'gcal_tag_filter_access_token';
    const OPTION_REFRESH_TOKEN = 'gcal_tag_filter_refresh_token';
    const OPTION_CALENDAR_ID   = 'gcal_tag_filter_calendar_id';
    const OPTION_OAUTH_STATE   = 'gcal_tag_filter_oauth_state';

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_client();
    }

    /**
     * Initialize Google Client.
     */
    private function init_client() {
        $client_id     = get_option( self::OPTION_CLIENT_ID );
        $client_secret = get_option( self::OPTION_CLIENT_SECRET );

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return;
        }

        try {
            $this->client = new Google_Client();
            $this->client->setClientId( $client_id );
            $this->client->setClientSecret( $client_secret );
            $this->client->setRedirectUri( $this->get_redirect_uri() );
            $this->client->addScope( self::OAUTH_SCOPE );
            $this->client->setAccessType( 'offline' );
            $this->client->setPrompt( 'consent' );
            $this->log_debug( 'init_client: Client initialized successfully' );
        } catch ( Exception $e ) {
            $this->log_error( 'init_client: Failed to initialize - ' . $e->getMessage() );
            $this->client = null;
        }
    }

    /**
     * Get OAuth redirect URI.
     *
     * @return string
     */
    public function get_redirect_uri() {
        return admin_url( 'admin.php?page=gcal-tag-filter-settings&gcal_oauth_callback=1' );
    }

    /**
     * Get authorization URL.
     *
     * @return string
     */
    public function get_auth_url() {
        if ( ! $this->client ) {
            return '';
        }

        // Generate and store state parameter for CSRF protection
        $state = wp_generate_password( 32, false );
        update_option( self::OPTION_OAUTH_STATE, $state );

        // Set state parameter on the client
        $this->client->setState( $state );

        return $this->client->createAuthUrl();
    }

    /**
     * Handle OAuth callback.
     *
     * @param string $code Authorization code.
     * @param string $state State parameter from callback.
     * @return bool Success status.
     */
    public function handle_callback( $code, $state = '' ) {
        if ( ! $this->client ) {
            $this->log_error( 'handle_callback: Client not initialized' );
            return false;
        }

        // Verify state parameter for CSRF protection
        $stored_state = get_option( self::OPTION_OAUTH_STATE );
        if ( empty( $stored_state ) || $stored_state !== $state ) {
            $this->log_error( 'handle_callback: Invalid state parameter (CSRF check failed)' );
            return false;
        }

        // Clear the state now that we've verified it
        delete_option( self::OPTION_OAUTH_STATE );

        try {
            $token = $this->client->fetchAccessTokenWithAuthCode( $code );

            if ( isset( $token['error'] ) ) {
                $this->log_error( 'handle_callback: Token exchange error - ' . $token['error'] );
                return false;
            }

            // Store encrypted access token
            $access_token = $this->encrypt_token( $token['access_token'] );
            update_option( self::OPTION_ACCESS_TOKEN, $access_token );
            $this->log_debug( 'handle_callback: Access token stored' );

            // Store encrypted refresh token if present
            if ( isset( $token['refresh_token'] ) ) {
                $refresh_token = $this->encrypt_token( $token['refresh_token'] );
                update_option( self::OPTION_REFRESH_TOKEN, $refresh_token );
                $this->log_debug( 'handle_callback: Refresh token stored' );
            }

            return true;
        } catch ( Exception $e ) {
            $this->log_error( 'handle_callback Exception: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if user is authenticated.
     *
     * @return bool
     */
    public function is_authenticated() {
        $access_token = get_option( self::OPTION_ACCESS_TOKEN );
        return ! empty( $access_token );
    }

    /**
     * Get authenticated Google Client.
     *
     * @return Google_Client|null
     */
    public function get_authenticated_client() {
        $this->log_debug( 'get_authenticated_client: Starting authentication check' );

        if ( ! $this->client ) {
            $this->log_error( 'get_authenticated_client: Client not initialized' );
            return null;
        }

        if ( ! $this->is_authenticated() ) {
            $this->log_error( 'get_authenticated_client: Not authenticated (is_authenticated returned false)' );
            return null;
        }

        try {
            // Get decrypted access token
            $encrypted_token = get_option( self::OPTION_ACCESS_TOKEN );
            $this->log_debug( 'get_authenticated_client: Encrypted token exists: ' . ( $encrypted_token ? 'yes' : 'no' ) );

            $access_token = $this->decrypt_token( $encrypted_token );

            if ( empty( $access_token ) ) {
                $this->log_error( 'get_authenticated_client: Access token is empty after decryption' );
                return null;
            }

            $this->log_debug( 'get_authenticated_client: Access token decrypted successfully (length: ' . strlen( $access_token ) . ')' );

            $this->client->setAccessToken( array( 'access_token' => $access_token ) );

            // Check if token is expired
            if ( $this->client->isAccessTokenExpired() ) {
                $this->log_debug( 'get_authenticated_client: Access token expired, attempting refresh' );
                // Try to refresh
                if ( ! $this->refresh_token() ) {
                    $this->log_error( 'get_authenticated_client: Token refresh failed' );
                    return null;
                }
                $this->log_debug( 'get_authenticated_client: Token refreshed successfully' );
            } else {
                $this->log_debug( 'get_authenticated_client: Access token is valid' );
            }

            $this->log_debug( 'get_authenticated_client: Returning authenticated client' );
            return $this->client;
        } catch ( Exception $e ) {
            $this->log_error( 'get_authenticated_client Exception: ' . $e->getMessage() );
            $this->log_error( 'Stack trace: ' . $e->getTraceAsString() );
            return null;
        }
    }

    /**
     * Refresh access token.
     *
     * @return bool
     */
    private function refresh_token() {
        $this->log_debug( 'refresh_token: Starting token refresh' );

        $encrypted_refresh_token = get_option( self::OPTION_REFRESH_TOKEN );
        $this->log_debug( 'refresh_token: Encrypted refresh token exists: ' . ( $encrypted_refresh_token ? 'yes' : 'no' ) );

        $refresh_token = $this->decrypt_token( $encrypted_refresh_token );

        if ( empty( $refresh_token ) ) {
            $this->log_error( 'refresh_token: Refresh token is empty after decryption' );
            return false;
        }

        $this->log_debug( 'refresh_token: Refresh token decrypted successfully (length: ' . strlen( $refresh_token ) . ')' );

        try {
            $this->log_debug( 'refresh_token: Calling Google API to fetch new access token' );
            $this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
            $new_token = $this->client->getAccessToken();

            $this->log_debug( 'refresh_token: Response received from Google' );

            if ( isset( $new_token['error'] ) ) {
                $this->log_error( 'refresh_token: Google returned error: ' . $new_token['error'] );
                if ( isset( $new_token['error_description'] ) ) {
                    $this->log_error( 'refresh_token: Error description: ' . $new_token['error_description'] );
                }
                return false;
            }

            if ( isset( $new_token['access_token'] ) ) {
                $this->log_debug( 'refresh_token: New access token received (length: ' . strlen( $new_token['access_token'] ) . ')' );
                $encrypted_token = $this->encrypt_token( $new_token['access_token'] );
                update_option( self::OPTION_ACCESS_TOKEN, $encrypted_token );
                $this->log_debug( 'refresh_token: New access token saved successfully' );
                return true;
            }

            $this->log_error( 'refresh_token: No access_token in response' );
            return false;
        } catch ( Exception $e ) {
            $this->log_error( 'refresh_token Exception: ' . $e->getMessage() );
            $this->log_error( 'Stack trace: ' . $e->getTraceAsString() );
            return false;
        }
    }

    /**
     * Disconnect (revoke access).
     */
    public function disconnect() {
        try {
            if ( $this->client ) {
                $this->client->revokeToken();
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'GCal OAuth Revoke Error: ' . $e->getMessage() );
            }
        }

        // Clear all stored credentials
        delete_option( self::OPTION_ACCESS_TOKEN );
        delete_option( self::OPTION_REFRESH_TOKEN );
        delete_option( self::OPTION_CALENDAR_ID );
        delete_option( self::OPTION_OAUTH_STATE );
    }

    /**
     * Save OAuth credentials.
     *
     * @param string $client_id     Client ID.
     * @param string $client_secret Client Secret.
     * @return bool
     */
    public function save_credentials( $client_id, $client_secret ) {
        $sanitized_id = sanitize_text_field( $client_id );
        $sanitized_secret = sanitize_text_field( $client_secret );

        update_option( self::OPTION_CLIENT_ID, $sanitized_id );
        update_option( self::OPTION_CLIENT_SECRET, $sanitized_secret );

        $this->init_client();

        return $this->client !== null;
    }

    /**
     * Get user's accessible calendars.
     *
     * @return array|false Array of calendars or false on error.
     */
    public function get_calendar_list() {
        $client = $this->get_authenticated_client();

        if ( ! $client ) {
            return false;
        }

        try {
            $service = new Google_Service_Calendar( $client );
            $calendar_list = $service->calendarList->listCalendarList();

            $calendars = array();
            foreach ( $calendar_list->getItems() as $calendar ) {
                $calendars[] = array(
                    'id'      => $calendar->getId(),
                    'summary' => $calendar->getSummary(),
                    'primary' => $calendar->getPrimary(),
                );
            }

            return $calendars;
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'GCal Calendar List Error: ' . $e->getMessage() );
            }
            return false;
        }
    }

    /**
     * Get selected calendar ID.
     *
     * @return string|false
     */
    public function get_selected_calendar_id() {
        return get_option( self::OPTION_CALENDAR_ID, false );
    }

    /**
     * Set selected calendar ID.
     *
     * @param string $calendar_id Calendar ID.
     */
    public function set_calendar_id( $calendar_id ) {
        update_option( self::OPTION_CALENDAR_ID, sanitize_text_field( $calendar_id ) );
    }

    /**
     * Encrypt token for secure storage.
     *
     * @param string $token Token to encrypt.
     * @return string
     */
    private function encrypt_token( $token ) {
        if ( ! $token ) {
            return '';
        }

        // Use WordPress auth key and salt for encryption
        $key    = wp_salt( 'auth' );
        $method = 'AES-256-CBC';

        if ( ! in_array( $method, openssl_get_cipher_methods(), true ) ) {
            // Fallback to base64 if encryption not available
            return base64_encode( $token );
        }

        $iv     = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $method ) );
        $encrypted = openssl_encrypt( $token, $method, $key, 0, $iv );

        return base64_encode( $encrypted . '::' . $iv );
    }

    /**
     * Decrypt token.
     *
     * @param string $encrypted_token Encrypted token.
     * @return string
     */
    private function decrypt_token( $encrypted_token ) {
        if ( ! $encrypted_token ) {
            return '';
        }

        $key    = wp_salt( 'auth' );
        $method = 'AES-256-CBC';

        $decoded = base64_decode( $encrypted_token );

        if ( strpos( $decoded, '::' ) === false ) {
            // Fallback for non-encrypted tokens
            return base64_decode( $encrypted_token );
        }

        list( $encrypted_data, $iv ) = explode( '::', $decoded, 2 );

        return openssl_decrypt( $encrypted_data, $method, $key, 0, $iv );
    }

    /**
     * Log debug message.
     *
     * @param string $message Debug message.
     */
    private function log_debug( $message ) {
        // Always log OAuth debug messages to help troubleshoot authentication issues
        // These go to the PHP error log, not displayed on the website
        error_log( '[GCal OAuth DEBUG] ' . $message );
    }

    /**
     * Log error message.
     *
     * @param string $message Error message.
     */
    private function log_error( $message ) {
        // Always log OAuth errors - these are critical authentication issues
        error_log( '[GCal OAuth ERROR] ' . $message );
    }
}
