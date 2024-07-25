<?php

namespace VatsimDatafeed\DatafeedClasses;

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
	public array $pilot_ratings;
	/** @var MilitaryRatings[] */
	public array $military_ratings;

	/**
	 * @param Pilots[] $pilots
	 * @param Controllers[] $controllers
	 * @param Atis[] $atis
	 * @param Servers[] $servers
	 * @param Prefiles[] $prefiles
	 * @param Facilities[] $facilities
	 * @param Ratings[] $ratings
	 * @param PilotRatings[] $pilot_ratings
	 * @param MilitaryRatings[] $military_ratings
	 */
	public function __construct(
		General $general,
		array $pilots,
		array $controllers,
		array $atis,
		array $servers,
		array $prefiles,
		array $facilities,
		array $ratings,
		array $pilot_ratings,
		array $military_ratings
	) {
		$this->general = $general;
		$this->pilots = $pilots;
		$this->controllers = $controllers;
		$this->atis = $atis;
		$this->servers = $servers;
		$this->prefiles = $prefiles;
		$this->facilities = $facilities;
		$this->ratings = $ratings;
		$this->pilot_ratings = $pilot_ratings;
		$this->military_ratings = $military_ratings;
	}

	public static function fromJson(\stdClass $data): self
	{
		return new self(
			General::fromJson($data->general),
			array_map(static function($data) {
				return Pilots::fromJson($data);
			}, $data->pilots),
			array_map(static function($data) {
				return Controllers::fromJson($data);
			}, $data->controllers),
			array_map(static function($data) {
				return Atis::fromJson($data);
			}, $data->atis),
			array_map(static function($data) {
				return Servers::fromJson($data);
			}, $data->servers),
			array_map(static function($data) {
				return Prefiles::fromJson($data);
			}, $data->prefiles),
			array_map(static function($data) {
				return Facilities::fromJson($data);
			}, $data->facilities),
			array_map(static function($data) {
				return Ratings::fromJson($data);
			}, $data->ratings),
			array_map(static function($data) {
				return PilotRatings::fromJson($data);
			}, $data->pilot_ratings),
			array_map(static function($data) {
				return MilitaryRatings::fromJson($data);
			}, $data->military_ratings)
		);
	}
}
