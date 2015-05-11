<?php
namespace tests;
use Ably\AblyRest;

require_once __DIR__ . '/factories/TestApp.php';

class AppStatsTest extends \PHPUnit_Framework_TestCase {

    protected static $testApp;
    protected static $defaultOptions;
    protected static $ably;
    protected static $timestamp;
    protected static $timestampOlder; // fixtures for limit tests

    public static function setUpBeforeClass() {
        self::$testApp = new TestApp();
        self::$defaultOptions = self::$testApp->getOptions();
        self::$ably = new AblyRest( array_merge( self::$defaultOptions, array(
            'key' => self::$testApp->getAppKeyDefault()->string,
        ) ) );

        self::$timestamp = strtotime('-2 weeks monday') + 14 * 3600 + 5 * 60; // previous week, monday @ 14:05:00
        self::$timestampOlder = strtotime('-3 weeks monday') + 14 * 3600 + 5 * 60; // -2 weeks, monday @ 14:05:00

        $fixtureEntries = array(
            '{
                "intervalId": "' . gmdate( 'Y-m-d:H:i', self::$timestamp ) . '",
                "inbound":  { "realtime": { "messages": { "count": 50, "data": 5000 } } },
                "outbound": { "realtime": { "messages": { "count": 20, "data": 2000 } } }
            }',
            '{
                "intervalId": "' . gmdate( 'Y-m-d:H:i', self::$timestamp + 60 ) . '",
                "inbound":  { "realtime": { "messages": { "count": 60, "data": 6000 } } },
                "outbound": { "realtime": { "messages": { "count": 10, "data": 1000 } } }
            }',
            '{
                "intervalId": "' . gmdate( 'Y-m-d:H:i', self::$timestamp + 120 ) . '",
                "inbound":       { "realtime": { "messages": { "count": 70, "data": 7000 } } },
                "outbound":      { "realtime": { "messages": { "count": 40, "data": 4000 } } },
                "persisted":     { "presence": { "count": 20, "data": 2000 } },
                "connections":   { "tls":      { "peak": 20,  "opened": 10 } },
                "channels":      { "peak": 50, "opened": 30 },
                "apiRequests":   { "succeeded": 50, "failed": 10 },
                "tokenRequests": { "succeeded": 60, "failed": 20 }
            }',
        );

        for ($i = 0; $i < 101; $i++) {
            $fixtureEntries[] = '{
                "intervalId": "' . gmdate( 'Y-m-d:H:i', self::$timestampOlder + $i * 60 ) . '",
                "channels":      { "peak": ' . ($i + 1) . ', "opened": 1 }
            }';
        }

        $fixtureJSON = "[\n" . implode( ",\n", $fixtureEntries ) . "\n]";

        self::$ably->post( '/stats', array(), $fixtureJSON );
    }

    public static function tearDownAfterClass() {
        // echo 'The appId was: '.self::$testApp->getAppKeyDefault()->string."\n";
        self::$testApp->release();
    }

    /**
     * Check minute-level stats exist (forwards)
     */
    public function testAppstatsMinute0() {
        // get the stats for this channel 
        // note that bounds are inclusive 
        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 50, $stats->items[0]->inbound->all->all->count, "Expected 50 messages" );

        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000 + 60000,
            "end" => self::$timestamp * 1000 + 60000
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 60, $stats->items[0]->inbound->all->all->count, "Expected 60 messages" );

        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000 + 120000,
            "end" => self::$timestamp * 1000 + 120000
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 70, $stats->items[0]->inbound->all->all->count, "Expected 70 messages" );
    }

    /**
     * Check minute-level stats exist (backwards)
     */
    public function testAppstatsMinute1() {
        // get the stats for this channel 
        // note that bounds are inclusive 
        $stats = self::$ably->stats(array(
            "direction" => "backwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 50, $stats->items[0]->inbound->all->all->count, "Expected 50 messages" );

        $stats = self::$ably->stats(array(
            "direction" => "backwards",
            "start" => self::$timestamp * 1000 + 60000,
            "end" => self::$timestamp * 1000 + 60000
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 60, $stats->items[0]->inbound->all->all->count, "Expected 60 messages" );

        $stats = self::$ably->stats(array(
            "direction" => "backwards",
            "start" => self::$timestamp * 1000 + 120000,
            "end" => self::$timestamp * 1000 + 120000
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 70, $stats->items[0]->inbound->all->all->count, "Expected 70 messages" );
    }

    /**
     * Check hour-level stats exist (forwards)
     */
    public function testAppstatsHour0() {
        // get the stats for this channel 
        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "unit" => "hour"
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 180, $stats->items[0]->inbound->all->all->count, "Expected 180 messages" );
    }

    /**
     * Check day-level stats exist (forwards)
     */
    public function testAppstatsDay0() {
        // get the stats for this channel 
        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "unit" => "day"
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 180, $stats->items[0]->inbound->all->all->count, "Expected 180 messages" );
    }

    /**
     * Check month-level stats exist (forwards)
     */
    public function testAppstatsMonth0() {
        // get the stats for this channel 
        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "unit" => "month"
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 180, $stats->items[0]->inbound->all->all->count, "Expected 180 messages" );
    }

    /**
     * Publish events and check limit query param (backwards)
     */
    public function testAppstatsLimit0() {
        // get the stats for this channel 
        $stats = self::$ably->stats(array(
            "direction" => "backwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "limit" => 1
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 70, $stats->items[0]->inbound->all->all->count, "Expected 70 messages" );
    }

    /**
     * Check limit query param (forwards)
     */
    public function testAppstatsLimit1() {
        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "limit" => 1
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 50, $stats->items[0]->inbound->all->all->count, "Expected 50 messages" );
    }

    /**
     * Check query pagination (backwards)
     */
    public function testAppstatsPagination0() {
        $stats = self::$ably->stats(array(
            "direction" => "backwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "limit" => 1
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 70, $stats->items[0]->inbound->all->all->count, "Expected 70 messages" );
        // get next page 
        $stats = $stats->next();
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 60, $stats->items[0]->inbound->all->all->count, "Expected 60 messages" );
        // get next page 
        $stats = $stats->next();
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 50, $stats->items[0]->inbound->all->all->count, "Expected 50 messages" );
        // verify that there is no next page 
        $this->assertFalse( $stats->hasNext(), "Expected not to have next page" );
        $this->assertNull( $stats->next(), "Expected null next page" );
    }

    /**
     * Check query pagination (forwards)
     */
    public function testAppstatsPagination1() {
        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "limit" => 1
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 50, $stats->items[0]->inbound->all->all->count, "Expected 50 messages" );
        // get next page 
        $stats = $stats->next();
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 60, $stats->items[0]->inbound->all->all->count, "Expected 60 messages" );
        // get next page 
        $stats = $stats->next();
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 70, $stats->items[0]->inbound->all->all->count, "Expected 70 messages" );
        // verify that there is no next page 
        $this->assertFalse( $stats->hasNext(), "Expected not to have next page" );
        $this->assertNull( $stats->next(), "Expected null next page" );
    }

    /**
     * Check query pagination rel="first" (backwards)
     */
    public function testAppstatsPagination2() {
        $stats = self::$ably->stats(array(
            "direction" => "backwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "limit" => 1
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 70, $stats->items[0]->inbound->all->all->count, "Expected 70 messages" );
        // get next page 
        $stats = $stats->next();
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 60, $stats->items[0]->inbound->all->all->count, "Expected 60 messages" );
        // get first page 
        $stats = $stats->first();
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 70, $stats->items[0]->inbound->all->all->count, "Expected 70 messages" );
    }

    /**
     * Check query pagination rel="first" (forwards)
     */
    public function testAppstatsPagination3() {
        $stats = self::$ably->stats(array(
            "direction" => "forwards",
            "start" => self::$timestamp * 1000,
            "end" => self::$timestamp * 1000 + 120000,
            "limit" => 1
        ));
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 50, $stats->items[0]->inbound->all->all->count, "Expected 50 messages" );
        // get next page 
        $stats = $stats->next();
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 60, $stats->items[0]->inbound->all->all->count, "Expected 60 messages" );
        // get first page 
        $stats = $stats->first();
        $this->assertEquals( 1, count( $stats->items ), "Expected 1 record" );
        $this->assertEquals( 50, $stats->items[0]->inbound->all->all->count, "Expected 50 messages" );
    }

    /**
     * Verify default pagination limit (100), direction (backwards) and unit (minute)
     */
    public function testPaginationDefaults () {
        $stats = self::$ably->stats(array(
            "start" => self::$timestampOlder * 1000,
            "end" => self::$timestampOlder * 1000 + 120 * 60 * 1000,
        ));
        $this->assertEquals( 100, count( $stats->items ), "Expected 100 records" );
 
        // verify order
        $actualRecordsPeakData = array();
        foreach ($stats->items as $minute) {
            $actualRecordsPeakData[] = $minute->channels->peak * 1;
        }
        $expectedData = range( 101, 2, -1 );
        $this->assertEquals( $expectedData, $actualRecordsPeakData, 'Expected records in backward order' );
    }
}