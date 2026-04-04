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
        '<li><span class="wcs-step-icon">1</span><span id="step-token">获取访问令牌</span></li>' +
        '<li><span class="wcs-step-icon">2</span><span id="step-cover">上传封面图片</span></li>' +
        '<li><span class="wcs-step-icon">3</span><span id="step-content">处理文章内容</span></li>' +
        '<li><span class="wcs-step-icon">4</span><span id="step-images">上传文章图片</span></li>' +
        '<li><span class="wcs-step-icon">5</span><span id="step-submit">提交到公众号</span></li>' +
        '</ul>' +
        '</div>' +
        '</div>';

    /**
     * Get config from hidden div
     */
    function getWcsConfig() {
        var configEl = document.getElementById('wcs-config');
        if (!configEl) {
            return { actionUrl: '', securityToken: '' };
        }
        return {
            actionUrl: configEl.getAttribute('data-action'),
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

    // Expose functions globally
    WeChatSync.handleAjaxProgress = handleAjaxProgress;
    WeChatSync.resetLoadingState = resetLoadingState;
    WeChatSync.wcsShowModal = wcsShowModal;
    WeChatSync.wcsAlert = wcsAlert;
    WeChatSync.wcsSuccess = wcsSuccess;
    WeChatSync.wcsError = wcsError;
    WeChatSync.wcsConfirm = wcsConfirm;

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

                wcsConfirm('确认发布到公众号吗？', function() {
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
    };
})();
