<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\OverviewRepository;
use app\service\ApiResponder;
use app\service\DateRangeService;
use app\service\TenantContext;
use InvalidArgumentException;
use think\Request;
use think\Response;

final class Overview
{
    public function index(Request $request, TenantContext $tenant, DateRangeService $dates, OverviewRepository $repository, ApiResponder $api): Response
    {
        try {
            $range = $dates->parse($request->get('from'), $request->get('to'), 7);
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        }
        $rawChannelId = $request->get('channelId');
        $channelId = $tenant->resolveChannelId($rawChannelId === null || $rawChannelId === '' ? null : (int) $rawChannelId);
        $currency = trim((string) $request->get('currencyCode', '')) ?: null;
        $gameId = (int) $request->get('gameId', 0) ?: null;

        return $api->success([
            'summary' => $repository->summary($channelId, $range['fromSql'], $range['toSql'], $currency, $gameId),
            'trend' => $repository->trend($channelId, $range['fromSql'], $range['toSql'], $currency, $gameId),
            'meta' => ['from' => $range['from']->format(DATE_ATOM), 'to' => $range['to']->format(DATE_ATOM), 'timezone' => $range['timezone'], 'channelId' => $channelId],
        ]);
    }
}
