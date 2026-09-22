<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait UsesUuidPrimaryKey
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
