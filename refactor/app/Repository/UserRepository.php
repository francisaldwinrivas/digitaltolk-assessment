<?php

namespace DTApi\Repository;

use DTApi\Models\User;
use DB;
use Log;

class UserRepository extends BaseRepository
{
    /**
     * @var DTApi\Models\User
     */
    protected $model;

    function __construct(User $model)
    {
        parent::__construct($model);
    }
}