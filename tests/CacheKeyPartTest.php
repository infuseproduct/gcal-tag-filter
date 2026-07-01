<?php
use PHPUnit\Framework\TestCase;

class CacheKeyPartTest extends TestCase {

    public function test_is_order_independent() {
        $this->assertSame(
            GCal_Cache::build_calendar_key_part( array( 'a@x.com', 'b@x.com' ) ),
            GCal_Cache::build_calendar_key_part( array( 'b@x.com', 'a@x.com' ) )
        );
    }

    public function test_differs_for_different_selections() {
        $this->assertNotSame(
            GCal_Cache::build_calendar_key_part( array( 'a@x.com' ) ),
            GCal_Cache::build_calendar_key_part( array( 'a@x.com', 'b@x.com' ) )
        );
    }

    public function test_empty_selection_is_empty_string() {
        $this->assertSame( '', GCal_Cache::build_calendar_key_part( array() ) );
    }
}
