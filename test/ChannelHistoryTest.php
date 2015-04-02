<?php

require_once dirname(__FILE__) . '/../lib/ably.php';
require_once 'factories/TestOption.php';

class ChannelHistoryTest extends PHPUnit_Framework_TestCase {

    protected static $options;
    protected static $ably0;
    protected $ably;
    protected $timeOffset;

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
        $this->timeOffset = $this->ably->time() - $this->ably->system_time();
    }

    /**
     * Publish events with data of various datatypes
     */
    public function testPublishEventsWithVariousDataTypes() {
        echo '==testPublishEventsWithVariousDataTypes()';

        # first publish some messages
        $history0 = $this->ably->channel('persisted:history0');

        # not supported
        // $history0->publish("history0", true);
        // $history0->publish("history1", 24);
        // $history0->publish("history2", 24.234);

        $history0->publish("history3", 'This is a string message payload');
        $history0->publish("history4", unpack('H*', 'This is a byte[] message payload')[1]);
        $history0->publish("history5", json_encode(array('test' => 'This is a JSONObject message payload')));
        $history0->publish("history6", json_encode(array('This is a JSONArray message payload')));

        # wait for history to be persisted
        sleep(5);

        # get the history for this channel
        $messages = $history0->history();
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 4, count($messages), 'Expected 4 messages' );

        $actual_message_order = array();

        # verify message contents
        foreach ($messages as $message) {
            array_push($actual_message_order, $message->name);
            switch ($message->name) {
                
                # not supported
                // case 'history0' : $this->assertEquals( true, $message->data, 'Expect history0 to be Boolean(true)' ); break;
                // case 'history1' : $this->assertEquals( 24, $message->data, 'Expect history1 to be Integer(24)' ); break;
                // case 'history2' : $this->assertEquals( 24.234, $message->data, 'Expect history2 to be Float(24.234)' ); break;

                case 'history3' : $this->assertEquals( 'This is a string message payload', $message->data, 'Expect history3 to be expected String' ); break;
                case 'history4' : $this->assertEquals( 'This is a byte[] message payload', pack('H*', $message->data), 'Expect history4 to be expected byte[]' ); break;
                case 'history5' : $this->assertEquals( '{"test":"This is a JSONObject message payload"}', $message->data, 'Expect history5 to be expected JSONObject' ); break;
                case 'history6' : $this->assertEquals( '["This is a JSONArray message payload"]', $message->data, 'Expect history6 to be expected JSONArray' ); break;
            }

        }

        # verify message order
        $this->assertEquals( array('history6', 'history5', 'history4', 'history3'), $actual_message_order, 'Expect messages in reverse order' );
    }


    /**
     * Publish events and check expected order (forwards)
     */
    public function testPublishEventsAndCheckOrderForwards() {
        echo '==testPublishEventsAndCheckOrderForwards()';

        # publish some messages
        $history1 = $this->ably->channel('persisted:history1');
        for ($i=0; $i<50; $i++) {
            $history1->publish('history'.$i, sprintf('%s',$i));
        }

        # wait for the history to be persisted
        sleep(5);

        # get the history for this channel
        $messages = $history1->history( array('direction' => 'forwards') );
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 50, count($messages), 'Expected 50 messages' );

        # verify message order
        $actual_message_history = array();
        for ($i=0; $i<50; $i++) {
            array_push( $actual_message_history, $messages[$i]->data);
        }
        $expected_message_history = range(0, 49);
        $this->assertEquals( $expected_message_history, $actual_message_history, 'Expect messages in forward order');
    }

    /**
     * Publish events and check expected order (backwards)
     */
    public function testPublishEventsAndCheckOrderBackwards() {
        echo '==testPublishEventsAndCheckOrderBackwards()';

        # publish some messages
        $history2 = $this->ably->channel('persisted:history2');
        for ($i=0; $i<50; $i++) {
            $history2->publish('history'.$i, sprintf('%s',$i));
        }

        # wait for the history to be persisted
        sleep(5);

        $messages = $history2->history( array('direction' => 'backwards') );
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 50, count($messages), 'Expected 50 messages' );

        # verify message order
        $actual_message_history = array();
        for ($i=0; $i<50; $i++) {
            array_push( $actual_message_history, $messages[$i]->data);
        }
        $expected_message_history = range(49, 0, -1);
        $this->assertEquals( $expected_message_history, $actual_message_history, 'Expect messages in backward order');
    }


    /**
     * Publish events, get limited history and check expected order (forwards)
     */
    public function testPublishEventsGetLimitedHistoryAndCheckOrderForwards() {
        echo '==testPublishEventsGetLimitedHistoryAndCheckOrderForwards()';

        # publish some messages
        $history3 = $this->ably->channel('persisted:history3');
        for ($i=0; $i<50; $i++) {
            $history3->publish('history'.$i, sprintf('%s',$i));
        }

        # wait for the history to be persisted
        sleep(5);

        $messages = $history3->history( array('direction' => 'forwards', 'limit' => 25) );
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 25, count($messages), 'Expected 25 messages' );

        # verify message order
        $actual_message_history = array();
        for ($i=0; $i<25; $i++) {
            array_push( $actual_message_history, $messages[$i]->data);
        }
        $expected_message_history = range(0, 24);
        $this->assertEquals( $expected_message_history, $actual_message_history, 'Expect messages in forward order');
    }

    /**
     * Publish events, get limited history and check expected order (backwards)
     */
    public function testPublishEventsGetLimitedHistoryAndCheckOrderBackwards() {
        echo '==testPublishEventsGetLimitedHistoryAndCheckOrderBackwards()';

        # publish some messages
        $history4 = $this->ably->channel('persisted:history4');
        for ($i=0; $i<50; $i++) {
            $history4->publish('history'.$i, sprintf('%s',$i));
        }

        # wait for the history to be persisted
        sleep(5);

        $messages = $history4->history( array('direction' => 'backwards', 'limit' => 25) );
        $this->assertNotNull( $messages, 'Expected non-null messages' );
        $this->assertEquals( 25, count($messages), 'Expected 25 messages' );

        # verify message order
        $actual_message_history = array();
        for ($i=0; $i<25; $i++) {
            array_push( $actual_message_history, $messages[$i]->data);
        }
        $expected_message_history = range(49, 25, -1);
        $this->assertEquals( $expected_message_history, $actual_message_history, 'Expect messages in backward order');
    }

    /**
     * Publish events and check expected history based on time slice (forwards)
     */
    public function testPublishEventsAndCheckExpectedHistoryInTimeSlicesForwards() {
        echo '==testPublishEventsAndCheckExpectedHistoryInTimeSlicesForwards()';

        $interval_start = $interval_end = 0;

        # first publish some messages
        $history5 = $this->ably->channel('persisted:history5');

        # send batches of messages with short inter-message delay
        for ($i=0; $i<20; $i++) {
            $history5->publish('history'.$i, sprintf('%s',$i));
            usleep(100000); // sleep for 0.1 of a second
        }
        $interval_start = $this->timeOffset + $this->ably->system_time() + 1;
        for ($i=20; $i<40; $i++) {
            $history5->publish('history'.$i, sprintf('%s',$i));
            usleep(100000); // sleep for 0.1 of a second
        }
        $interval_end = $this->timeOffset + $this->ably->system_time();
        for ($i=40; $i<60; $i++) {
            $history5->publish('history'.$i, sprintf('%s',$i));
            usleep(100000); // sleep for 0.1 of a second
        }

        # wait for the history to be persisted
        sleep(5);

        # get the history for this channel
        $messages = $history5->history(array(
            'direction' => 'forwards',
            'start'     => $interval_start,
            'end'       => $interval_end,
        ));
        $this->assertNotNull( $messages, 'Expect non-null messages' );
        $this->assertEquals( 20, count($messages), 'Expected 20 messages' );

        # verify message order
        $actual_message_history = array();
        for ($i=20; $i<40; $i++) {
            array_push( $actual_message_history, $messages[$i-20]->data);
        }
        $expected_message_history = range(20, 39);
        $this->assertEquals( $expected_message_history, $actual_message_history, 'Expect messages in forward order');
    }

    /**
     * Publish events and check expected history based on time slice (backwards)
     */
    public function testPublishEventsAndCheckExpectedHistoryInTimeSlicesBackwards() {
        echo '==testPublishEventsAndCheckExpectedHistoryInTimeSlicesBackwards()';

        $interval_start = $interval_end = 0;

        # first publish some messages
        $history6 = $this->ably->channel('persisted:history6');

        # send batches of messages with short inter-message delay
        for ($i=0; $i<20; $i++) {
            $history6->publish('history'.$i, sprintf('%s',$i));
            usleep(100000); // sleep for 0.1 of a second
        }
        $interval_start = $this->timeOffset + $this->ably->system_time() + 1;
        for ($i=20; $i<40; $i++) {
            $history6->publish('history'.$i, sprintf('%s',$i));
            usleep(100000); // sleep for 0.1 of a second
        }
        $interval_end = $this->timeOffset + $this->ably->system_time();
        for ($i=40; $i<60; $i++) {
            $history6->publish('history'.$i, sprintf('%s',$i));
            usleep(100000); // sleep for 0.1 of a second
        }

        # wait for the history to be persisted
        sleep(5);

        # get the history for this channel
        $messages = $history6->history(array(
            'direction' => 'backwards',
            'start'     => $interval_start,
            'end'       => $interval_end,
        ));

        $this->assertNotNull( $messages, 'Expect non-null messages' );
        $this->assertEquals( 20, count($messages), 'Expected 20 messages' );

        # verify message order
        $actual_message_history = array();
        for ($i=20; $i<40; $i++) {
            array_push( $actual_message_history, $messages[$i-20]->data);
        }
        $expected_message_history = range(39, 20, -1);
        $this->assertEquals( $expected_message_history, $actual_message_history, 'Expect messages in backward order' );
    }
}