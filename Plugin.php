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

    private static function getLoadingStyles()
    {
        ?>
        <style>
            .wcs-loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.7);
                display: flex;
                z-index: 10000;
                justify-content: center;
                align-items: center;
                backdrop-filter: blur(4px);
            }
            .wcs-loading-card {
                background: linear-gradient(145deg, #ffffff, #f5f5f5);
                border-radius: 16px;
                padding: 40px 50px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                width: 360px;
                margin: 0 auto;
            }
            .wcs-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 24px;
                background: linear-gradient(135deg, #07c160, #06ad56);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 8px 24px rgba(7, 193, 96, 0.4);
            }
            .wcs-icon svg {
                width: 48px;
                height: 48px;
                fill: white;
            }
            .wcs-title {
                font-size: 20px;
                font-weight: 600;
                color: #333;
                margin-bottom: 8px;
            }
            .wcs-step {
                font-size: 14px;
                color: #999;
                margin-bottom: 24px;
                min-height: 20px;
            }
            .wcs-progress-container {
                width: 100%;
                height: 6px;
                background: #e8e8e8;
                border-radius: 3px;
                overflow: hidden;
                margin-bottom: 16px;
            }
            .wcs-progress-bar {
                width: 0%;
                height: 100%;
                background: linear-gradient(90deg, #07c160, #10b040);
                border-radius: 3px;
                transition: width 0.4s ease;
            }
            .wcs-steps-list {
                text-align: left;
                margin: 0;
                padding: 0 10px;
                list-style: none;
            }
            .wcs-steps-list li {
                display: flex;
                align-items: center;
                padding: 8px 0;
                font-size: 14px;
                color: #999;
                transition: all 0.3s;
            }
            .wcs-steps-list li.active {
                color: #07c160;
                font-weight: 500;
            }
            .wcs-steps-list li.done {
                color: #999;
            }
            .wcs-steps-list li .wcs-step-icon {
                width: 20px;
                height: 20px;
                border-radius: 50%;
                border: 2px solid currentColor;
                margin-right: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                flex-shrink: 0;
            }
            .wcs-steps-list li.active .wcs-step-icon {
                background: #07c160;
                border-color: #07c160;
                color: white;
            }
            .wcs-steps-list li.done .wcs-step-icon {
                background: #07c160;
                border-color: #07c160;
                color: white;
            }
            .wcs-steps-list li.done .wcs-step-icon::after {
                content: "✓";
            }
            @keyframes wcs-pulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.05); }
            }
            .wcs-loading-card.loading .wcs-icon {
                animation: wcs-pulse 1.5s ease-in-out infinite;
            }
            /* 自定义模态框样式 */
            .wcs-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.6);
                display: none;
                z-index: 10001;
                justify-content: center;
                align-items: center;
                backdrop-filter: blur(4px);
            }
            .wcs-modal-overlay.show {
                display: flex;
            }
            .wcs-modal-card {
                background: #fff;
                border-radius: 12px;
                padding: 28px 32px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                width: 320px;
                max-width: 90%;
            }
            .wcs-modal-icon {
                width: 56px;
                height: 56px;
                margin: 0 auto 16px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
            }
            .wcs-modal-icon.success {
                background: rgba(7, 193, 96, 0.1);
                color: #07c160;
            }
            .wcs-modal-icon.error {
                background: rgba(240, 68, 68, 0.1);
                color: #f04444;
            }
            .wcs-modal-icon.warning {
                background: rgba(250, 173, 20, 0.1);
                color: #faad14;
            }
            .wcs-modal-icon.info {
                background: rgba(24, 144, 255, 0.1);
                color: #1890ff;
            }
            .wcs-modal-title {
                font-size: 18px;
                font-weight: 600;
                color: #333;
                margin-bottom: 8px;
            }
            .wcs-modal-message {
                font-size: 14px;
                color: #666;
                margin-bottom: 24px;
                line-height: 1.5;
            }
            .wcs-modal-buttons {
                display: flex;
                gap: 12px;
                justify-content: center;
            }
            .wcs-modal-btn {
                padding: 10px 24px;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                border: none;
                transition: all 0.2s;
            }
            .wcs-modal-btn-primary {
                background: #07c160;
                color: #fff;
            }
            .wcs-modal-btn-primary:hover {
                background: #06ad56;
            }
            .wcs-modal-btn-secondary {
                background: #f5f5f5;
                color: #666;
            }
            .wcs-modal-btn-secondary:hover {
                background: #e8e8e8;
            }
            .wcs-modal-btn-danger {
                background: #f04444;
                color: #fff;
            }
            .wcs-modal-btn-danger:hover {
                background: #dc3545;
            }
        </style>
        <?php
    }

    private static function getLoadingScript()
    {
        ?>
        <script>
            function handleAjaxProgress() {
                $('#wcs-loading-overlay').fadeIn();

                // 设置步骤
                const steps = [
                    { id: 'step-token', text: '获取访问令牌' },
                    { id: 'step-cover', text: '上传封面图片' },
                    { id: 'step-content', text: '处理文章内容' },
                    { id: 'step-images', text: '上传文章图片' },
                    { id: 'step-submit', text: '提交到公众号' }
                ];

                let currentStep = 0;

                function updateStep(stepIndex) {
                    steps.forEach((step, index) => {
                        const li = $('#' + step.id).closest('li');
                        li.removeClass('active done');
                        if (index < stepIndex) {
                            li.addClass('done');
                        } else if (index === stepIndex) {
                            li.addClass('active');
                        }
                    });
                    const progress = ((stepIndex + 1) / steps.length) * 100;
                    $('#wcs-progress-bar').css('width', progress + '%');
                }

                updateStep(0);

                const progressInterval = setInterval(() => {
                    if (currentStep < steps.length - 1) {
                        currentStep++;
                        updateStep(currentStep);
                    } else {
                        clearInterval(progressInterval);
                    }
                }, 1500);

                return progressInterval;
            }

            function resetLoadingState() {
                $('#wcs-progress-bar').css('width', '0%');
                $('.wcs-steps-list li').removeClass('active done');
            }

            // 显示模态框
            function wcsShowModal(options) {
                // 如果已经显示，则移除
                var existingModal = document.getElementById('wcs-modal');
                if (existingModal) {
                    existingModal.remove();
                }

                var type = options.type || 'info';
                var title = options.title || '';
                var message = options.message || '';
                var buttons = options.buttons || [];
                var iconText = {
                    success: '√',
                    error: '×',
                    warning: '!',
                    info: 'i'
                }[type];

                var html = '<div class="wcs-modal-overlay" id="wcs-modal" style="display:none;">' +
                    '<div class="wcs-modal-card">' +
                    '<div class="wcs-modal-icon ' + type + '">' + iconText + '</div>';
                if (title) {
                    html += '<div class="wcs-modal-title">' + title + '</div>';
                }
                if (message) {
                    html += '<div class="wcs-modal-message">' + message + '</div>';
                }
                html += '<div class="wcs-modal-buttons">';
                for (var i = 0; i < buttons.length; i++) {
                    var btn = buttons[i];
                    var btnClass = btn.primary ? 'wcs-modal-btn wcs-modal-btn-primary' : 'wcs-modal-btn wcs-modal-btn-secondary';
                    if (btn.danger) {
                        btnClass = 'wcs-modal-btn wcs-modal-btn-danger';
                    }
                    html += '<button class="' + btnClass + '" data-action="' + (btn.action || '') + '">' + btn.text + '</button>';
                }
                html += '</div></div></div>';

                document.body.insertAdjacentHTML('beforeend', html);

                var modal = document.getElementById('wcs-modal');
                modal.style.display = 'flex';

                var btns = modal.querySelectorAll('.wcs-modal-btn');
                for (var j = 0; j < btns.length; j++) {
                    btns[j].addEventListener('click', function() {
                        var action = this.getAttribute('data-action');
                        modal.remove();
                        if (action && typeof window[action] === 'function') {
                            window[action]();
                        }
                    });
                }
            }

            // 快捷方法
            function wcsAlert(message, callback) {
                wcsShowModal({
                    type: 'info',
                    message: message,
                    buttons: [{ text: '确定', action: callback ? 'wcsAlertCallback' : '' }]
                });
                if (callback) window.wcsAlertCallback = callback;
            }
            function wcsSuccess(message, callback) {
                wcsShowModal({
                    type: 'success',
                    message: message,
                    buttons: [{ text: '确定', primary: true, action: callback ? 'wcsSuccessCallback' : '' }]
                });
                if (callback) window.wcsSuccessCallback = callback;
            }
            function wcsError(message, callback) {
                wcsShowModal({
                    type: 'error',
                    message: message,
                    buttons: [{ text: '确定', primary: true, action: callback ? 'wcsErrorCallback' : '' }]
                });
                if (callback) window.wcsErrorCallback = callback;
            }
            function wcsConfirm(message, onConfirm) {
                wcsShowModal({
                    type: 'warning',
                    message: message,
                    buttons: [
                        { text: '取消', action: '' },
                        { text: '确认', primary: true, action: 'wcsConfirmCallback' }
                    ]
                });
                window.wcsConfirmCallback = onConfirm;
            }
        </script>
        <?php
    }

    private static function getLoadingHtml()
    {
        ?>
        <div class="wcs-loading-overlay" id="wcs-loading-overlay" style="display:none;">
            <div class="wcs-loading-card loading">
                <div class="wcs-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.11.24-.245 0-.06-.024-.12-.04-.178l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.87c-.135-.004-.272-.012-.407-.012zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982z"/>
                    </svg>
                </div>
                <div class="wcs-title">正在同步到公众号</div>
                <div class="wcs-step" id="wcs-current-step">准备中...</div>
                <div class="wcs-progress-container">
                    <div class="wcs-progress-bar" id="wcs-progress-bar"></div>
                </div>
                <ul class="wcs-steps-list">
                    <li><span class="wcs-step-icon">1</span><span id="step-token">获取访问令牌</span></li>
                    <li><span class="wcs-step-icon">2</span><span id="step-cover">上传封面图片</span></li>
                    <li><span class="wcs-step-icon">3</span><span id="step-content">处理文章内容</span></li>
                    <li><span class="wcs-step-icon">4</span><span id="step-images">上传文章图片</span></li>
                    <li><span class="wcs-step-icon">5</span><span id="step-submit">提交到公众号</span></li>
                </ul>
            </div>
        </div>
        <?php
    }

    public static function addSyncAction()
    {
        echo '<script>console.log("WeChatSync Plugin loaded successfully!");</script>';
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
                const syncAction = '<li><a href="#" data-action="<?php $security = Typecho_Widget::widget("Widget_Security"); echo $security->index("/action/WeChatSync_action_plugin?do=custom_action"); ?>">发布公众号</a></li>';
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
                        wcsAlert('<?php _e("请至少选择一篇文章"); ?>');
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
                            $('#wcs-progress-bar').css('width', '100%');
                            $('#wcs-current-step').text('同步完成！');
                            setTimeout(() => {
                                $('#wcs-loading-overlay').fadeOut();
                                resetLoadingState();
                                window.location.reload();
                            }, 800);
                        },
                        error: function(xhr) {
                            clearInterval(progressInterval);
                            $('#wcs-loading-overlay').fadeOut();
                            resetLoadingState();
                            let errorMsg = xhr.status + ' ' + xhr.statusText;
                            try {
                                const resp = JSON.parse(xhr.responseText);
                                if (resp.error) {
                                    errorMsg = resp.error;
                                }
                            } catch(e) {}
                            wcsError('<?php _e("操作失败：") ?>' + errorMsg);
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
                        wcsAlert('<?php _e("文章未保存，请先保存草稿或发布文章"); ?>');
                        return;
                    }

                    wcsConfirm('<?php _e("确认发布到公众号吗？"); ?>', function() {
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
                                $('#wcs-progress-bar').css('width', '100%');
                                $('#wcs-current-step').text('同步完成！');
                                setTimeout(() => {
                                    $('#wcs-loading-overlay').fadeOut();
                                    resetLoadingState();
                                    wcsSuccess('<?php _e("已成功发布到公众号"); ?>');
                                }, 800);
                            },
                            error: function(xhr) {
                                clearInterval(progressInterval);
                                $('#wcs-loading-overlay').fadeOut();
                                resetLoadingState();
                                let errorMsg = xhr.status + ' ' + xhr.statusText;
                                try {
                                    const resp = JSON.parse(xhr.responseText);
                                    if (resp.error) {
                                        errorMsg = resp.error;
                                    }
                                } catch(e) {}
                                wcsError('<?php _e("操作失败：") ?>' + errorMsg);
                            }
                        });
                    });
                });
            });
        </script>
        <?php
    }
}
