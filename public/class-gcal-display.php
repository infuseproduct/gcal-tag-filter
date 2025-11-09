<?php
/**
 * Display/Rendering Handler
 *
 * Handles rendering of calendar and list views.
 *
 * @package GCal_Tag_Filter
 */

class GCal_Display {

    /**
     * Render error message.
     *
     * @param string $message Error message.
     * @return string HTML output.
     */
    public function render_error( $message ) {
        ob_start();
        ?>
        <div class="gcal-error">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render empty state.
     *
     * @return string HTML output.
     */
    private function render_empty_state() {
        ob_start();
        ?>
        <div class="gcal-empty-state">
            <p><?php esc_html_e( 'Aucun événement trouvé.', 'gcal-tag-filter' ); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render calendar view.
     *
     * @param array  $events Array of events.
     * @param string $period Period type.
     * @param array  $tags   Filter tags.
     * @param bool   $show_categories Whether to show category sidebar.
     * @param string $selected_category Currently selected category.
     * @param bool   $show_display_style Whether to show display style toggle.
     * @param string $current_view Current view type.
     * @param int    $url_year Optional year parameter.
     * @param int    $url_month Optional month parameter.
     * @param int    $url_week Optional week parameter.
     * @return string HTML output.
     */
    public function render_calendar_view( $events, $period, $tags, $show_categories = false, $selected_category = '', $show_display_style = false, $current_view = 'calendar', $url_year = null, $url_month = null, $url_week = null ) {
        // Generate unique ID for this calendar instance
        $instance_id = 'gcal-' . uniqid();

        // Prepare events data for JavaScript
        $prepared_events = $this->prepare_events_for_js( $events );
        $events_json = wp_json_encode( $prepared_events );

        // Debug logging
        error_log( sprintf(
            'GCal Display: Instance %s - Preparing %d events for JS (is_admin: %s)',
            $instance_id,
            count( $prepared_events ),
            current_user_can( 'manage_options' ) ? 'yes' : 'no'
        ) );

        ob_start();
        ?>
        <div class="gcal-wrapper-with-sidebar <?php echo ( $show_categories || $show_display_style ) ? 'has-sidebar' : ''; ?>">
            <?php if ( $show_categories || $show_display_style ) : ?>
                <?php echo $this->render_sidebar( $events, $selected_category, $instance_id, $show_categories, $show_display_style, $current_view ); ?>
            <?php endif; ?>

            <div class="gcal-calendar-wrapper"
                 id="<?php echo esc_attr( $instance_id ); ?>"
                 data-period="<?php echo esc_attr( $period ); ?>"
                 data-tags="<?php echo esc_attr( implode( ',', $tags ) ); ?>"
                 data-events="<?php echo esc_attr( $events_json ); ?>">
            <?php if ( $period !== 'future' ) : ?>
            <div class="gcal-calendar-header">
                <div class="gcal-header-left">
                    <button class="gcal-nav-prev" aria-label="<?php esc_attr_e( 'Précédent', 'gcal-tag-filter' ); ?>">
                        ‹
                    </button>
                    <h3 class="gcal-calendar-title"></h3>
                    <button class="gcal-nav-next" aria-label="<?php esc_attr_e( 'Suivant', 'gcal-tag-filter' ); ?>">
                        ›
                    </button>
                </div>
                <div class="gcal-view-toggle">
                    <button class="gcal-view-btn <?php echo $period === 'week' ? 'active' : ''; ?>" data-view="week">
                        <?php esc_html_e( 'Semaine', 'gcal-tag-filter' ); ?>
                    </button>
                    <button class="gcal-view-btn <?php echo $period === 'month' ? 'active' : ''; ?>" data-view="month">
                        <?php esc_html_e( 'Mois', 'gcal-tag-filter' ); ?>
                    </button>
                    <button class="gcal-view-btn <?php echo $period === 'year' ? 'active' : ''; ?>" data-view="year">
                        <?php esc_html_e( 'Année', 'gcal-tag-filter' ); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="gcal-calendar-loading">
                <div class="gcal-spinner"></div>
            </div>

            <div class="gcal-calendar-grid" data-current-view="<?php echo esc_attr( $period ); ?>">
                <?php if ( $period === 'week' ) : ?>
                    <?php echo $this->render_week_view( $events, $url_year, $url_month, $url_week ); ?>
                <?php elseif ( $period === 'year' ) : ?>
                    <?php echo $this->render_year_view( $events, $url_year ); ?>
                <?php else : ?>
                    <?php echo $this->render_month_view( $events, $url_year, $url_month ); ?>
                <?php endif; ?>
            </div>
            </div><!-- .gcal-calendar-wrapper -->

            <!-- Event Modal -->
            <div class="gcal-modal" id="<?php echo esc_attr( $instance_id ); ?>-modal" style="display: none;">
                <div class="gcal-modal-overlay"></div>
                <div class="gcal-modal-content">
                    <button class="gcal-modal-close" aria-label="<?php esc_attr_e( 'Close', 'gcal-tag-filter' ); ?>">×</button>
                    <div class="gcal-modal-body"></div>
                </div>
            </div>
        </div><!-- .gcal-wrapper-with-sidebar -->
        <?php
        return ob_get_clean();
    }

    /**
     * Render month view grid.
     *
     * @param array $events Array of events.
     * @param int   $url_year Optional year parameter from URL.
     * @param int   $url_month Optional month parameter from URL.
     * @return string HTML output.
     */
    private function render_month_view( $events, $url_year = null, $url_month = null ) {
        // Group events by date
        $events_by_date = $this->group_events_by_date( $events );

        // Use URL date if provided, otherwise current month
        if ( $url_year && $url_month ) {
            $now = new DateTime();
            $now->setDate( $url_year, $url_month, 1 );
        } else {
            $now = new DateTime();
        }
        $month_start = new DateTime( $now->format( 'Y-m-01' ) );
        $month_end = new DateTime( $now->format( 'Y-m-t' ) );

        ob_start();
        ?>
        <div class="gcal-month-view">
            <div class="gcal-weekday-headers">
                <?php
                $weekdays = array(
                    __( 'Lun', 'gcal-tag-filter' ),
                    __( 'Mar', 'gcal-tag-filter' ),
                    __( 'Mer', 'gcal-tag-filter' ),
                    __( 'Jeu', 'gcal-tag-filter' ),
                    __( 'Ven', 'gcal-tag-filter' ),
                    __( 'Sam', 'gcal-tag-filter' ),
                    __( 'Dim', 'gcal-tag-filter' ),
                );
                foreach ( $weekdays as $day ) :
                    ?>
                    <div class="gcal-weekday"><?php echo esc_html( $day ); ?></div>
                <?php endforeach; ?>
            </div>

            <div class="gcal-days-grid">
                <?php
                // Start from the first day of the week containing the 1st
                $calendar_start = clone $month_start;
                $day_of_week = (int) $calendar_start->format( 'w' );
                // Adjust for Monday start (0=Sunday, 1=Monday, etc.)
                $days_from_monday = ( $day_of_week === 0 ) ? 6 : $day_of_week - 1;
                if ( $days_from_monday > 0 ) {
                    $calendar_start->modify( "-{$days_from_monday} days" );
                }

                // Render 6 weeks (42 days)
                for ( $i = 0; $i < 42; $i++ ) :
                    $date_str = $calendar_start->format( 'Y-m-d' );
                    $is_current_month = $calendar_start->format( 'Y-m' ) === $now->format( 'Y-m' );
                    $is_today = $date_str === $now->format( 'Y-m-d' );
                    $day_events = $events_by_date[ $date_str ] ?? array();

                    $classes = array( 'gcal-day' );
                    if ( ! $is_current_month ) {
                        $classes[] = 'gcal-day-other-month';
                    }
                    if ( $is_today ) {
                        $classes[] = 'gcal-day-today';
                    }
                    if ( ! empty( $day_events ) ) {
                        $classes[] = 'gcal-day-has-events';
                    }
                    ?>
                    <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-date="<?php echo esc_attr( $date_str ); ?>">
                        <div class="gcal-day-number"><?php echo esc_html( $calendar_start->format( 'j' ) ); ?></div>
                        <div class="gcal-day-events">
                            <?php foreach ( $day_events as $event ) : ?>
                                <?php echo $this->render_event_item( $event ); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php
                    $calendar_start->modify( '+1 day' );
                endfor;
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render week view.
     *
     * @param array $events Array of events.
     * @param int   $url_year Optional year parameter from URL.
     * @param int   $url_month Optional month parameter from URL.
     * @param int   $url_week Optional week parameter from URL.
     * @return string HTML output.
     */
    private function render_week_view( $events, $url_year = null, $url_month = null, $url_week = null ) {
        // Group events by date
        $events_by_date = $this->group_events_by_date( $events );

        // Initialize $now for "is today" checks later
        $now = new DateTime();

        // Use URL date if provided, otherwise current week
        if ( $url_year && $url_month && $url_week ) {
            // Calculate the Monday of the specified week
            // Week 1 is the week containing the 1st of the month
            // Find the first Monday of the month
            $first_of_month = new DateTime();
            $first_of_month->setDate( $url_year, $url_month, 1 );
            $first_day_weekday = (int) $first_of_month->format( 'N' ); // 1=Monday, 7=Sunday

            error_log( 'GCal Display - Week render: year=' . $url_year . ', month=' . $url_month . ', week=' . $url_week . ', first_day_weekday=' . $first_day_weekday );

            // Calculate when the first Monday occurs
            if ( $first_day_weekday === 1 ) {
                // First day is already Monday
                $first_monday = clone $first_of_month;
            } else {
                // Find next Monday after the 1st
                $days_to_monday = 8 - $first_day_weekday;
                $first_monday = clone $first_of_month;
                $first_monday->modify( '+' . $days_to_monday . ' days' );
            }

            // Now calculate which Monday we need
            // Week 1 includes all days before first Monday
            // If week is 1 and first day is after Monday, we need the PREVIOUS Monday
            if ( $url_week === 1 && $first_day_weekday > 1 ) {
                // Week 1 includes days before the first Monday, so go back
                $monday = clone $first_of_month;
                $days_back = $first_day_weekday - 1;
                $monday->modify( '-' . $days_back . ' days' );
                error_log( 'GCal Display - Week 1 special case: going back ' . $days_back . ' days to ' . $monday->format('Y-m-d') );
            } else {
                // Calculate from first Monday
                $weeks_to_add = $url_week - 1;
                if ( $first_day_weekday === 1 ) {
                    // If month starts on Monday, week 1 starts on that Monday
                    $monday = clone $first_monday;
                    $monday->modify( '+' . ( $weeks_to_add * 7 ) . ' days' );
                    error_log( 'GCal Display - Month starts on Monday, week ' . $url_week . ' = ' . $monday->format('Y-m-d') );
                } else {
                    // If month doesn't start on Monday, week 1 starts on the Monday BEFORE the 1st
                    $monday = clone $first_of_month;
                    $monday->modify( '-' . ( $first_day_weekday - 1 ) . ' days' );
                    $monday->modify( '+' . ( $weeks_to_add * 7 ) . ' days' );
                    error_log( 'GCal Display - Normal week calculation: week ' . $url_week . ' = ' . $monday->format('Y-m-d') );
                }
            }
        } else {
            // Get current week starting from Monday
            $day_of_week = $now->format( 'N' ); // 1 (Monday) through 7 (Sunday)
            $monday = clone $now;
            $monday->modify( '-' . ( $day_of_week - 1 ) . ' days' ); // Go back to Monday
        }

        // French day names
        $french_days = array( 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim' );

        ob_start();
        ?>
        <div class="gcal-week-view">
            <?php for ( $i = 0; $i < 7; $i++ ) : ?>
                <?php
                $date = clone $monday;
                $date->modify( "+{$i} days" );
                $date_str = $date->format( 'Y-m-d' );
                $day_events = $events_by_date[ $date_str ] ?? array();
                $is_today = $date_str === $now->format( 'Y-m-d' );
                ?>
                <div class="gcal-week-day <?php echo $is_today ? 'gcal-day-today' : ''; ?>" data-date="<?php echo esc_attr( $date_str ); ?>">
                    <div class="gcal-week-day-header">
                        <div class="gcal-week-day-name"><?php echo esc_html( $french_days[ $i ] ); ?></div>
                        <div class="gcal-week-day-number"><?php echo esc_html( $date->format( 'j' ) ); ?></div>
                    </div>
                    <div class="gcal-week-day-events">
                        <?php if ( ! empty( $day_events ) ) : ?>
                            <?php foreach ( $day_events as $event ) : ?>
                                <?php echo $this->render_event_item( $event ); ?>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="gcal-no-events"><?php esc_html_e( 'Aucun événement', 'gcal-tag-filter' ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render year view (12 months grid).
     *
     * @param array $events Array of events.
     * @param int   $url_year Optional year parameter from URL.
     * @return string HTML output.
     */
    private function render_year_view( $events, $url_year = null ) {
        // Group events by month
        $events_by_month = array();
        foreach ( $events as $event ) {
            $start_date = new DateTime( $event['start'] );
            $month_key = $start_date->format( 'Y-m' );
            if ( ! isset( $events_by_month[ $month_key ] ) ) {
                $events_by_month[ $month_key ] = array();
            }
            $events_by_month[ $month_key ][] = $event;
        }

        // Use URL year if provided, otherwise current year
        if ( $url_year ) {
            $year = $url_year;
        } else {
            $now = new DateTime();
            $year = $now->format( 'Y' );
        }

        ob_start();
        ?>
        <div class="gcal-year-view">
            <?php for ( $month = 1; $month <= 12; $month++ ) : ?>
                <?php
                $month_key = sprintf( '%s-%02d', $year, $month );
                $month_events = $events_by_month[ $month_key ] ?? array();
                $month_date = new DateTime( $month_key . '-01' );
                $french_months = array( 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' );
                $month_name = $french_months[ $month - 1 ];
                ?>
                <div class="gcal-year-month">
                    <div class="gcal-year-month-header">
                        <h4><?php echo esc_html( ucfirst( $month_name ) ); ?></h4>
                        <span class="gcal-year-month-count">
                            <?php echo count( $month_events ); ?>
                            <?php echo count( $month_events ) === 1 ? esc_html__( 'événement', 'gcal-tag-filter' ) : esc_html__( 'événements', 'gcal-tag-filter' ); ?>
                        </span>
                    </div>
                    <div class="gcal-year-month-events">
                        <?php if ( ! empty( $month_events ) ) : ?>
                            <?php foreach ( $month_events as $index => $event ) : ?>
                                <?php
                                $event_start = new DateTime( $event['start'] );
                                $event_date = $event_start->format( 'j' ); // Day of month without leading zeros
                                $is_hidden = $index >= 5;
                                ?>
                                <div class="gcal-year-event gcal-event-item <?php echo $is_hidden ? 'gcal-year-event-hidden' : ''; ?>" data-event-id="<?php echo esc_attr( $event['id'] ); ?>" role="button" tabindex="0">
                                    <?php if ( ! empty( $event['tags'] ) ) : ?>
                                        <?php
                                        $category_color = GCal_Categories::get_category_color( $event['tags'][0] );
                                        ?>
                                        <span class="gcal-year-event-dot" style="background-color: <?php echo esc_attr( $category_color ); ?>"></span>
                                    <?php endif; ?>
                                    <span class="gcal-year-event-date"><?php echo esc_html( $event_date ); ?></span>
                                    <span class="gcal-year-event-title"><?php echo esc_html( $event['title'] ); ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if ( count( $month_events ) > 5 ) : ?>
                                <button class="gcal-year-more" data-month="<?php echo esc_attr( $month_key ); ?>">
                                    <span class="gcal-year-more-text">+<?php echo count( $month_events ) - 5; ?> <?php esc_html_e( 'de plus', 'gcal-tag-filter' ); ?></span>
                                    <span class="gcal-year-less-text" style="display: none;"><?php esc_html_e( 'Voir moins', 'gcal-tag-filter' ); ?></span>
                                </button>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="gcal-no-events"><?php esc_html_e( 'Aucun événement', 'gcal-tag-filter' ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render list view.
     *
     * @param array  $events Array of events.
     * @param string $period Period type.
     * @param array  $tags   Filter tags.
     * @param bool   $show_categories Whether to show category sidebar.
     * @param string $selected_category Currently selected category.
     * @param bool   $show_display_style Whether to show display style toggle.
     * @param string $current_view Current view type.
     * @param int    $url_year Optional year parameter.
     * @param int    $url_month Optional month parameter.
     * @param int    $url_week Optional week parameter.
     * @param bool   $hide_past Optional. Hide past events. Default false.
     * @return string HTML output.
     */
    public function render_list_view( $events, $period, $tags, $show_categories = false, $selected_category = '', $show_display_style = false, $current_view = 'list', $url_year = null, $url_month = null, $url_week = null, $hide_past = false ) {
        // Generate unique ID for this list instance
        $instance_id = 'gcal-list-' . uniqid();

        // Filter out past events if hide_past is true
        if ( $hide_past && ! empty( $events ) ) {
            $now = new DateTime( 'now', new DateTimeZone( 'Asia/Hong_Kong' ) );
            $events = array_filter( $events, function( $event ) use ( $now ) {
                // For all-day events, compare dates only (end date is exclusive from Google)
                if ( $event['is_all_day'] ) {
                    // End date is exclusive, so event on 2025-10-25 has end = 2025-10-26
                    // We want to show it until the end of 2025-10-25
                    $event_end = new DateTime( $event['end'], new DateTimeZone( 'Asia/Hong_Kong' ) );
                    $event_end->modify( '-1 day' ); // Make it inclusive
                    $event_end->setTime( 23, 59, 59 ); // End of day
                    $now_date = clone $now;
                    $now_date->setTime( 0, 0, 0 ); // Start of current day
                    return $event_end >= $now_date;
                } else {
                    // For timed events, compare exact timestamps
                    // DateTime string from Google includes timezone, e.g., "2025-10-25T14:00:00+08:00"
                    $event_end = new DateTime( $event['end'] );
                    return $event_end >= $now;
                }
            } );
        }

        // Prepare events data for JavaScript
        $prepared_events = $this->prepare_events_for_js( $events );
        $events_json = wp_json_encode( $prepared_events );

        // Debug logging
        error_log( sprintf(
            'GCal Display: Instance %s - Preparing %d events for JS (is_admin: %s)',
            $instance_id,
            count( $prepared_events ),
            current_user_can( 'manage_options' ) ? 'yes' : 'no'
        ) );

        ob_start();
        ?>
        <div class="gcal-wrapper-with-sidebar <?php echo ( $show_categories || $show_display_style ) ? 'has-sidebar' : ''; ?>">
            <?php if ( $show_categories || $show_display_style ) : ?>
                <?php echo $this->render_sidebar( $events, $selected_category, $instance_id, $show_categories, $show_display_style, $current_view ); ?>
            <?php endif; ?>

            <div class="gcal-list-wrapper"
                 id="<?php echo esc_attr( $instance_id ); ?>"
                 data-period="<?php echo esc_attr( $period ); ?>"
                 data-tags="<?php echo esc_attr( implode( ',', $tags ) ); ?>"
                 data-events="<?php echo esc_attr( $events_json ); ?>">
            <?php if ( $period !== 'future' ) : ?>
            <div class="gcal-list-header">
                <div class="gcal-header-left">
                    <button class="gcal-nav-prev" aria-label="<?php esc_attr_e( 'Précédent', 'gcal-tag-filter' ); ?>">
                        ‹
                    </button>
                    <h3 class="gcal-list-title"></h3>
                    <button class="gcal-nav-next" aria-label="<?php esc_attr_e( 'Suivant', 'gcal-tag-filter' ); ?>">
                        ›
                    </button>
                </div>
                <div class="gcal-view-toggle">
                    <button class="gcal-view-btn <?php echo $period === 'week' ? 'active' : ''; ?>" data-view="week">
                        <?php esc_html_e( 'Semaine', 'gcal-tag-filter' ); ?>
                    </button>
                    <button class="gcal-view-btn <?php echo $period === 'month' ? 'active' : ''; ?>" data-view="month">
                        <?php esc_html_e( 'Mois', 'gcal-tag-filter' ); ?>
                    </button>
                    <button class="gcal-view-btn <?php echo $period === 'year' ? 'active' : ''; ?>" data-view="year">
                        <?php esc_html_e( 'Année', 'gcal-tag-filter' ); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <div class="gcal-list-loading">
                <div class="gcal-spinner"></div>
            </div>

            <div class="gcal-list">
                <?php if ( empty( $events ) ) : ?>
                    <?php echo $this->render_empty_state(); ?>
                <?php else : ?>
                    <?php foreach ( $events as $event ) : ?>
                        <?php echo $this->render_list_event_card( $event ); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            </div><!-- .gcal-list-wrapper -->

            <!-- Event Modal -->
            <div class="gcal-modal" id="<?php echo esc_attr( $instance_id ); ?>-modal" style="display: none;">
                <div class="gcal-modal-overlay"></div>
                <div class="gcal-modal-content">
                    <button class="gcal-modal-close" aria-label="<?php esc_attr_e( 'Close', 'gcal-tag-filter' ); ?>">×</button>
                    <div class="gcal-modal-body"></div>
                </div>
            </div>
        </div><!-- .gcal-wrapper-with-sidebar -->
        <?php
        return ob_get_clean();
    }

    /**
     * Render individual event item for calendar.
     *
     * @param array $event Event data.
     * @return string HTML output.
     */
    private function render_event_item( $event ) {
        $category_color = '';
        $is_untagged = ! empty( $event['is_untagged'] );
        $has_unknown_tags = ! empty( $event['has_unknown_tags'] );
        $css_class = '';

        if ( $is_untagged ) {
            // Untagged events get black background (for admins only)
            $category_color = '#000000';
            $css_class = 'gcal-event-untagged';
        } elseif ( $has_unknown_tags ) {
            // Events with unknown tags get dark red background (for admins only)
            $category_color = '#8B0000'; // Dark red
            $css_class = 'gcal-event-unknown-tags';
        } elseif ( ! empty( $event['tags'] ) ) {
            $category_color = GCal_Categories::get_category_color( $event['tags'][0] );
        }

        $time = '';
        if ( ! $event['is_all_day'] ) {
            $start_time = new DateTime( $event['start'] );
            $end_time = new DateTime( $event['end'] );
            $time = $this->format_time( $start_time ) . ' - ' . $this->format_time( $end_time );
        }

        // Add warning emoji for untagged or unknown-tag events
        $title = ( $is_untagged || $has_unknown_tags ) ? '⚠️ ' . $event['title'] : $event['title'];

        ob_start();
        ?>
        <div class="gcal-event-item <?php echo esc_attr( $css_class ); ?>"
             data-event-id="<?php echo esc_attr( $event['id'] ); ?>"
             style="background-color: <?php echo esc_attr( $category_color ); ?>;">
            <?php if ( $time ) : ?>
                <span class="gcal-event-time"><?php echo esc_html( $time ); ?></span>
            <?php endif; ?>
            <span class="gcal-event-title"><?php echo esc_html( $title ); ?></span>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render event card for list view.
     *
     * @param array $event Event data.
     * @return string HTML output.
     */
    private function render_list_event_card( $event ) {
        $start_date = new DateTime( $event['start'] );
        $category_color = '';
        $category_name = '';
        $is_untagged = ! empty( $event['is_untagged'] );
        $has_unknown_tags = ! empty( $event['has_unknown_tags'] );
        $css_class = '';

        if ( $is_untagged ) {
            // Untagged events get black background (for admins only)
            $category_color = '#000000';
            $category_name = '⚠️ Non catégorisé';
            $css_class = 'gcal-event-untagged';
        } elseif ( $has_unknown_tags ) {
            // Events with unknown tags get dark red background (for admins only)
            $category_color = '#8B0000';
            // Show the first invalid tag
            $first_invalid = ! empty( $event['invalid_tags'][0] ) ? $event['invalid_tags'][0] : 'UNKNOWN';
            $category_name = '⚠️ Tag inconnu: ' . $first_invalid;
            $css_class = 'gcal-event-unknown-tags';
        } elseif ( ! empty( $event['tags'] ) ) {
            $category_color = GCal_Categories::get_category_color( $event['tags'][0] );
            $category_name = GCal_Categories::get_category_display_name( $event['tags'][0] );
        }

        // Add warning emoji for untagged or unknown-tag events
        $title = ( $is_untagged || $has_unknown_tags ) ? '⚠️ ' . $event['title'] : $event['title'];

        ob_start();
        ?>
        <div class="gcal-list-event-card <?php echo esc_attr( $css_class ); ?>"
             data-event-id="<?php echo esc_attr( $event['id'] ); ?>"
             style="border-left-color: <?php echo esc_attr( $category_color ); ?>">

            <div class="gcal-event-date">
                <?php
                $french_months_short = array( 'jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'aoû', 'sep', 'oct', 'nov', 'déc' );
                $month_index = (int) $start_date->format( 'n' ) - 1;
                ?>
                <div class="gcal-event-month"><?php echo esc_html( $french_months_short[ $month_index ] ); ?></div>
                <div class="gcal-event-day"><?php echo esc_html( $start_date->format( 'd' ) ); ?></div>
                <div class="gcal-event-time">
                    <?php if ( $event['is_all_day'] ) : ?>
                        <?php esc_html_e( 'Toute la journée', 'gcal-tag-filter' ); ?>
                    <?php else : ?>
                        <?php
                        $end_date = new DateTime( $event['end'] );
                        ?>
                        <span class="gcal-event-start"><?php echo esc_html( $this->format_time( $start_date ) . ' - ' . $this->format_time( $end_date ) ); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gcal-event-details">
                <h3 class="gcal-event-title"><?php echo esc_html( $title ); ?></h3>

                <?php if ( ! empty( $event['description'] ) ) : ?>
                    <div class="gcal-event-description">
                        <?php echo $this->make_links_clickable( wp_trim_words( $event['description'], 30 ) ); ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $event['location'] ) ) : ?>
                    <div class="gcal-event-location">
                        <span class="gcal-location-icon">📍</span>
                        <?php if ( ! empty( $event['map_link'] ) ) : ?>
                            <a href="<?php echo esc_url( $event['map_link'] ); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html( $event['location'] ); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html( $event['location'] ); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <button class="gcal-event-read-more" data-event-id="<?php echo esc_attr( $event['id'] ); ?>">
                    <?php esc_html_e( 'En savoir plus', 'gcal-tag-filter' ); ?> →
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Group events by date.
     *
     * @param array $events Array of events.
     * @return array Events grouped by date (Y-m-d).
     */
    private function group_events_by_date( $events ) {
        $grouped = array();

        foreach ( $events as $event ) {
            $start_date = new DateTime( $event['start'] );
            $date_key = $start_date->format( 'Y-m-d' );

            if ( ! isset( $grouped[ $date_key ] ) ) {
                $grouped[ $date_key ] = array();
            }

            $grouped[ $date_key ][] = $event;
        }

        return $grouped;
    }

    /**
     * Prepare events data for JavaScript.
     *
     * @param array $events Array of events.
     * @return array Events prepared for JSON encoding.
     */
    private function prepare_events_for_js( $events ) {
        $prepared = array_map(
            function( $event ) {
                // Get category display names
                $category_names = array();
                if ( ! empty( $event['tags'] ) ) {
                    foreach ( $event['tags'] as $tag ) {
                        $category_names[] = GCal_Categories::get_category_display_name( $tag );
                    }
                }

                return array(
                    'id'             => $event['id'],
                    'title'          => $event['title'],
                    'description'    => $event['description'], // Keep as plain text - JavaScript will format it
                    'location'       => $event['location'],
                    'start'          => $event['start'],
                    'end'            => $event['end'],
                    'isAllDay'       => $event['is_all_day'],
                    'tags'           => $event['tags'],
                    'invalidTags'    => isset( $event['invalid_tags'] ) ? $event['invalid_tags'] : array(),
                    'categoryNames'  => $category_names,
                    'mapLink'        => $event['map_link'],
                    'htmlLink'       => $event['html_link'],
                );
            },
            $events
        );

        // Reindex array to ensure sequential keys for proper JSON array encoding
        return array_values( $prepared );
    }

    /**
     * Render sidebar with display style toggle and/or categories.
     *
     * @param array  $events Array of events.
     * @param string $selected_category Currently selected category.
     * @param string $instance_id Instance ID.
     * @param bool   $show_categories Whether to show category list.
     * @param bool   $show_display_style Whether to show display style toggle.
     * @param string $current_view Current view type.
     * @return string HTML output.
     */
    private function render_sidebar( $events, $selected_category, $instance_id, $show_categories, $show_display_style, $current_view ) {
        ob_start();
        ?>
        <div class="gcal-sidebar">
            <?php if ( $show_display_style ) : ?>
                <div class="gcal-display-style-toggle">
                    <button class="gcal-display-btn <?php echo $current_view === 'list' ? 'active' : ''; ?>" data-display="list">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e( 'Liste', 'gcal-tag-filter' ); ?>
                    </button>
                    <button class="gcal-display-btn <?php echo $current_view === 'calendar' ? 'active' : ''; ?>" data-display="calendar">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e( 'Calendrier', 'gcal-tag-filter' ); ?>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ( $show_categories ) : ?>
                <?php echo $this->render_category_sidebar( $events, $selected_category, $instance_id ); ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render category sidebar for filtering.
     *
     * @param array  $events Array of events.
     * @param string $selected_category Currently selected category.
     * @param string $instance_id Instance ID.
     * @return string HTML output.
     */
    private function render_category_sidebar( $events, $selected_category, $instance_id ) {
        // Collect all unique categories from events
        $all_categories = array();
        $has_untagged = false;
        $has_unknown_tags = false;
        foreach ( $events as $event ) {
            if ( ! empty( $event['tags'] ) ) {
                foreach ( $event['tags'] as $tag ) {
                    $all_categories[] = strtoupper( $tag );
                }
            }
            if ( ! empty( $event['is_untagged'] ) ) {
                $has_untagged = true;
            }
            if ( ! empty( $event['has_unknown_tags'] ) ) {
                $has_unknown_tags = true;
            }
        }

        // Remove duplicates and sort alphabetically
        $all_categories = array_unique( $all_categories );
        sort( $all_categories );

        // Check if current user is admin
        $is_admin = current_user_can( 'manage_options' );

        ob_start();
        ?>
        <div class="gcal-category-sidebar" data-instance="<?php echo esc_attr( $instance_id ); ?>">
            <h3 class="gcal-category-title"><?php esc_html_e( 'Catégories', 'gcal-tag-filter' ); ?></h3>

            <!-- Mobile Dropdown -->
            <div class="gcal-category-dropdown-wrapper">
                <select class="gcal-category-dropdown" data-instance="<?php echo esc_attr( $instance_id ); ?>">
                    <option value="" <?php echo empty( $selected_category ) ? 'selected' : ''; ?>>
                        <?php esc_html_e( 'Toutes les catégories', 'gcal-tag-filter' ); ?>
                    </option>
                    <?php foreach ( $all_categories as $category ) : ?>
                        <option value="<?php echo esc_attr( $category ); ?>"
                                <?php echo $category === strtoupper( $selected_category ) ? 'selected' : ''; ?>>
                            <?php echo esc_html( $this->get_category_display_name( $category ) ); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ( $is_admin && $has_untagged ) : ?>
                        <option value="UNTAGGED" <?php echo strtoupper( $selected_category ) === 'UNTAGGED' ? 'selected' : ''; ?>>
                            ⚠️ <?php esc_html_e( 'Sans catégorie', 'gcal-tag-filter' ); ?>
                        </option>
                    <?php endif; ?>
                    <?php if ( $is_admin && $has_unknown_tags ) : ?>
                        <option value="UNKNOWN" <?php echo strtoupper( $selected_category ) === 'UNKNOWN' ? 'selected' : ''; ?>>
                            ⚠️ <?php esc_html_e( 'Tags inconnus', 'gcal-tag-filter' ); ?>
                        </option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Desktop Button List -->
            <ul class="gcal-category-list">
                <li>
                    <button class="gcal-category-btn <?php echo empty( $selected_category ) ? 'active' : ''; ?>"
                            data-category="">
                        <?php esc_html_e( 'Toutes les catégories', 'gcal-tag-filter' ); ?>
                    </button>
                </li>
                <?php foreach ( $all_categories as $category ) : ?>
                    <li>
                        <button class="gcal-category-btn <?php echo $category === strtoupper( $selected_category ) ? 'active' : ''; ?>"
                                data-category="<?php echo esc_attr( $category ); ?>">
                            <?php echo esc_html( $this->get_category_display_name( $category ) ); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
                <?php if ( $is_admin && $has_untagged ) : ?>
                    <li>
                        <button class="gcal-category-btn <?php echo strtoupper( $selected_category ) === 'UNTAGGED' ? 'active' : ''; ?>"
                                data-category="UNTAGGED">
                            ⚠️ <?php esc_html_e( 'Sans catégorie', 'gcal-tag-filter' ); ?>
                        </button>
                    </li>
                <?php endif; ?>
                <?php if ( $is_admin && $has_unknown_tags ) : ?>
                    <li>
                        <button class="gcal-category-btn <?php echo strtoupper( $selected_category ) === 'UNKNOWN' ? 'active' : ''; ?>"
                                data-category="UNKNOWN">
                            ⚠️ <?php esc_html_e( 'Tags inconnus', 'gcal-tag-filter' ); ?>
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get display name for category.
     *
     * @param string $category Category ID.
     * @return string Display name.
     */
    private function get_category_display_name( $category ) {
        return GCal_Categories::get_category_display_name( $category );
    }

    /**
     * Format time in 24-hour format without :00 minutes.
     *
     * @param DateTime $datetime DateTime object.
     * @return string Formatted time (e.g., "18h" instead of "18:00" or "18h30").
     */
    private function format_time( $datetime ) {
        $minutes = $datetime->format( 'i' );
        if ( $minutes === '00' ) {
            return $datetime->format( 'G' ) . 'h';
        }
        return $datetime->format( 'G' ) . 'h' . $minutes;
    }

    /**
     * Make URLs in text clickable.
     *
     * @param string $text Text to process.
     * @return string Text with URLs converted to clickable links.
     */
    private function make_links_clickable( $text ) {
        // Escape HTML first to prevent XSS
        $text = esc_html( $text );

        // Pattern to match URLs (http, https, www)
        $pattern = '#\b((https?://|www\.)[^\s<]+)#i';

        // Replace URLs with clickable links
        $text = preg_replace_callback(
            $pattern,
            function( $matches ) {
                $url = $matches[1];

                // Add http:// if URL starts with www.
                $href = ( strpos( $url, 'www.' ) === 0 ) ? 'http://' . $url : $url;

                return sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url( $href ),
                    esc_html( $url )
                );
            },
            $text
        );

        return $text;
    }
}
