<?php

namespace VatsimDatafeed\DatafeedClasses;;

class FlightPlan
{
	public string $flightRules;
	public string $aircraft;
	public string $aircraftFaa;
	public string $aircraftShort;
	public string $departure;
	public string $arrival;
	public string $alternate;
	public string $cruiseTas;
	public string $altitude;
	public string $deptime;
	public string $enrouteTime;
	public string $fuelTime;
	public string $remarks;
	public string $route;
	public int $revisionId;
	public string $assignedTransponder;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->flightRules = $data->flight_rules;
		$instance->aircraft = $data->aircraft;
		$instance->aircraftFaa = $data->aircraft_faa;
		$instance->aircraftShort = $data->aircraft_short;
		$instance->departure = $data->departure;
		$instance->arrival = $data->arrival;
		$instance->alternate = $data->alternate;
		$instance->cruiseTas = $data->cruise_tas;
		$instance->altitude = $data->altitude;
		$instance->deptime = $data->deptime;
		$instance->enrouteTime = $data->enroute_time;
		$instance->fuelTime = $data->fuel_time;
		$instance->remarks = $data->remarks;
		$instance->route = $data->route;
		$instance->revisionId = $data->revision_id;
		$instance->assignedTransponder = $data->assigned_transponder;
		return $instance;
	}
}
