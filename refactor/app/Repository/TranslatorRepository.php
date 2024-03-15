<?php

namespace DTApi\Repository;

use DTApi\Models\Translator;
use DB;
use Log;

class TranslatorRepository extends BaseRepository
{
    /**
     * @var DTApi\Models\Job
     */
    protected $model;

    function __construct(Translator $model)
    {
        parent::__construct($model);
    }
}