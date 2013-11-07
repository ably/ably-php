<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once 'factories/TestOption.php';

class AppStatsTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected $ably;
    protected $timeOffset;

    public static function setUpBeforeClass() {

        self::$options = TestOption::get_instance()->get_opts();

    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {

        $options = self::$options;
        $defaults = array(
            'debug'     => false,
            'encrypted' => $options['encrypted'],
            'host'      => $options['host'],
            'key'       => $options['first_private_api_key'],
            'port'      => $options['port'],
        );

        $this->ably = new AblyRest( $defaults );
        $this->timeOffset = $this->ably->time() - $this->ably->system_time();
    }

    /**
     * Publish events and check stats exist (forwards)
     */
    public function testStatsExistForwards() {
        echo '== testStatsExistForwards()';

        # wait for the start of the next minute
        $t = $this->timeOffset + $this->ably->system_time();
        $interval_start = ceil(($t + 1000)/60000)*60000;
        $wait = ceil(($interval_start - $t)/1000);
        sleep($wait);

        # publish some messages
        $stats0 = $this->ably->channel('stats0');
        for ($i=0; $i < 5; $i++) {
            $stats0->publish( 'stats'.$i, $i );
        }

        # wait for the stats to be persisted
        $interval_end = $this->timeOffset + $this->ably->system_time();
        sleep( 10 );

        $stats = $this->ably->stats(array(
            'direction' => 'forwards',
            'start'     => $interval_start,
            'end'       => $interval_end,
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 5, $stats[0]->inbound->all->all->count );
    }

    /**
     * Publish events and check stats exist (backwards)
     */
    public function testStatsExistBackwards() {
        echo '== testStatsExistBackwards()';

        # wait for the start of next minute
        $t = $this->timeOffset + $this->ably->system_time();
        $interval_start = ceil(($t + 1000)/60000)*60000;
        $wait = ceil(($interval_start - $t)/1000);
        sleep($wait);

        # publish some messages
        $stats1 = $this->ably->channel('stats1');
        for ($i=0; $i < 5; $i++) {
            $stats1->publish( 'stats'.$i, $i );
        }

        # wait for the stats to be persisted
        $interval_end = $this->timeOffset + $this->ably->system_time();
        sleep( 10 );

        $stats = $this->ably->stats(array(
            'direction' => 'backwards',
            'start'     => $interval_start,
            'end'       => $interval_end,
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 5, $stats[0]->inbound->all->all->count );
    }
}