/**
 * WeChatSync Admin JavaScript
 */
(function() {
    var WeChatSync = window.WeChatSync = {};

    // Loading overlay HTML template
    var loadingHtml = '<div class="wcs-loading-overlay" id="wcs-loading-overlay" style="display:none;">' +
        '<div class="wcs-loading-card loading">' +
        '<div class="wcs-icon">' +
        '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.11.24-.245 0-.06-.024-.12-.04-.178l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.87c-.135-.004-.272-.012-.407-.012zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982z"/>' +
        '</svg>' +
        '</div>' +
        '<div class="wcs-title">正在同步到公众号</div>' +
        '<div class="wcs-step" id="wcs-current-step">准备中...</div>' +
        '<div class="wcs-progress-container">' +
        '<div class="wcs-progress-bar" id="wcs-progress-bar"></div>' +
        '</div>' +
        '<ul class="wcs-steps-list">' +
        '<li><span class="wcs-step-icon">1</span><span class="wcs-step-text" id="step-token">获取访问令牌</span><span class="wcs-step-check">✓</span></li>' +
        '<li><span class="wcs-step-icon">2</span><span class="wcs-step-text" id="step-cover">上传封面图片</span><span class="wcs-step-check">✓</span></li>' +
        '<li><span class="wcs-step-icon">3</span><span class="wcs-step-text" id="step-content">处理文章内容</span><span class="wcs-step-check">✓</span></li>' +
        '<li><span class="wcs-step-icon">4</span><span class="wcs-step-text" id="step-images">上传文章图片</span><span class="wcs-step-check">✓</span></li>' +
        '<li><span class="wcs-step-icon">5</span><span class="wcs-step-text" id="step-submit">提交到公众号</span><span class="wcs-step-check">✓</span></li>' +
        '</ul>' +
        '</div>' +
        '</div>';

    /**
     * Get config from hidden div
     */
    function getWcsConfig() {
        var configEl = document.getElementById('wcs-config');
        if (!configEl) {
            return { actionUrl: '', previewUrl: '', securityToken: '' };
        }
        return {
            actionUrl: configEl.getAttribute('data-action'),
            previewUrl: configEl.getAttribute('data-preview'),
            securityToken: configEl.getAttribute('data-token')
        };
    }

    /**
     * Initialize loading overlay
     */
    function initLoadingOverlay() {
        if ($('#wcs-loading-overlay').length === 0) {
            $(document.body).append(loadingHtml);
        }
    }

    /**
     * Handle AJAX progress animation
     */
    function handleAjaxProgress() {
        initLoadingOverlay();
        $('#wcs-loading-overlay').fadeIn();

        var steps = [
            { id: 'step-token', text: '获取访问令牌' },
            { id: 'step-cover', text: '上传封面图片' },
            { id: 'step-content', text: '处理文章内容' },
            { id: 'step-images', text: '上传文章图片' },
            { id: 'step-submit', text: '提交到公众号' }
        ];

        var currentStep = 0;

        function updateStep(stepIndex) {
            steps.forEach(function(step, index) {
                var li = $('#' + step.id).closest('li');
                li.removeClass('active done');
                if (index < stepIndex) {
                    li.addClass('done');
                } else if (index === stepIndex) {
                    li.addClass('active');
                }
            });
            var progress = ((stepIndex + 1) / steps.length) * 100;
            $('#wcs-progress-bar').css('width', progress + '%');
        }

        updateStep(0);

        var progressInterval = setInterval(function() {
            if (currentStep < steps.length - 1) {
                currentStep++;
                updateStep(currentStep);
            } else {
                clearInterval(progressInterval);
            }
        }, 1500);

        return progressInterval;
    }

    /**
     * Reset loading state
     */
    function resetLoadingState() {
        $('#wcs-progress-bar').css('width', '0%');
        $('.wcs-steps-list li').removeClass('active done');
    }

    /**
     * Show modal dialog
     */
    function wcsShowModal(options) {
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

    /**
     * Alert dialog
     */
    function wcsAlert(message, callback) {
        wcsShowModal({
            type: 'info',
            message: message,
            buttons: [{ text: '确定', action: callback ? 'wcsAlertCallback' : '' }]
        });
        if (callback) window.wcsAlertCallback = callback;
    }

    /**
     * Success dialog
     */
    function wcsSuccess(message, callback) {
        wcsShowModal({
            type: 'success',
            message: message,
            buttons: [{ text: '确定', primary: true, action: callback ? 'wcsSuccessCallback' : '' }]
        });
        if (callback) window.wcsSuccessCallback = callback;
    }

    /**
     * Error dialog
     */
    function wcsError(message, callback) {
        wcsShowModal({
            type: 'error',
            message: message,
            buttons: [{ text: '确定', primary: true, action: callback ? 'wcsErrorCallback' : '' }]
        });
        if (callback) window.wcsErrorCallback = callback;
    }

    /**
     * Confirm dialog
     */
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

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildPreviewDocument(title, content) {
        var previewStyles = '' +
            'html,body{margin:0;padding:0;background:#fff;color:#243228;font-family:-apple-system,BlinkMacSystemFont,"Helvetica Neue","PingFang SC","Microsoft YaHei",sans-serif;}' +
            'body{padding:0;line-height:1.75;word-break:break-word;}' +
            'img{max-width:100%;height:auto;display:block;}' +
            'h1,h2,h3,h4,h5,h6{color:#17241b;line-height:1.4;}' +
            'a{color:#1f7a45;text-decoration:none;}' +
            'blockquote{margin:1em 0;padding:0.75em 1em;border-left:4px solid #8bc79a;background:#f6fbf7;color:#486050;}' +
            'table{width:100%;border-collapse:collapse;display:block;overflow:auto;}' +
            'table th,table td{border:1px solid #dbe7dd;padding:8px 10px;}' +
            'pre{white-space:pre-wrap;word-break:break-word;}' +
            'code{font-family:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace;}' +
            '.wx-shell{min-height:100vh;background:linear-gradient(180deg,#ffffff 0%,#fbfcfb 100%);}' +
            '.wx-topbar{height:44px;display:flex;align-items:center;justify-content:center;position:sticky;top:0;background:rgba(255,255,255,0.96);backdrop-filter:blur(10px);border-bottom:1px solid #edf2ee;font-size:16px;font-weight:600;color:#132118;z-index:2;}' +
            '.wx-topbar::before{content:"‹";position:absolute;left:16px;font-size:24px;line-height:1;color:#223328;}' +
            '.wx-article{padding:22px 22px 32px;}' +
            '.wx-authorbar{display:flex;align-items:center;gap:12px;margin-bottom:18px;}' +
            '.wx-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#35c568,#1d8f47);display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;font-weight:700;box-shadow:0 6px 18px rgba(53,197,104,0.22);}' +
            '.wx-author-meta{display:flex;flex-direction:column;gap:2px;}' +
            '.wx-author-name{font-size:14px;font-weight:700;color:#153020;}' +
            '.wx-author-sub{font-size:12px;color:#8a968e;}' +
            '.wx-follow{margin-left:auto;padding:6px 14px;border-radius:999px;border:1px solid #8fd6a7;background:#f4fff7;color:#1f8f48;font-size:12px;font-weight:700;}' +
            '.code-snippet__fix{display:flex!important;margin:10px 0!important;border:1px solid #e6ece8!important;border-radius:10px!important;background:#f7faf8!important;overflow:hidden!important;}' +
            '.code-snippet__line-index{margin:0!important;padding:14px 10px!important;list-style:none!important;background:#eef4ef!important;color:#7f8f84!important;counter-reset:wcs-line!important;min-width:28px!important;}' +
            '.code-snippet__line-index li{height:20px!important;line-height:20px!important;position:relative!important;}' +
            '.code-snippet__line-index li::before{counter-increment:wcs-line!important;content:counter(wcs-line)!important;font-size:12px!important;display:block!important;text-align:right!important;}' +
            'pre.code-snippet__js{margin:0!important;padding:14px 14px 14px 12px!important;flex:1!important;background:transparent!important;overflow:auto!important;}' +
            'pre.code-snippet__js code{display:block!important;line-height:20px!important;white-space:pre!important;}' +
            '.wcs-preview-title{font-size:26px;line-height:1.35;color:#17241b;margin:0 0 10px;font-weight:700;letter-spacing:0.01em;}' +
            '.wcs-preview-meta{font-size:13px;color:#88958d;margin-bottom:18px;}';

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' +
            title +
            '</title><style>' +
            previewStyles +
            '</style></head><body><div class="wx-shell"><div class="wx-topbar">公众号</div><div class="wx-article"><div class="wx-authorbar"><div class="wx-avatar">微</div><div class="wx-author-meta"><div class="wx-author-name">WeChatSync 预览</div><div class="wx-author-sub">今天 · 预览稿</div></div><div class="wx-follow">关注</div></div><h2 class="wcs-preview-title">' +
            title +
            '</h2><div class="wcs-preview-meta">公众号图文模拟效果</div>' +
            content +
            '</div></div></body></html>';
    }

    function wcsShowPreview(data, onConfirm) {
        var existingModal = document.getElementById('wcs-preview-modal');
        if (existingModal) {
            existingModal.remove();
        }

        var title = escapeHtml(data.title || '未命名文章');
        var content = data.content || '<p>暂无预览内容</p>';
        var previewDocument = buildPreviewDocument(title, content);
        var html = '<div class="wcs-preview-overlay" id="wcs-preview-modal">' +
            '<div class="wcs-preview-shell">' +
            '<div class="wcs-preview-header">' +
            '<div class="wcs-preview-header-main">' +
            '<div class="wcs-preview-eyebrow">公众号预览</div>' +
            '<div class="wcs-preview-heading">发布前确认排版效果</div>' +
            '</div>' +
            '<button type="button" class="wcs-preview-close" data-action="close">×</button>' +
            '</div>' +
            '<div class="wcs-preview-body">' +
            '<div class="wcs-preview-sidebar">' +
            '<div class="wcs-preview-panel">' +
            '<div class="wcs-preview-badge">WeChat Article</div>' +
            '<h3 class="wcs-preview-panel-title">' + title + '</h3>' +
            '<p class="wcs-preview-panel-text">这里预览的是发布前的公众号排版效果。代码块、图片和正文样式会尽量贴近实际图文展示。</p>' +
            '<div class="wcs-preview-panel-note">确认无误后再发布，避免进草稿箱后再回头调排版。</div>' +
            '</div>' +
            '<div class="wcs-preview-actions wcs-preview-actions-sidebar">' +
            '<button type="button" class="wcs-modal-btn wcs-modal-btn-secondary" data-action="close">返回修改</button>' +
            '<button type="button" class="wcs-modal-btn wcs-modal-btn-primary" data-action="publish">确认发布</button>' +
            '</div>' +
            '</div>' +
            '<div class="wcs-preview-phone">' +
            '<div class="wcs-preview-side wcs-preview-side-left"></div>' +
            '<div class="wcs-preview-side wcs-preview-side-left wcs-preview-side-short"></div>' +
            '<div class="wcs-preview-side wcs-preview-side-right"></div>' +
            '<div class="wcs-preview-notch"></div>' +
            '<div class="wcs-preview-screen">' +
            '<div class="wcs-preview-statusbar">' +
            '<span>9:41</span>' +
            '<div class="wcs-preview-status-icons">' +
            '<span class="wcs-signal"></span>' +
            '<span class="wcs-wifi"></span>' +
            '<span class="wcs-battery"></span>' +
            '</div>' +
            '</div>' +
            '<iframe class="wcs-preview-frame" title="公众号预览" referrerpolicy="no-referrer"></iframe>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', html);

        var modal = document.getElementById('wcs-preview-modal');
        var frame = modal.querySelector('.wcs-preview-frame');
        if (frame) {
            frame.srcdoc = previewDocument;
        }
        var buttons = modal.querySelectorAll('[data-action]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].addEventListener('click', function() {
                var action = this.getAttribute('data-action');
                if (action === 'publish' && typeof onConfirm === 'function') {
                    modal.remove();
                    onConfirm();
                    return;
                }
                modal.remove();
            });
        }
    }

    function requestPreview(config, cid, onSuccess) {
        $.ajax({
            url: config.previewUrl,
            type: 'POST',
            data: {
                cid: cid,
                '_': config.securityToken
            },
            success: function(response) {
                var data = response;
                if (typeof response === 'string') {
                    try {
                        data = JSON.parse(response);
                    } catch (e) {
                        wcsError('预览返回格式无效');
                        return;
                    }
                }

                if (!data || !data.content) {
                    wcsError('未生成可用的预览内容');
                    return;
                }

                onSuccess(data);
            },
            error: function(xhr) {
                var errorMsg = xhr.status + ' ' + xhr.statusText;
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.error) {
                        errorMsg = resp.error;
                    }
                } catch (e) {}
                wcsError('预览失败：' + errorMsg);
            }
        });
    }

    // Expose functions globally
    WeChatSync.handleAjaxProgress = handleAjaxProgress;
    WeChatSync.resetLoadingState = resetLoadingState;
    WeChatSync.wcsShowModal = wcsShowModal;
    WeChatSync.wcsAlert = wcsAlert;
    WeChatSync.wcsSuccess = wcsSuccess;
    WeChatSync.wcsError = wcsError;
    WeChatSync.wcsConfirm = wcsConfirm;
    WeChatSync.wcsShowPreview = wcsShowPreview;

    /**
     * Initialize manage posts page
     */
    WeChatSync.initManagePosts = function() {
        initLoadingOverlay();

        $(document).ready(function() {
            var config = getWcsConfig();
            var syncAction = '<li><a href="#" data-action="' + config.actionUrl + '">发布公众号</a></li>';
            $('.dropdown-menu').each(function() {
                $(this).append(syncAction);
            });

            $('.dropdown-menu a[data-action*="do=custom_action"]').on('click', function(e) {
                e.preventDefault();
                var actionUrl = $(this).data('action');
                var cids = [];
                $('.operate-form input[name="cid[]"]:checked').each(function() {
                    cids.push($(this).val());
                });

                if (cids.length === 0) {
                    wcsAlert('请至少选择一篇文章');
                    return;
                }

                var progressInterval = handleAjaxProgress();

                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    data: {
                        cid: cids,
                        '_': config.securityToken
                    },
                    success: function(response) {
                        clearInterval(progressInterval);
                        $('#wcs-progress-bar').css('width', '100%');
                        $('#wcs-current-step').text('同步完成！');
                        setTimeout(function() {
                            $('#wcs-loading-overlay').fadeOut();
                            resetLoadingState();
                            window.location.reload();
                        }, 800);
                    },
                    error: function(xhr) {
                        clearInterval(progressInterval);
                        $('#wcs-loading-overlay').fadeOut();
                        resetLoadingState();
                        var errorMsg = xhr.status + ' ' + xhr.statusText;
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.error) {
                                errorMsg = resp.error;
                            }
                        } catch(e) {}
                        wcsError('操作失败：' + errorMsg);
                    }
                });
            });
        });
    };

    /**
     * Initialize write post page
     */
    WeChatSync.initWritePost = function() {
        initLoadingOverlay();

        $(document).ready(function() {
            var config = getWcsConfig();
            var syncButton = '<button type="button" id="btn-custom-action" class="btn">发布公众号</button>';
            $('#btn-submit').before(syncButton);

            $('#btn-custom-action').on('click', function(e) {
                e.preventDefault();
                var cid = $('input[name="cid"]').val();
                if (!cid) {
                    wcsAlert('文章未保存，请先保存草稿或发布文章');
                    return;
                }

                requestPreview(config, cid, function(preview) {
                    wcsShowPreview(preview, function() {
                        var progressInterval = handleAjaxProgress();

                        $.ajax({
                            url: config.actionUrl,
                            type: 'POST',
                            data: {
                                cid: [cid],
                                '_': config.securityToken
                            },
                            success: function(response) {
                                clearInterval(progressInterval);
                                $('#wcs-progress-bar').css('width', '100%');
                                $('#wcs-current-step').text('同步完成！');
                                setTimeout(function() {
                                    $('#wcs-loading-overlay').fadeOut();
                                    resetLoadingState();
                                    wcsSuccess('已成功发布到公众号');
                                }, 800);
                            },
                            error: function(xhr) {
                                clearInterval(progressInterval);
                                $('#wcs-loading-overlay').fadeOut();
                                resetLoadingState();
                                var errorMsg = xhr.status + ' ' + xhr.statusText;
                                try {
                                    var resp = JSON.parse(xhr.responseText);
                                    if (resp.error) {
                                        errorMsg = resp.error;
                                    }
                                } catch(e) {}
                                wcsError('操作失败：' + errorMsg);
                            }
                        });
                    });
                });
            });
        });
    };
})();
