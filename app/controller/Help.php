<?php

declare(strict_types=1);

namespace app\controller;

use app\service\CsrfTokenService;
use app\service\HelpDocumentService;
use app\service\TenantContext;
use InvalidArgumentException;
use think\Request;
use think\Response;

final class Help
{
    public function index(Request $request, TenantContext $tenant, CsrfTokenService $csrf,
        HelpDocumentService $documents): Response
    {
        $actor = $tenant->user();
        $catalog = $documents->catalog((string) $actor['role_code']);
        $slug = trim((string) $request->get('document', $catalog[0]['slug'] ?? 'faq'));
        try { $document = $documents->read($slug, (string) $actor['role_code']); }
        catch (InvalidArgumentException) { $document = $documents->read($catalog[0]['slug'] ?? 'faq', (string) $actor['role_code']); }
        return view('help/index', [
            'identity' => $actor,
            'logout_token' => $csrf->issue('logout'),
            'activeMenu' => 'help',
            'documents' => $catalog,
            'document' => $document,
        ]);
    }

    public function download(string $slug, TenantContext $tenant, HelpDocumentService $documents): Response
    {
        try { $document = $documents->read($slug, (string) $tenant->user()['role_code']); }
        catch (InvalidArgumentException $e) { return response($e->getMessage(), 404); }
        return response($document['markdown'], 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($document['filename']),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
