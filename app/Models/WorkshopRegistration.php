<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkshopRegistration extends Pivot
{
    protected $table = 'workshop_registrations';
    
    // We are using user_id and workshop_id as the primary key pair
    protected $primaryKey = ['user_id', 'workshop_id'];
    public $incrementing = false;

    protected function setKeysForSaveQuery($query)
    {
        return $query->where('user_id', $this->getAttribute('user_id'))
                     ->where('workshop_id', $this->getAttribute('workshop_id'));
    }
    
    protected $fillable = [
        'user_id',
        'workshop_id',
        'status', // 'pending', 'accepted', 'declined'
        'reason_for_interest',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }
}
