<?php

namespace DTApi\Repository;

use DTApi\Models\UserLanguage;
use DB;
use Log;

class UserLanguageRepository extends BaseRepository
{
    /**
     * @var DTApi\Models\UserLanguage
     */
    protected $model;

    function __construct(UserLanguage $model)
    {
        parent::__construct($model);
    }
}