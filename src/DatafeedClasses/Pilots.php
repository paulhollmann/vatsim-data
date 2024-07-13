<?php

namespace VatsimDatafeed\DatafeedClasses;;

class Pilots
{
	public int $cid;
	public string $name;
	public string $callsign;
	public string $server;
	public int $pilotRating;
	public int $militaryRating;
	public float $latitude;
	public float $longitude;
	public int $altitude;
	public int $groundspeed;
	public string $transponder;
	public int $heading;
	public int|float $qnhIHg;
	public int $qnhMb;
	public ?FlightPlan $flightPlan;
	public string $logonTime;
	public string $lastUpdated;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->cid = $data->cid;
		$instance->name = $data->name;
		$instance->callsign = $data->callsign;
		$instance->server = $data->server;
		$instance->pilotRating = $data->pilot_rating;
		$instance->militaryRating = $data->military_rating;
		$instance->latitude = $data->latitude;
		$instance->longitude = $data->longitude;
		$instance->altitude = $data->altitude;
		$instance->groundspeed = $data->groundspeed;
		$instance->transponder = $data->transponder;
		$instance->heading = $data->heading;
		$instance->qnhIHg = $data->qnh_i_hg;
		$instance->qnhMb = $data->qnh_mb;
		$instance->flightPlan = ($data->flight_plan ?? null) !== null ? FlightPlan::fromJson($data->flight_plan) : null;
		$instance->logonTime = $data->logon_time;
		$instance->lastUpdated = $data->last_updated;
		return $instance;
	}
}
