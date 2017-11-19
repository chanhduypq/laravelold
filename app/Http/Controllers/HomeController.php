<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Domain;
use App\Models\Product;

class HomeController extends Controller {

    public function index() {

        set_time_limit(0);
        
        $domain=new Domain();
        $domain->initOrReset();
        
        $product=new Product('123456','qoo10.sg','dresses',1000);
        echo '<pre>';
        var_dump($product->getPriceHistory(Product::SORT_BY_DATE, Product::ASC));
        echo '</pre>';
        $product->addPriceHistory(2000, '2017-11-18');
        echo '<pre>';
        var_dump($product->getPriceHistory(Product::SORT_BY_DATE, Product::ASC));
        echo '</pre>';
        
        $user=new User('chanhduypq@gmail.com','0812');
        $user->followProduct('123456', 'qoo10.sg');

        $user->setNotificationForProduct('123456', 'qoo10.sg', 500);
        $has=$user->hasNotificationForProduct('123456', 'qoo10.sg', $price);
        var_dump($has);
        var_dump($price);
        
        $user->setNotificationForProduct('123456', 'qoo10.sg', 2000);
        $has=$user->hasNotificationForProduct('123456', 'qoo10.sg', $price);
        var_dump($has);
        var_dump($price);
        
        $product->setCurrentPrice(3000);
        $has=$user->hasNotificationForProduct('123456', 'qoo10.sg', $price);
        var_dump($has);
        var_dump($price);
        
    }

}
