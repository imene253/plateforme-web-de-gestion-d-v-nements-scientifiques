<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'evaluator_id',
        'relevance_score',
        'scientific_quality_score',
        'originality_score',
        'comments',
        'recommendation',
        'is_completed',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
    ];

    // Relations
    // each evaluation belongs to one submission
    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    // Calculate total score
    public function getTotalScoreAttribute()
    {
        return $this->relevance_score + $this->scientific_quality_score + $this->originality_score;
    }
}

// Create an event first
$event = \App\Models\Event::create([
    'organizer_id' => 1, // Make sure this user exists
    'title' => 'Test Conference',
    'description' => 'Test conference description',
    'start_date' => '2024-12-01',
    'end_date' => '2024-12-03',
    'location' => 'Test Location',
    'theme' => 'Test Theme',
    'type' => 'conference',
    'contact_email' => 'test@example.com',
    'status' => 'upcoming'
]);

