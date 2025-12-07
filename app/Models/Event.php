<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'location',
        'theme',
        'type',
        'contact_email',
        'contact_phone',
        'status',
        'scientific_committee',
        'invited_speakers',
        'banner_image',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'scientific_committee' => 'array',
        'invited_speakers' => 'array',
    ];

    // Relations
    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function registrations()
{
    return $this->hasMany(Registration::class);
}

public function getRegistrationCountAttribute()
{
    return $this->registrations()->count();
}

public function getPaidRegistrationsCountAttribute()
{
    return $this->registrations()->paid()->count();
}

// ADDED RELATIONSHIPS FOR MODULE 6
public function sesions()
{
    // Relationship to the sessions table
    return $this->hasMany(Session::class, 'event_id');
}

// 💡 REQUIRED CHANGE: Relationship to the new ProgramPeriod table
public function programPeriods()
{
    // Relationship to the new ProgramPeriod table
    return $this->hasMany(ProgramPeriod::class, 'event_id');
}
}