<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionAnswer extends Model
{
    protected $fillable = ['session_question_id', 'user_id', 'content'];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
