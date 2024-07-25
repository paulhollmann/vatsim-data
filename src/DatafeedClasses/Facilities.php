<?php

namespace VatsimDatafeed\DatafeedClasses;

class Facilities
{
	public int $id;
	public string $short;
	public string $long;

	public function __construct(
		int $id,
		string $short,
		string $long
	) {
		$this->id = $id;
		$this->short = $short;
		$this->long = $long;
	}

	public static function fromJson(\stdClass $data): self
	{
		return new self(
			$data->id,
			$data->short,
			$data->long
		);
	}
}
