<?php
/**
 * 同步文章到微信公众号草稿箱
 *
 * @package WeChatSync
 * @author TeohZY
 * @version 1.0.1
 * @link https://blog.teohzy.com
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class WeChatSync_Plugin implements Typecho_Plugin_Interface
{
    public static function activate()
    {
        $adminFooterPath = ltrim(__TYPECHO_ADMIN_DIR__, '/') . 'footer.php';
        Typecho_Plugin::factory($adminFooterPath)->end = array('WeChatSync_Plugin', 'addSyncAction');
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

    private static function getLoadingStyles()
    {
        ?>
        <style>
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: none;
                z-index: 1000;
                justify-content: center;
                align-items: center;
            }
            .loading-content{
                width: 100%;
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 20px;
            }
            .spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
            }
            .progress-container {
                width: 300px;
                background: #f3f3f3;
                border-radius: 5px;
                margin-top: 10px;
            }
            .progress-bar {
                width: 0%;
                height: 20px;
                background: #3498db;
                border-radius: 5px;
                transition: width 0.3s;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .info-text {
                color: #fff;
                font-size: 16px;
                text-align: center;
            }
        </style>
        <?php
    }

    private static function getLoadingScript()
    {
        ?>
        <script>
            function handleAjaxProgress() {
                $('#loading-overlay').fadeIn();
                let progress = 0;
                const progressInterval = setInterval(() => {
                    progress = Math.min(progress + 10, 90);
                    $('#progress-bar').css('width', progress + '%');
                }, 500);
                return progressInterval;
            }
        </script>
        <?php
    }

    private static function getLoadingHtml()
    {
        ?>
        <div class="loading-overlay" id="loading-overlay" style="display: none;">
            <div class="loading-content">
                <div class="spinner"></div>
                <div class="progress-container">
                    <div class="progress-bar" id="progress-bar"></div>
                </div>
                <div class="info-text">上传中请稍等...</div>
            </div>
        </div>
        <?php
    }

    public static function addSyncAction()
    {
        $request = Typecho_Request::getInstance();
        if (strpos($request->getRequestUri(), __TYPECHO_ADMIN_DIR__ . 'manage-posts.php') !== false) {
            self::addManagePostsMenu();
        }

        if (strpos($request->getRequestUri(), __TYPECHO_ADMIN_DIR__ . 'write-post.php') !== false) {
            self::addWritePostButton();
        }
    }

    public static function addManagePostsMenu(){
        $request = Typecho_Request::getInstance();
        self::getLoadingStyles();
        self::getLoadingScript();
        self::getLoadingHtml();
        ?>
        <script>
            $(document).ready(function() {
                console.log("Dropdown menus found: ", $('.dropdown-menu').length);
                const syncAction = '<li><a href="#" data-action="<?php $security = Typecho_Widget::widget("Widget_Security"); echo $security->index("/action/custom_action_plugin?do=custom_action"); ?>">发布公众号</a></li>';
                $('.dropdown-menu').each(function() {
                    $(this).append(syncAction);
                });

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

                    const progressInterval = handleAjaxProgress();

                    $.ajax({
                        url: actionUrl,
                        type: 'POST',
                        data: {
                            cid: cids,
                            '_': '<?php echo Typecho_Widget::widget("Widget_Security")->getToken($request->getRequestUrl()); ?>'
                        },
                        success: function(response) {
                            clearInterval(progressInterval);
                            $('#progress-bar').css('width', '100%');
                            setTimeout(() => {
                                $('#loading-overlay').fadeOut();
                                window.location.reload();
                            }, 500);
                        },
                        error: function(xhr) {
                            clearInterval(progressInterval);
                            $('#loading-overlay').fadeOut();
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
        self::getLoadingStyles();
        self::getLoadingScript();
        self::getLoadingHtml();
        ?>
        <script>
            $(document).ready(function() {
                const syncButton = '<button type="button" id="btn-custom-action" class="btn"><?php _e("发布公众号"); ?></button>';
                $('#btn-submit').before(syncButton);

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

                    const progressInterval = handleAjaxProgress();

                    $.ajax({
                        url: '<?php $security = Typecho_Widget::widget("Widget_Security"); echo $security->index("/action/WeChatSync_action_plugin?do=custom_action"); ?>',
                        type: 'POST',
                        data: {
                            cid: [cid],
                            '_': '<?php echo Typecho_Widget::widget("Widget_Security")->getToken($request->getRequestUrl()); ?>'
                        },
                        success: function(response) {
                            clearInterval(progressInterval);
                            $('#progress-bar').css('width', '100%');
                            setTimeout(() => {
                                $('#loading-overlay').fadeOut();
                                alert('<?php _e("已成功发布到公众号"); ?>');
                            }, 500);
                        },
                        error: function(xhr) {
                            clearInterval(progressInterval);
                            $('#loading-overlay').fadeOut();
                            alert('<?php _e("操作失败：") ?>' + xhr.status + ' ' + xhr.statusText);
                        }
                    });
                });
            });
        </script>
        <?php
    }
}