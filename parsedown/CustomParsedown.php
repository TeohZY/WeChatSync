<?php
require 'Parsedown.php';

class CustomParsedown extends Parsedown
{
    protected function blockHeader($Line)
    {
        $Block = parent::blockHeader($Line);
        if ($Block !== null) {
            $level = $Block['element']['name']; // 获取h1-h6
            $Block['element']['attributes']['class'] = "custom-{$level}";

            switch ($level) {
                case 'h1':
                    $Block['element']['attributes']['style'] = '
                        font-size: 1.7em;
                        font-weight: normal;
                        border-bottom: 2px solid hsl(216, 100%, 68%);
                        margin-top: 30px;
                        margin-bottom: 15px;
                    ';
                    $Block['element']['text'] = '<span class="prefix" style="display: none;"></span><span class="content" style="
                        background: hsl(216, 100%, 68%);
                        color: white;
                        padding: 3px 10px;
                        border-top-right-radius: 3px;
                        border-top-left-radius: 3px;
                        margin-right: 3px;
                        margin-top: 30px;
                        margin-bottom: 15px;
                    ">' . $Block['element']['text'] . '</span><span class="suffix"></span>';
                    break;

                case 'h2':
                    $Block['element']['attributes']['style'] = '
                        font-weight: normal;
                        color: #333;
                        font-size: 1.4em;
                        border-bottom: 1px solid hsl(216, 100%, 68%);
                        margin-top: 30px;
                        margin-bottom: 15px;
                    ';
                    $Block['element']['text'] = '<span class="prefix" style="display: none;"></span><span class="content" style="border-bottom: 1px solid hsl(216, 100%, 68%);">' . $Block['element']['text'] . '</span><span class="suffix"></span>';
                    break;

                case 'h3':
                    $Block['element']['attributes']['style'] = '
                        font-weight: normal;
                        color: #333;
                        font-size: 1.2em;
                        margin-top: 30px;
                        margin-bottom: 15px;
                    ';
                    break;

                case 'h4': // 摘要
                    $Block['element']['attributes']['style'] = '
                        font-weight: normal;
                        font-size: 1em;
                        width: 80%;
                        border: 1px solid hsl(216, 100%, 68%);
                        border-top: 4px solid hsl(216, 100%, 68%);
                        padding: 10px;
                        margin: 30px auto;
                        color: #333;
                        margin-top: 30px;
                        margin-bottom: 15px;
                    ';
                    break;

                case 'h5': // 强调
                    $Block['element']['attributes']['style'] = '
                        font-weight: normal;
                        font-size: 1.3em;
                        text-align: center;
                        background: hsl(216, 100%, 68%);
                        border: 3px double #fff;
                        width: 80%;
                        padding: 10px;
                        margin: 30px auto;
                        color: #fff;
                        margin-top: 30px;
                        margin-bottom: 15px;
                    ';
                    break;

                case 'h6': // 序号
                    $Block['element']['attributes']['style'] = '
                        font-size: 1.5em;
                        font-weight: normal;
                        color: hsl(216, 100%, 68%);
                        border-bottom: 1px solid hsl(216, 100%, 68%);
                        margin-top: 30px;
                        margin-bottom: 15px;
                    ';
                    break;
            }
        }
        return $Block;
    }
    protected function paragraph($Line)
    {
        // 调用父类方法获取默认的段落结构
        $Block = parent::paragraph($Line);
        if ($Block !== null) {
            $Block['element']['attributes']['style'] = '
            font-size: 16px; padding-top: 8px; padding-bottom: 8px; margin: 0; line-height: 26px; color: #666
            ';
        }
        return $Block;
    }
    
    protected function blockQuote($Line)
    {
        $Block = parent::blockQuote($Line);
        if ($Block !== null) {
            $Block['element']['attributes']['class'] = 'custom-blockquote';
            $Block['element']['attributes']['style'] = '
                background: #f9f9f9;
                border-left: 4px solid hsl(216, 100%, 68%);
                padding: 10px;
            ';
        }
        return $Block;
    }
    
    protected function inlineCode($Line, $Block = null)
    {
        $Block = parent::inlineCode($Line, $Block);
        if ($Block !== null) {
            // 这里可以根据需要添加自定义的 CSS 类
            if (isset($Block['element']['attributes']['class']) && $Block['element']['attributes']['class'] === 'inline-code') {
                $Block['element']['attributes']['class'] = 'custom-inline-code'; // p 标签内的 code
                $Block['element']['attributes']['style'] = '
                font-size: 14px;
                word-wrap: break-word;
                padding: 2px 4px;
                border-radius: 4px;
                margin: 0 2px;
                color: #1e6bb8;
                background-color: rgba(27, 31, 35, .05);
                font-family: Operator Mono, Consolas, Monaco, Menlo, monospace;
                word-break: break-all;
                color: hsl(216, 100%, 68%);
            ';
            } else {
                $Block['element']['attributes']['class'] = 'custom-block-code'; // 非 p 标签内的 code
            }
            $Block['element']['attributes']['style'] = '
                font-size: 14px;
                word-wrap: break-word;
                padding: 2px 4px;
                border-radius: 4px;
                margin: 0 2px;
                color: #1e6bb8;
                background-color: rgba(27, 31, 35, .05);
                font-family: Operator Mono, Consolas, Monaco, Menlo, monospace;
                word-break: break-all;
                color: hsl(216, 100%, 68%);
            ';
        }
        return $Block;
    }

    protected function blockList($Block)
    {
        $Block = parent::blockList($Block);
        
        if ($Block !== null && $Block['element']['name'] == 'ol') {
            $Block['element']['name'] = 'ol';  // 保持为有序列表
            $Block['element']['attributes']['style'] = '
                margin-top: 8px; margin-bottom: 8px; color: black; list-style-type: decimal; padding-left: 2em;
            ';
        }

        if ($Block !== null && $Block['element']['name'] == 'ul') {
            $Block['element']['name'] = 'ul'; 
            $Block['element']['attributes']['style'] = '
                margin-top: 8px; margin-bottom: 8px; color: black; list-style-type: disc; padding-left: 2em;
            ';
        }
    
        if (isset($Block)) {
            // 自定义 <li> 标签的 HTML 属性
            $Block['li']['attributes'] = [
                'style' => 'color: #666;'
            ];
        }
        return $Block;
    }
    

    protected function lines(array $lines)
    {
        $Block = parent::lines($lines);
        if ($Block !== null) {
            // 正则表达式匹配 <li> 标签及其内容
                $pattern = '/<li([^>]*)>(.*?)<\/li>/s';
                // 替换函数，在 <li> 的内容外层添加 <section>
                $callback = function ($matches) {
                    $attributes = $matches[1]; // <li> 的属性（如 class 和 style）
                    $content = $matches[2];    // <li> 的内容
                    return "<li$attributes><section style='margin-top: 5px; margin-bottom: 5px; line-height: 26px; text-align: left; color: rgb(1,1,1); font-weight: 500;'>
                        $content
                    </section></li>";
                };
                // 使用 preg_replace_callback 进行替换
                $Block = preg_replace_callback($pattern, $callback, $Block);
        }
        return $Block;
    }

    protected function blockTable($Line, array $Block = null)
    {
        $Block = parent::blockTable($Line, $Block);

        return $Block;
    }

    protected function blockTableContinue($Line, array $Block){

        $Block = parent::blockTableContinue($Line, $Block);
                if (isset($Block['element'])) {
            $Block['element']['attributes']['style'] = 'display: table; text-align: left; margin: 1.5em auto; width: auto;';
            // 自定义 tr 的样式
            foreach ($Block['element']['text'] as &$section) {
                // 检查是否是 thead 或 tbody
                if (isset($section['name']) && ($section['name'] === 'thead' || $section['name'] === 'tbody')) {
                    if($section['name'] === 'tbody'){
                        $section['attributes'] = ['style' => 'border: 0;'];
                    }
                    // 遍历 thead 或 tbody 中的 tr
                    foreach ($section['text'] as &$row) {
                        if (isset($row['name']) && $row['name'] === 'tr') {
                            // 自定义 tr 的样式
                            $row['attributes'] = ['style' => 'border: 0; border-top: 1px solid #ccc; background-color: white;'];
            
                            // 遍历 tr 中的 td 或 th
                            foreach ($row['text'] as &$cell) {
                                if (isset($cell['name']) && ($cell['name'] === 'td' || $cell['name'] === 'th')) {
                                    // 自定义 td 或 th 的样式
                                    $cell['attributes'] = ['style' => 'font-size: 16px; border: 1px solid #ccc; padding: 5px 10px; color: #666; text-align: center;'];
                                }
                            }
                        }
                    }
                }
            }
        }
        return $Block;
    }

    protected function inlineEmphasis($Excerpt)
    {
        if (!isset($Excerpt['text'][1])) {
            return;
        }

        $marker = $Excerpt['text'][0];

        if ($Excerpt['text'][1] === $marker && preg_match($this->StrongRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'strong';
        } elseif (preg_match($this->EmRegex[$marker], $Excerpt['text'], $matches)) {
            $emphasis = 'em';
        } else {
            return;
        }

        $result = [
            'extent' => strlen($matches[0]),
            'element' => [
                'name' => $emphasis,
                'handler' => 'line',
                'text' => $matches[1],
            ],
        ];

        if ($emphasis === 'strong') {
            $result['element']['attributes'] = [
                'style' => 'color: hsl(216, 80%, 44%);',
            ];
        }

        return $result;
    }
    protected function inlineLink($Excerpt)
    {
        $Inline = parent::inlineLink($Excerpt);
        if ($Inline !== null) {
            $text = $Excerpt['text'];
            $context = $Excerpt['context'];
            if( '!' . $text === $context){
                return $Inline;
            }
            // 处理超链接
            $title = $Inline['element']['text'];
            $url = $Inline['element']['attributes']['href'];
    
            // 将 a 标签替换为 "标题：链接" 的格式
            $Inline['element'] = [
                'name' => 'span', // 使用 span 标签（或者直接返回纯文本）
                'text' => $title . ': ' . $url,
                'attributes' => [
                    'style' => '
                    color: hsl(187, 100%, 45%);
                    font-weight: normal;
                    border-bottom: 1px solid hsl(187, 100%, 45%);
                '
                ],
            ];
        }
        return $Inline;
    }
}
?>
