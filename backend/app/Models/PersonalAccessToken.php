<?php

namespace HiEvents\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumAccessToken;

class PersonalAccessToken extends SanctumAccessToken
{
    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->mergeFillable(['account_id']);
    }
}