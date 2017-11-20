<?php

namespace App\Models;

use Illuminate\Support\Facades\Redis;
use App\Models\Product;

class User {

    private $email = null;
    private $password = null;
    private $profile = array();
    private $products = array();

    public function __construct($email, $password) {
        $this->email = $email;
        $this->password = $password;
        if (!self::existsEmail($email)) {
            self::addUser($email, $password);
        } else {
            if (self::login($email, $password)) {
                $redis = Redis::connection();
                $this->profile = $redis->hgetall("user_account_$email");
                if ($redis->exists("user_products_$email")) {
                    $products = $redis->lrange("user_products_$email", 0, -1);
                    foreach ($products as $product) {
                        list($domain, $id) = explode('_', $product);
                        $this->products[] = array('domain' => $domain, 'id' => $id);
                    }
                }
            }
        }
    }

    public static function getCount() {
        $redis = Redis::connection();
        return $redis->llen("users");
    }

    public static function getAllUsers() {
        $users = array();
        $redis = Redis::connection();
        $emails = $redis->lrange("users", 0, -1);
        foreach ($emails as $email) {
            $users[] = $redis->hgetall("user_account_$email");
        }
        return $users;
    }

    public static function getUsers($offset, $limit) {
        if (!ctype_digit($offset) || !ctype_digit($limit)) {
            return array();
        }
        $start = $offset;
        $end = $start + $limit - 1;
        $users = array();
        $redis = Redis::connection();
        $emails = $redis->lrange("users", $start, $end);
        foreach ($emails as $email) {
            $users[] = $redis->hgetall("user_account_$email");
        }
        return $users;
    }

    public static function existsEmail($email) {
        $redis = Redis::connection();
        return $redis->exists("user_account_$email");
    }

    public static function removeUser($email) {
        $redis = Redis::connection();

        $emails = $redis->lrange("users", 0, -1);
        if (in_array($email, $emails)) {
            $redis->del("users");
            $redis->del("user_account_$email");
            $redis->del("user_products_$email");
            foreach ($emails as $value) {
                if ($value != $email) {
                    $redis->rpush("users", $value);
                }
            }
        }
    }

    public function remove() {
        $email = $this->email;
        $redis = Redis::connection();

        $emails = $redis->lrange("users", 0, -1);
        if (in_array($email, $emails)) {
            $redis->del("users");
            $redis->del("user_account_$email");
            $redis->del("user_products_$email");
            foreach ($emails as $value) {
                if ($value != $email) {
                    $redis->rpush("users", $value);
                }
            }
        }

        $this->email = $this->password = null;
        $this->products = $this->profile = array();
    }

    public function getProfile() {
        return $this->profile;
    }

    public static function login($email, $password) {
        $redis = Redis::connection();
        if ($redis->exists("user_account_$email")) {
            $user = $redis->hgetall("user_account_$email");
            if ($user['password'] == $password) {
                return true;
            }
        }
        return FALSE;
    }

    public static function addUser($email, $password, $data = array()) {
        $redis = Redis::connection();
        if (self::existsEmail($email)) {
            return null;
        }
        $redis->rpush("users", $email);
        $data['email'] = $email;
        $data['password'] = $password;
        $redis->hmset("user_account_$email", $data);
        return new User($email, $password);
    }

    public static function updateUserProfile($email, $password, $data) {
        if (!is_array($data) || count($data) == 0) {
            return;
        }
        $redis = Redis::connection();
        if (!self::login($email, $password)) {
            return;
        }
        //không cho phép đổi email và password
        $data['email'] = $email;
        $data['password'] = $password;

        $redis->hmset("user_account_$email", $data);
    }

    public function updateProfile($data) {
        if (!is_array($data) || count($data) == 0) {
            return;
        }
        $redis = Redis::connection();

        $email = $this->email;
        //không cho phép đổi email và password
        $data['email'] = $this->email;
        $data['password'] = $this->password;

        $redis->hmset("user_account_$email", $data);

        foreach ($data as $key => $value) {
            $this->profile[$key] = $value;
        }

        $this->profile['email'] = $this->email;
        $this->profile['password'] = $this->password;
    }

    public static function updateUserPassword($email, $password, $newPassword) {
        $redis = Redis::connection();
        if (!self::login($email, $password)) {
            return;
        }
        $data['password'] = $newPassword;

        $redis->hmset("user_account_$email", $data);
    }

    public function updatePassword($newPassword) {
        $redis = Redis::connection();

        $email = $this->email;
        $data['password'] = $newPassword;

        $redis->hmset("user_account_$email", $data);

        $this->profile['password'] = $newPassword;
    }

    public function getProductsFollowed() {
        return $this->products;
    }

    public function getProductsFollowedByDomain($domain) {
        $email = $this->email;
        $redis = Redis::connection();

        $productsReturn = array();
        $products = $redis->lrange("user_products_$domain" . "_" . $email, 0, -1);
        foreach ($products as $product) {
            list($domain, $id) = explode('_', $product);
            $productsReturn[] = array('domain' => $domain, 'id' => $id);
        }
        return $productsReturn;
    }

    public function alreadyFollowProduct($productId, $domain) {
        foreach ($this->products as $value) {
            if ($value['domain'] == $domain && $value['id'] == $productId) {
                return true;
            }
        }
        return FALSE;
    }

    public function followProduct($productId, $domain) {

        $email = $this->email;
        $redis = Redis::connection();

        if (!$this->alreadyFollowProduct($productId, $domain)) {
            $redis->rpush("user_products_$email", $domain . "_" . $productId);
            $redis->rpush("user_products_$domain" . "_" . $email, $domain . "_" . $productId);
            $this->products[] = array('domain' => $domain, 'id' => $productId);
        }
    }

    public function unfollowProduct($productId, $domain) {
        $email = $this->email;

        $redis = Redis::connection();

        if ($this->alreadyFollowProduct($productId, $domain)) {
            $productIds = $redis->lrange("user_products_$email", 0, -1);
            $redis->del("user_products_$email");
            foreach ($productIds as $value) {
                if ($value != $domain . "_" . $productId) {
                    $redis->rpush("user_products_$email", $value);
                }
            }

            $productIds = $redis->lrange("user_products_$domain" . "_" . $email, 0, -1);
            $redis->del("user_products_$domain" . "_" . $email);
            foreach ($productIds as $value) {
                if ($value != $domain . "_" . $productId) {
                    $redis->rpush("user_products_$domain" . "_" . $email, $value);
                }
            }

            $products = $this->products;
            foreach ($products as $key => $value) {
                if ($value['domain'] == $domain && $value['id'] == $productId) {
                    unset($products["$key"]);
                }
            }
            $this->products = $products;
        }
    }

    public function setNotificationForProduct($productId, $domain, $price) {
        if (!$this->alreadyFollowProduct($productId, $domain)) {
            $this->followProduct($productId, $domain);
        }
        $redis = Redis::connection();
        $email = $this->email;
        $redis->hset("user_notifications_$email", $domain . "_" . $productId, $price);
    }

    public function hasNotificationForProduct($productId, $domain, &$price = null) {
        $product = new Product($productId, $domain);

        $redis = Redis::connection();

        $email = $this->email;
        $data = $redis->hgetall("user_notifications_$email");
        if ($product->getCurrentPrice() <= $data[$domain . "_" . $productId]) {
            $price = $product->getCurrentPrice();
            return true;
        }
        return false;
    }

}
