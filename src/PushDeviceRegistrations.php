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

    /**
     *  Returns a DeviceDetails object if the device id is found or results in
     *  a not found error if the device cannot be found.
     *
     *  @param string $deviceId the id of the device
     */
    public function get ($deviceId) {
        $path = '/push/deviceRegistrations/' . $deviceId;
        $body = $this->ably->get( $path );
        $body = json_decode(json_encode($body), true); // Convert stdClass to array
        return new DeviceDetails ( $body );
    }


}
