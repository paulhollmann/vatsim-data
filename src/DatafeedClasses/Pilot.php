<?php

namespace VatsimData\DatafeedClasses;

class Pilot
{
    public int $cid;

    public string $name;

    public string $callsign;

    public string $server;

    public int $pilot_rating;

    public int $military_rating;

    public float $latitude;

    public float $longitude;

    public int $altitude;

    public int $groundspeed;

    public string $transponder;

    public int $heading;

    public float|int $qnh_i_hg;

    public int $qnh_mb;

    public ?FlightPlan $flight_plan;

    public string $logon_time;

    public string $last_updated;

    public function __construct(
        int $cid,
        string $name,
        string $callsign,
        string $server,
        int $pilot_rating,
        int $military_rating,
        float $latitude,
        float $longitude,
        int $altitude,
        int $groundspeed,
        string $transponder,
        int $heading,
        float|int $qnh_i_hg,
        int $qnh_mb,
        ?FlightPlan $flight_plan,
        string $logon_time,
        string $last_updated
    ) {
        $this->cid = $cid;
        $this->name = $name;
        $this->callsign = $callsign;
        $this->server = $server;
        $this->pilot_rating = $pilot_rating;
        $this->military_rating = $military_rating;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->altitude = $altitude;
        $this->groundspeed = $groundspeed;
        $this->transponder = $transponder;
        $this->heading = $heading;
        $this->qnh_i_hg = $qnh_i_hg;
        $this->qnh_mb = $qnh_mb;
        $this->flight_plan = $flight_plan;
        $this->logon_time = $logon_time;
        $this->last_updated = $last_updated;
    }

    public static function fromJson(object $data): self
    {
        return new self(
            $data->cid,
            $data->name,
            $data->callsign,
            $data->server,
            $data->pilot_rating,
            $data->military_rating,
            $data->latitude,
            $data->longitude,
            $data->altitude,
            $data->groundspeed,
            $data->transponder,
            $data->heading,
            $data->qnh_i_hg,
            $data->qnh_mb,
            ($data->flight_plan ?? null) !== null ? FlightPlan::fromJson($data->flight_plan) : null,
            $data->logon_time,
            $data->last_updated
        );
    }
}
