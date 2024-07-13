<?php

namespace VatsimDatafeed\DatafeedClasses;;

class Servers
{
	public string $ident;
	public string $hostnameOrIp;
	public string $location;
	public string $name;
	public int $clientsConnectionAllowed;
	public bool $clientConnectionsAllowed;
	public bool $isSweatbox;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->ident = $data->ident;
		$instance->hostnameOrIp = $data->hostname_or_ip;
		$instance->location = $data->location;
		$instance->name = $data->name;
		$instance->clientsConnectionAllowed = $data->clients_connection_allowed;
		$instance->clientConnectionsAllowed = $data->client_connections_allowed;
		$instance->isSweatbox = $data->is_sweatbox;
		return $instance;
	}
}
