<?php

namespace VatsimData\TransceiverClasses;

class TransceiverOwner
{
    public string $callsign;

    /** @var Transceiver[] */
    public array $transceivers;

    /**
     * @param  Transceiver[]  $transceivers
     */
    public function __construct(string $callsign, array $transceivers)
    {
        $this->callsign = $callsign;
        $this->transceivers = $transceivers;
    }

    public static function fromJson(object $data): self
    {
        return new self(
            $data->callsign,
            array_map(static function ($data) {
                return Transceiver::fromJson($data);
            }, $data->transceivers)
        );
    }
}
