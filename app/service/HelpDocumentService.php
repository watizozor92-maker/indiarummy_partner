<?php

declare(strict_types=1);

namespace app\service;

use InvalidArgumentException;
use think\App;

final class HelpDocumentService
{
    private const DOCUMENTS = [
        'platform' => ['title' => '平台管理员操作手册', 'file' => '平台管理员操作手册.md', 'roles' => ['platform_admin']],
        'merchant-admin' => ['title' => '商户管理员操作手册', 'file' => '商户管理员操作手册.md', 'roles' => ['platform_admin', 'merchant_admin']],
        'merchant-viewer' => ['title' => '商户只读账号操作手册', 'file' => '商户只读账号操作手册.md', 'roles' => ['platform_admin', 'merchant_admin', 'merchant_viewer']],
        'faq' => ['title' => '常见问题与故障处理', 'file' => '常见问题与故障处理.md', 'roles' => ['platform_admin', 'merchant_admin', 'merchant_viewer']],
    ];

    public function __construct(private readonly App $app) {}

    public function catalog(string $role): array
    {
        $items = [];
        foreach (self::DOCUMENTS as $slug => $document) {
            if (!in_array($role, $document['roles'], true)) continue;
            $items[] = ['slug' => $slug, 'title' => $document['title']];
        }
        return $items;
    }

    public function read(string $slug, string $role): array
    {
        $document = self::DOCUMENTS[$slug] ?? null;
        if (!is_array($document) || !in_array($role, $document['roles'], true)) {
            throw new InvalidArgumentException('帮助文档不存在或当前账号无权查看');
        }
        $path = $this->app->getRootPath() . 'doc' . DIRECTORY_SEPARATOR . $document['file'];
        $markdown = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($markdown)) throw new InvalidArgumentException('帮助文档暂不可用');
        return [
            'slug' => $slug,
            'title' => $document['title'],
            'filename' => $document['file'],
            'markdown' => $markdown,
            'html' => $this->render($markdown),
        ];
    }

    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/u', str_replace("\t", '    ', $markdown)) ?: [];
        $html = [];
        $paragraph = [];
        $list = null;
        $inCode = false;
        $code = [];
        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph === []) return;
            $html[] = '<p>' . $this->inline(implode(' ', $paragraph)) . '</p>';
            $paragraph = [];
        };
        $closeList = static function () use (&$list, &$html): void {
            if ($list === null) return;
            $html[] = '</' . $list . '>';
            $list = null;
        };

        foreach ($lines as $line) {
            if (preg_match('/^```/', $line) === 1) {
                $flushParagraph(); $closeList();
                if ($inCode) {
                    $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
                    $code = []; $inCode = false;
                } else $inCode = true;
                continue;
            }
            if ($inCode) { $code[] = $line; continue; }
            if (trim($line) === '') { $flushParagraph(); $closeList(); continue; }
            if (preg_match('/^(#{1,4})\s+(.+)$/u', $line, $match) === 1) {
                $flushParagraph(); $closeList(); $level = strlen($match[1]);
                $html[] = '<h' . $level . '>' . $this->inline($match[2]) . '</h' . $level . '>';
                continue;
            }
            if (preg_match('/^[-*]\s+(.+)$/u', $line, $match) === 1) {
                $flushParagraph();
                if ($list !== 'ul') { $closeList(); $list = 'ul'; $html[] = '<ul>'; }
                $html[] = '<li>' . $this->inline($match[1]) . '</li>';
                continue;
            }
            if (preg_match('/^\d+\.\s+(.+)$/u', $line, $match) === 1) {
                $flushParagraph();
                if ($list !== 'ol') { $closeList(); $list = 'ol'; $html[] = '<ol>'; }
                $html[] = '<li>' . $this->inline($match[1]) . '</li>';
                continue;
            }
            $paragraph[] = trim($line);
        }
        if ($inCode) $html[] = '<pre><code>' . htmlspecialchars(implode("\n", $code), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
        $flushParagraph(); $closeList();
        return implode("\n", $html);
    }

    private function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;
        return preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
    }
}
