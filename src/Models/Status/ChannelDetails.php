<?php

namespace Ably\Models\Status;

/**
 * https://docs.ably.io/client-lib-development-guide/features/#CHD1
 */
class ChannelDetails
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $channelId;

    /**
     * @var ChannelStatus
     */
    public $status;
}

/**
 * https://docs.ably.io/client-lib-development-guide/features/#CHS1
 */
class ChannelStatus
{
    /**
     * @var bool
     */
    public $isActive;

    /**
     * @var ChannelOccupancy
     */
    public $occupancy;
}

/**
 * https://docs.ably.io/client-lib-development-guide/features/#CHO1
 */
class ChannelOccupancy
{
    /**
     * @var ChannelMetrics
     */
    public $metrics;
}

/**
 * https://docs.ably.io/client-lib-development-guide/features/#CHM1
 */
class ChannelMetrics
{
    /**
     * @var int
     */
    public $connections;

    /**
     * @var int
     */
    public $presenceConnections;

    /**
     * @var int
     */
    public $presenceMembers;

    /**
     * @var int
     */
    public $presenceSubscribers;

    /**
     * @var int
     */
    public $publishers;

    /**
     * @var int
     */
    public $subscribers;
}
