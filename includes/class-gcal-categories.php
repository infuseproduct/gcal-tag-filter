<?php
/**
 * Category Whitelist Management
 *
 * Manages the whitelist of allowed categories/tags.
 *
 * @package GCal_Tag_Filter
 */

class GCal_Categories {

    /**
     * Option name for storing categories.
     */
    const OPTION_CATEGORIES = 'gcal_tag_filter_categories';

    /**
     * Default categories.
     */
    const DEFAULT_CATEGORIES = array(
        array(
            'id'           => 'COMMUNITY',
            'display_name' => 'Community Events',
            'color'        => '#4285F4',
        ),
        array(
            'id'           => 'WORKSHOP',
            'display_name' => 'Workshops',
            'color'        => '#0F9D58',
        ),
        array(
            'id'           => 'TRAINING',
            'display_name' => 'Training',
            'color'        => '#F4B400',
        ),
    );

    /**
     * Get all categories.
     *
     * @return array Array of categories.
     */
    public static function get_categories() {
        $categories = get_option( self::OPTION_CATEGORIES, array() );

        // If empty, return default categories
        if ( empty( $categories ) ) {
            $categories = self::DEFAULT_CATEGORIES;
            update_option( self::OPTION_CATEGORIES, $categories );
        }

        return $categories;
    }

    /**
     * Get category by ID.
     *
     * @param string $category_id Category ID.
     * @return array|null Category data or null if not found.
     */
    public static function get_category( $category_id ) {
        $categories = self::get_categories();

        foreach ( $categories as $category ) {
            if ( strtoupper( $category['id'] ) === strtoupper( $category_id ) ) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Add a new category.
     *
     * @param string $id           Category ID (uppercase alphanumeric with underscores/hyphens).
     * @param string $display_name Display name.
     * @param string $color        Hex color code.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function add_category( $id, $display_name, $color = '#4285F4' ) {
        // Validate ID format
        if ( ! GCal_Parser::is_valid_tag_format( $id ) ) {
            return new WP_Error(
                'invalid_format',
                __( 'Category ID must be uppercase alphanumeric with underscores or hyphens only.', 'google-calendar-tag-filter' )
            );
        }

        // Check if already exists
        if ( self::category_exists( $id ) ) {
            return new WP_Error(
                'already_exists',
                __( 'A category with this ID already exists.', 'google-calendar-tag-filter' )
            );
        }

        $categories = self::get_categories();

        $categories[] = array(
            'id'           => strtoupper( $id ),
            'display_name' => sanitize_text_field( $display_name ),
            'color'        => sanitize_hex_color( $color ),
        );

        return update_option( self::OPTION_CATEGORIES, $categories );
    }

    /**
     * Update a category.
     *
     * @param string $id           Category ID.
     * @param string $display_name Display name.
     * @param string $color        Hex color code.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function update_category( $id, $display_name, $color ) {
        $categories = self::get_categories();
        $found      = false;

        foreach ( $categories as $key => $category ) {
            if ( strtoupper( $category['id'] ) === strtoupper( $id ) ) {
                $categories[ $key ]['display_name'] = sanitize_text_field( $display_name );
                $categories[ $key ]['color']        = sanitize_hex_color( $color );
                $found = true;
                break;
            }
        }

        if ( ! $found ) {
            return new WP_Error(
                'not_found',
                __( 'Category not found.', 'google-calendar-tag-filter' )
            );
        }

        return update_option( self::OPTION_CATEGORIES, $categories );
    }

    /**
     * Delete a category.
     *
     * @param string $id Category ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function delete_category( $id ) {
        $categories = self::get_categories();
        $new_categories = array();
        $found = false;

        foreach ( $categories as $category ) {
            if ( strtoupper( $category['id'] ) === strtoupper( $id ) ) {
                $found = true;
                continue; // Skip this category (delete it)
            }
            $new_categories[] = $category;
        }

        if ( ! $found ) {
            return new WP_Error(
                'not_found',
                __( 'Category not found.', 'google-calendar-tag-filter' )
            );
        }

        return update_option( self::OPTION_CATEGORIES, $new_categories );
    }

    /**
     * Check if category exists.
     *
     * @param string $id Category ID.
     * @return bool True if exists, false otherwise.
     */
    public static function category_exists( $id ) {
        return self::get_category( $id ) !== null;
    }

    /**
     * Get category color.
     *
     * @param string $category_id Category ID.
     * @return string Color hex code or default.
     */
    public static function get_category_color( $category_id ) {
        $category = self::get_category( $category_id );

        if ( $category && isset( $category['color'] ) ) {
            return $category['color'];
        }

        return '#4285F4'; // Default blue
    }

    /**
     * Get category display name.
     *
     * @param string $category_id Category ID.
     * @return string Display name or ID if not found.
     */
    public static function get_category_display_name( $category_id ) {
        $category = self::get_category( $category_id );

        if ( $category && isset( $category['display_name'] ) ) {
            return $category['display_name'];
        }

        return $category_id; // Fallback to ID
    }

    /**
     * Reset to default categories.
     *
     * @return bool True on success.
     */
    public static function reset_to_defaults() {
        return update_option( self::OPTION_CATEGORIES, self::DEFAULT_CATEGORIES );
    }
}
