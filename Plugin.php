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
        Typecho_Plugin::factory("admin/footer.php")->end = array('WeChatSync_Plugin', 'addSyncAction');
        Helper::addAction('WeChatSync_action_plugin', 'WeChatSync_Action');
        return _t('插件已启用');
    }

    public static function deactivate()
    {
        Helper::removeAction('WeChatSync_action_plugin');
        self::clearCacheDir();
        return _t('插件已禁用，缓存目录已清空');
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $appid = new Typecho_Widget_Helper_Form_Element_Text('appid', NULL, '', _t('公众号AppId'), _t('请填写微信公众号的AppId'));
        $form->addInput($appid);

        $secret = new Typecho_Widget_Helper_Form_Element_Text('secret', NULL, '', _t('公众号Secret'), _t('请填写微信公众号的Secret'));
        $form->addInput($secret);

        $author = new Typecho_Widget_Helper_Form_Element_Text('author', NULL, '', _t('公众号文章作者'), _t('请填写文章作者，默认使用个人资料中的昵称'));
        $form->addInput($author);

        $abstractField = new Typecho_Widget_Helper_Form_Element_Text('摘要字段', NULL, '', _t('文章摘要字段'), _t('请填写主题对应摘要字段，默认为abstract'));
        $form->addInput($abstractField);

        $addSourceUrl = new Typecho_Widget_Helper_Form_Element_Radio('addSourceUrl', array('1' => '是', '0' => '否'), '1', _t('添加原文链接'), _t('开启后会在文章末尾添加指向原文的链接'));
        $form->addInput($addSourceUrl);
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    public static function addSyncAction()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginUrl = $options->pluginUrl . '/WeChatSync';

        // 构建 action URL
        $actionUrl = rtrim($options->siteUrl, '/') . '/action/WeChatSync_action_plugin?do=custom_action';
        $previewUrl = rtrim($options->siteUrl, '/') . '/action/WeChatSync_action_plugin?do=preview';

        // 获取安全 token
        $security = Typecho_Widget::widget('Widget_Security');
        $request = Typecho_Request::getInstance();
        $securityToken = $security->getToken($request->getRequestUrl());

        echo '<link rel="stylesheet" href="' . $pluginUrl . '/Assets/admin.css">';
        echo '<script src="' . $pluginUrl . '/Assets/admin.js"></script>';
        echo '<div id="wcs-config" data-action="' . htmlspecialchars($actionUrl) . '" data-preview="' . htmlspecialchars($previewUrl) . '" data-token="' . htmlspecialchars($securityToken) . '" style="display:none;"></div>';
        echo '<script>WeChatSync.debugConfig = {actionUrl: "' . htmlspecialchars($actionUrl) . '", previewUrl: "' . htmlspecialchars($previewUrl) . '", token: "' . htmlspecialchars($securityToken) . '"};</script>';

        if (strpos($request->getRequestUri(), __TYPECHO_ADMIN_DIR__ . 'manage-posts.php') !== false) {
            echo '<script>WeChatSync.initManagePosts();</script>';
        }

        if (strpos($request->getRequestUri(), __TYPECHO_ADMIN_DIR__ . 'write-post.php') !== false) {
            echo '<script>WeChatSync.initWritePost();</script>';
        }
    }

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
            if (is_dir($path)) {
                self::removeDir($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function removeDir($dir)
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
