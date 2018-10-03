<?php
namespace Ably;

use Ably\Models\DeviceDetails;

class PushDeviceRegistrations {

    private $ably;

    /**
     * Constructor
     * @param AblyRest $ably Ably API instance
     */
    public function __construct( AblyRest $ably ) {
        $this->ably = $ably;
    }

    /**
     * Creates or updates the device. Returns a DeviceDetails object.
     *
     * @param array $device an array with the device information
     */
    public function save ( $device ) {
        $deviceDetails = new DeviceDetails( $device );
        $path = '/push/deviceRegistrations/' . $deviceDetails->id;
        $params = $deviceDetails->toArray();
        $body = $this->ably->put( $path, [], json_encode($params) );
        $body = json_decode(json_encode($body), true); // Convert stdClass to array
        return new DeviceDetails ( $body );
    }

}
