<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Registration extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id', 
        'registration_type',
        'payment_status',
        'amount',
        'additional_info',
        'registered_at',
        'payment_date',
        'payment_method',
        'notes',
        'is_confirmed',
        'confirmation_code',
    ];

    protected $casts = [
        'additional_info' => 'array',
        'registered_at' => 'datetime',
        'payment_date' => 'datetime',
        'is_confirmed' => 'boolean',
        'amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        
        // Generate confirmation code automatically
        static::creating(function ($registration) {
            if (empty($registration->confirmation_code)) {
                $registration->confirmation_code = 'REG-' . strtoupper(Str::random(8));
            }
            if (empty($registration->registered_at)) {
                $registration->registered_at = now();
            }
        });
    }

    // Relations
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('is_confirmed', true);
    }

    // Helper methods
    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }

    public function isConfirmed()
    {
        return $this->is_confirmed;
    }

    public function markAsPaid($paymentMethod = null)
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_date' => now(),
            'payment_method' => $paymentMethod,
            'is_confirmed' => true,
        ]);
    }

    public function generateBadgeData()
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'institution' => $this->user->institution,
            'event' => $this->event->title,
            'type' => $this->registration_type,
            'confirmation_code' => $this->confirmation_code,
        ];
    }
}