<?php

namespace Ably\Models\Status;

class ChannelMetrics extends \Ably\Models\BaseOptions
{
    public $connections;

    public $presenceConnections;

    public $presenceMembers;

    public $presenceSubscribers;

    public $publishers;

    public $subscribers;
}
