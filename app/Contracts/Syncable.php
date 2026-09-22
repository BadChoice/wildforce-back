<?php

namespace App\Contracts;

interface Syncable
{
    /**
     * @return list<string>
     */
    public static function syncAttributes(): array;

    /**
     * @return array<string, mixed>
     */
    public function syncPayload(): array;
}
