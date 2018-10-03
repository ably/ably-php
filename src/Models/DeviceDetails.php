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

    const DevicePushTransportType = ['fcm', 'gcm', 'apns', 'web'];
    const DevicePlatform = ['android', 'ios', 'browser'];
    const DeviceFormFactor = ['phone', 'tablet', 'desktop', 'tv', 'watch', 'car', 'embedded', 'other'];

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
     * @var string
     */
    public $metadata;

    /**
     * @var string
     */
    public $platform;

    /**
     * @var string
     */
    public $push;

    /**
     * @var string
     */
    public $deviceSecret;

    public function __construct( array $options = [] ) {
        parent::__construct( $options );

        $transportType = get($this->push, 'recipient.transportType');
        if ($transportType && ! in_array($transportType, self::DevicePushTransportType)) {
            throw new \InvalidArgumentException(
                sprintf('unexpected transport type %s', $transportType)
            );
        }

        if ($this->platform && ! in_array($this->platform, self::DevicePlatform)) {
            throw new \InvalidArgumentException(
                sprintf('unexpected form factor %s', $this->platform)
            );
        }

        if ($this->formFactor && ! in_array($this->formFactor, self::DeviceFormFactor)) {
            throw new \InvalidArgumentException(
                sprintf('unexpected form factor %s', $this->formFactor)
            );
        }
    }

}
