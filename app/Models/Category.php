<?php

namespace App\Models;

use Illuminate\Support\Facades\Redis;

class Category {

    public function getCount() {
        $redis = Redis::connection();
        if ($redis->exists("categories")) {
            return $redis->llen("categories");
        } else {
            return 0;
        }
    }

    public function getAllCategories() {
        $redis = Redis::connection();
        if ($redis->exists("categories")) {
            return $redis->lrange("categories", 0, -1);
        }
        return array();
    }

    public function existsCategory($category) {
        $redis = Redis::connection();
        if ($redis->exists("categories")) {
            return in_array($category, $redis->lrange("categories", 0, -1));
        }
        return false;
    }

    public function add($category) {
        if ($this->existsCategory($category)) {
            return;
        }
        $redis = Redis::connection();
        $redis->rpush("categories", $category);
    }

    

    public function initOrReset() {
        $redis = Redis::connection();
        if ($redis->exists("categories")) {
            $redis->del("categories");
        }
        $redis->rpush("categories", 'dresses');
        $redis->rpush("categories", 'jeans');
        $redis->rpush("categories", 'shorts');
    }

}
