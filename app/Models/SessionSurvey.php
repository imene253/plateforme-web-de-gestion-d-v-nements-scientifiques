<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionSurvey extends Model
{
    protected $fillable = [
        'session_id', 'user_id', 'quality', 'relevance', 'organization'
    ];
}
