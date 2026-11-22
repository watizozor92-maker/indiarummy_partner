<?php

declare(strict_types=1);

namespace tests\Unit;

use app\service\HelpDocumentService;
use PHPUnit\Framework\TestCase;
use think\App;

final class HelpDocumentServiceTest extends TestCase
{
    private HelpDocumentService $service;

    protected function setUp(): void
    {
        $this->service = new HelpDocumentService(new App(dirname(__DIR__, 2)));
    }

    public function testCatalogIsFilteredByRole(): void
    {
        self::assertCount(4, $this->service->catalog('platform_admin'));
        self::assertSame(['merchant-admin', 'merchant-viewer', 'faq'], array_column($this->service->catalog('merchant_admin'), 'slug'));
        self::assertSame(['merchant-viewer', 'faq'], array_column($this->service->catalog('merchant_viewer'), 'slug'));
    }

    public function testRoleCannotReadPlatformManual(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->read('platform', 'merchant_admin');
    }

    public function testAllowedDocumentCanBeLoadedFromDocDirectory(): void
    {
        $document = $this->service->read('faq', 'merchant_viewer');
        self::assertSame('常见问题与故障处理.md', $document['filename']);
        self::assertStringContainsString('# 常见问题与故障处理', $document['markdown']);
        self::assertStringContainsString('<h1>常见问题与故障处理</h1>', $document['html']);
    }

    public function testMarkdownIsEscapedAndFormatted(): void
    {
        $html = $this->service->render("# 标题\n\n- `项目`\n\n<script>alert(1)</script>");
        self::assertStringContainsString('<h1>标题</h1>', $html);
        self::assertStringContainsString('<li><code>项目</code></li>', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}
