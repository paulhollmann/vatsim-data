<?php

namespace VatsimData\TransceiverClasses;

class Transceiver
{
    public int $id;

    public int $frequency;

    public readonly string $frequencyString;

    public float $latDeg;

    public float|int $lonDeg;

    public float|int $heightMslM;

    public float|int $heightAglM;

    public function __construct(
        int $id,
        int $frequency,
        float $latDeg,
        float|int $lonDeg,
        float|int $heightMslM,
        float|int $heightAglM
    ) {
        $this->id = $id;
        $this->frequency = $frequency;
        $this->latDeg = $latDeg;
        $this->lonDeg = $lonDeg;
        $this->heightMslM = $heightMslM;
        $this->heightAglM = $heightAglM;
        $this->frequencyString = substr(strval($frequency), 0, 3).'.'.substr(strval($frequency), 3, 3);
    }

    public static function fromJson(\stdClass $data): self
    {
        return new self(
            $data->id,
            $data->frequency,
            $data->latDeg,
            $data->lonDeg,
            $data->heightMslM,
            $data->heightAglM
        );
    }
}
