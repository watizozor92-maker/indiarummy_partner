<?php

declare(strict_types=1);

namespace app\controller\api;

use app\repository\OverviewRepository;
use app\repository\StatisticsRepository;
use app\service\ApiResponder;
use app\service\AuditService;
use app\service\CsvExportService;
use app\service\DateRangeService;
use app\service\TenantContext;
use InvalidArgumentException;
use think\Request;
use think\Response;

final class Statistics
{
    public function index(Request $request,TenantContext $tenant,DateRangeService $dates,OverviewRepository $overview,StatisticsRepository $statistics,ApiResponder $api):Response
    {
        try{$range=$dates->parse($request->get('from'),$request->get('to'),30);}catch(InvalidArgumentException $e){return $api->error('VALIDATION_FAILED',$e->getMessage(),422);}
        $rawChannel=$request->get('channelId');$channel=$tenant->resolveChannelId($rawChannel===null||$rawChannel===''?null:(int)$rawChannel);
        $currency=trim((string)$request->get('currencyCode',''))?:null;
        $data=['summary'=>$overview->summary($channel,$range['fromSql'],$range['toSql'],$currency,null),'games'=>$statistics->games($channel,$range['fromSql'],$range['toSql'],$currency),'meta'=>['from'=>$range['from']->format(DATE_ATOM),'to'=>$range['to']->format(DATE_ATOM),'timezone'=>$range['timezone']]];
        if($tenant->isPlatformAdmin())$data['merchants']=$statistics->merchants($range['fromSql'],$range['toSql'],$currency);
        return $api->success($data);
    }

    public function export(Request $request, TenantContext $tenant, DateRangeService $dates, StatisticsRepository $statistics, ApiResponder $api, CsvExportService $csv, AuditService $audit): Response
    {
        try {
            $range = $dates->parse($request->get('from'), $request->get('to'), 30);
        } catch (InvalidArgumentException $exception) {
            return $api->error('VALIDATION_FAILED', $exception->getMessage(), 422);
        }
        $rawChannel = $request->get('channelId');
        $channelId = $tenant->resolveChannelId($rawChannel === null || $rawChannel === '' ? null : (int) $rawChannel);
        $currency = trim((string) $request->get('currencyCode', '')) ?: null;
        $games = $statistics->games($channelId, $range['fromSql'], $range['toSql'], $currency);
        $rows = array_map(static fn (array $row): array => [
            $channelId ?? '全部', $row['game_id'], $row['title'], $row['currency_code'],
            $row['bet_amount'], $row['payout_amount'], $row['profit_amount'], $row['actual_rtp'],
            $row['payout_hit_rate'], $row['net_win_hit_rate'], $row['player_count'], $row['spin_count'], $row['max_payout'],
            $range['fromSql'], $range['toSql'],
        ], $games);
        $actor = $tenant->user();
        $audit->record('statistics.export', 'success', (string) $request->ip(), (string) $request->header('user-agent', ''), (int) $actor['id'], (int) $actor['channel_id'], (string) $actor['role_code'], 'statistics', '', '', null, null, ['targetChannelId' => $channelId, 'rowCount' => count($rows), 'from' => $range['fromSql'], 'to' => $range['toSql']]);

        return $csv->download('statistics-' . date('Ymd-His') . '.csv', ['渠道ID', '游戏ID', '游戏名称', '币种', '投注', '派彩', '渠道利润', '实际RTP', '派彩局命中率', '净赢局命中率', '玩家数', '局数', '最高派彩', '开始时间', '结束时间'], $rows);
    }
}
