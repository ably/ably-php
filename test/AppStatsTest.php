<?php

require_once '../lib/ably.php';
require_once 'factories/TestOption.php';

class AppStatsTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected $ably;

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
    }

    /**
     * Publish events and check stats exist (forwards)
     */
    public function testStatsExistForwards() {
        echo '== testStatsExistForwards()';

        # wait for the start of next minute
        $ably_time = ceil($this->ably->time()/1000);
        $interval_start = ceil(time()/60)*60 + abs($ably_time - time()) + 1;
        sleep($interval_start - time());

        # publish some messages
        $stats0 = $this->ably->channel('stats0');
        for ($i=0; $i < 5; $i++) {
            $stats0->publish( 'stats'.$i, $i );
        }

        # wait for the stats to be persisted
        $interval_end = time();
        sleep( 120 );

        $stats = $this->ably->stats(array(
            'direction' => 'forwards',
            'start'     => $interval_start*1000,
            'end'       => $interval_end*1000,
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 5, $stats[0]->published->messageCount );
    }

    /**
     * Publish events and check stats exist (backwards)
     */
    public function testStatsExistBackwards() {
        echo '== testStatsExistBackwards()';

        # wait for the start of next minute
        $ably_time = ceil($this->ably->time()/1000);
        $interval_start = ceil(time()/60)*60 + abs($ably_time - time()) + 1;
        #var_dump(gmdate("r",$interval_start));
        sleep($interval_start - time());

        # publish some messages
        $stats1 = $this->ably->channel('stats1');
        for ($i=0; $i < 5; $i++) {
            $stats1->publish( 'stats'.$i, $i );
        }

        # wait for the stats to be persisted
        $interval_end = time();
        #var_dump(gmdate("r",$interval_end));
        sleep( 120 );

        #var_dump($interval_start*1000);
        #var_dump($interval_end*1000);

        $stats = $this->ably->stats(array(
            'direction' => 'backwards',
            'start'     => $interval_start*1000,
            'end'       => $interval_end*1000,
        ));
        #var_dump($stats);

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 5, $stats[0]->published->messageCount );
    }
}