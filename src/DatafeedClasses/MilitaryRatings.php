<?php

namespace VatsimData\DatafeedClasses;

class MilitaryRatings
{
	public int $id;
	public string $short_name;
	public string $long_name;

	public function __construct(
		int $id,
		string $short_name,
		string $long_name
	) {
		$this->id = $id;
		$this->short_name = $short_name;
		$this->long_name = $long_name;
	}

	public static function fromJson(\stdClass $data): self
	{
		return new self(
			$data->id,
			$data->short_name,
			$data->long_name
		);
	}
}
