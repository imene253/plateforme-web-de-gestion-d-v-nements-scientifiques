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
}