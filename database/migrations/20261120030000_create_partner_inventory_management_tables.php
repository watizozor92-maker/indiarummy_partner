<?php

use think\migration\Migrator;

class CreatePartnerInventoryManagementTables extends Migrator
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE `partner_inventory_grant` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` int unsigned NOT NULL,
  `visibility` varchar(16) NOT NULL DEFAULT 'none' COMMENT 'none/summary/full',
  `can_view_ledger` tinyint unsigned NOT NULL DEFAULT 0,
  `adjust_mode` varchar(16) NOT NULL DEFAULT 'none' COMMENT 'none/request/direct',
  `game_ids_json` json DEFAULT NULL COMMENT 'NULL或空数组表示全部游戏',
  `currency_codes_json` json DEFAULT NULL COMMENT 'NULL或空数组表示全部币种',
  `single_limit` decimal(30,4) NOT NULL DEFAULT 0,
  `daily_limit` decimal(30,4) NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `created_by` bigint unsigned NOT NULL DEFAULT 0,
  `updated_by` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partner_inventory_grant_channel` (`channel_id`),
  KEY `idx_partner_inventory_grant_status` (`status`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户库存查看和调整授权'
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE `partner_inventory_adjustment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_key` varchar(64) NOT NULL,
  `channel_id` int unsigned NOT NULL,
  `game_id` int unsigned NOT NULL,
  `currency_code` varchar(12) NOT NULL,
  `adjustment_type` varchar(16) NOT NULL COMMENT 'topup/withdraw',
  `amount` decimal(30,4) NOT NULL,
  `cash_before` decimal(30,4) DEFAULT NULL,
  `cash_after` decimal(30,4) DEFAULT NULL,
  `reason` varchar(255) NOT NULL,
  `status` varchar(16) NOT NULL COMMENT 'pending/executed/rejected/failed',
  `execution_mode` varchar(16) NOT NULL COMMENT 'request/direct/platform',
  `requested_by` bigint unsigned NOT NULL,
  `requested_name` varchar(64) NOT NULL DEFAULT '',
  `requested_ip` varchar(64) NOT NULL DEFAULT '',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_name` varchar(64) NOT NULL DEFAULT '',
  `review_reason` varchar(255) NOT NULL DEFAULT '',
  `runtime_error` varchar(500) NOT NULL DEFAULT '',
  `reviewed_at` datetime DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partner_inventory_adjustment_key` (`requested_by`,`request_key`),
  KEY `idx_partner_inventory_adjustment_channel` (`channel_id`,`status`,`created_at`),
  KEY `idx_partner_inventory_adjustment_review` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存调整申请、审批和执行记录'
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `partner_inventory_adjustment`');
        $this->execute('DROP TABLE IF EXISTS `partner_inventory_grant`');
    }
}
