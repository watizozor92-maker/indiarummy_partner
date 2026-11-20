<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\RoundRepository;
use app\service\ApiResponder;
use app\service\AuditService;
use app\service\CsvExportService;
use app\service\DateRangeService;
use app\service\TenantContext;
use InvalidArgumentException;
use think\Request;
use think\Response;

final class Rounds
{
    private const EXPORT_LIMIT = 10000;

    public function index(Request $request, TenantContext $tenant, DateRangeService $dates, RoundRepository $repository, ApiResponder $api): Response
    {
        try {
            $range = $dates->parse($request->get('from'), $request->get('to'), 7, 92);
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        }
        $rawChannelId = $request->get('channelId');
        $channelId = $tenant->resolveChannelId($rawChannelId === null || $rawChannelId === '' ? null : (int) $rawChannelId);
        $filters = [
            'from' => $range['fromSql'], 'to' => $range['toSql'],
            'uid' => $request->get('uid'), 'gameId' => $request->get('gameId'),
            'currencyCode' => trim((string) $request->get('currencyCode', '')),
            'roundId' => $request->get('roundId'), 'sceneName' => trim((string) $request->get('sceneName', '')),
            'modeName' => trim((string) $request->get('modeName', '')),
            'minimumBet' => $request->get('minimumBet') === null || $request->get('minimumBet') === '' ? null : (float) $request->get('minimumBet'),
            'minimumPayout' => $request->get('minimumPayout') === null || $request->get('minimumPayout') === '' ? null : (float) $request->get('minimumPayout'),
        ];

        $result = $repository->paginate($channelId, $filters, max(1, (int) $request->get('page', 1)), min(100, max(1, (int) $request->get('pageSize', 20))));
        foreach ($result['items'] as &$item) unset($item['cash_before'], $item['cash_after']);
        return $api->success($result);
    }

    public function detail(int $id, Request $request, TenantContext $tenant, RoundRepository $repository, ApiResponder $api, AuditService $audit): Response
    {
        $rawChannelId = $request->get('channelId');
        $channelId = $tenant->resolveChannelId($rawChannelId === null || $rawChannelId === '' ? null : (int) $rawChannelId);
        $round = $repository->find($channelId, $id);

        if ($round !== null) {
            $actor=$tenant->user();
            $audit->record('round.detail.view','success',(string)$request->ip(),(string)$request->header('user-agent',''),(int)$actor['id'],(int)$actor['channel_id'],(string)$actor['role_code'],'inventory_ledger',(string)$id,'',null,null,['targetChannelId'=>$channelId,'roundId'=>$round['round_id']??0]);
            unset($round['cash_before'], $round['cash_after'], $round['experience_before'], $round['experience_after']);
        }

        return $round === null
            ? $api->error('RESOURCE_NOT_FOUND', '游戏记录不存在或不可见', 404)
            : $api->success($round);
    }

    public function export(Request $request, TenantContext $tenant, DateRangeService $dates, RoundRepository $repository, ApiResponder $api, CsvExportService $csv, AuditService $audit): Response
    {
        try {
            $range = $dates->parse($request->get('from'), $request->get('to'), 7, 92);
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        }
        $rawChannelId = $request->get('channelId');
        $channelId = $tenant->resolveChannelId($rawChannelId === null || $rawChannelId === '' ? null : (int) $rawChannelId);
        $filters = $this->filters($request, $range);
        $first = $repository->paginate($channelId, $filters, 1, 100);
        if ((int) $first['total'] > self::EXPORT_LIMIT) {
            return $api->error('EXPORT_LIMIT_EXCEEDED', '导出结果超过 10000 条记录，请缩小筛选范围', 422, ['total' => (int) $first['total'], 'limit' => self::EXPORT_LIMIT]);
        }
        $items = $first['items'];
        $pages = (int) ceil((int) $first['total'] / 100);
        for ($page = 2; $page <= $pages; $page++) {
            $result = $repository->paginate($channelId, $filters, $page, 100);
            array_push($items, ...$result['items']);
        }
        $rows = array_map(static fn (array $item): array => [$item['id'], $item['channel_id'], $item['round_id'], $item['uid'], $item['playername'], $item['game_id'], $item['title'], $item['currency_code'], $item['bet'], $item['payout'], $item['profit'], $item['win_multiplier'], $item['scene_name'], $item['mode_name'], $item['event_type'], $item['create_time']], $items);
        $actor = $tenant->user();
        $audit->record('rounds.export', 'success', (string) $request->ip(), (string) $request->header('user-agent', ''), (int) $actor['id'], (int) $actor['channel_id'], (string) $actor['role_code'], 'inventory_ledger', '', '', null, null, ['targetChannelId' => $channelId, 'rowCount' => count($rows), 'from' => $range['fromSql'], 'to' => $range['toSql']]);

        return $csv->download('rounds-' . date('Ymd-His') . '.csv', ['账本ID', '渠道ID', '局号', 'UID', '玩家名称', '游戏ID', '游戏名称', '币种', '投注', '派彩', '渠道利润', '中奖倍数', '场景', '模式', '事件类型', '时间'], $rows);
    }

    private function filters(Request $request, array $range): array
    {
        return [
            'from' => $range['fromSql'], 'to' => $range['toSql'],
            'uid' => $request->get('uid'), 'gameId' => $request->get('gameId'),
            'currencyCode' => trim((string) $request->get('currencyCode', '')),
            'roundId' => $request->get('roundId'), 'sceneName' => trim((string) $request->get('sceneName', '')),
            'modeName' => trim((string) $request->get('modeName', '')),
            'minimumBet' => $request->get('minimumBet') === null || $request->get('minimumBet') === '' ? null : (float) $request->get('minimumBet'),
            'minimumPayout' => $request->get('minimumPayout') === null || $request->get('minimumPayout') === '' ? null : (float) $request->get('minimumPayout'),
        ];
    }
}
