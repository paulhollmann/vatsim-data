<?php

namespace VatsimData\TransceiverClasses;

class TransceiverOwner
{
	public string $callsign;
	/** @var Transceivers[] */
	public array $transceivers;

	/**
	 * @param Transceivers[] $transceivers
	 */
	public function __construct(string $callsign, array $transceivers)
	{
		$this->callsign = $callsign;
		$this->transceivers = $transceivers;
	}

	public static function fromJson(\stdClass $data): self
	{
		return new self(
			$data->callsign,
			array_map(static function($data) {
				return Transceivers::fromJson($data);
			}, $data->transceivers)
		);
	}
}
