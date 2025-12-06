<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditPackage extends Model
{
    use SoftDeletes;

    protected $table = 'credit_packages';
    protected $primaryKey = 'id';

    protected $fillable = [
        'code',
        'tenor',
        'interest_rate',
        'down_payment_rule',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'credit_package_id');
    }
}
