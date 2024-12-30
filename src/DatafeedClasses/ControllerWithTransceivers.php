<?php

namespace VatsimData\DatafeedClasses;

use VatsimData\TransceiverClasses\Transceiver;
use VatsimData\TransceiverData;

class ControllerWithTransceivers extends Controller
{
    /**
     * @var Transceiver[]
     */
    public array $transceivers;

    public function __construct(Controller $controller)
    {
        parent::__construct($controller->cid,
            $controller->name,
            $controller->callsign,
            $controller->frequency,
            $controller->facility,
            $controller->rating,
            $controller->server,
            $controller->visual_range,
            $controller->text_atis ?? null,
            $controller->last_updated,
            $controller->logon_time);
        $this->transceivers = TransceiverData::Owner($this->callsign)?->transceivers ?? [];
    }
}
