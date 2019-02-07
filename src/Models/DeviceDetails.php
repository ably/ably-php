<?php
namespace Ably\Models;

function get($arr, $key) {
    foreach(explode('.', $key) as $k) {
        if (is_null($arr) || ! isset($arr[$k])) {
          return NULL;
        }
        $arr = $arr[$k];
    }

    return $arr;
}

class DeviceDetails extends BaseOptions {

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $clientId;

    /**
     * @var string
     */
    public $formFactor;

    /**
     * @var array
     */
    public $metadata;

    /**
     * @var string
     */
    public $platform;

    /**
     * @var \Ably\Models\DevicePushDetails
     */
    public $push;

    /**
     * @var string
     */
    public $deviceSecret;

}
