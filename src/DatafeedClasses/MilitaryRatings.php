<?php

namespace VatsimDatafeed\DatafeedClasses;;

class MilitaryRatings
{
	public int $id;
	public string $shortName;
	public string $longName;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->id = $data->id;
		$instance->shortName = $data->short_name;
		$instance->longName = $data->long_name;
		return $instance;
	}
}
