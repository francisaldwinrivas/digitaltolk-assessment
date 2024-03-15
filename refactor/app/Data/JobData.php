<?php

namespace DTApi\Data;

use DTApi\Models\Job;


class JobData
{
    public $attributes;

    public function __construct(Job $job)
    {
        $attributes['job_id'] = $job->id;
        $attributes['from_language_id'] = $job->from_language_id;
        $attributes['immediate'] = $job->immediate;
        $attributes['duration'] = $job->duration;
        $attributes['status'] = $job->status;
        $attributes['gender'] = $job->gender;
        $attributes['certified'] = $job->certified;
        $attributes['due'] = $job->due;
        $attributes['job_type'] = $job->job_type;
        $attributes['customer_phone_type'] = $job->customer_phone_type;
        $attributes['customer_physical_type'] = $job->customer_physical_type;
        $attributes['customer_town'] = $job->town;
        $attributes['customer_type'] = $job->user->userMeta->customer_type;

        $dueDate = explode(" ", $job->due);
        $attributes['due_date'] = $dueDate[0];
        $attributes['due_time'] = $dueDate[1];

        $attributes['job_for'] = array();
        if ($job->gender != null) {
            if ($job->gender == 'male') {
                $attributes['job_for'][] = 'Man';
            } else if ($job->gender == 'female') {
                $attributes['job_for'][] = 'Kvinna';
            }
        }
        if ($job->certified != null) {
            if ($job->certified == 'both') {
                $attributes['job_for'][] = 'Godkänd tolk';
                $attributes['job_for'][] = 'Auktoriserad';
            } else if ($job->certified == 'yes') {
                $attributes['job_for'][] = 'Auktoriserad';
            } else if ($job->certified == 'n_health') {
                $attributes['job_for'][] = 'Sjukvårdstolk';
            } else if ($job->certified == 'law' || $job->certified == 'n_law') {
                $attributes['job_for'][] = 'Rätttstolk';
            } else {
                $attributes['job_for'][] = $job->certified;
            }
        }
    }
}
