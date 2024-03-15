<?php

namespace DTApi\Repository;

use DTApi\Models\Job;
use DB;
use Log;

class JobRepository extends BaseRepository
{
    /**
     * @var DTApi\Models\Job
     */
    protected $model;

    function __construct(Job $model)
    {
        parent::__construct($model);
    }
}