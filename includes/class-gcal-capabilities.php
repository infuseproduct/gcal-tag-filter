<?php
/**
 * Capabilities Management
 *
 * Manages custom capabilities for the GCal Tag Filter plugin.
 *
 * @package GCal_Tag_Filter
 */

class GCal_Capabilities {

	/**
	 * Custom capabilities for the plugin.
	 *
	 * @var array
	 */
	private static $capabilities = array(
		'gcal_manage_settings'   => 'Manage GCal Settings',     // OAuth, calendar selection, cache
		'gcal_manage_categories' => 'Manage GCal Categories',   // Add/edit/delete categories
		'gcal_view_admin'        => 'View GCal Admin Panel',    // Access admin pages
		'gcal_view_untagged'     => 'View Untagged Events',     // See events without tags
	);

	/**
	 * Get all custom capabilities.
	 *
	 * @return array Array of capability slugs and names.
	 */
	public static function get_capabilities() {
		return self::$capabilities;
	}

	/**
	 * Add capabilities to a role.
	 *
	 * @param string $role_name WordPress role name.
	 * @param array  $caps Array of capability slugs to add.
	 */
	public static function add_caps_to_role( $role_name, $caps = array() ) {
		$role = get_role( $role_name );

		if ( ! $role ) {
			return;
		}

		// If no specific caps provided, add all
		if ( empty( $caps ) ) {
			$caps = array_keys( self::$capabilities );
		}

		foreach ( $caps as $cap ) {
			if ( isset( self::$capabilities[ $cap ] ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove capabilities from a role.
	 *
	 * @param string $role_name WordPress role name.
	 * @param array  $caps Array of capability slugs to remove. If empty, removes all plugin caps.
	 */
	public static function remove_caps_from_role( $role_name, $caps = array() ) {
		$role = get_role( $role_name );

		if ( ! $role ) {
			return;
		}

		// If no specific caps provided, remove all
		if ( empty( $caps ) ) {
			$caps = array_keys( self::$capabilities );
		}

		foreach ( $caps as $cap ) {
			if ( isset( self::$capabilities[ $cap ] ) ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Setup default capabilities on plugin activation.
	 *
	 * Adds all capabilities to administrator role by default.
	 */
	public static function setup_default_capabilities() {
		// Add all capabilities to administrator
		self::add_caps_to_role( 'administrator' );

		// Optionally add view capabilities to editor role
		// Uncomment if you want editors to have view access by default
		// self::add_caps_to_role( 'editor', array( 'gcal_view_admin', 'gcal_view_untagged' ) );
	}

	/**
	 * Remove all plugin capabilities from all roles.
	 *
	 * Called on plugin uninstall.
	 */
	public static function remove_all_capabilities() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		$roles = $wp_roles->get_names();

		foreach ( array_keys( $roles ) as $role_name ) {
			self::remove_caps_from_role( $role_name );
		}
	}

	/**
	 * Check if current user can manage plugin settings.
	 *
	 * @return bool
	 */
	public static function can_manage_settings() {
		return current_user_can( 'gcal_manage_settings' );
	}

	/**
	 * Check if current user can manage categories.
	 *
	 * @return bool
	 */
	public static function can_manage_categories() {
		return current_user_can( 'gcal_manage_categories' );
	}

	/**
	 * Check if current user can view admin panel.
	 *
	 * @return bool
	 */
	public static function can_view_admin() {
		return current_user_can( 'gcal_view_admin' );
	}

	/**
	 * Check if current user can view untagged events.
	 *
	 * @return bool
	 */
	public static function can_view_untagged() {
		return current_user_can( 'gcal_view_untagged' );
	}
}
