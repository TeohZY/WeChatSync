<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once dirname(__FILE__) . '/Library/SyncRenderer.php';

class WeChatSync_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $this->widget('Widget_User')->pass('editor');

        $cids = $this->request->filter('int')->getArray('cid');

        if ($this->request->get('do') === 'preview') {
            $cid = intval($this->request->get('cid'));
            try {
                $preview = SyncRenderer::preview($cid);
                $this->response->setStatus(200);
                echo json_encode($preview);
            } catch (Throwable $e) {
                error_log(sprintf(
                    '[WeChatSync] preview cid=%d error=%s file=%s line=%d',
                    $cid,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ));
                $this->response->setStatus(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            return;
        }

        if (!empty($cids) && $this->request->get('do') === 'custom_action') {
            $successCount = 0;
            $errors = [];
            foreach ($cids as $cid) {
                try {
                    SyncRenderer::render($cid);
                    $successCount++;
                } catch (Throwable $e) {
                    error_log(sprintf(
                        '[WeChatSync] cid=%d error=%s file=%s line=%d',
                        $cid,
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    ));
                    $errors[] = '文章 ' . $cid . '：' . $e->getMessage();
                }
            }

            if (empty($errors)) {
                $this->widget('Widget_Notice')->set(_t('已对 %d 篇文章执行自定义操作', $successCount), 'success');
                $this->response->goBack();
            } else {
                $errorMsg = implode('；', $errors);
                $this->response->setStatus(500);
                echo json_encode(['error' => $errorMsg]);
                return;
            }
        } else {
            $this->widget('Widget_Notice')->set(_t('请选择文章'), 'error');
            $this->response->goBack();
        }
    }
}
