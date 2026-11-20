<?php

declare(strict_types=1);

namespace app\service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class DateRangeService
{
    public function __construct(private readonly string $timezone = 'Asia/Singapore'){}

    public function parse(?string $from, ?string $to, int $defaultDays = 7, int $maximumDays = 366): array
    {
        $timezone = new DateTimeZone($this->timezone);
        $end = $to ? $this->parseDate($to, $timezone, true) : new DateTimeImmutable('now', $timezone);
        $start = $from
            ? $this->parseDate($from, $timezone, false)
            : $end->sub(new DateInterval('P' . max(1, $defaultDays - 1) . 'D'))->setTime(0, 0);

        if ($start > $end) {
            throw new InvalidArgumentException('开始时间不能晚于结束时间');
        }
        if ($start->diff($end)->days > $maximumDays) {
            throw new InvalidArgumentException(sprintf('查询范围不能超过 %d 天', $maximumDays));
        }

        return [
            'from' => $start,
            'to' => $end,
            'fromSql' => $start->format('Y-m-d H:i:s'),
            'toSql' => $end->format('Y-m-d H:i:s'),
            'timezone' => $timezone->getName(),
        ];
    }

    private function parseDate(string $value, DateTimeZone $timezone, bool $endOfDay): DateTimeImmutable
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('日期格式必须为 YYYY-MM-DD');
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0, 0);
    }
}
