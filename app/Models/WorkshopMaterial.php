<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'title',
        'description',
        'type',
        'file_path',
        'external_url',
        'is_public',
    ];  

    // Define the relationship to the workshop
    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }
}
