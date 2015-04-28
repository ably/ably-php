<?php
namespace Ably\Models;

/**
 * Base class for messages sent over channels.
 * Provides automatic encoding and decoding.
 */
class Stats {
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
                throw new AblyException( 'Invalid object or JSON encoded object', 400, 40000 );
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