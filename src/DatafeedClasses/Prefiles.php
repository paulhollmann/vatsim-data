<?php

namespace VatsimDatafeed\DatafeedClasses;

class Prefiles
{
	public int $cid;
	public string $name;
	public string $callsign;
	public FlightPlan $flight_plan;
	public string $last_updated;

	public function __construct(
		int $cid,
		string $name,
		string $callsign,
		FlightPlan $flight_plan,
		string $last_updated
	) {
		$this->cid = $cid;
		$this->name = $name;
		$this->callsign = $callsign;
		$this->flight_plan = $flight_plan;
		$this->last_updated = $last_updated;
	}

	public static function fromJson(\stdClass $data): self
	{
		return new self(
			$data->cid,
			$data->name,
			$data->callsign,
			FlightPlan::fromJson($data->flight_plan),
			$data->last_updated
		);
	}
}
