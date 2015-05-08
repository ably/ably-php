<?php
namespace Ably\Models;

/**
 * Model for stats
 */
class Stats {
    /**
     * @var stdClass $all MessageTypes representing the total of all inbound and outbound message traffic.
     * This is the aggregate number that is considered in applying account message limits.
     * @var stdClass $inbound MessageTraffic representing inbound messages (ie published by clients and sent inbound to the Ably service) by all transport types;
     * @var stdClass $outbound MessageTraffic representing outbound messages (ie delivered by the Ably service to connected and subscribed clients);
     * @var stdClass $persisted MessageTypes representing the aggregate volume of messages persisted;
     * @var stdClass $connections ConnectionTypes representing the usage of connections;
     * @var stdClass $channels ResourceCount representing the number of channels activated and used;
     * @var stdClass $apiRequests RequestCount representing the number of requests made to the REST API;
     * @var stdClass $tokenRequests RequestCount representing the number of requests made to issue access tokens.
     */
    public $all;
    public $inbound;
    public $outbound;
    public $persisted;
    public $connections;
    public $channels;
    public $apiRequests;
    public $tokenRequests;
    public $count;
    public $unit;
    public $intervalId;

    /**
     * Populates stats from JSON
     * @param string|stdClass $json JSON string or an already decoded object.
     * @throws AblyException
     */
    public function fromJSON( $json ) {
        $this->clearFields();

        if (is_object( $json )) {
            $obj = $json;
        } else {
            $obj = @json_decode($json);
            if (!$obj) {
                throw new AblyException( 'Invalid object or JSON encoded object' );
            }
        }

        // stats are usually wrapped in an array
        if ( is_array( $obj ) ) {
            $obj = $obj[0];
        }

        $class = get_class( $this );
        foreach ($obj as $key => $value) {
            if (property_exists( $class, $key )) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Sets all the public fields to null
     */
    protected function clearFields() {
        $fields = get_object_vars( $this );
        unset( $fields['cipherParams'] );

        foreach ($fields as $key => $value) {
            $this->$key = null;
        }
    }
}