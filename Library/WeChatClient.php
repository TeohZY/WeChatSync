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
        error_log("WeChatSync: uploadCover 开始");
        $db = Typecho_Db::get();
        $imagePath = $db->fetchRow($db->select('str_value')
            ->from('table.fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', 'thumb'));

        $imagePath = $imagePath['str_value'];

        error_log("WeChatSync: 封面图片路径 = " . $imagePath);

        if (empty($imagePath)) {
            return $this->getMediaId();
        }

        // 原始路径，用于判断是否需要删除临时文件
        $originalPath = $imagePath;
        $tempFiles = array();

        // 检查并转换不兼容的图片格式
        $convertedPath = $this->convertToJpeg($imagePath);
        if ($convertedPath !== $imagePath) {
            $imagePath = $convertedPath;
            $tempFiles[] = $imagePath;
        }

        error_log("WeChatSync: 封面图处理后路径 = " . $imagePath);

        $accessToken = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$accessToken ."&type=image";
        error_log("WeChatSync: 开始上传封面图到微信 (type=image)...");
        $res = $this->curl($url, '', true, $imagePath);
        error_log("WeChatSync: 封面图上传成功, media_id = " . $res->media_id);

        // 删除临时文件
        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
                error_log("WeChatSync: 删除临时文件 $tempFile");
            }
        }

        return $res->media_id;
    }

    /**
     * 将不兼容的图片格式转换为 JPG
     * @param string $imagePath 图片路径
     * @return string 转换后的图片路径
     */
    private function convertToJpeg($imagePath)
    {
        $isNetworkImage = false;
        $tempFiles = array();

        // 如果是网络图片，先下载到本地
        if (strpos($imagePath, 'http') === 0) {
            $content = @file_get_contents($imagePath);
            if ($content === false) {
                return $imagePath;
            }
            $isNetworkImage = true;
            // 临时文件，扩展名无所谓，上传时会检测真实类型
            $tempFile = $this->cacheDir . '/temp_' . uniqid() . '.tmp';
            file_put_contents($tempFile, $content);
            $imagePath = $tempFile;
            $tempFiles[] = $tempFile;
        }

        // 检查文件是否存在
        if (!file_exists($imagePath)) {
            return $imagePath;
        }

        // 获取图片类型
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return $imagePath;
        }

        $imageType = $imageInfo[2];

        // JPG/PNG/GIF/BMP 直接返回
        if (in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_BMP])) {
            return $imagePath;
        }

        // 只转换 WEBP
        $srcImage = null;
        switch ($imageType) {
            case IMAGETYPE_WEBP:
                $srcImage = @imagecreatefromwebp($imagePath);
                break;
        }

        if ($srcImage !== false && $srcImage !== null) {
            $outputPath = $this->cacheDir . '/converted_' . uniqid() . '.jpg';
            $result = imagejpeg($srcImage, $outputPath, 90);
            imagedestroy($srcImage);

            if ($result && file_exists($outputPath)) {
                // 删除下载的临时文件
                foreach ($tempFiles as $tf) {
                    if (file_exists($tf)) @unlink($tf);
                }
                return $outputPath;
            }
        }

        return $imagePath;
    }

    /**
     * 确保缩略图大小不超过 64KB
     * @param string $imagePath 图片路径
     * @return string 处理后的图片路径
     */
    private function ensureThumbSize($imagePath)
    {
        $maxSize = 64 * 1024; // 64KB
        $fileSize = filesize($imagePath);

        if ($fileSize <= $maxSize) {
            return $imagePath;
        }

        error_log("WeChatSync: 图片大小 " . $fileSize . " 超过 64KB，需要压缩");

        // 加载图片
        $imageInfo = @getimagesize($imagePath);
        if ($imageInfo === false) {
            return $imagePath;
        }

        $imageType = $imageInfo[2];
        $srcImage = null;

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $srcImage = @imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $srcImage = @imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_WEBP:
                $srcImage = @imagecreatefromwebp($imagePath);
                break;
            case IMAGETYPE_GIF:
                $srcImage = @imagecreatefromgif($imagePath);
                break;
        }

        if ($srcImage === null) {
            return $imagePath;
        }

        // 获取原始尺寸
        $origWidth = imagesx($srcImage);
        $origHeight = imagesy($srcImage);

        // 逐步减小尺寸和压缩质量，直到文件大小合适
        $quality = 90;
        $scale = 1.0;

        do {
            $newWidth = (int)($origWidth * $scale);
            $newHeight = (int)($origHeight * $scale);

            $dstImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            // 保存到临时文件检查大小
            $tempFile = $this->cacheDir . '/thumb_check_' . uniqid() . '.jpg';
            imagejpeg($dstImage, $tempFile, $quality);
            imagedestroy($dstImage);

            $checkSize = filesize($tempFile);
            error_log("WeChatSync: 缩略图压缩尝试 - 质量=$quality, 尺寸=${newWidth}x${newHeight}, 大小=$checkSize");

            if ($checkSize > $maxSize) {
                @unlink($tempFile);
                // 减小质量或尺寸
                if ($quality > 30) {
                    $quality -= 15;
                } else {
                    $scale *= 0.8;
                }
            } else {
                // 大小合适，替换原文件
                $outputFile = $this->cacheDir . '/thumb_final_' . uniqid() . '.jpg';
                rename($tempFile, $outputFile);
                imagedestroy($srcImage);
                return $outputFile;
            }
        } while ($scale >= 0.2 && $quality >= 10);

        imagedestroy($srcImage);
        return $imagePath;
    }

    /**
     * 上传图片到素材库
     */
    public function uploadImageToWeChat($html)
    {
        error_log("WeChatSync: uploadImageToWeChat 开始");
        $accessToken = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.$accessToken;

        preg_match_all('/<img[^>]+>/i', $html, $matches);
        $images = $matches[0];
        error_log("WeChatSync: 发现 " . count($images) . " 张图片");

        foreach ($images as $index => $image) {
            preg_match('/src="([^"]+)"/i', $image, $srcMatches);
            $src = $srcMatches[1];
            error_log("WeChatSync: 处理第 " . $index . " 张图片: " . $src);

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
                // 检查文件
                if (!file_exists($imagePath)) {
                    throw new Exception('文件不存在: ' . $imagePath);
                }
                $fileSize = filesize($imagePath);
                error_log("WeChatSync curl: 文件=$imagePath, 大小=$fileSize");

                // 使用 CURLFile 上传
                $curlFile = new CURLFile($imagePath, 'image/jpeg', basename($imagePath));
                $postData = array('media' => $curlFile);
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
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
