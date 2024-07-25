<?php

namespace VatsimDatafeed\DatafeedClasses;

class FlightPlan
{
	public string $flight_rules;
	public string $aircraft;
	public string $aircraft_faa;
	public string $aircraft_short;
	public string $departure;
	public string $arrival;
	public string $alternate;
	public string $cruise_tas;
	public string $altitude;
	public string $deptime;
	public string $enroute_time;
	public string $fuel_time;
	public string $remarks;
	public string $route;
	public int $revision_id;
	public string $assigned_transponder;

	public function __construct(
		string $flight_rules,
		string $aircraft,
		string $aircraft_faa,
		string $aircraft_short,
		string $departure,
		string $arrival,
		string $alternate,
		string $cruise_tas,
		string $altitude,
		string $deptime,
		string $enroute_time,
		string $fuel_time,
		string $remarks,
		string $route,
		int $revision_id,
		string $assigned_transponder
	) {
		$this->flight_rules = $flight_rules;
		$this->aircraft = $aircraft;
		$this->aircraft_faa = $aircraft_faa;
		$this->aircraft_short = $aircraft_short;
		$this->departure = $departure;
		$this->arrival = $arrival;
		$this->alternate = $alternate;
		$this->cruise_tas = $cruise_tas;
		$this->altitude = $altitude;
		$this->deptime = $deptime;
		$this->enroute_time = $enroute_time;
		$this->fuel_time = $fuel_time;
		$this->remarks = $remarks;
		$this->route = $route;
		$this->revision_id = $revision_id;
		$this->assigned_transponder = $assigned_transponder;
	}

	public static function fromJson(\stdClass $data): self
	{
		return new self(
			$data->flight_rules,
			$data->aircraft,
			$data->aircraft_faa,
			$data->aircraft_short,
			$data->departure,
			$data->arrival,
			$data->alternate,
			$data->cruise_tas,
			$data->altitude,
			$data->deptime,
			$data->enroute_time,
			$data->fuel_time,
			$data->remarks,
			$data->route,
			$data->revision_id,
			$data->assigned_transponder
		);
	}
}
