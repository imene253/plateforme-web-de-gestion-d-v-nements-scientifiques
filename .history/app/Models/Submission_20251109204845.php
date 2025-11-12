<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'author_id',
        'title',
        'authors',
        'abstract',
        'keywords',
        'type',
        'pdf_file',
        'status',
        'admin_notes',
        'submission_deadline',
    ];

    protected $casts = [
        'authors' => 'array',
        'keywords' => 'array',
        'submission_deadline' => 'date',
    ];

    // Relations
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}