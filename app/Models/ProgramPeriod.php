<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        "event_id",
        "title",
        "description",
        "room",
        "start_time",
        "end_time",
    ];

    public function event() {
        return $this->belongsTo(Event::class);
    }
}
