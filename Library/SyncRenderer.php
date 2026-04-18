<?php
require_once dirname(__FILE__) . '/WeChatClient.php';
require_once dirname(__FILE__) . '/ContentProcessor.php';

class SyncRenderer
{
    private $client;
    private $processor;
    private $setting;

    public function __construct()
    {
        $this->client = new WeChatClient();
        $this->processor = new ContentProcessor();
        $this->setting = $this->client->getSetting();
    }

    /**
     * 获取文章对象
     */
    public function getPost($cid)
    {
        $db = Typecho_Db::get();
        $post = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ?', intval($cid))
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish'));

        if (!empty($post)) {
            try {
                $archive = Widget_Archive::alloc('type=post', 'cid=' . intval($cid));
                if (!empty($archive->permalink)) {
                    $post['permalink'] = $archive->permalink;
                }
            } catch (Exception $e) {
            }
        }

        return $post;
    }

    /**
     * 插件实现方法
     */
    public static function render($cid)
    {
        $instance = new self();
        return $instance->syncArticle($cid);
    }

    /**
     * 生成预览数据
     */
    public static function preview($cid)
    {
        $instance = new self();
        return $instance->buildPreview($cid);
    }

    /**
     * 同步文章
     */
    private function syncArticle($cid)
    {
        $abstractField = $this->setting->abstractField ?? 'abstract';
        $post = $this->getPost($cid);
        $author = $this->setting->author;

        if (empty($author)) {
            $user = Typecho_Widget::widget('Widget_User');
            $author = $user->screenName;
        }

        if (empty($post) || !isset($post['text'])) {
            throw new Exception('文章不存在或内容为空');
        }

        if (!empty($post['password'])) {
            throw new Exception('受保护的文章无法同步');
        }

        if (strlen($post['text']) <= 100) {
            throw new Exception('文章内容太短，无法同步');
        }

        if (empty($this->setting->appid) || empty($this->setting->secret)) {
            throw new Exception('请先配置微信公众号的AppId和Secret');
        }

        $db = Typecho_Db::get();
        $abstractRow = $db->fetchRow($db->select('str_value')
            ->from('table.fields')
            ->where('cid = ?', intval($cid))
            ->where('name = ?', $abstractField));
        $digest = $abstractRow ? $abstractRow['str_value'] : '';

        $title = isset($post['title']) ? $post['title'] : '';
        $permalink = !empty($post['permalink']) ? $post['permalink'] : $this->processor->getPermalinkByCid($cid);
        $accessToken = $this->client->getAccessToken();
        $url = 'https://api.weixin.qq.com/cgi-bin/draft/add?access_token='.$accessToken;
        $mediaId = $this->client->uploadCover($cid);
        $html = $this->processor->renderMarkdown($post['text']);
        $html = html_entity_decode($this->client->uploadImageToWeChat($html), ENT_QUOTES, 'UTF-8');

        $article = [
            "title"=>$title,
            "author"=>$author,
            "content"=>$html,
            "thumb_media_id"=>$mediaId,
            "digest" => $digest
        ];

        if (!empty($this->setting->addSourceUrl)) {
            $article["content_source_url"] = $permalink;
        }

        $array = [
            "articles"=>[$article]
        ];

        $result = $this->client->curl($url, json_encode($array, JSON_UNESCAPED_UNICODE), true);

        if (!isset($result->media_id)) {
            throw new Exception('发布失败：未获取到 media_id');
        }

        return $result->media_id;
    }

    /**
     * 构造公众号预览内容
     */
    private function buildPreview($cid)
    {
        $post = $this->getPost($cid);

        if (empty($post) || !isset($post['text'])) {
            throw new Exception('文章不存在或内容为空');
        }

        $html = html_entity_decode($this->processor->renderMarkdown($post['text']), ENT_QUOTES, 'UTF-8');

        return [
            'title' => isset($post['title']) ? $post['title'] : '',
            'content' => $html
        ];
    }
}
