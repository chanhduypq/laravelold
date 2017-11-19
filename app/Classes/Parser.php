<?php

namespace App\Classes;

use Faker\Provider\UserAgent;
use Illuminate\Support\Facades\Log;
use Sunra\PhpSimple\HtmlDomParser;

class Parser
{
    private $proxy;
    private $attempts;

    /**
     * Parser constructor.
     */
    public function __construct()
    {
        $this->proxy = env('PARSER_PROXY');
        $this->attempts = env('PARSER_ATTEMPTS', 0);

        $handlerStack = \GuzzleHttp\HandlerStack::create();
        $handlerStack->push(\GuzzleHttp\Middleware::retry(function($retry, $request, $response, $reason) {

            if(is_object($response) && $response->getStatusCode() == '200') {
                return false;
            }
            elseif(is_object($response) && $response->getStatusCode() == '404') {
                Log::info('Parse product error with code: '.$response->getStatusCode());
                return false;
            }

            Log::info('Parse product attempt #'.($retry+1));

            return $retry < $this->attempts;
        }));

        $this->client = new \GuzzleHttp\Client(['handler' => $handlerStack, 'verify' => false]);
    }

    /**
     *
     */
    public function parseAmazonFromAPI($url)
    {
        $par = explode('/',$url);
        $find_dp = false;
        $asin = '';
        foreach ($par as $item) {
            if($find_dp) {
                $asin = $item;
                break;
            }
            if($item == 'dp') {
                $find_dp = true;
            }
        }
        $api = 'http://35.189.145.94/fetch_mws/?asin='.$asin;
        $response = $this->client->request('GET', $api, [
            'headers' => [
                'Content-Type' => 'text/html',
                'User-Agent' => UserAgent::userAgent(),
            ],
            'proxy' => ($this->proxy ? $this->proxy : null)
        ]);
        $result = $response->getBody();
        $data = (array) json_decode($result);

        if(strpos($data['price'],"$") === false) {
            $data['price'] = '$'.$data['price'];
        }
        $parsePrice = explode('.',$data['price']);
        if(count($parsePrice) == 1) {
            $data['price'] = $data['price'].'.00';
        }
        if(count($parsePrice) == 2 && strlen($parsePrice[1]) == 1) {
            $data['price'] = $data['price'].'0';
        }
        $data['clearPrice'] = str_replace('$', '', $data['price']);
        $data['colors'] = isset($data['colors']) ? (array) $data['colors'] : array();
        $data['confs'] = isset($data['confs']) ? (array) $data['confs'] : array();
        $data['details'] = isset($data['details']) ? (array) $data['details'] : array(
            'shipping_dimension' => '',
            'product_dimension' => '',
            'shipping_weight' => '',
            'product_weight' => ''
        );
        $data['sizes'] = isset($data['sizes']) ? (array) $data['sizes'] : array();
        $data['styles'] = isset($data['styles']) ? (array) $data['styles'] : array();

        return $data;
    }


    /**
     * @param $url
     * @return array|bool
     */
    public function parseAmazon($url)
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'Content-Type' => 'text/html',
                    'User-Agent' => UserAgent::userAgent(),
                ],
                'proxy' => ($this->proxy ? $this->proxy : null)
            ]);

            $text = (string) $response->getBody();
            $html = HtmlDomParser::str_get_html($text);

            if($html) {

                $confs = [];
                $colors = [];
                $sizes = [];
                $styles = [];
                $details = [
                    'shipping_dimension' => '',
                    'product_dimension' => '',
                    'shipping_weight' => '',
                    'product_weight' => '',
                ];

                if(!$img = $html->find('#imgTagWrapperId', 0)) {
                    return false;
                }

                $img = $img->find('img', 0)->src;
                $title = trim($html->find('#productTitle', 0)->plaintext);
                $price = trim($html->find('#priceblock_ourprice', 0)->plaintext);

                if(strpos($price, '-')) {
                    $price = trim(explode('-', $price)[0]);
                }

                $clearPrice = str_replace('$', '', $price);

                if($colorsHtml = $html->find('#variation_color_name', 0)) {
                    $colorsHtml = $colorsHtml->find('img');
                }

                if($confsHtml = $html->find('#variation_configuration', 0)) {
                    $confsHtml = $confsHtml->find('.a-size-base');
                }

                $detailKey = '';

                if($detailsHtml = $html->find('#productDetails_detailBullets_sections1', 0)) {
                    $detailsHtml = $detailsHtml->find('tr');
                    $detailKey = 'td';
                }
                elseif($detailsHtml = $html->find('#productDetails_techSpec_section_2', 0)) {
                    $detailsHtml = $detailsHtml->find('tr');
                    $detailKey = 'td';
                }
                elseif($detailsHtml = $html->find('#detailBullets_feature_div', 0)) {
                    $detailsHtml = $detailsHtml->find('li');
                    $detailKey = 'span';
                }

                if($sizesHtml = $html->find('#variation_size_name', 0)) {
                    $sizesHtml = $sizesHtml->find('.a-size-base');
                }

                if($stylesHtml = $html->find('#variation_style_name', 0)) {
                    $stylesHtml = $stylesHtml->find('li');
                }

                if($stylesHtml) {
                    foreach ($stylesHtml as $v) {
                        $styles[] = $v->plaintext;
                    }
                }

                if($sizesHtml) {
                    foreach ($sizesHtml as $v) {
                        $sizes[] = $v->plaintext;
                    }
                }

                if($colorsHtml) {
                    foreach ($colorsHtml as $v) {
                        $colors[$v->alt] = $v->src;
                    }
                }

                if($confsHtml) {
                    foreach ($confsHtml as $v) {
                        $confs[] = $v->plaintext;
                    }
                }

                if($detailsHtml) {
                    foreach ($detailsHtml as $v) {
                        if(stripos($v->plaintext, 'Shipping Dimensions') !== FALSE) {
                            $details['shipping_dimension'] = $this->getDetailData($v, $detailKey);
                        }

                        if(stripos($v->plaintext, 'Product Dimensions') !== FALSE) {
                            $details['product_dimension'] = $this->getDetailData($v, $detailKey);
                        }

                        if(stripos($v->plaintext, 'Shipping Weight') !== FALSE) {
                            $details['shipping_weight'] = $this->getDetailData($v, $detailKey);
                        }

                        if(stripos($v->plaintext, 'Product Weight') !== FALSE) {
                            $details['product_weight'] = $this->getDetailData($v, $detailKey);
                        }
                    }
                }

                return [
                    'image' => $img,
                    'title' => $title,
                    'price' => $price,
                    'clearPrice' => $clearPrice,
                    'colors' => $colors,
                    'confs' => $confs,
                    'details' => $details,
                    'sizes' => $sizes,
                    'styles' => $styles,
                ];
            }

            return false;
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::error('Parse product error: ' . $e->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            Log::error('Parse product error: ' . $e->getMessage());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            Log::error('Parse product error: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Parse product error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * @param $html
     * @param $detailKey
     * @return string
     */
    public function getDetailData($html, $detailKey)
    {
        if($detailKey == 'span') {
            $str = $html->find('span', 0)->find('span', 1)->plaintext;
        }
        elseif($detailKey == 'td') {
            $str = $html->find('td', 0)->plaintext;
        }

        if($pos = strpos($str, "(")) {
            $str = substr($str, 0, ($pos - 1));
        }

        return trim($str);
    }
}