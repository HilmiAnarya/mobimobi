<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes;

    // Eksplisit Definisi Tabel & PK
    protected $table = 'customers';
    protected $primaryKey = 'id';

    // White-listing kolom yang boleh diisi (Security Best Practice)
    protected $fillable = [
        'nik',
        'name',
        'phone',
        'address',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }
}
