<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyRating extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'survey_id',
        'question_id',
        'value',
    ];

    // A Rating belongs to ONE survey
    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }
}
