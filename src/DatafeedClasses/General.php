<?php

namespace VatsimDatafeed\DatafeedClasses;;

class General
{
	public int $version;
	public int $reload;
	public string $update;
	public string $updateTimestamp;
	public int $connectedClients;
	public int $uniqueUsers;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->version = $data->version;
		$instance->reload = $data->reload;
		$instance->update = $data->update;
		$instance->updateTimestamp = $data->update_timestamp;
		$instance->connectedClients = $data->connected_clients;
		$instance->uniqueUsers = $data->unique_users;
		return $instance;
	}
}
