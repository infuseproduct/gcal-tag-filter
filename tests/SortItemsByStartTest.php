<?php
use PHPUnit\Framework\TestCase;

class SortItemsByStartTest extends TestCase {

    /** Build a stub event whose getStart() returns an object with date/dateTime. */
    private function fakeEvent( array $start ) {
        return new class( $start ) {
            private $start;
            public function __construct( array $s ) {
                $this->start = (object) $s;
            }
            public function getStart() {
                return $this->start;
            }
        };
    }

    public function test_sorts_timed_events_ascending() {
        $late  = $this->fakeEvent( array( 'dateTime' => '2026-07-01T15:00:00Z' ) );
        $early = $this->fakeEvent( array( 'dateTime' => '2026-07-01T09:00:00Z' ) );

        $sorted = GCal_Calendar::sort_items_by_start( array( $late, $early ) );

        $this->assertSame( '2026-07-01T09:00:00Z', $sorted[0]->getStart()->dateTime );
        $this->assertSame( '2026-07-01T15:00:00Z', $sorted[1]->getStart()->dateTime );
    }

    public function test_merges_and_orders_across_calendars() {
        // Simulates two calendars' items concatenated then sorted.
        $items = array(
            $this->fakeEvent( array( 'dateTime' => '2026-07-03T10:00:00Z' ) ), // cal A
            $this->fakeEvent( array( 'date' => '2026-07-01' ) ),               // cal B all-day
            $this->fakeEvent( array( 'dateTime' => '2026-07-02T08:00:00Z' ) ), // cal A
        );

        $sorted = GCal_Calendar::sort_items_by_start( $items );

        $this->assertSame( '2026-07-01', $sorted[0]->getStart()->date );
        $this->assertSame( '2026-07-02T08:00:00Z', $sorted[1]->getStart()->dateTime );
        $this->assertSame( '2026-07-03T10:00:00Z', $sorted[2]->getStart()->dateTime );
    }

    public function test_empty_input_returns_empty() {
        $this->assertSame( array(), GCal_Calendar::sort_items_by_start( array() ) );
    }
}
