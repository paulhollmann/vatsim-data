<?php

namespace VatsimDatafeed\DatafeedClasses;;

class Controllers
{
	public int $cid;
	public string $name;
	public string $callsign;
	public string $frequency;
	public int $facility;
	public int $rating;
	public string $server;
	public int $visualRange;
	/** @var string[]|null */
	public ?array $textAtis;
	public string $lastUpdated;
	public string $logonTime;

	public static function fromJson(\stdClass $data): self
	{
		$instance = new self();
		$instance->cid = $data->cid;
		$instance->name = $data->name;
		$instance->callsign = $data->callsign;
		$instance->frequency = $data->frequency;
		$instance->facility = $data->facility;
		$instance->rating = $data->rating;
		$instance->server = $data->server;
		$instance->visualRange = $data->visual_range;
		$instance->textAtis = $data->text_atis ?? null;
		$instance->lastUpdated = $data->last_updated;
		$instance->logonTime = $data->logon_time;
		return $instance;
	}
}
