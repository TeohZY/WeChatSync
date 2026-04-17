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
            $response = $this->curl($url, json_encode($array), true);
            $mediaList = isset($response->item) && is_array($response->item) ? $response->item : [];

            if (empty($mediaList)) {
                throw new Exception('未找到可用的微信图片素材，请先在公众号素材库上传一张图片，或为文章设置 thumb 封面');
            }

            $matching = null;
            foreach ($mediaList as $media) {
                if (isset($media->name) && $media->name == "typecho.jpg") {
                    $matching = $media;
                    break;
                }
            }

            if ($matching != null) {
                $media_id = $matching->media_id;
            } else {
                if (empty($mediaList[0]->media_id)) {
                    throw new Exception('微信图片素材缺少 media_id，无法作为默认封面使用');
                }
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

        list($imagePath, $tempFiles) = $this->prepareCoverImage($imagePath);

        if (file_exists($imagePath)) {
            $sizedPath = $this->ensureThumbSize($imagePath);
            if ($sizedPath !== $imagePath) {
                $imagePath = $sizedPath;
                $tempFiles[] = $imagePath;
            }
        }

        $accessToken = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$accessToken ."&type=image";
        $res = $this->curl($url, '', true, $imagePath);

        if (empty($res->media_id)) {
            throw new Exception('微信封面上传失败：未返回 media_id');
        }

        // 删除临时文件
        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }

        return $res->media_id;
    }

    /**
     * 将封面统一准备为本地 JPEG 文件，避免微信素材接口对格式提示敏感
     * @param string $imagePath
     * @return array{0:string,1:array}
     */
    private function prepareCoverImage($imagePath)
    {
        $tempFiles = array();

        if (strpos($imagePath, 'http') === 0) {
            $content = @file_get_contents($imagePath);
            if ($content === false) {
                throw new Exception('无法下载封面图片：' . $imagePath);
            }

            $localFile = $this->cacheDir . '/upload_' . uniqid() . '.img';
            file_put_contents($localFile, $content);
            $imagePath = $localFile;
            $tempFiles[] = $imagePath;
        }

        $preparedPath = $this->convertToJpeg($imagePath);
        if ($preparedPath !== $imagePath) {
            $imagePath = $preparedPath;
            $tempFiles[] = $imagePath;
        }

        return array($imagePath, $tempFiles);
    }

    /**
     * 将封面图片标准化为 JPG，降低微信素材接口的格式兼容问题
     * @param string $imagePath 图片路径
     * @return string 转换后的图片路径
     */
    private function convertToJpeg($imagePath)
    {
        if (!file_exists($imagePath)) {
            throw new Exception('封面文件不存在：' . $imagePath);
        }

        $realType = $this->detectImageType($imagePath);

        if ($realType === 'jpeg') {
            return $imagePath;
        }

        $srcImage = $this->createImageResource($imagePath, $realType);
        if ($srcImage === null) {
            throw new Exception('不支持的封面图片格式：' . $realType);
        }

        $width = imagesx($srcImage);
        $height = imagesy($srcImage);
        $dstImage = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($dstImage, 255, 255, 255);
        imagefill($dstImage, 0, 0, $white);
        imagecopy($dstImage, $srcImage, 0, 0, 0, 0, $width, $height);

        $outputPath = $this->cacheDir . '/converted_' . uniqid() . '.jpg';
        $result = imagejpeg($dstImage, $outputPath, 90);

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        if (!$result || !file_exists($outputPath)) {
            throw new Exception('封面图片转换 JPG 失败');
        }

        return $outputPath;
    }

    /**
     * 通过魔术字节检测图片真实类型
     * @param string $imagePath 图片路径
     * @return string 图片类型 (jpeg, png, webp, gif, bmp, unknown)
     */
    private function detectImageType($imagePath)
    {
        $handle = @fopen($imagePath, 'rb');
        if ($handle === false) {
            return 'unknown';
        }

        $data = fread($handle, 12);
        fclose($handle);

        if (substr($data, 0, 2) === "\xFF\xD8") {
            return 'jpeg';
        }
        if (substr($data, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
            return 'png';
        }
        if (substr($data, 0, 4) === "RIFF" && substr($data, 8, 4) === "WEBP") {
            return 'webp';
        }
        if (substr($data, 0, 3) === "GIF") {
            return 'gif';
        }
        if (substr($data, 0, 2) === "BM") {
            return 'bmp';
        }

        return 'unknown';
    }

    /**
     * 按真实格式创建图片资源
     * @param string $imagePath
     * @param string $realType
     * @return resource|GdImage|null
     */
    private function createImageResource($imagePath, $realType)
    {
        switch ($realType) {
            case 'jpeg':
                return @imagecreatefromjpeg($imagePath);
            case 'png':
                return @imagecreatefrompng($imagePath);
            case 'gif':
                return @imagecreatefromgif($imagePath);
            case 'bmp':
                return function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($imagePath) : null;
            case 'webp':
                if (!function_exists('imagecreatefromwebp')) {
                    throw new Exception('服务器未启用 GD WebP 支持，无法处理 WebP 封面。请安装支持 WebP 的 GD 扩展，或将封面改为 JPG/PNG');
                }
                return @imagecreatefromwebp($imagePath);
            default:
                return null;
        }
    }

    /**
     * 根据实际文件内容推断上传 MIME
     * 微信素材接口对 MIME 和真实格式不一致比较敏感
     * @param string $imagePath 图片路径
     * @return string
     */
    private function detectImageMime($imagePath)
    {
        $type = $this->detectImageType($imagePath);

        switch ($type) {
            case 'jpeg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'gif':
                return 'image/gif';
            case 'bmp':
                return 'image/bmp';
            case 'webp':
                return 'image/webp';
            default:
                return 'application/octet-stream';
        }
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
                if (!function_exists('imagecreatefromwebp')) {
                    throw new Exception('服务器未启用 GD WebP 支持，无法压缩 WebP 封面。请安装支持 WebP 的 GD 扩展，或将封面改为 JPG/PNG');
                }
                $srcImage = @imagecreatefromwebp($imagePath);
                break;
            case IMAGETYPE_GIF:
                $srcImage = @imagecreatefromgif($imagePath);
                break;
            case IMAGETYPE_BMP:
                $srcImage = function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($imagePath) : null;
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
        $accessToken = $this->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.$accessToken;

        preg_match_all('/<img[^>]+>/i', $html, $matches);
        $images = $matches[0];

        foreach ($images as $image) {
            if (!preg_match('/src="([^"]+)"/i', $image, $srcMatches) || empty($srcMatches[1])) {
                continue;
            }

            $src = $srcMatches[1];
            list($uploadPath, $tempFiles) = $this->prepareArticleImage($src);

            try {
                $res = $this->curl($url, '', true, $uploadPath);
            } catch (Exception $e) {
                foreach ($tempFiles as $tempFile) {
                    if (file_exists($tempFile)) {
                        @unlink($tempFile);
                    }
                }
                throw new Exception('微信正文图片上传失败：' . $src . '，' . $e->getMessage());
            }

            if (empty($res->url)) {
                foreach ($tempFiles as $tempFile) {
                    if (file_exists($tempFile)) {
                        @unlink($tempFile);
                    }
                }
                throw new Exception('微信正文图片上传失败：' . $src . '，未返回图片地址');
            }

            $wxImageUrl = $res->url;
            $html = str_replace($src, $wxImageUrl, $html);

            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            }
        }

        $html = $this->formatHtmlWithDOM($html);
        return $html;
    }

    /**
     * 将正文图片准备为微信更稳定接受的本地文件
     * @param string $imagePath
     * @return array{0:string,1:array}
     */
    private function prepareArticleImage($imagePath)
    {
        $tempFiles = array();
        $localPath = $imagePath;

        if (preg_match('/^https?:\/\//', $imagePath)) {
            $content = @file_get_contents($imagePath);
            if ($content === false) {
                throw new Exception('无法下载图片');
            }

            $localPath = $this->cacheDir . '/article_' . uniqid() . '.img';
            file_put_contents($localPath, $content);
            $tempFiles[] = $localPath;
        }

        if (!file_exists($localPath)) {
            throw new Exception('图片文件不存在');
        }

        $realType = $this->detectImageType($localPath);
        if ($realType !== 'jpeg' && $realType !== 'png') {
            $convertedPath = $this->convertToJpeg($localPath);
            if ($convertedPath !== $localPath) {
                $localPath = $convertedPath;
                $tempFiles[] = $localPath;
            }
        }

        return array($localPath, $tempFiles);
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
                if (!file_exists($imagePath)) {
                    throw new Exception('文件不存在: ' . $imagePath);
                }

                // 使用 CURLFile 上传本地文件
                $mimeType = $this->detectImageMime($imagePath);
                $curlFile = new CURLFile($imagePath, $mimeType, basename($imagePath));
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
