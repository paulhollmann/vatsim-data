<?php

namespace VatsimData\DatafeedClasses;

class Atis
{
	public int $cid;
	public string $name;
	public string $callsign;
	public string $frequency;
	public int $facility;
	public int $rating;
	public string $server;
	public int $visual_range;
	public ?string $atis_code;
	/** @var string[] */
	public array $text_atis;
	public string $last_updated;
	public string $logon_time;

	/**
	 * @param string[] $text_atis
	 */
	public function __construct(
		int $cid,
		string $name,
		string $callsign,
		string $frequency,
		int $facility,
		int $rating,
		string $server,
		int $visual_range,
		?string $atis_code,
		array $text_atis,
		string $last_updated,
		string $logon_time
	) {
		$this->cid = $cid;
		$this->name = $name;
		$this->callsign = $callsign;
		$this->frequency = $frequency;
		$this->facility = $facility;
		$this->rating = $rating;
		$this->server = $server;
		$this->visual_range = $visual_range;
		$this->atis_code = $atis_code;
		$this->text_atis = $text_atis;
		$this->last_updated = $last_updated;
		$this->logon_time = $logon_time;
	}

	public static function fromJson(\stdClass $data): self
	{
		return new self(
			$data->cid,
			$data->name,
			$data->callsign,
			$data->frequency,
			$data->facility,
			$data->rating,
			$data->server,
			$data->visual_range,
			$data->atis_code ?? null,
			$data->text_atis ?? [],
			$data->last_updated,
			$data->logon_time
		);
	}
}
