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
     * @return string HTML output.
     */
    public function render_calendar_view( $events, $period, $tags ) {
        if ( empty( $events ) ) {
            return $this->render_empty_state();
        }

        // Generate unique ID for this calendar instance
        $instance_id = 'gcal-' . uniqid();

        // Prepare events data for JavaScript
        $events_json = wp_json_encode( $this->prepare_events_for_js( $events ) );

        ob_start();
        ?>
        <div class="gcal-calendar-wrapper"
             id="<?php echo esc_attr( $instance_id ); ?>"
             data-period="<?php echo esc_attr( $period ); ?>"
             data-tags="<?php echo esc_attr( implode( ',', $tags ) ); ?>"
             data-events="<?php echo esc_attr( $events_json ); ?>">
            <div class="gcal-calendar-header">
                <button class="gcal-nav-prev" aria-label="<?php esc_attr_e( 'Previous', 'gcal-tag-filter' ); ?>">
                    ‹
                </button>
                <h3 class="gcal-calendar-title"></h3>
                <button class="gcal-nav-next" aria-label="<?php esc_attr_e( 'Next', 'gcal-tag-filter' ); ?>">
                    ›
                </button>
            </div>

            <div class="gcal-calendar-loading">
                <div class="gcal-spinner"></div>
            </div>

            <div class="gcal-calendar-grid">
                <?php if ( $period === 'week' ) : ?>
                    <?php echo $this->render_week_view( $events ); ?>
                <?php else : ?>
                    <?php echo $this->render_month_view( $events ); ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Event Modal -->
        <div class="gcal-modal" id="<?php echo esc_attr( $instance_id ); ?>-modal" style="display: none;">
            <div class="gcal-modal-overlay"></div>
            <div class="gcal-modal-content">
                <button class="gcal-modal-close" aria-label="<?php esc_attr_e( 'Close', 'gcal-tag-filter' ); ?>">×</button>
                <div class="gcal-modal-body"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render month view grid.
     *
     * @param array $events Array of events.
     * @return string HTML output.
     */
    private function render_month_view( $events ) {
        // Group events by date
        $events_by_date = $this->group_events_by_date( $events );

        // Get current month
        $now = new DateTime();
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
     * @return string HTML output.
     */
    private function render_week_view( $events ) {
        // Group events by date
        $events_by_date = $this->group_events_by_date( $events );

        // Get current week (7 days from today)
        $now = new DateTime();

        ob_start();
        ?>
        <div class="gcal-week-view">
            <?php for ( $i = 0; $i < 7; $i++ ) : ?>
                <?php
                $date = clone $now;
                $date->modify( "+{$i} days" );
                $date_str = $date->format( 'Y-m-d' );
                $day_events = $events_by_date[ $date_str ] ?? array();
                $is_today = $i === 0;
                ?>
                <div class="gcal-week-day <?php echo $is_today ? 'gcal-day-today' : ''; ?>" data-date="<?php echo esc_attr( $date_str ); ?>">
                    <div class="gcal-week-day-header">
                        <div class="gcal-week-day-name"><?php echo esc_html( $date->format( 'D' ) ); ?></div>
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
     * Render list view.
     *
     * @param array  $events Array of events.
     * @param string $period Period type.
     * @param array  $tags   Filter tags.
     * @return string HTML output.
     */
    public function render_list_view( $events, $period, $tags ) {
        if ( empty( $events ) ) {
            return $this->render_empty_state();
        }

        // Generate unique ID for this list instance
        $instance_id = 'gcal-list-' . uniqid();

        // Prepare events data for JavaScript
        $events_json = wp_json_encode( $this->prepare_events_for_js( $events ) );

        ob_start();
        ?>
        <div class="gcal-list-wrapper"
             id="<?php echo esc_attr( $instance_id ); ?>"
             data-period="<?php echo esc_attr( $period ); ?>"
             data-tags="<?php echo esc_attr( implode( ',', $tags ) ); ?>"
             data-events="<?php echo esc_attr( $events_json ); ?>">
            <div class="gcal-list-loading">
                <div class="gcal-spinner"></div>
            </div>

            <div class="gcal-list">
                <?php foreach ( $events as $event ) : ?>
                    <?php echo $this->render_list_event_card( $event ); ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Event Modal -->
        <div class="gcal-modal" id="<?php echo esc_attr( $instance_id ); ?>-modal" style="display: none;">
            <div class="gcal-modal-overlay"></div>
            <div class="gcal-modal-content">
                <button class="gcal-modal-close" aria-label="<?php esc_attr_e( 'Close', 'gcal-tag-filter' ); ?>">×</button>
                <div class="gcal-modal-body"></div>
            </div>
        </div>
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
        if ( ! empty( $event['tags'] ) ) {
            $category_color = GCal_Categories::get_category_color( $event['tags'][0] );
        }

        $time = '';
        if ( ! $event['is_all_day'] ) {
            $start_time = new DateTime( $event['start'] );
            $time = $start_time->format( 'g:i A' );
        }

        ob_start();
        ?>
        <div class="gcal-event-item"
             data-event-id="<?php echo esc_attr( $event['id'] ); ?>"
             style="border-left-color: <?php echo esc_attr( $category_color ); ?>">
            <?php if ( $time ) : ?>
                <span class="gcal-event-time"><?php echo esc_html( $time ); ?></span>
            <?php endif; ?>
            <span class="gcal-event-title"><?php echo esc_html( $event['title'] ); ?></span>
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

        if ( ! empty( $event['tags'] ) ) {
            $category_color = GCal_Categories::get_category_color( $event['tags'][0] );
            $category_name = GCal_Categories::get_category_display_name( $event['tags'][0] );
        }

        ob_start();
        ?>
        <div class="gcal-list-event-card"
             data-event-id="<?php echo esc_attr( $event['id'] ); ?>"
             style="border-left-color: <?php echo esc_attr( $category_color ); ?>">

            <div class="gcal-event-date">
                <div class="gcal-event-month"><?php echo esc_html( $start_date->format( 'M' ) ); ?></div>
                <div class="gcal-event-day"><?php echo esc_html( $start_date->format( 'd' ) ); ?></div>
            </div>

            <div class="gcal-event-details">
                <h3 class="gcal-event-title"><?php echo esc_html( $event['title'] ); ?></h3>

                <?php if ( $category_name ) : ?>
                    <div class="gcal-event-category"><?php echo esc_html( $category_name ); ?></div>
                <?php endif; ?>

                <div class="gcal-event-time">
                    <?php if ( $event['is_all_day'] ) : ?>
                        <?php esc_html_e( 'Toute la journée', 'gcal-tag-filter' ); ?>
                    <?php else : ?>
                        <span class="gcal-event-start"><?php echo esc_html( $start_date->format( 'g:i A' ) ); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty( $event['description'] ) ) : ?>
                    <div class="gcal-event-description">
                        <?php echo esc_html( wp_trim_words( $event['description'], 30 ) ); ?>
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
                    <?php esc_html_e( 'Read more', 'gcal-tag-filter' ); ?> →
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
        return array_map(
            function( $event ) {
                return array(
                    'id'          => $event['id'],
                    'title'       => $event['title'],
                    'description' => $event['description'],
                    'location'    => $event['location'],
                    'start'       => $event['start'],
                    'end'         => $event['end'],
                    'isAllDay'    => $event['is_all_day'],
                    'tags'        => $event['tags'],
                    'mapLink'     => $event['map_link'],
                    'htmlLink'    => $event['html_link'],
                );
            },
            $events
        );
    }
}
