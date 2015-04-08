<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once dirname(__FILE__) . '/factories/TestOption.php';

class PresenceTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected static $ably0;
    protected static $channelFixture;
    protected $ably;
    protected $timeOffset;
    protected $fixture;
    protected $presenceChannel;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();
        self::$ably0 = new AblyRest(array(
            'debug'     => false,
            'encrypted' => self::$options['encrypted'],
            'host'      => self::$options['host'],
            'key'       => self::$options['first_private_api_key'],
            'port'      => self::$options['port'],
        ));

        $spec = json_decode(file_get_contents(dirname(__FILE__).'/fixtures/test_app_spec.json'));
        self::$channelFixture = $spec->channels[0];
    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {

        $this->ably = self::$ably0;
        $this->timeOffset = $this->ably->time() - $this->ably->system_time();
        $this->fixture = self::$channelFixture;
        $this->presenceChannel = $this->ably->channel('persisted:presence_fixtures');
    }

    /**
     * Compare presence data with fixture
     */
    public function testComparePresenceDataWithFixture() {
        $presence = $this->presenceChannel->presence->get();

        # verify presence existence and count
        $this->assertNotNull( $presence, 'Expected non-null presence data' );
        $this->assertEquals( 4, count($presence), 'Expected 4 presence entries' );

        # verify presence contents
        $fixturePresenceMap = array();
        foreach ($this->fixture->presence as $entry) {
            $fixturePresenceMap[$entry->clientId] = $entry->data;
        }
        
        foreach ($presence as $entry) {
            $this->assertTrue(
                array_key_exists($entry->clientId, $fixturePresenceMap) && $fixturePresenceMap[$entry->clientId] == $entry->data,
                'Expected presence contents to match'
            );
        }

        # verify limit / pagination
        $presenceLimit = $this->presenceChannel->presence->history( array( 'limit' => 2, 'direction' => 'forwards' ) );

        $this->assertTrue( $presenceLimit->isFirstPage(), 'Expected the page to be first' );
        $this->assertEquals( 2, count($presenceLimit), 'Expected 2 presence entries' );

        $nextPage = $presenceLimit->getNextPage();
        $this->assertEquals( 2, count($presenceLimit), 'Expected 2 presence entries on 2nd page' );

        $this->assertEquals( 2, count($nextPage), 'Expected 2 presence entries on 2nd page' );
        $this->assertTrue( $nextPage->isLastPage(), 'Expected last page' );
    }

    /**
     * Compare presence history with fixture
     */
    public function testComparePresenceHistoryWithFixture() {
        $history = $this->presenceChannel->presence->history();

        # verify history existence and count
        $this->assertNotNull( $history, 'Expected non-null history data' );
        $this->assertEquals( 4, count($history), 'Expected 4 history entries' );

        # verify history contents
        $fixtureHistoryMap = array();
        foreach ($this->fixture->presence as $entry) {
            $fixtureHistoryMap[$entry->clientId] = $entry->data;
        }
        
        foreach ($history as $entry) {
            $this->assertTrue(
                isset($fixtureHistoryMap[$entry->clientId]) && $fixtureHistoryMap[$entry->clientId] == $entry->data,
                'Expected presence contents to match'
            );
        }

        # verify limit / pagination - forwards
        $historyLimit = $this->presenceChannel->presence->history( array( 'limit' => 2, 'direction' => 'forwards' ) );
        
        $this->assertTrue( $historyLimit->isFirstPage(), 'Expected the page to be first' );
        $this->assertEquals( 2, count($historyLimit), 'Expected 2 presence entries' );

        $nextPage = $historyLimit->getNextPage();

        $this->assertEquals( $this->fixture->presence[0]->clientId, $historyLimit[0]->clientId, 'Expected least recent presence activity to be the first' );
        $this->assertEquals( $this->fixture->presence[3]->clientId, $nextPage[1]->clientId, 'Expected most recent presence activity to be the last' );

        # verify limit / pagination - backwards
        $historyLimit = $this->presenceChannel->presence->history( array( 'limit' => 2, 'direction' => 'backwards' ) );

        $this->assertTrue( $historyLimit->isFirstPage(), 'Expected the page to be first' );
        $this->assertEquals( 2, count($historyLimit), 'Expected 2 presence entries' );

        $nextPage = $historyLimit->getNextPage();

        $this->assertEquals( $this->fixture->presence[3]->clientId, $historyLimit[0]->clientId, 'Expected most recent presence activity to be the first' );
        $this->assertEquals( $this->fixture->presence[0]->clientId, $nextPage[1]->clientId, 'Expected least recent presence activity to be the last' );
    }

    /*
     * Check whether time range queries work properly
     */
    public function testPresenceHistoryTimeRange() {
        # ensure some time has passed since mock presence data was sent
        $delay = 1000; // sleep for 1000ms
        usleep($delay * 1000); // in microseconds

        $now = $this->timeOffset + $this->ably->system_time();

        # test with start parameter
        try {
            $history = $this->presenceChannel->presence->history( array( 'start' => $now ) );
            $this->assertEquals( 0, count($history), 'Expected 0 presence entries' );
        } catch (AblyRequestException $e) {
            $this->fail( 'Start parameter - ' . $e->getMessage() . ', HTTP code: ' . $e->getCode() );
        }

        # test with end parameter
        try {
            $history = $this->presenceChannel->presence->history( array( 'end' => $now ) );
            $this->assertEquals( 4, count($history), 'Expected 4 presence entries' );
        } catch (AblyRequestException $e) {
            $this->fail( 'End parameter - ' . $e->getMessage() . ', HTTP code: ' . $e->getCode() );
        }

        # test with both start and end parameters - time range: ($now - 500ms) ... $now
        try {
            $history = $this->presenceChannel->presence->history( array( 'start' => $now - ($delay / 2), 'end' => $now ) );
            $this->assertEquals( 0, count($history), 'Expected 0 presence entries' );
        } catch (AblyRequestException $e) {
            $this->fail( 'Start + end parameter - ' . $e->getMessage() . ', HTTP code: ' . $e->getCode() );
        }

        # test ISO 8601 date format
        try {
            $history = $this->presenceChannel->presence->history( array( 'end' => gmdate('c', $now / 1000) ) );
            $this->assertEquals( 4, count($history), 'Expected 4 presence entries' );
        } catch (AblyRequestException $e) {
            $this->fail( 'ISO format: ' . $e->getMessage() . ', HTTP code: ' . $e->getCode() );
        }
    }
}