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
    // each
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
        if ($this->relevance_score && $this->scientific_quality_score && $this->originality_score) {
            return $this->relevance_score + $this->scientific_quality_score + $this->originality_score;
        }
        return null;
    }

    // Calculate average score
    public function getAverageScoreAttribute()
    {
        if ($this->total_score) {
            return round($this->total_score / 3, 2);
        }
        return null;
    }
}