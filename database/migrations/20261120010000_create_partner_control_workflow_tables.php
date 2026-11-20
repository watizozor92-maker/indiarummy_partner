<?php

use think\migration\Migrator;

class CreatePartnerControlWorkflowTables extends Migrator
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE `partner_control_preset_grant` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` int unsigned NOT NULL,
  `game_id` int unsigned NOT NULL,
  `preset_key` varchar(32) NOT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `created_by` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partner_control_grant` (`channel_id`,`game_id`,`preset_key`),
  KEY `idx_partner_control_grant_status` (`channel_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户可用游戏控制预设授权'
SQL);
        $this->execute(<<<'SQL'
CREATE TABLE `partner_control_preview` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `channel_id` int unsigned NOT NULL,
  `game_id` int unsigned NOT NULL,
  `currency_code` varchar(12) NOT NULL,
  `preset_key` varchar(32) NOT NULL,
  `expected_version` bigint unsigned NOT NULL,
  `payload_json` json NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partner_control_preview_token` (`token_hash`),
  KEY `idx_partner_control_preview_actor` (`actor_id`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='控制发布短期预览凭证'
SQL);
        $this->execute(<<<'SQL'
CREATE TABLE `partner_idempotency_request` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(64) NOT NULL,
  `idempotency_key` varchar(64) NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `request_hash` char(64) NOT NULL,
  `status` varchar(16) NOT NULL,
  `response_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partner_idempotency_action_key` (`action`,`idempotency_key`),
  KEY `idx_partner_idempotency_actor_time` (`actor_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='敏感写操作幂等记录'
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `partner_idempotency_request`');
        $this->execute('DROP TABLE IF EXISTS `partner_control_preview`');
        $this->execute('DROP TABLE IF EXISTS `partner_control_preset_grant`');
    }
}
