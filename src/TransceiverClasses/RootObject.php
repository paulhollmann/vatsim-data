<?php

namespace VatsimData\TransceiverClasses;

class RootObject
{
    /**
     * @return TransceiverOwner[]
     */
    public static function fromJson(array $data): array
    {
        return array_map(static function ($single) {
            return RootObject::fromJson($single);
        }, $data);
    }
}
