<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionQuestion extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'content',
        'upvotes_count'
    ];

    // Requirement 1: Link to the user who asked
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Link to the session (table: sesions)
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    // Requirement 2: Link to votes
    public function votes()
    {
        return $this->hasMany(QuestionVote::class);
    }

    public function answers()
    {
        return $this->hasMany(SessionAnswer::class);
    }
}
