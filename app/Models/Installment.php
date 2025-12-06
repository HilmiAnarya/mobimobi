<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    use SoftDeletes;

    protected $table = 'installments';
    protected $primaryKey = 'id';

    protected $fillable = [
        'order_id',
        'installment_number',
        'due_date',
        'amount_due',
        'amount_paid',
        'paid_at',
        'status',
        'penalty_fee',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'penalty_fee' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
