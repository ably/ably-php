<?php

namespace Ably\Models\Status;

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

class ChannelOccupancy
{
    /**
     * @var ChannelMetrics
     */
    public $metrics;
}

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
