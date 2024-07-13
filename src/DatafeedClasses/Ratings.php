<?php

namespace VatsimDatafeed\DatafeedClasses;;

class Ratings
{
	public int $id;
	public string $short;
	public string $long;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->id = $data->id;
		$instance->short = $data->short;
		$instance->long = $data->long;
		return $instance;
	}
}
