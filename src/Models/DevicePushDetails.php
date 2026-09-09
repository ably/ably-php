<?php
namespace Ably\PubSub\Models;

class DevicePushDetails extends BaseOptions {

    /**
     * @var \Ably\PubSub\Models\ErrorInfo
     */
    public $errorReason;

    /**
     * @var array
     */
    public $recipient;

    /**
     * @var string
     */
    public $state;

}
