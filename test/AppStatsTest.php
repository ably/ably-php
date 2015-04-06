<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once 'factories/TestOption.php';

class AppStatsTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected static $ably0;
    protected $ably;
    protected $time_offset;

    public static function setUpBeforeClass() {
        self::$options = TestOption::get_instance()->get_opts();
        self::$ably0 = new AblyRest(array(
            'debug'     => false,
            'encrypted' => self::$options['encrypted'],
            'host'      => self::$options['host'],
            'key'       => self::$options['first_private_api_key'],
            'port'      => self::$options['port'],
        ));
    }

    public static function tearDownAfterClass() {
        TestOption::get_instance()->clear_opts();
    }

    protected function setUp() {
        $this->ably = self::$ably0;
        $this->time_offset = $this->ably->time() - $this->ably->system_time();
    }

    public function testPublishEventsForwards() {
        echo '== testPublishEventsForwards()';

        $interval = array();

        # wait for the start of the next minute
        $t = $this->time_offset + $this->ably->system_time();
        $interval[0] = ceil(($t + 1000)/60000)*60000;
        $wait = ceil(($interval[0] - $t)/1000);
        sleep($wait);

        # publish some messages
        $stats0 = $this->ably->channel('appstats_0');
        for ($i=0; $i < 5; $i++) {
            $stats0->publish( 'stats'.$i, $i );
        }

        # wait for the stats to be persisted
        $interval[1] = $this->time_offset + $this->ably->system_time();
        sleep( 10 );

        $this->assertTrue(true);

        return $interval;
    }

    /**
     * Check minute-level stats exist (forwards)
     * @depends testPublishEventsForwards
     */
    public function testMinuteLevelStatsExistForwards(array $interval) {
        echo '== testMinuteLevelStatsExistForwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'forwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 5, (int)$stats[0]->inbound->all->messages->count );
    }

    /**
     * Check hour-level stats exist (forwards)
     * @depends testPublishEventsForwards
     */
    public function testHourLevelStatsExistForwards(array $interval) {
        echo '== testMinuteLevelStatsExistForwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'forwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
            'by'        => 'hour',
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 5, (int)$stats[0]->inbound->all->messages->count );
    }

    /**
     * Check day-level stats exist (forwards)
     * @depends testPublishEventsForwards
     */
    public function testDayLevelStatsExistForwards(array $interval) {
        echo '== testDayLevelStatsExistForwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'forwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
            'by'        => 'day',
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 5, (int)$stats[0]->inbound->all->messages->count );
    }


    /**
     * Check month-level stats exist (forwards)
     * @depends testPublishEventsForwards
     */
    public function testMonthLevelStatsExistForwards(array $interval) {
        echo '== testMonthLevelStatsExistForwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'forwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
            'by'        => 'month',
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 5, (int)$stats[0]->inbound->all->messages->count );
    }

    /**
     * Publish events (backwards)
     */
    public function testPublishEventsBackwards() {
        echo '==testPublishEventsBackwards()';

        $interval = array();

        # wait for the start of the next minute
        $t = $this->time_offset + $this->ably->system_time();
        $interval[0] = ceil(($t + 1000)/60000)*60000;
        $wait = ceil(($interval[0] - $t)/1000);
        sleep($wait);

        # publish some messages
        $stats0 = $this->ably->channel('appstats_1');
        for ($i=0; $i < 6; $i++) {
            $stats0->publish( 'stats'.$i, $i );
        }

        # wait for the stats to be persisted
        $interval[1] = $this->time_offset + $this->ably->system_time();
        sleep( 10 );

        $this->assertTrue(true);

        return $interval;
    }

    /**
     * Check minute-level stats exist (backwards)
     * @depends testPublishEventsBackwards
     */
    public function testMinuteLevelStatsExistBackwards(array $interval) {
        echo '== testMinuteLevelStatsExistBackwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'backwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 6, (int)$stats[0]->inbound->all->messages->count );
    }

    /**
     * Check hour-level stats exist (backwards)
     * @depends testPublishEventsBackwards
     */
    public function testHourLevelStatsExistBackwards(array $interval) {
        echo '== testHourLevelStatsExistBackwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'backwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
            'by'        => 'hour',
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertTrue ( count($stats) == 1 || count($stats) == 2, 'Expected 1 or two records' );
        if (count($stats) == 1) {
            $this->assertEquals ( 11, (int)$stats[0]->inbound->all->messages->count );
        } else {
            $this->assertEquals ( 6, (int)$stats[1]->inbound->all->messages->count );
        }

    }

    /**
     * Check day-level stats exist (backwards)
     * @depends testPublishEventsBackwards
     */
    public function testDayLevelStatsExistBackwards(array $interval) {
        echo '== testDayLevelStatsExistBackwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'backwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
            'by'        => 'day',
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertTrue ( count($stats) == 1 || count($stats) == 2, 'Expected 1 or two records' );
        if (count($stats) == 1) {
            $this->assertEquals ( 11, (int)$stats[0]->inbound->all->messages->count );
        } else {
            $this->assertEquals ( 6, (int)$stats[1]->inbound->all->messages->count );
        }
    }


    /**
     * Check month-level stats exist (backwards)
     * @depends testPublishEventsBackwards
     */
    public function testMonthLevelStatsExistBackwards(array $interval) {
        echo '== testMonthLevelStatsExistBackwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'backwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
            'by'        => 'month',
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertTrue ( count($stats) == 1 || count($stats) == 2, 'Expected 1 or two records' );
        if (count($stats) == 1) {
            $this->assertEquals ( 11, (int)$stats[0]->inbound->all->messages->count );
        } else {
            $this->assertEquals ( 6, (int)$stats[1]->inbound->all->messages->count );
        }
    }

    /**
     * Publish events with limit query
     */
    public function testPublishEventsLimit() {
        echo '==testPublishEventsLimit()';

        $interval = array();

        # wait for the start of the next minute
        $t = $this->time_offset + $this->ably->system_time();
        $interval[0] = ceil(($t + 1000)/60000)*60000;
        $wait = ceil(($interval[0] - $t)/1000);
        sleep($wait);

        # publish some messages
        $stats0 = $this->ably->channel('appstats_2');
        for ($i=0; $i < 7; $i++) {
            $stats0->publish( 'stats'.$i, $i );
        }

        # wait for the stats to be persisted
        $interval[1] = $this->time_offset + $this->ably->system_time();
        sleep( 10 );

        $this->assertTrue(true);

        return $interval;
    }

    /**
     * Check limit query param (backwards)
     * @depends testPublishEventsLimit
     */
    public function testLimitParamBackwards(array $interval) {
        echo '== testLimitParamBackwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'backwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
            'limit'     => '1',
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 7, (int)$stats[0]->inbound->all->messages->count );
    }

    /**
     * Check limit query param (forwards)
     * @depends testPublishEventsLimit
     */
    public function testLimitParamForwards(array $interval) {
        echo '== testLimitParamForwards()';

        $stats = $this->ably->stats(array(
            'direction' => 'forwards',
            'start'     => $interval[0],
            'end'       => $interval[1],
            'limit'     => '1',
        ));

        $this->assertNotNull( $stats, 'Expected non-null stats' );
        $this->assertEquals ( 1, count($stats), 'Expected 1 record' );
        $this->assertEquals ( 7, (int)$stats[0]->inbound->all->messages->count );
    }

    // TODO: Pagination tests

}