<?php
namespace Ably\PubSub\Models;

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
     * @var \Ably\PubSub\Models\DevicePushDetails
     */
    public $push;

    /**
     * @var string
     */
    public $deviceSecret;

}
