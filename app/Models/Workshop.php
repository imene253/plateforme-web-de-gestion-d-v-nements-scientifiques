<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\WorkshopRegistration;

class Workshop extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'description',
        'animator_id',
        'start_time',
        'end_time',
        'room',
        'max_participants',
        'current_participants',
    ];

    public function registrations()
    {
        return $this->belongsToMany(User::class, 'workshop_registrations')
                    ->using(WorkshopRegistration::class) 
                    ->withPivot('status', 'reason_for_interest'); // Only these fields are needed
    }
    
    // Define the relationship to the workshop registrations
    public function registrationSubmissions()
    {
        return $this->hasMany(WorkshopRegistration::class);
    }

    // Define the relationship to the animator
    public function animator()
    {
        return $this->belongsTo(User::class, 'animator_id');
    }

    // Define the relationship to the workshop materials
    public function materials()
    {
        return $this->hasMany(WorkshopMaterial::class);
    }

    // Define the relationship to the event
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
