<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'title', 'style', 'color', 'size', 'conf', 'user_price', 'shop_price', 'order_id', 'quantity', 'image', 'url',
    ];

    public function getPrice()
    {
        return env('SITE_CURRENCY').$this->shop_price;
    }

    public function getDimensions() {
        if($dimension = $this->shipping_dimension) {
        } elseif(!$dimension = $this->product_dimension) {
            return false;
        }

        $dimension = trim(str_replace('inches', '', $dimension));

        return explode('x', $dimension);
    }

    public function getWeightAttribute()
    {
        if(!empty($this->shipping_weight)) {
            $weight = explode(" ", $this->shipping_weight)[0];
        } elseif(!empty($this->product_weight)) {
            $weight = explode(" ", $this->product_weight)[0];
        } else {
            $weight = 1;
        }

        return $weight;
    }
}
