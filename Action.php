<?php
require dirname(__FILE__) .  '/parsedown/CustomParsedown.php';
require dirname(__FILE__) .  '/geshi/geshi.php';
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class WeChatSync_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        // 验证权限
        $this->widget('Widget_User')->pass('editor');

        // 获取选中的文章 ID
        $cids = $this->request->filter('int')->getArray('cid');

        if (!empty($cids) && $this->request->get('do') === 'custom_action') {
            $db = Typecho_Db::get();
            foreach ($cids as $cid) {
                try {
                    AsyncTask::render($cid); 
                } catch (Exception $e) {
                        $logMessage = sprintf(
                        "[%s] Exception: %s\nFile: %s (Line: %d)\nStack Trace:\n%s",
                        date('Y-m-d H:i:s'),
                        $e->getMessage(),       // 异常消息
                        $e->getFile(),          // 异常文件
                        $e->getLine(),          // 异常行号
                        $e->getTraceAsString()  // 调用堆栈
                    );
                    error_log("\n " . $logMessage . "\n",3,dirname(__FILE__) . "/cache/error.txt");
                }
            }

            // 设置成功提示并返回
            $this->widget('Widget_Notice')->set(_t('已对 %d 篇文章执行自定义操作', count($cids)), 'success');
            $this->response->goBack();
        } else {
            $this->widget('Widget_Notice')->set(_t('请选择文章'), 'error');
            $this->response->goBack();
        }
    }
}

class AsyncTask{
    // 获取文章对象
    public static function getPost($cid){
        return $post = Widget_Archive::alloc('type=post', 'cid=' . intval($cid));
    }

    public static function getSetting(){
       $options = Typecho_Widget::widget('Widget_Options');
       return $options->plugin('WeChatSync');
    }

    /* 获取微信access_token的方法 */
    public static function getAccessToken()
    {
        // 检查缓存中是否存在access_token
        $file = dirname(__FILE__) . '/cache/accessToken';
        $accessToken = file_exists($file) ? unserialize(file_get_contents($file)) : '';
        if (empty($accessToken) || self::isAccessTokenExpired($accessToken)) {
            // 如果缓存中不存在或已过期，重新请求获取access_token
            $newAccessToken = self::requestAccessToken();

            // 将新的access_token存储到缓存中
            file_put_contents($file, serialize($newAccessToken));

            return $newAccessToken->access_token;
        }

        return $accessToken->access_token;
    }

    /* 判断access_token是否过期的方法 */
    public static function isAccessTokenExpired($accessToken)
    {
        $time = time();
        if ($time > ($accessToken->expires_time)) {
            return true;
        }
        return false; // 假设access_token未过期
    }

    /* 请求获取新的微信access_token的方法 */
    public static function requestAccessToken()
    {
        $appid = self::getSetting()->appid;
        $secret = self::getSetting()->secret;
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$secret;
        $newAccessToken = self::curl($url);
        $newAccessToken->expires_time = time()+$newAccessToken->expires_in;

        return $newAccessToken;
    }

    /* 获取mediaid的方法 */
    public static function getMediaId(){
        $file = dirname(__FILE__) . '/cache/mediaId';
        $mediaId = file_exists($file) ? file_get_contents($file) : '';
        if (empty($mediaId)) {
            $accessToken = self::getAccessToken();
            $url = 'https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token='.$accessToken;
            // 获取图片素材列表中的图片作为图文消息的封面
            $array = [
                "type"=>"image",
                "offset"=>0,
                "count"=>20
            ];
            $mediaList = (self::curl($url,json_encode($array),true))->item;
            // return $mediaList;
            $matching = null;
            foreach ($mediaList as $media) {
                if ($media->name == "typecho.jpg") {
                    $matching = $entry;
                    break;
                }
            }
            if ($matching != null) {
                $media_id = $matching->media_id;
            } else {
                // 如果不存在匹配的条目，获取数组的第一个条目的media_id
                $media_id = $mediaList[0]->media_id;
            }
            file_put_contents($file, $media_id);
            return $media_id;
        }
        return $mediaId;

    }
    /* 上传封面图片 */
    public static function uploadCover($cid){
        $db = Typecho_Db::get();
        $imagePath = $db->fetchRow($db->select('str_value')
        ->from('table.fields')
        ->where('cid = ?', $cid)
        ->where('name = ?', 'thumb'));

        $imagePath = $imagePath['str_value'] ;

        if(empty($imagePath)){
         return self::getMediaId();
        }

        $accessToken = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token='.$accessToken ."&type=image";
        $res = self::curl($url,'',true,$imagePath);
        return $res->media_id;
    }
    /* 上传图片到素材库 */
    public static function uploadImageToWeChat($text){
        $html = self::renderMarkdown($text);
        $accessToken = self::getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token='.$accessToken;

        // 匹配所有的 <img> 标签
        preg_match_all('/<img[^>]+>/i', $html, $matches);
        $images = $matches[0];

        foreach ($images as $image) {
            // 提取 <img> 标签中的 src 属性值
            preg_match('/src="([^"]+)"/i', $image, $srcMatches);
            $src = $srcMatches[1];

            // 上传图片文件
            $res = self::curl($url,'',true,$src);

            // 获取上传后的图片 URL
            $wxImageUrl = $res->url;

            // 替换 HTML 中的图片标签中的 src 属性为上传后的图片 URL
            $html = str_replace($src, $wxImageUrl, $html);
        }

	$html = self::formatHtmlWithDOM($html);
        return $html;
    }

    /**
     * Curl 请求
     * @param $url
     */
    public static function curl($url,$jsonData = '',$ispost = false,$imagePath ='')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 忽略 SSL 证书验证
        if ($ispost) {
            // POST 请求
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
            // 设置请求体数据为 JSON 字符串
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

            // 设置请求头为 application/json

        }

        $response = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($response);
        if (!isset($responseData->errmsg)) {
            return $responseData;
        } else {
            // 请求失败，处理错误信息
            throw new Exception($responseData->errmsg);
        }
    }
    public static function codeHightLight($text){
        $text = preg_replace_callback(
            '/<pre><code(?: class="language-(.*?)")?>(.*?)<\/code><\/pre>/s',
            function ($matches) {
                // 如果没有匹配到语言类型，默认使用 'plaintext'
                $language = isset($matches[1]) && !empty($matches[1]) ? $matches[1] : 'plaintext';
                $code = $matches[2]; // 获取代码并转义 HTML 实体
                $geshi = new GeShi($code, $language);
                $highlighted_code = $geshi->parse_code();
                $highlighted_code = preg_replace('/^<pre[^>]*>|<\/pre>$/', '', $highlighted_code);
                // 将代码分割成行
                // 分割成行
                $lines = explode("\n", $highlighted_code);
                $line_num = null;
                $line_code = null;
                $line_numbered_code = '';
                foreach ($lines as $index => $line) {
                    $line_num .= '<li style="visibility: visible;"></li>';
                    $line_code .= '<code style="visibility: visible;">' . $line . '</code>';
                }
                return '
                <section class="code-snippet__fix code-snippet__js"
                style="margin-top: 5px; margin-bottom: 5px; text-align: left; font-weight: 500; font-size: 14px; margin: 10px 0; display: block; color: #333; position: relative; background-color: rgba(0,0,0,0.03); border: 1px solid #f0f0f0; border-radius: 2px; display: flex; line-height: 20px; word-wrap: break-word !important;"
                >
                    <ul class="code-snippet__line-index code-snippet__js" style="visibility: visible;">
                        ' . $line_num . '
                    </ul>
                    <pre class="code-snippet__js" data-lang="'. $language .'" style="visibility: visible;">
                        ' . $line_code . '
                    </pre>
                </section>
                ';
            },
            $text
        );
        return $text;
    }
    public static function formatHtmlWithDOM($html) {
        // 创建 DOMDocument 实例
        $dom = new DOMDocument('1.0', 'UTF-8');  // 设置编码为 UTF-8
    
        // 处理HTML错误
        libxml_use_internal_errors(true);
    
        // 加载HTML内容，并强制指定 UTF-8 编码
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');  // 转换为HTML实体
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
        // 清理 DOMDocument 中的多余空格
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//text()') as $textNode) {
            // 跳过 <code> 标签中的内容
            if ($textNode->parentNode->nodeName != 'code') {
                $textNode->nodeValue = trim(preg_replace('/\s+/', ' ', $textNode->nodeValue));
            }
        }
    
        // 处理 style 和 class 属性中的多余空格
        foreach ($dom->getElementsByTagName('*') as $element) {
            if ($element->hasAttribute('style')) {
                $style = $element->getAttribute('style');
                $element->setAttribute('style', preg_replace('/\s+/', ' ', $style));
            }
            if ($element->hasAttribute('class')) {
                $class = $element->getAttribute('class');
                $element->setAttribute('class', preg_replace('/\s+/', ' ', $class));
            }
        }
    
        // 输出格式化后的HTML，确保保存为 UTF-8 编码
        return mb_convert_encoding($dom->saveHTML(), 'UTF-8', 'HTML-ENTITIES');
    }

    /* 格式化标签 */
    public static function ParseCode($text)
    {
        $text = self::codeHightLight($text);
        return $text;
    }

    public static function renderMarkdown($text)
    {
        // 重新赋值给$text
        $text = str_replace("<!--markdown-->", "", $text);
        
        // 实例化Parsedown对象
        $parsedown = new CustomParsedown();
        
        // 将Markdown转换为HTML
        $htmlContent = $parsedown->text($text);
        $htmlContent = self::ParseCode($htmlContent);
        // 返回处理后的内容

        $htmlContent = '<section id="nice" data-tool="markdown编辑器" data-website="https://markdown.com.cn/editor"
style="font-size: 16px; color: black; padding: 25px 30px; line-height: 1.6; word-spacing: 0px; letter-spacing: 0px; word-wrap: break-word; text-align: justify; margin-top: -10px; font-family: \'PingFang SC\', \'Microsoft YaHei\', sans-serif; word-break: break-all;">' . $htmlContent . '</section>';

        return $htmlContent;
    }
    public static function getPermalinkByCid($cid)
    {
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');

        // 查询文章
        $post = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish'));

        if (empty($post)) {
            return null;
        }

        // 获取文章的分类
        $category = $db->fetchRow($db->select('slug')->from('table.metas')
            ->where('type = ?', 'category')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $cid));

        // 获取路由规则
        $postPattern = $options->routingTable['post']['url'];
        error_log("\n url options". json_encode($postPattern) ."\n",3,dirname(__FILE__) . "/cache/error.txt");

        // 替换路由中的占位符
        $permalink = $postPattern;
        $permalink = str_replace('[cid:digital]', $post['cid'], $permalink);
        $permalink = str_replace('[slug]', $post['slug'], $permalink);
        $permalink = str_replace('[category]', $category['slug'] ?? 'default', $permalink);
        $permalink = str_replace('[year:digital:4]', date('Y', $post['created']), $permalink);
        $permalink = str_replace('[month:digital:2]', date('m', $post['created']), $permalink);
        $permalink = str_replace('[day:digital:2]', date('d', $post['created']), $permalink);

        // 拼接站点根 URL
        return rtrim($options->siteUrl, '/') . '/' . ltrim($permalink, '/');
    }
    
    /* 插件实现方法 */
    public static function render($cid){
        $setting = self::getSetting();
        $abstractField = $setting->abstractField ?? 'abstract';
        $post = self::getPost($cid);
        $author = $setting->author;
        if (empty($author)) {
            $user = Typecho_Widget::widget('Widget_User');
            $author = $user->screenName;
        } 
        if (empty($post->password) && strlen($post->text) > 100 && $setting->appid && $setting->secret) {
            $accessToken = self::getAccessToken();
            $url = 'https://api.weixin.qq.com/cgi-bin/draft/add?access_token='.$accessToken;
            $mediaId = self::uploadCover($cid);
            $html = html_entity_decode(self::uploadImageToWeChat($post->text), ENT_QUOTES, 'UTF-8');
            $array = [
                "articles"=>[
                    [
                        "title"=>$post->title,
                        "author"=>$author,
                        "content"=>$html,
                        "content_source_url"=>$post->permalink,
                        "thumb_media_id"=>$mediaId,
                        "digest" => $post->fields->$abstractField
                    ]
                ]
            ];
            self::curl($url,json_encode($array, JSON_UNESCAPED_UNICODE),true);
        }
    }
}