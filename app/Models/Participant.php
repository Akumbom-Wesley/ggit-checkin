<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    /** Order here = order on screen, in reports and in charts. */
    public const CATEGORIES = [
        'registered' => 'Registered',
        'speaker'    => 'Speaker',
        'partner'    => 'Partner / Sponsor',
        'invited'    => 'Invited guest',
        'walkin'     => 'Walk-in',
    ];

    protected $fillable = [
        'full_name', 'email', 'phone', 'category', 'partner_name',
        'notes', 'paid', 'payment_proof', 'amount', 'paid_at',
        'registered_at', 'checked_in_at',
    ];

    protected $casts = [
        'paid'          => 'boolean',
        'amount'        => 'integer',
        'paid_at'       => 'datetime',
        'registered_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Keep phone_key and email normalised on every save.
        static::saving(function (Participant $p) {
            $p->phone_key = static::phoneKey($p->phone);
            $p->email = $p->email ? mb_strtolower(trim($p->email)) : null;
        });
    }

    /** Last 9 digits, so "+237 677 138 370" and "677138370" match. */
    public static function phoneKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits === '' ? null : substr($digits, -9);
    }

    public static function money(int $amount): string
    {
        return number_format($amount, 0, '.', ',').' '.config('checkin.currency');
    }

    /** Compact shape sent to the check-in screen. */
    public function toClient(): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->full_name,
            'email'   => $this->email,
            'phone'   => $this->phone,
            'cat'     => $this->category,
            'partner' => $this->partner_name,
            'notes'   => $this->notes,
            'amount'  => (int) $this->amount,
            'in'      => $this->checked_in_at?->toIso8601String(),
        ];
    }
}