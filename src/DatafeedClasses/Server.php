<?php

namespace VatsimData\DatafeedClasses;

class Server
{
    public string $ident;

    public string $hostname_or_ip;

    public string $location;

    public string $name;

    public int $clients_connection_allowed;

    public bool $client_connections_allowed;

    public bool $is_sweatbox;

    public function __construct(
        string $ident,
        string $hostname_or_ip,
        string $location,
        string $name,
        int $clients_connection_allowed,
        bool $client_connections_allowed,
        bool $is_sweatbox
    ) {
        $this->ident = $ident;
        $this->hostname_or_ip = $hostname_or_ip;
        $this->location = $location;
        $this->name = $name;
        $this->clients_connection_allowed = $clients_connection_allowed;
        $this->client_connections_allowed = $client_connections_allowed;
        $this->is_sweatbox = $is_sweatbox;
    }

    public static function fromJson(object $data): self
    {
        return new self(
            $data->ident,
            $data->hostname_or_ip,
            $data->location,
            $data->name,
            $data->clients_connection_allowed,
            $data->client_connections_allowed,
            $data->is_sweatbox
        );
    }
}
