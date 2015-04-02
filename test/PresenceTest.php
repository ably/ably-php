<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once 'factories/TestOption.php';

class PresenceTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected static $ably0;
    protected static $channelFixture;
    protected $ably;
    protected $timeOffset;
    protected $fixture;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();
        self::$ably0 = new AblyRest(array(
            'debug'     => false,
            'encrypted' => self::$options['encrypted'],
            'host'      => self::$options['host'],
            'key'       => self::$options['first_private_api_key'],
            'port'      => self::$options['port'],
        ));

        $spec = json_decode(file_get_contents('fixtures/test_app_spec.json'));
        self::$channelFixture = $spec->channels[0];
    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {

        $this->ably = self::$ably0;
        $this->timeOffset = $this->ably->time() - $this->ably->system_time();
        $this->fixture = self::$channelFixture;
    }

    /**
     * Compare presence data with fixture
     */
    public function testComparePresenceDataWithFixture() {
        echo '=='.__FUNCTION__.'()';

        $presenceChannel = $this->ably->channel('persisted:presence_fixtures');
        $presence = $presenceChannel->presence();

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
        $presenceLimit = $presenceChannel->presence_history( array( 'limit' => 2, 'direction' => 'forwards' ) );

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
        echo '=='.__FUNCTION__.'()';

        $presenceChannel = $this->ably->channel('persisted:presence_fixtures');
        $history = $presenceChannel->presence_history();

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
        $historyLimit = $presenceChannel->presence_history( array( 'limit' => 2, 'direction' => 'forwards' ) );
        
        $this->assertTrue( $historyLimit->isFirstPage(), 'Expected the page to be first' );
        $this->assertEquals( 2, count($historyLimit), 'Expected 2 presence entries' );

        $nextPage = $historyLimit->getNextPage();

        $this->assertEquals( $this->fixture->presence[0]->clientId, $historyLimit[0]->clientId, 'Expected least recent presence activity to be the first' );
        $this->assertEquals( $this->fixture->presence[3]->clientId, $nextPage[1]->clientId, 'Expected most recent presence activity to be the last' );

        # verify limit / pagination - backwards
        $historyLimit = $presenceChannel->presence_history( array( 'limit' => 2, 'direction' => 'backwards' ) );

        $this->assertTrue( $historyLimit->isFirstPage(), 'Expected the page to be first' );
        $this->assertEquals( 2, count($historyLimit), 'Expected 2 presence entries' );

        $nextPage = $historyLimit->getNextPage();

        $this->assertEquals( $this->fixture->presence[3]->clientId, $historyLimit[0]->clientId, 'Expected most recent presence activity to be the first' );
        $this->assertEquals( $this->fixture->presence[0]->clientId, $nextPage[1]->clientId, 'Expected least recent presence activity to be the last' );
    }
}