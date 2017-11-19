<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Auth;
use Illuminate\Http\Request;
use Mail;

class Order extends Model
{
    CONST ORDER_UNPAID = 0;
    CONST ORDER_NEW = 1;
    CONST ORDER_COMPLETE = 4;
    CONST ORDER_PROCESS = 3;
    CONST ORDER_CANCEL = 2;

    public $dates = ['delivery_date', 'user_delivery_date'];
    public $fillable = ['status', 'delivery_date', 'shipping_weight', 'delivery_name', 'delivery_price', 'paypal_status', 'paypal_id'];

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function products()
    {
        return $this->hasMany('App\Product');
    }

    public function scopebyId($query, $value)
    {
        if($value) {
            return $query->where('id', $value);
        }
    }

    public function scopebyStatus($query, $value)
    {
        if($value) {
            return $query->where('status', $value);
        }
    }

    public function create(array $data, array $cart)
    {
        $this->delivery_date = Carbon::createFromTimestamp(strtotime($data['delivery_date']))->format('Y-m-d');
        $this->delivery_price = $data['delivery_price'];
        $this->delivery_name = $data['delivery_name'];
        $this->user_id = Auth::user()->id;
        $this->status = self::ORDER_UNPAID;
        $this->save();

        foreach($cart as $item) {
            $product = new Product();
            $product->order_id = $this->id;
            $product->title = $item['title'];
            $product->shop_price = $item['shop_price'];
            $product->quantity = $item['quantity'];
            $product->url = $item['url'];
            $product->color = $item['color'];
            $product->style = $item['style'];
            $product->conf = $item['conf'];
            $product->size = $item['size'];
            $product->image = $item['image'];
//            $product->shipping_weight = $item['shipping_weight'];
//            $product->shipping_dimension = $item['shipping_dimension'];
//            $product->product_weight = $item['product_weight'];
//            $product->product_dimension = $item['product_dimension'];
            $product->save();
        }

        return true;
    }

    public function setStatusAttribute($value)
    {
        if(!empty($this->attributes['status']) && $value != $this->attributes['status']) {
            $this->attributes['status'] = $value;

            Mail::send('mail.notify', ['msg' => 'Status changed to: '.$this->showStatus()], function($m) {
                $m->to($this->user->email)->subject('Status changed');
            });
        } else {
            $this->attributes['status'] = $value;
        }
    }

    public function getTotalWeightAttribute()
    {
        $weight = array_sum(array_map(function($item) {
            return !empty($item['shipping_weight']) ? explode(" ", $item['shipping_weight'])[0] : 0;
        }, $this->getRelation('products')->toArray()));

        return $weight ? $weight : 1;
    }

    /**
     * @return mixed
     */
    public function getTotalPrice()
    {
        return $this->with('products')->getRelation('products')->sum('shop_price');
    }

    /**
     * @return mixed
     */
    public function getTotalPriceAttribute()
    {
        return $this->getRelation('products')->sum('shop_price');
    }

    public function getTotalPriceWithCurrency()
    {
        return env('SITE_CURRENCY').$this->totalPrice;
    }

    public function showStatus()
    {
        switch ($this->status) {
            case self::ORDER_UNPAID:
                $status = 'Unpaid';
                break;
            case self::ORDER_NEW:
                $status = 'New';
                break;
            case self::ORDER_CANCEL:
                $status = 'Cancel';
                break;
            case self::ORDER_COMPLETE:
                $status = 'Complete';
                break;
            case self::ORDER_PROCESS:
                $status = 'Process';
                break;
            default:
                $status = 'Undefined status';
                break;
        }

        return $status;
    }
}
