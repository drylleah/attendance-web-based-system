<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentReport extends Model
{
    protected $table = 'incident_reports';

    public $timestamps = false;

    protected $fillable = [
        'reported_by',
        'reporter_name',
        'subject_id_no',
        'subject_name',
        'incident_date',
        'incident_type',
        'description',
        'status',
        'remarks',
    ];

    protected $casts = [
        'incident_date' => 'date',
    ];
}
