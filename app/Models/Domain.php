<?php

namespace App\Models;

use Illuminate\Support\Facades\Redis;

class Domain {

    public static function getCount() {
        $redis = Redis::connection();
        return count($redis->smembers("domains"));
    }

    public static function getAllDomains() {
        $redis = Redis::connection();
        return $redis->smembers("domains");
    }

    public static function getAllCategoriesByDomain($domain) {
        if (!is_string($domain) || trim($domain) == '') {
            return array();
        }
        $redis = Redis::connection();
        return $redis->smembers($domain);
    }

    public static function exists($domain) {
        if (!is_string($domain) || trim($domain) == '') {
            return false;
        }
        $redis = Redis::connection();
        return $redis->sismember("domains", $domain);
    }

    public static function add($domain) {
        $redis = Redis::connection();

        if (is_array($domain) && count($domain) > 0) {
            foreach ($domain as $key => $value) {
                if (is_array($value)) {
                    unset($domain["$key"]);
                }
            }
            if (count($domain) > 0) {
                $redis->sadd("domains", $domain);
            }
        } else if (is_string($domain) && trim($domain) != '') {
            $redis->sadd("domains", $domain);
        }
    }

    public static function remove($domain) {
        $redis = Redis::connection();
        if (is_array($domain) && count($domain) > 0) {
            foreach ($domain as $key => $value) {
                if (is_array($value)) {
                    unset($domain["$key"]);
                }
            }
            if (count($domain) > 0) {
                $redis->srem("domains", $domain);
            }
        } else if (is_string($domain) && trim($domain) != '') {
            $redis->srem("domains", array($domain));
        }
    }

    public static function addCategory($domain, $category) {
        if (!is_string($domain) || trim($domain) == '' || !is_string($category) || trim($category) == '') {
            return;
        }
        $redis = Redis::connection();
        $redis->sadd($domain, $category);
    }

    public static function removeCategory($domain, $category) {
        if (!is_string($domain) || trim($domain) == '' || !is_string($category) || trim($category) == '') {
            return;
        }
        $redis = Redis::connection();
        $redis->srem($domain, $category);
    }

    public static function initOrReset() {
        $redis = Redis::connection();
        $redis->del("domains");
        $redis->sadd("domains", array('qoo10.sg', 'lazada.sg', 'shopee.sg'));
    }

}
