<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $table = 'sesions';
    protected $fillable = [
        'event_id',
        'title',
        'room',
        'start_time',
        'end_time',
        'session_chair_id',
    ];

    // Relationship: A session belongs to one event
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
    
    // Relationship: A session is chaired by one user (session chair)
    public function chair()
    {
        return $this->belongsTo(User::class, 'session_chair_id');
    }

    // Relationship: A session has many submissions (papers assigned to it)
    public function submissions() 
    {
        return $this->hasMany(Submission::class, 'session_id');
    }
}
