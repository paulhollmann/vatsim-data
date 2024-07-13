<?php

namespace VatsimDatafeed\DatafeedClasses;;

class RootObject
{
	public General $general;
	/** @var Pilots[] */
	public array $pilots;
	/** @var Controllers[] */
	public array $controllers;
	/** @var Atis[] */
	public array $atis;
	/** @var Servers[] */
	public array $servers;
	/** @var Prefiles[] */
	public array $prefiles;
	/** @var Facilities[] */
	public array $facilities;
	/** @var Ratings[] */
	public array $ratings;
	/** @var PilotRatings[] */
	public array $pilotRatings;
	/** @var MilitaryRatings[] */
	public array $militaryRatings;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->general = General::fromJson($data->general);
		$instance->pilots = array_map(static function($data) {
			return Pilots::fromJson($data);
		}, $data->pilots);
		$instance->controllers = array_map(static function($data) {
			return Controllers::fromJson($data);
		}, $data->controllers);
		$instance->atis = array_map(static function($data) {
			return Atis::fromJson($data);
		}, $data->atis);
		$instance->servers = array_map(static function($data) {
			return Servers::fromJson($data);
		}, $data->servers);
		$instance->prefiles = array_map(static function($data) {
			return Prefiles::fromJson($data);
		}, $data->prefiles);
		$instance->facilities = array_map(static function($data) {
			return Facilities::fromJson($data);
		}, $data->facilities);
		$instance->ratings = array_map(static function($data) {
			return Ratings::fromJson($data);
		}, $data->ratings);
		$instance->pilotRatings = array_map(static function($data) {
			return PilotRatings::fromJson($data);
		}, $data->pilot_ratings);
		$instance->militaryRatings = array_map(static function($data) {
			return MilitaryRatings::fromJson($data);
		}, $data->military_ratings);
		return $instance;
	}
}
