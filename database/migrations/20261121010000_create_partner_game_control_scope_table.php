<?php

use think\migration\Migrator;

class CreatePartnerGameControlScopeTable extends Migrator
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE `partner_game_control_scope` (
  `channel_id` int unsigned NOT NULL,
  `game_id` int unsigned NOT NULL,
  `allow_custom` tinyint unsigned NOT NULL DEFAULT 0,
  `rtp_min` decimal(7,6) NOT NULL DEFAULT 0.900000,
  `rtp_max` decimal(7,6) NOT NULL DEFAULT 1.000000,
  `rtp_step` decimal(7,6) NOT NULL DEFAULT 0.001000,
  `hit_min` decimal(7,6) NOT NULL DEFAULT 0.050000,
  `hit_max` decimal(7,6) NOT NULL DEFAULT 0.500000,
  `hit_step` decimal(7,6) NOT NULL DEFAULT 0.005000,
  `allow_rollback` tinyint unsigned NOT NULL DEFAULT 0,
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `created_by` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`channel_id`,`game_id`),
  KEY `idx_partner_game_control_scope_status` (`channel_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商户游戏目标调控授权范围'
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `partner_game_control_scope`');
    }
}
