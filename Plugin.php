<?php
/**
 * 同步文章到微信公众号草稿箱
 *
 * @package WeChatSync
 * @author TeohZY
 * @version 1.0.0
 * @link https://blog.teohzy.com
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class WeChatSync_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        // 注入 JavaScript 到 footer.php
        $adminUrl = Helper::options()->adminUrl;
        Typecho_Plugin::factory($adminUrl . 'footer.php')->end = array('WeChatSync_Plugin', 'addSyncAction');
        Helper::addAction('WeChatSync_action_plugin', 'WeChatSync_Action');
        return _t('插件已启用');
    }

    public static function deactivate()
    {
        Helper::removeAction('WeChatSync_action_plugin');
        self::clearCacheDir();
        return _t('插件已禁用，缓存目录已清空');
    }

    public static function config(Typecho_Widget_Helper_Form $form) {
        $appid = new Typecho_Widget_Helper_Form_Element_Text('appid', NULL, '', _t('公众号AppId'), _t('请填写微信公众号的AppId'));
        $form->addInput($appid);
        $secret = new Typecho_Widget_Helper_Form_Element_Text('secret', NULL, '', _t('公众号Secret'), _t('请填写微信公众号的Secret'));
        $form->addInput($secret);
        $author = new Typecho_Widget_Helper_Form_Element_Text('author', NULL, '', _t('公众号文章作者'), _t('请填写文章作者，默认使用个人资料中的昵称'));
        $form->addInput($author);
        $abstractField = new Typecho_Widget_Helper_Form_Element_Text('摘要字段', NULL, '', _t('文章摘要字段'), _t('请填写主题对应摘要字段，默认为abstract'));
        $form->addInput($abstractField);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    private static function clearCacheDir()
    {
        $cacheDir = __DIR__ . '/cache';
        if (!is_dir($cacheDir)) {
            return;
        }
        $items = scandir($cacheDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $cacheDir . '/' . $item;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public static function addSyncAction()
    {
        $request = Typecho_Request::getInstance();
        // 检查当前请求是否为管理文章页面
        if (strpos($request->getRequestUri(), '/admin/manage-posts.php') !== false) {
            self::addManagePostsMenu();
        }

        if (strpos($request->getRequestUri(), '/admin/write-post.php') !== false) {
            self::addWritePostButton();
        }
    }

    public static function addManagePostsMenu(){
        $request = Typecho_Request::getInstance();
        ?>
        <script>
            $(document).ready(function() {
                console.log("Dropdown menus found: ", $('.dropdown-menu').length);
                // 添加自定义操作到下拉菜单
                const syncAction = '<li><a href="#" data-action="<?php $security = Typecho_Widget::widget("Widget_Security"); echo $security->index("/action/custom_action_plugin?do=custom_action"); ?>">发布公众号</a></li>';
                $('.dropdown-menu').each(function() {
                    $(this).append(syncAction);
                });

                // 监听下拉菜单点击
                $('.dropdown-menu a[data-action*="do=custom_action"]').on('click', function(e) {
                    e.preventDefault();
                    const actionUrl = $(this).data('action');
                    const cids = [];
                    $('.operate-form input[name="cid[]"]:checked').each(function() {
                        cids.push($(this).val());
                    });

                    if (cids.length === 0) {
                        alert('<?php _e("请至少选择一篇文章"); ?>');
                        return;
                    }

                    // 发送 AJAX 请求
                    $.ajax({
                        url: actionUrl,
                        type: 'POST',
                        data: {
                            cid: cids,
                            '_': '<?php echo Typecho_Widget::widget("Widget_Security")->getToken($request->getRequestUrl()); ?>' // 显式传递 CSRF 令牌
                        },
                        success: function(response) {
                            // alert('<?php _e("自定义操作已执行"); ?>');
                            window.location.reload();
                        },
                        error: function(xhr) {
                            alert('<?php _e("操作失败：") ?>' + xhr.status + ' ' + xhr.statusText);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public static function addWritePostButton(){
        $request = Typecho_Request::getInstance();
        ?>
        <script>
            $(document).ready(function() {
                // 添加“发布公众号”按钮
                const syncButton = '<button type="button" id="btn-custom-action" class="btn"><?php _e("发布公众号"); ?></button>';
                $('#btn-submit').before(syncButton);
                // 监听按钮点击
                $('#btn-custom-action').on('click', function(e) {
                    e.preventDefault();
                    const cid = $('input[name="cid"]').val();
                    if (!cid) {
                        alert('<?php _e("文章未保存，请先保存草稿或发布文章"); ?>');
                        return;
                    }

                    if (!confirm('<?php _e("确认发布到公众号吗？"); ?>')) {
                        return;
                    }

                    // 发送 AJAX 请求
                    $.ajax({
                        url: '<?php $security = Typecho_Widget::widget("Widget_Security"); echo $security->index("/action/WeChatSync_action_plugin?do=custom_action"); ?>',
                        type: 'POST',
                        data: {
                            cid: [cid],
                            '_': '<?php echo Typecho_Widget::widget("Widget_Security")->getToken($request->getRequestUrl()); ?>'
                        },
                        success: function(response) {
                            alert('<?php _e("已成功发布到公众号"); ?>');
                            // 可选择刷新页面或保持当前状态
                            // window.location.reload();
                        },
                        error: function(xhr) {
                            alert('<?php _e("操作失败：") ?>' + xhr.status + ' ' + xhr.statusText);
                        }
                    });
                });
            });
        </script>
        <?php
    }
}