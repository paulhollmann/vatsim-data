<?php

namespace VatsimDatafeed\DatafeedClasses;;

class Prefiles
{
	public int $cid;
	public string $name;
	public string $callsign;
	public FlightPlan $flightPlan;
	public string $lastUpdated;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->cid = $data->cid;
		$instance->name = $data->name;
		$instance->callsign = $data->callsign;
		$instance->flightPlan = FlightPlan::fromJson($data->flight_plan);
		$instance->lastUpdated = $data->last_updated;
		return $instance;
	}
}
