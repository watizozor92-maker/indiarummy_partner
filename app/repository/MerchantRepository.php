<?php

declare(strict_types=1);

namespace app\repository;

use think\facade\Db;

final class MerchantRepository
{
    public function paginate(string $keyword, ?int $status, int $page, int $pageSize): array
    {
        $query = Db::connect('game')->table('d_channel_parter');
        if ($keyword !== '') {
            $query->whereLike('agency_uid|show_name', '%' . $keyword . '%');
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $items = $query
            ->field('id,agency_uid,show_name,back_rate,currency_code,credit_limit,purchase_limit,used_coin,status,create_time,update_time,apikey')
            ->order('id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $channelIds = array_map(static fn (array $item): int => (int) $item['id'], $items);
        $userCounts = [];
        if ($channelIds !== []) {
            $counts = Db::connect('game')->table('d_user')
                ->whereIn('channel_agency', $channelIds)
                ->fieldRaw('channel_agency, COUNT(*) AS user_count')
                ->group('channel_agency')
                ->select()
                ->toArray();
            foreach ($counts as $count) {
                $userCounts[(int) $count['channel_agency']] = (int) $count['user_count'];
            }
        }

        foreach ($items as &$item) {
            $item['user_count'] = $userCounts[(int) $item['id']] ?? 0;
            $item['api_key_masked'] = $this->maskSecret((string) ($item['apikey'] ?? ''));
            unset($item['apikey']);
        }

        return ['items' => $items, 'page' => $page, 'pageSize' => $pageSize, 'total' => $total];
    }

    public function find(int $channelId): ?array
    {
        $merchant = Db::connect('game')->table('d_channel_parter')->where('id', $channelId)->find();
        if (!is_array($merchant)) {
            return null;
        }

        $merchant['api_key_masked'] = $this->maskSecret((string) ($merchant['apikey'] ?? ''));
        unset($merchant['apikey']);
        $merchant['user_count'] = Db::connect('game')->table('d_user')->where('channel_agency', $channelId)->count();
        $merchant['currencies'] = Db::connect('game')->table('s_channel_currency')->alias('cc')
            ->leftJoin('s_currency_profile cp', 'cp.currency_code = cc.currency_code')
            ->where('cc.channel_id', $channelId)
            ->field('cc.currency_code,cc.is_default,cc.status,cp.currency_name,cp.symbol,cp.decimal_places')
            ->order('cc.is_default', 'desc')
            ->order('cc.currency_code')
            ->select()
            ->toArray();

        return $merchant;
    }

    public function options(): array
    {
        return Db::connect('game')->table('d_channel_parter')
            ->field('id,agency_uid,show_name,status,currency_code')
            ->order('id')
            ->select()
            ->toArray();
    }

    private function maskSecret(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        return '••••••••' . substr($secret, -4);
    }
}
