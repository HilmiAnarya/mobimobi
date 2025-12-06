<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Car extends Model
{
    use SoftDeletes;

    protected $table = 'cars';
    protected $primaryKey = 'id';

    protected $fillable = [
        'chassis_number',
        'engine_number',
        'model',
        'color',
        'price',
        'status', // ready, booked, sold
        'image_path',
    ];

    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'car_id');
    }
}
