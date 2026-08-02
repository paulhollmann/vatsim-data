<?php

namespace VatsimData\StandData;

enum FlightStatus: string
{
    case AT_GATE = 'at_gate';
    case TAXI_FOR_DEPARTURE = 'taxi_for_departure';
    case TAKING_OFF = 'taking_off';
    case DEPARTING = 'departing';
    case ARRIVING = 'arriving';
    case TAXI_TO_GATE = 'taxi_to_gate';
    case ARRIVED_AT_GATE = 'arrived_at_gate';
    case UNKNOWN = 'unknown';
}
