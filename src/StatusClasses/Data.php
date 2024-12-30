<?php

namespace VatsimData\StatusClasses;

class Data
{
    /** @var string[] */
    public array $v3;

    /** @var string[] */
    public array $transceivers;

    /** @var string[] */
    public array $servers;

    /** @var string[] */
    public array $serversSweatbox;

    /** @var string[] */
    public array $serversAll;

    public static function fromJson(\stdClass $data): self
    {
        $instance = new self;
        $instance->v3 = $data->v3;
        $instance->transceivers = $data->transceivers;
        $instance->servers = $data->servers;
        $instance->serversSweatbox = $data->servers_sweatbox;
        $instance->serversAll = $data->servers_all;

        return $instance;
    }
}
