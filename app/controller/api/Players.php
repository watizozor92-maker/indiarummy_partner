<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\PlayerRepository;
use app\service\ApiResponder;
use app\service\AuditService;
use app\service\CsvExportService;
use app\service\DateRangeService;
use app\service\TenantContext;
use InvalidArgumentException;
use think\Request;
use think\Response;

final class Players
{
    private const EXPORT_LIMIT = 5000;

    public function index(Request $request, TenantContext $tenant, DateRangeService $dates, PlayerRepository $repository, ApiResponder $api): Response
    {
        $channelId = $this->requiredChannel($request, $tenant, $api);
        if ($channelId instanceof Response) return $channelId;
        try {
            $range = $dates->parse($request->get('from'), $request->get('to'), 30);
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        }
        $page = max(1, (int) $request->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));
        $statusValue = $request->get('status');

        return $api->success($repository->paginate(
            $channelId,
            trim((string) $request->get('keyword', '')),
            $statusValue === null || $statusValue === '' ? null : (int) $statusValue,
            $page,
            $pageSize,
            (string) $request->get('sort', 'lastActiveAt'),
            (string) $request->get('direction', 'desc'),
            $range['fromSql'],
            $range['toSql'],
            trim((string) $request->get('currencyCode', '')) ?: null
        ));
    }

    public function detail(int $uid, Request $request, TenantContext $tenant, DateRangeService $dates, PlayerRepository $repository, ApiResponder $api, AuditService $audit): Response
    {
        $channelId = $this->requiredChannel($request, $tenant, $api);
        if ($channelId instanceof Response) return $channelId;
        try {
            $range = $dates->parse($request->get('from'), $request->get('to'), 30);
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        }
        $player = $repository->find($channelId, $uid, $range['fromSql'], $range['toSql'], trim((string) $request->get('currencyCode', '')) ?: null);
        if ($player !== null) {
            $player['analytics_meta'] = ['from' => $range['from']->format(DATE_ATOM), 'to' => $range['to']->format(DATE_ATOM), 'timezone' => $range['timezone']];
        }

        if ($player !== null) {
            $actor=$tenant->user();
            $audit->record('player.detail.view','success',(string)$request->ip(),(string)$request->header('user-agent',''),(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'player',(string)$uid,'',null,null,['targetChannelId'=>$channelId]);
        }

        return $player === null
            ? $api->error('RESOURCE_NOT_FOUND', '玩家不存在或不属于当前商户', 404)
            : $api->success($player);
    }

    public function export(Request $request, TenantContext $tenant, DateRangeService $dates, PlayerRepository $repository, ApiResponder $api, CsvExportService $csv, AuditService $audit): Response
    {
        $channelId = $this->requiredChannel($request, $tenant, $api);
        if ($channelId instanceof Response) return $channelId;
        try {
            $range = $dates->parse($request->get('from'), $request->get('to'), 30);
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        }

        $statusValue = $request->get('status');
        $args = [
            $channelId,
            trim((string) $request->get('keyword', '')),
            $statusValue === null || $statusValue === '' ? null : (int) $statusValue,
            (string) $request->get('sort', 'lastActiveAt'),
            (string) $request->get('direction', 'desc'),
            $range['fromSql'],
            $range['toSql'],
            trim((string) $request->get('currencyCode', '')) ?: null,
        ];
        $first = $repository->paginate($args[0], $args[1], $args[2], 1, 100, $args[3], $args[4], $args[5], $args[6], $args[7]);
        if ((int) $first['total'] > self::EXPORT_LIMIT) {
            return $api->error('EXPORT_LIMIT_EXCEEDED', '导出结果超过 5000 名玩家，请缩小筛选范围', 422, ['total' => (int) $first['total'], 'limit' => self::EXPORT_LIMIT]);
        }
        $items = $first['items'];
        $pages = (int) ceil((int) $first['total'] / 100);
        for ($page = 2; $page <= $pages; $page++) {
            $result = $repository->paginate($args[0], $args[1], $args[2], $page, 100, $args[3], $args[4], $args[5], $args[6], $args[7]);
            array_push($items, ...$result['items']);
        }

        $rows = [];
        foreach ($items as $item) {
            $stats = $item['period_stats'] ?: [['currency_code' => '', 'bet_amount' => '', 'payout_amount' => '', 'profit_amount' => '', 'spin_count' => '', 'last_play_at' => '']];
            foreach ($stats as $stat) {
                $rows[] = [$item['uid'], $item['playername'], $item['coin'], $item['status'], $item['create_time'], $item['login_time'], $item['lastgameid'], $stat['currency_code'], $stat['bet_amount'], $stat['payout_amount'], $stat['profit_amount'], $stat['spin_count'], $stat['last_play_at']];
            }
        }
        $this->auditExport($audit, $tenant, $request, 'players.export', $channelId, count($rows), $range['fromSql'], $range['toSql']);

        return $csv->download('players-' . date('Ymd-His') . '.csv', ['UID', '玩家名称', '余额', '状态', '注册时间', '最后登录', '最后游戏ID', '币种', '周期投注', '周期派彩', '周期利润', '局数', '最后游戏时间'], $rows);
    }

    private function auditExport(AuditService $audit, TenantContext $tenant, Request $request, string $action, int $channelId, int $rowCount, string $from, string $to): void
    {
        $actor = $tenant->user();
        $audit->record($action, 'success', (string) $request->ip(), (string) $request->header('user-agent', ''), (int) $actor['id'], (int) $actor['channel_id'], (string) $actor['role_code'], 'player', '', '', null, null, ['targetChannelId' => $channelId, 'rowCount' => $rowCount, 'from' => $from, 'to' => $to]);
    }

    private function requiredChannel(Request $request, TenantContext $tenant, ApiResponder $api): int|Response
    {
        $rawChannelId = $request->get('channelId');
        $channelId = $tenant->resolveChannelId($rawChannelId === null || $rawChannelId === '' ? null : (int) $rawChannelId);
        return $channelId === null
            ? $api->error('VALIDATION_FAILED', '平台管理员查询玩家时必须选择商户', 422)
            : $channelId;
    }
}
