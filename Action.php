<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once dirname(__FILE__) . '/Library/SyncRenderer.php';

class WeChatSync_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $this->widget('Widget_User')->pass('editor');

        $cids = $this->request->filter('int')->getArray('cid');

        if (!empty($cids) && $this->request->get('do') === 'custom_action') {
            $successCount = 0;
            $errors = [];
            foreach ($cids as $cid) {
                try {
                    SyncRenderer::render($cid);
                    $successCount++;
                } catch (Exception $e) {
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
