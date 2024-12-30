<?php

namespace VatsimData\DatafeedClasses;

class RootObject
{
    public General $general;

    /** @var Pilot[] */
    public array $pilots;

    /** @var Controller[] */
    public array $controllers;

    /** @var Atis[] */
    public array $atis;

    /** @var Server[] */
    public array $servers;

    /** @var Prefile[] */
    public array $prefiles;

    /** @var Facility[] */
    public array $facilities;

    /** @var AtcRating[] */
    public array $ratings;

    /** @var PilotRating[] */
    public array $pilot_ratings;

    /** @var MilitaryRating[] */
    public array $military_ratings;

    /**
     * @param  Pilot[]  $pilots
     * @param  Controller[]  $controllers
     * @param  Atis[]  $atis
     * @param  Server[]  $servers
     * @param  Prefile[]  $prefiles
     * @param  Facility[]  $facilities
     * @param  AtcRating[]  $ratings
     * @param  PilotRating[]  $pilot_ratings
     * @param  MilitaryRating[]  $military_ratings
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

    public static function fromJson(object $data): self
    {
        return new self(
            General::fromJson($data->general),
            array_map(static function ($data) {
                return Pilot::fromJson($data);
            }, $data->pilots),
            array_map(static function ($data) {
                return Controller::fromJson($data);
            }, $data->controllers),
            array_map(static function ($data) {
                return Atis::fromJson($data);
            }, $data->atis),
            array_map(static function ($data) {
                return Server::fromJson($data);
            }, $data->servers),
            array_map(static function ($data) {
                return Prefile::fromJson($data);
            }, $data->prefiles),
            array_map(static function ($data) {
                return Facility::fromJson($data);
            }, $data->facilities),
            array_map(static function ($data) {
                return AtcRating::fromJson($data);
            }, $data->ratings),
            array_map(static function ($data) {
                return PilotRating::fromJson($data);
            }, $data->pilot_ratings),
            array_map(static function ($data) {
                return MilitaryRating::fromJson($data);
            }, $data->military_ratings)
        );
    }
}
