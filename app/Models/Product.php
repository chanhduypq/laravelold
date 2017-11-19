<?php

namespace App\Models;

use Illuminate\Support\Facades\Redis;
use App\Models\Category;
use App\Models\Domain;

class Product {

    const DESC = 'DESC';
    const ASC = 'ASC';
    const SORT_BY_PRICE = 'price';
    const SORT_BY_DATE = 'date';

    public $prices;
    private $attributes = array();
    private $domain = null;
    private $id = null;
    private $category = null;
    private $currentPrice = 0;

    public function __construct($id, $domain, $category = '', $currentPrice = 0) {
        $this->attributes = self::getDetail($id, $domain);
        $this->domain = $domain;
        $this->id = $id;
        if (count($this->attributes) == 0) {
            self::add($domain, $category, $id, $currentPrice);
            $this->attributes = self::getDetail($id, $domain);
        }
        $this->category = $this->attributes['category'];
        $this->currentPrice = $this->attributes['current_price'];
    }

    public function setCurrentPrice($currentPrice) {
        $this->attributes['current_price'] = $currentPrice;
        $this->currentPrice = $currentPrice;

        $redis = Redis::connection();
        $redis->del("product_" . $this->domain . "_" . $this->id);
        $redis->hmset("product_" . $this->domain . "_" . $this->id, $this->attributes);

        return $this;
    }

    public function setCategory($category) {
        $this->attributes['category'] = $category;
        $this->category = $category;

        $redis = Redis::connection();
        $redis->del("product_" . $this->domain . "_" . $this->id);
        $redis->hmset("product_" . $this->domain . "_" . $this->id, $this->attributes);

        return $this;
    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function getDomain() {
        return $this->domain;
    }

    public function getCode() {
        return $this->id;
    }

    public function getCategory() {
        return $this->category;
    }

    public function getCurrentPrice() {
        return $this->currentPrice;
    }

    public static function getCount() {
        $redis = Redis::connection();
        return $redis->llen("products");
    }

    public static function getAllProducts() {
        $products = array();
        $redis = Redis::connection();
        $domain_codes = $redis->lrange("products", 0, -1);
        foreach ($domain_codes as $domain_code) {
            $products[] = $redis->hgetall("product_$domain_code");
        }
        return $products;
    }

    public static function getProducts($offset, $limit) {
        if (!ctype_digit($offset) || !ctype_digit($limit)) {
            return array();
        }
        $start = $offset;
        $end = $start + $limit - 1;
        $products = array();
        $redis = Redis::connection();
        $domain_codes = $redis->lrange("products", $start, $end);
        foreach ($domain_codes as $domain_code) {
            $products[] = $redis->hgetall("product_$domain_code");
        }
        return $products;
    }

    public static function getCountByDomain($domain) {
        $redis = Redis::connection();
        return $redis->llen("product_$domain");
    }

    public static function getAllProductsByDomain($domain) {
        $products = array();
        $redis = Redis::connection();
        $domain_codes = $redis->lrange("product_$domain", 0, -1);
        foreach ($domain_codes as $domain_code) {
            $products[] = $redis->hgetall("product_$domain_code");
        }
        return $products;
    }

    public static function getProductsByDomain($domain, $offset, $limit) {
        if (!ctype_digit($offset) || !ctype_digit($limit)) {
            return array();
        }
        $start = $offset;
        $end = $start + $limit - 1;
        $products = array();
        $redis = Redis::connection();
        $domain_codes = $redis->lrange("product_$domain", $start, $end);
        foreach ($domain_codes as $domain_code) {
            $products[] = $redis->hgetall("product_$domain_code");
        }
        return $products;
    }

    public static function getCountByDomainAndCategory($domain, $category) {
        $redis = Redis::connection();
        return $redis->llen("product_$domain" . "_" . $category);
    }

    public static function getAllProductsByDomainAndCategory($domain, $category) {
        $products = array();
        $redis = Redis::connection();
        $domain_codes = $redis->lrange("product_$domain" . "_" . $category, 0, -1);
        foreach ($domain_codes as $domain_code) {
            $products[] = $redis->hgetall("product_$domain_code");
        }
        return $products;
    }

    public static function getProductsByDomainAndCategory($domain, $category, $offset, $limit) {
        if (!ctype_digit($offset) || !ctype_digit($limit)) {
            return array();
        }
        $start = $offset;
        $end = $start + $limit - 1;
        $products = array();
        $redis = Redis::connection();
        $domain_codes = $redis->lrange("product_$domain" . "_" . $category, $start, $end);
        foreach ($domain_codes as $domain_code) {
            $products[] = $redis->hgetall("product_$domain_code");
        }
        return $products;
    }

    public static function exists($id, $domain) {
        $redis = Redis::connection();
        return $redis->exists("product_" . $domain . "_" . $id);
    }

    public static function getDetail($id, $domain) {
        $redis = Redis::connection();
        return $redis->hgetall("product_" . $domain . "_" . $id);
    }

    public static function update($domain, $id, $data, $deleteOldData = false) {
        if (!is_array($data) || count($data) == 0) {
            return;
        }
        unset($data['current_price']);
        unset($data['price_history']);
        if (!is_array($data) || count($data) == 0) {
            return;
        }
        $oldData = self::getDetail($id, $domain);

        if ($deleteOldData == true) {
            foreach ($oldData as $key => $value) {
                if (!in_array($key, array('domain', 'id', 'category', 'current_price', 'price_history'))) {
                    unset($oldData["$key"]);
                }
            }
        }
        foreach ($data as $key => $value) {
            if (!in_array($key, array('domain', 'id', 'category'))) {
                $oldData["$key"] = $value;
            }
        }

        $newData = $oldData;


        $redis = Redis::connection();
        $data['domain'] = $domain;
        $data['id'] = $id;

        $redis->del("product_" . $domain . "_" . $id);
        $redis->hmset("product_" . $domain . "_" . $id, $newData);
    }

    public static function updateOption($domain, $id, $optionName, $valueOption) {
        if (in_array($optionName, array('domain', 'id', 'category', 'current_price', 'price_history'))) {
            return;
        }

        $redis = Redis::connection();
        $redis->hset("product_" . $domain . "_" . $id, $optionName, $valueOption);
    }

    public static function addOption($domain, $id, $optionName, $valueOption) {
        if (in_array($optionName, array('domain', 'id', 'category', 'current_price', 'price_history'))) {
            return;
        }

        $redis = Redis::connection();
        $redis->hset("product_" . $domain . "_" . $id, $optionName, $valueOption);
    }

    public static function updateCurrentPrice($domain, $id, $currentPrice) {
        $redis = Redis::connection();
        $redis->hset("product_" . $domain . "_" . $id, "current_price", $currentPrice);
    }

    public static function add($domain, $category, $id, $currentPrice, $data = array()) {
        if (self::exists($id, $domain)) {
            return new Product($id, $domain);
        }

        $redis = Redis::connection();

        $data['domain'] = $domain;
        $data['category'] = $category;
        $data['id'] = $id;
        unset($data['current_price']);
        $data['current_price'] = $currentPrice;
        unset($data['price_history']);
        $data['price_history'] = json_encode(array(strtotime(date('Y-m-d')) => $currentPrice));

        $redis->rpush("products", $domain . "_" . $id);
        $redis->rpush("product_" . $domain, $domain . "_" . $id);
        $redis->rpush("product_" . $domain . "_" . $category, $domain . "_" . $id);
        $redis->hmset("product_" . $domain . "_" . $id, $data);

        return new Product($id, $domain);
    }

    public static function removeAtProductTable($domain, $id) {
        $redis = Redis::connection();

        $products = $redis->lrange("products", 0, -1);
        if (in_array($domain . "_" . $id, $products)) {
            $redis->del("products");
            foreach ($products as $value) {
                if ($value != $domain . "_" . $id) {
                    $redis->rpush("products", $value);
                }
            }
        }
    }

    public static function removeAtProductDomainTable($domain, $id) {
        $redis = Redis::connection();

        $products = $redis->lrange("product_$domain", 0, -1);
        if (in_array($domain . "_" . $id, $products)) {
            $redis->del("product_$domain");
            foreach ($products as $value) {
                if ($value != $domain . "_" . $id) {
                    $redis->rpush("product_$domain", $value);
                }
            }
        }
    }

    public static function removeAtProductDomainCategoryTable($domain, $category, $id) {
        $redis = Redis::connection();

        $products = $redis->lrange("product_$domain" . "_" . $category, 0, -1);
        if (in_array($domain . "_" . $id, $products)) {
            $redis->del("product_$domain" . "_" . $category);
            foreach ($products as $value) {
                if ($value != $domain . "_" . $id) {
                    $redis->rpush("product_$domain" . "_" . $category, $value);
                }
            }
        }
    }

    public static function remove($domain, $id) {
        $redis = Redis::connection();

        $data = $redis->hgetall("product_" . $domain . "_" . $id);
        $category = $data['category'];
        $redis->del("product_" . $domain . "_" . $id);

        self::removeAtProductTable($domain, $id);
        self::removeAtProductDomainTable($domain, $id);
        self::removeAtProductDomainCategoryTable($domain, $category, $id);
    }

    public static function isDate($date) {
        if (!is_string($date) || trim($date) == '' || strpos($date, "-") === FALSE || count(explode("-", $date)) != 3) {
            return FALSE;
        }
        list($year, $month, $day) = explode("-", $date);
        if (!ctype_digit($year) || !ctype_digit($month) || !ctype_digit($day) || !checkdate(intval($month), intval($day), intval($year))) {
            return FALSE;
        }
        return TRUE;
    }

    public function addPriceHistory($price, $date = null) {
        if ($date == null) {
            $date = date('Y-m-d');
        } else {
            if (!self::isDate($date)) {
                return;
            }
        }

        $time = strtotime($date);

        $priceHistory = json_decode($this->attributes['price_history'], true);
        $priceHistory["$time"] = $price;

        $this->attributes['price_history'] = json_encode($priceHistory);
        if ($date == date('Y-m-d')) {
            $this->currentPrice = $price;
        }

        self::updateOption($this->domain, $this->id, 'price_history', json_encode($priceHistory));
        return $this;
    }

    public function getPriceHistory($sortBy = 'date', $sort = 'ASC') {
        $priceHistory = json_decode($this->attributes['price_history'], true);
        if (!is_array($priceHistory) || count($priceHistory) == 0) {
            return array();
        }

        $newPriceHistory = array();

        $dates = array_keys($priceHistory);
        $prices = array_values($priceHistory);
        if ($sortBy == self::SORT_BY_DATE) {
            if ($sort == self::ASC) {
                $dates = array_sort($dates);
            } else if ($sort == self::DESC) {
                $dates = array_sort($dates);
                $dates = array_reverse($dates);
            }
            foreach ($dates as $date) {
                $newPriceHistory[date("Y-m-d", $date)] = $priceHistory[$date];
            }
        } else if ($sortBy == self::SORT_BY_PRICE) {
            if ($sort == self::ASC) {
                $prices = array_sort($prices);
            } else if ($sort == self::DESC) {
                $prices = array_sort($prices);
                $prices = array_reverse($prices);
            }
            foreach ($prices as $price) {
                foreach ($priceHistory as $key => $value) {
                    if ($value == $price) {
                        $newPriceHistory[date("Y-m-d", $key)] = $value;
                        break;
                    }
                }
            }
        } else {
            return array();
        }



        return $newPriceHistory;
    }

    public function getMaxPrice() {
        $prices = $this->getPriceHistory(self::SORT_BY_PRICE, self::DESC);
        if (is_array($prices) && count($prices) > 0) {
            foreach ($prices as $key => $value) {
                return array($key => $value);
            }
        }
        return array();
    }

    public function getMinPrice() {
        $prices = $this->getPriceHistory(self::SORT_BY_PRICE, self::ASC);
        if (is_array($prices) && count($prices) > 0) {
            foreach ($prices as $key => $value) {
                return array($key => $value);
            }
        }
        return array();
    }

}
