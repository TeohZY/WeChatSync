<?php
require_once dirname(__FILE__) . '/../parsedown/CustomParsedown.php';
require_once dirname(__FILE__) . '/../geshi/geshi.php';

class WeChatClient
{
    private $pluginPath;
    private $cacheDir;
    private $setting;

    public function __construct()
    {
        $this->pluginPath = dirname(__FILE__);
        $this->cacheDir = $this->pluginPath . '/../cache';
        $this->setting = $this->getSetting();
    }

    public function getSetting()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        return $options->plugin('WeChatSync');
    }

    /**
     * 获取微信access_token的方法
     */
    public function getAccessToken()
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        $file = $this->cacheDir . '/accessToken';
        $accessToken = file_exists($file) ? unserialize(file_get_contents($file)) : '';

        if (empty($accessToken) || $this->isAccessTokenExpired($accessToken)) {
            $newAccessToken = $this->requestAccessToken();
            file_put_contents($file, serialize($newAccessToken));
            return $newAccessToken->access_token;
        }

        return $accessToken->access_token;
    }

    /**
     * 判断access_token是否过期
     */
    public function isAccessTokenExpired($accessToken)
    {
        $time = time();
        return $time > $accessToken->expires_time;
    }

    /**
     * 请求获取新的微信access_token
     */
    public function requestAccessToken()
    {
        $appid = $this->setting->appid;
        $secret = $this->setting->secret;
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$secret;
        $newAccessToken = $this->curl($url);

        $newAccessToken->expires_time = time() + $newAccessToken->expires_in;

        return $newAccessToken;
    }

    /**
     * 获取media_id的方法
     */
    public function getMediaId()
    {
        $file = $this->pluginPath . '/../cache/mediaId';
        $mediaId = file_exists($file) ? file_get_contents($file) : '';

        if (empty($mediaId)) {
            $accessToken = $this->getAccessToken();
            $url = 'https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token='.$accessToken;
            $array = [
                "type"=>"image",
                "offset"=>0,
                "count"=>20
            ];
            $mediaList = $this->curl($url, json_encode($array), true)->item;

            $matching = null;
            foreach ($mediaList as $media) {
                if ($media->name == "typecho.jpg") {
                    $matching = $media;
                    break;
                }
            }

            if ($matching != null) {
                $media_id = $matching->media_id;
            } else {
                $media_id = $mediaList[0]->media_id;
            }

            file_put_contents($file, $media_id);
            return $media_id;
        }

        return $mediaId;
    }

    /**
     * 上传封面图片
     */
    public function uploadCover($cid)
    {
        $db = Typecho_Db::get();
        $imagePath = $db->fetchRow($db->select('str_value')
            ->from('table.fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', 'thumb'));

        $imagePath = $imagePath['str_value'];

        if (empty($imagePath)) {
            return $this->getMediaId();
        }

        $accessToken = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$accessToken ."&type=image";
        $res = $this->curl($url, '', true, $imagePath);
        return $res->media_id;
    }

    /**
     * 上传图片到素材库
     */
    public function uploadImageToWeChat($html)
    {
        $accessToken = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.$accessToken;

        preg_match_all('/<img[^>]+>/i', $html, $matches);
        $images = $matches[0];

        foreach ($images as $image) {
            preg_match('/src="([^"]+)"/i', $image, $srcMatches);
            $src = $srcMatches[1];

            $res = $this->curl($url, '', true, $src);
            $wxImageUrl = $res->url;
            $html = str_replace($src, $wxImageUrl, $html);
        }

        $html = $this->formatHtmlWithDOM($html);
        return $html;
    }

    /**
     * Curl 请求
     */
    public function curl($url, $jsonData = '', $ispost = false, $imagePath = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        if ($ispost) {
            curl_setopt($ch, CURLOPT_POST, true);

            if (empty($imagePath)) {
                $postData = $jsonData;
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                ));
            } else {
                $postData = array(
                    'media' => new CURLFile($imagePath)
                );
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            throw new Exception('CURL 错误：' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception('HTTP 错误：状态码 ' . $httpCode . '，响应：' . $response);
        }

        $responseData = json_decode($response);

        if ($responseData === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON 解析失败：' . json_last_error_msg() . '，响应：' . $response);
        }

        if (isset($responseData->errcode) && $responseData->errcode !== 0) {
            throw new Exception('微信 API 错误：' . $responseData->errmsg . ' (错误码：' . $responseData->errcode . ')');
        }

        return $responseData;
    }

    /**
     * HTML 格式化
     */
    private function formatHtmlWithDOM($html)
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//text()') as $textNode) {
            if ($textNode->parentNode->nodeName != 'code') {
                $textNode->nodeValue = trim(preg_replace('/\s+/', ' ', $textNode->nodeValue));
            }
        }

        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute('style')) {
                $element->setAttribute('style', preg_replace('/\s+/', ' ', $element->getAttribute('style')));
            }
            if ($element->hasAttribute('class')) {
                $element->setAttribute('class', preg_replace('/\s+/', ' ', $element->getAttribute('class')));
            }
        }

        return mb_convert_encoding($dom->saveHTML(), 'UTF-8', 'HTML-ENTITIES');
    }
}
