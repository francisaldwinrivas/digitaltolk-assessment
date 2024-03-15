<?php

namespace DTApi\Repository;

use DTApi\Models\TranslatorJobRel;

class TranslatorJobRelRepository extends BaseRepository
{
    /**
     * @var DTApi\Models\TranslatorJobRel
     */
    protected $model;

    function __construct(TranslatorJobRel $TranslatorJobRel)
    {
        parent::__construct($model);
    }
}