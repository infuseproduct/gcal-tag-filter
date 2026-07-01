<?php
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

class ResolveCalendarIdsTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_returns_empty_array_when_nothing_configured() {
        $this->assertSame( array(), GCal_OAuth::resolve_calendar_ids( false, false ) );
    }

    public function test_falls_back_to_legacy_single_id() {
        $this->assertSame(
            array( 'legacy@group.calendar.google.com' ),
            GCal_OAuth::resolve_calendar_ids( false, 'legacy@group.calendar.google.com' )
        );
    }

    public function test_stored_empty_array_does_not_fall_back_to_legacy() {
        $this->assertSame(
            array(),
            GCal_OAuth::resolve_calendar_ids( array(), 'legacy@x.com' )
        );
    }

    public function test_prefers_array_option_over_legacy() {
        $this->assertSame(
            array( 'a@x.com', 'b@x.com' ),
            GCal_OAuth::resolve_calendar_ids( array( 'a@x.com', 'b@x.com' ), 'legacy@x.com' )
        );
    }

    public function test_filters_empty_and_whitespace_entries() {
        $this->assertSame(
            array( 'a@x.com' ),
            GCal_OAuth::resolve_calendar_ids( array( '', '  ', 'a@x.com' ), false )
        );
    }

    public function test_sanitize_dedupes_and_drops_empties() {
        // sanitize_text_field is a WP function; stub as identity/trimmer.
        Functions\when( 'sanitize_text_field' )->alias( function ( $v ) {
            return trim( (string) $v );
        } );

        $this->assertSame(
            array( 'a@x.com', 'b@x.com' ),
            GCal_OAuth::sanitize_calendar_ids( array( ' a@x.com ', 'b@x.com', 'a@x.com', '' ) )
        );
    }

    public function test_sanitize_returns_empty_array_for_non_array() {
        $this->assertSame( array(), GCal_OAuth::sanitize_calendar_ids( 'not-an-array' ) );
    }
}
