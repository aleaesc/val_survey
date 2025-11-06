<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use HasFactory, SoftDeletes;

    // Allow these fields to be filled from our API
    protected $fillable = [
        'service',
        'name',
        'age',
        'barangay',
        'email',
        'phone',
        'comments',
    ];

    // A Survey has MANY ratings
    public function ratings()
    {
        return $this->hasMany(SurveyRating::class);
    }
}