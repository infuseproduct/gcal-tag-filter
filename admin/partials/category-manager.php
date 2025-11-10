<?php
/**
 * Category Manager Partial
 *
 * Template for category whitelist management interface.
 *
 * @package GCal_Tag_Filter
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$categories = GCal_Categories::get_categories();

// Sort categories by display name (A-Z)
if ( ! empty( $categories ) ) {
    usort( $categories, function( $a, $b ) {
        return strcasecmp( $a['display_name'], $b['display_name'] );
    } );
}
?>

<div class="gcal-category-manager">
    <!-- Add New Category Form -->
    <div class="gcal-add-category-form">
        <h3><?php esc_html_e( 'Add New Category', 'google-calendar-tag-filter' ); ?></h3>

        <form id="gcal-add-category-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="category_id"><?php esc_html_e( 'Category ID', 'google-calendar-tag-filter' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="category_id" id="category_id" class="regular-text" required
                               pattern="[A-Z0-9_-]+" style="text-transform: uppercase;"
                               placeholder="<?php esc_attr_e( 'COMMUNITY', 'google-calendar-tag-filter' ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'Uppercase alphanumeric with underscores or hyphens only. Used in event tags and shortcodes.', 'google-calendar-tag-filter' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="category_display_name"><?php esc_html_e( 'Display Name', 'google-calendar-tag-filter' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="category_display_name" id="category_display_name" class="regular-text" required
                               placeholder="<?php esc_attr_e( 'Community Events', 'google-calendar-tag-filter' ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'User-friendly name shown to visitors.', 'google-calendar-tag-filter' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="category_color"><?php esc_html_e( 'Color', 'google-calendar-tag-filter' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="category_color" id="category_color" class="gcal-color-picker" value="#4285F4" />
                        <p class="description">
                            <?php esc_html_e( 'Color used for calendar color-coding.', 'google-calendar-tag-filter' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <button type="submit" class="button button-primary">
                <?php esc_html_e( 'Add Category', 'google-calendar-tag-filter' ); ?>
            </button>
        </form>
    </div>

    <!-- Categories List -->
    <div class="gcal-categories-list">
        <h3><?php esc_html_e( 'Existing Categories', 'google-calendar-tag-filter' ); ?></h3>

        <?php if ( empty( $categories ) ) : ?>
            <p class="description"><?php esc_html_e( 'No categories defined yet.', 'google-calendar-tag-filter' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Category ID', 'google-calendar-tag-filter' ); ?></th>
                        <th><?php esc_html_e( 'Display Name', 'google-calendar-tag-filter' ); ?></th>
                        <th><?php esc_html_e( 'Color', 'google-calendar-tag-filter' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'google-calendar-tag-filter' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $categories as $category ) : ?>
                        <tr data-category-id="<?php echo esc_attr( $category['id'] ); ?>">
                            <td>
                                <code><?php echo esc_html( $category['id'] ); ?></code>
                            </td>
                            <td class="category-display-name">
                                <?php echo esc_html( $category['display_name'] ); ?>
                            </td>
                            <td>
                                <span class="gcal-color-preview" style="background-color: <?php echo esc_attr( $category['color'] ); ?>"></span>
                                <code class="category-color"><?php echo esc_html( $category['color'] ); ?></code>
                            </td>
                            <td>
                                <button type="button" class="button button-small gcal-edit-category"
                                        data-id="<?php echo esc_attr( $category['id'] ); ?>"
                                        data-display-name="<?php echo esc_attr( $category['display_name'] ); ?>"
                                        data-color="<?php echo esc_attr( $category['color'] ); ?>">
                                    <?php esc_html_e( 'Edit', 'google-calendar-tag-filter' ); ?>
                                </button>
                                <button type="button" class="button button-small gcal-button-danger gcal-delete-category"
                                        data-id="<?php echo esc_attr( $category['id'] ); ?>">
                                    <?php esc_html_e( 'Delete', 'google-calendar-tag-filter' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="gcal-edit-category-modal" class="gcal-modal-backdrop" style="display: none;">
    <div class="gcal-modal-dialog">
        <div class="gcal-modal-header">
            <h3><?php esc_html_e( 'Edit Category', 'google-calendar-tag-filter' ); ?></h3>
            <button type="button" class="gcal-modal-close">×</button>
        </div>
        <div class="gcal-modal-body">
            <form id="gcal-edit-category-form">
                <input type="hidden" name="edit_category_id" id="edit_category_id" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="edit_category_display_name"><?php esc_html_e( 'Display Name', 'google-calendar-tag-filter' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="edit_category_display_name" id="edit_category_display_name" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="edit_category_color"><?php esc_html_e( 'Color', 'google-calendar-tag-filter' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="edit_category_color" id="edit_category_color" class="gcal-color-picker" />
                        </td>
                    </tr>
                </table>

                <div class="gcal-modal-footer">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Save Changes', 'google-calendar-tag-filter' ); ?>
                    </button>
                    <button type="button" class="button gcal-modal-close">
                        <?php esc_html_e( 'Cancel', 'google-calendar-tag-filter' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
