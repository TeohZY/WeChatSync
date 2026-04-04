<?php
require_once dirname(__FILE__) . '/../parsedown/CustomParsedown.php';
require_once dirname(__FILE__) . '/../geshi/geshi.php';

class ContentProcessor
{
    /**
     * 渲染 Markdown 为 HTML
     */
    public function renderMarkdown($text)
    {
        $text = str_replace("<!--markdown-->", "", $text);

        $parsedown = new CustomParsedown();
        $htmlContent = $parsedown->text($text);
        $htmlContent = $this->parseCode($htmlContent);

        $htmlContent = '<section id="nice" data-tool="markdown编辑器" data-website="https://markdown.com.cn/editor"
style="font-size: 16px; color: black; padding: 25px 30px; line-height: 1.6; word-spacing: 0px; letter-spacing: 0px; word-wrap: break-word; text-align: justify; margin-top: -10px; font-family: \'PingFang SC\', \'Microsoft YaHei\', sans-serif; word-break: break-all;">' . $htmlContent . '</section>';

        return $htmlContent;
    }

    /**
     * 格式化标签
     */
    public function parseCode($text)
    {
        $text = $this->codeHighlight($text);
        return $text;
    }

    /**
     * 代码高亮
     */
    public function codeHighlight($text)
    {
        $text = preg_replace_callback(
            '/<pre><code(?: class="language-(.*?)")?>(.*?)<\/code><\/pre>/s',
            function ($matches) {
                $language = isset($matches[1]) && !empty($matches[1]) ? $matches[1] : 'plaintext';
                $code = $matches[2];
                $geshi = new GeShi($code, $language);
                $highlighted_code = $geshi->parse_code();
                $highlighted_code = preg_replace('/^<pre[^>]*>|<\/pre>$/', '', $highlighted_code);

                $lines = explode("\n", $highlighted_code);
                $line_num = null;
                $line_code = null;

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

    /**
     * 获取文章永久链接
     */
    public function getPermalinkByCid($cid)
    {
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');

        $post = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('type = ?', 'post')
            ->where('status = ?', 'publish'));

        if (empty($post)) {
            return null;
        }

        $category = $db->fetchRow($db->select('slug')->from('table.metas')
            ->where('type = ?', 'category')
            ->join('table.relationships', 'table.relationships.mid = table.metas.mid')
            ->where('table.relationships.cid = ?', $cid));

        $postPattern = $options->routingTable['post']['url'];
        $permalink = $postPattern;
        $permalink = str_replace('[cid:digital]', $post['cid'], $permalink);
        $permalink = str_replace('[slug]', $post['slug'], $permalink);
        $permalink = str_replace('[category]', $category['slug'] ?? 'default', $permalink);
        $permalink = str_replace('[year:digital:4]', date('Y', $post['created']), $permalink);
        $permalink = str_replace('[month:digital:2]', date('m', $post['created']), $permalink);
        $permalink = str_replace('[day:digital:2]', date('d', $post['created']), $permalink);

        return rtrim($options->siteUrl, '/') . '/' . ltrim($permalink, '/');
    }
}
