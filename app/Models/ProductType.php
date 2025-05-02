<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductType extends Model
{
    protected $fillable =[

        "title", "slug", "is_active"
    ];

    public function product()
    {
    	return $this->hasMany('App\Models\Product');
    }
}
