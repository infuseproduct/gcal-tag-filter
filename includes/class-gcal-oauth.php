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
        } catch ( Exception $e ) {
            error_log( 'GCal OAuth Init Error: ' . $e->getMessage() );
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

        return $this->client->createAuthUrl();
    }

    /**
     * Handle OAuth callback.
     *
     * @param string $code Authorization code.
     * @return bool Success status.
     */
    public function handle_callback( $code ) {
        if ( ! $this->client ) {
            return false;
        }

        try {
            $token = $this->client->fetchAccessTokenWithAuthCode( $code );

            if ( isset( $token['error'] ) ) {
                return false;
            }

            // Store encrypted access token
            $access_token = $this->encrypt_token( $token['access_token'] );
            update_option( self::OPTION_ACCESS_TOKEN, $access_token );

            // Store encrypted refresh token if present
            if ( isset( $token['refresh_token'] ) ) {
                $refresh_token = $this->encrypt_token( $token['refresh_token'] );
                update_option( self::OPTION_REFRESH_TOKEN, $refresh_token );
            }

            return true;
        } catch ( Exception $e ) {
            error_log( 'GCal OAuth Error: ' . $e->getMessage() );
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
        if ( ! $this->client || ! $this->is_authenticated() ) {
            return null;
        }

        try {
            // Get decrypted access token
            $access_token = $this->decrypt_token( get_option( self::OPTION_ACCESS_TOKEN ) );

            if ( empty( $access_token ) ) {
                return null;
            }

            $this->client->setAccessToken( array( 'access_token' => $access_token ) );

            // Check if token is expired
            if ( $this->client->isAccessTokenExpired() ) {
                // Try to refresh
                if ( ! $this->refresh_token() ) {
                    return null;
                }
            }

            return $this->client;
        } catch ( Exception $e ) {
            error_log( 'GCal OAuth Error: ' . $e->getMessage() );
            return null;
        }
    }

    /**
     * Refresh access token.
     *
     * @return bool
     */
    private function refresh_token() {
        $refresh_token = $this->decrypt_token( get_option( self::OPTION_REFRESH_TOKEN ) );

        if ( empty( $refresh_token ) ) {
            return false;
        }

        try {
            $this->client->fetchAccessTokenWithRefreshToken( $refresh_token );
            $new_token = $this->client->getAccessToken();

            if ( isset( $new_token['access_token'] ) ) {
                $encrypted_token = $this->encrypt_token( $new_token['access_token'] );
                update_option( self::OPTION_ACCESS_TOKEN, $encrypted_token );
                return true;
            }

            return false;
        } catch ( Exception $e ) {
            error_log( 'GCal OAuth Refresh Error: ' . $e->getMessage() );
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
            error_log( 'GCal OAuth Revoke Error: ' . $e->getMessage() );
        }

        // Clear all stored credentials
        delete_option( self::OPTION_ACCESS_TOKEN );
        delete_option( self::OPTION_REFRESH_TOKEN );
        delete_option( self::OPTION_CALENDAR_ID );
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
            error_log( 'GCal Calendar List Error: ' . $e->getMessage() );
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
}
