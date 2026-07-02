<?php
use PHPUnit\Framework\TestCase;

class FetchRawItemsFromCalendarsTest extends TestCase {

    /** Build a stub event whose getStart() returns an object with date/dateTime. */
    private static function fakeEvent( array $start ) {
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

    /** Build a stub "page" object as returned by Google_Service_Calendar_Events::listEvents(). */
    private static function fakePage( array $items ) {
        return new class( $items ) {
            private $items;
            public function __construct( array $items ) {
                $this->items = $items;
            }
            public function getItems() {
                return $this->items;
            }
            public function getNextPageToken() {
                return null;
            }
        };
    }

    /**
     * Build a stub $service exposing a public $events object with listEvents().
     *
     * @param array $itemsByCalendar    Map of calendar id => array of stub events.
     * @param array $failingCalendarIds Calendar ids that should throw instead.
     */
    private static function fakeService( array $itemsByCalendar, array $failingCalendarIds = array() ) {
        return new class( $itemsByCalendar, $failingCalendarIds ) {
            public $events;
            public function __construct( array $itemsByCalendar, array $failingCalendarIds ) {
                $this->events = new class( $itemsByCalendar, $failingCalendarIds ) {
                    private $itemsByCalendar;
                    private $failingCalendarIds;
                    public function __construct( array $itemsByCalendar, array $failingCalendarIds ) {
                        $this->itemsByCalendar    = $itemsByCalendar;
                        $this->failingCalendarIds = $failingCalendarIds;
                    }
                    public function listEvents( $calendarId, $params ) {
                        if ( in_array( $calendarId, $this->failingCalendarIds, true ) ) {
                            throw new Exception( 'Simulated failure for ' . $calendarId );
                        }
                        $items = isset( $this->itemsByCalendar[ $calendarId ] ) ? $this->itemsByCalendar[ $calendarId ] : array();
                        return FetchRawItemsFromCalendarsTest::buildFakePage( $items );
                    }
                };
            }
        };
    }

    /** Public static wrapper so the anonymous $events class can build a page object. */
    public static function buildFakePage( array $items ) {
        return self::fakePage( $items );
    }

    public function test_one_calendar_fails_other_succeeds_returns_surviving_items() {
        $goodItems = array(
            self::fakeEvent( array( 'dateTime' => '2026-07-01T09:00:00Z' ) ),
        );

        $service = self::fakeService(
            array( 'good@x.com' => $goodItems ),
            array( 'bad@x.com' )
        );

        $result = GCal_Calendar::fetch_raw_items_from_calendars(
            $service,
            array( 'bad@x.com', 'good@x.com' ),
            array()
        );

        $this->assertCount( 1, $result );
        $this->assertSame( '2026-07-01T09:00:00Z', $result[0]->getStart()->dateTime );
    }

    public function test_all_calendars_fail_throws_exception() {
        $service = self::fakeService(
            array(),
            array( 'bad1@x.com', 'bad2@x.com' )
        );

        $this->expectException( \Exception::class );

        GCal_Calendar::fetch_raw_items_from_calendars(
            $service,
            array( 'bad1@x.com', 'bad2@x.com' ),
            array()
        );
    }

    public function test_merges_and_sorts_items_from_both_calendars_without_dedup() {
        $duplicateStart = array( 'dateTime' => '2026-07-02T08:00:00Z' );

        $itemsA = array(
            self::fakeEvent( array( 'dateTime' => '2026-07-03T10:00:00Z' ) ),
            self::fakeEvent( $duplicateStart ),
        );
        $itemsB = array(
            self::fakeEvent( array( 'date' => '2026-07-01' ) ),
            self::fakeEvent( $duplicateStart ), // identical start to one in calendar A
        );

        $service = self::fakeService(
            array(
                'calA@x.com' => $itemsA,
                'calB@x.com' => $itemsB,
            )
        );

        $result = GCal_Calendar::fetch_raw_items_from_calendars(
            $service,
            array( 'calA@x.com', 'calB@x.com' ),
            array()
        );

        // No de-duplication: all 4 items present.
        $this->assertCount( 4, $result );

        // Sorted ascending by start.
        $this->assertSame( '2026-07-01', $result[0]->getStart()->date );
        $this->assertSame( '2026-07-02T08:00:00Z', $result[1]->getStart()->dateTime );
        $this->assertSame( '2026-07-02T08:00:00Z', $result[2]->getStart()->dateTime );
        $this->assertSame( '2026-07-03T10:00:00Z', $result[3]->getStart()->dateTime );
    }
}
