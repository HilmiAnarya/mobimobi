<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';
    protected $primaryKey = 'id';

    protected $fillable = [
        'order_number',
        'customer_id',
        'car_id',
        'date',
        'payment_type', // cash, credit
        'total_price',
        'credit_package_id',
        'down_payment',
        'interest_amount',
        'monthly_installment',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'total_price' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'monthly_installment' => 'decimal:2',
    ];

    // Relasi dengan foreign key eksplisit juga biar aman
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id');
    }

    public function creditPackage(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class, 'credit_package_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class, 'order_id');
    }

    // Auto Generate Order Number
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->order_number)) {
                $model->order_number = 'INV/' . date('Y') . '/' . str_pad(static::count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
