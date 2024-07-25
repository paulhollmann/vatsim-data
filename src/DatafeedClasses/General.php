<?php

namespace VatsimData\DatafeedClasses;

class General
{
	public int $version;
	public int $reload;
	public string $update;
	public string $update_timestamp;
	public int $connected_clients;
	public int $unique_users;

	public function __construct(
		int $version,
		int $reload,
		string $update,
		string $update_timestamp,
		int $connected_clients,
		int $unique_users
	) {
		$this->version = $version;
		$this->reload = $reload;
		$this->update = $update;
		$this->update_timestamp = $update_timestamp;
		$this->connected_clients = $connected_clients;
		$this->unique_users = $unique_users;
	}

	public static function fromJson(\stdClass $data): self
	{
		return new self(
			$data->version,
			$data->reload,
			$data->update,
			$data->update_timestamp,
			$data->connected_clients,
			$data->unique_users
		);
	}
}
