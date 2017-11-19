<?php

namespace App\Traits;

use Illuminate\Support\Facades\Session;

trait CartTrait
{
    private $jsonOptions = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;

    /**
     * @return bool
     */
    protected function removeCart()
    {
        Session::remove('cart');

        return true;
    }

    protected function removeItem(array $item)
    {
        if(!$item['url']) {
            return false;
        }

        $basket = $this->getCart();

        if(empty($basket)) {
            return false;
        }

        foreach($basket as $k => $v) {
            if($v['url'] == $item['url']) {
                unset($basket[$k]);
                break;
            }
        }

        $this->removeCart();

        if(!empty($basket)) {
            array_map(function ($item) {
                Session::push('cart', $item);
            }, $basket);
        }

        return true;
    }

    protected function updateItem(array $item)
    {
        if(!$item['url']) {
            return false;
        }

        $basket = $this->getCart();

        foreach($basket as &$v) {
            if($v['url'] == $item['url']) {
                $v['quantity'] = $item['quantity'];
            }
        }

        unset($v);

        $this->removeCart();

        array_map(function($item) {
            Session::push('cart', $item);
        }, $basket);

        return true;
    }

    /**
     * @param array $item
     * @return bool
     */
    protected function addItem(array $item)
    {
        unset($item['_token']);

        Session::push('cart', $item);

        return true;
    }

    /**
     * @return mixed
     */
    protected function getCart()
    {
        return Session::get('cart');
    }
}