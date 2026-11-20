<?php

use think\migration\Migrator;
class CreatePartnerSecurityTables extends Migrator
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE `partner_admin_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` int unsigned NOT NULL DEFAULT 0 COMMENT '0=平台管理员，其余为商户渠道ID',
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(64) NOT NULL DEFAULT '',
  `role_code` varchar(32) NOT NULL COMMENT 'platform_admin/merchant_admin/merchant_viewer',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '0禁用，1启用',
  `failed_login_count` int unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(64) NOT NULL DEFAULT '',
  `password_changed_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partner_admin_username` (`username`),
  KEY `idx_partner_admin_channel_status` (`channel_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='渠道运营后台账号'
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE `partner_admin_ip_whitelist` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `channel_id` int unsigned NOT NULL DEFAULT 0 COMMENT '0=平台后台白名单',
  `ip_or_cidr` varchar(64) NOT NULL,
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `created_by` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_partner_ip_scope` (`channel_id`, `ip_or_cidr`),
  KEY `idx_partner_ip_channel_status` (`channel_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台登录IP白名单'
SQL);

        $this->execute(<<<'SQL'
CREATE TABLE `partner_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` varchar(40) NOT NULL DEFAULT '',
  `actor_id` bigint unsigned NOT NULL DEFAULT 0,
  `channel_id` int unsigned NOT NULL DEFAULT 0,
  `role_code` varchar(32) NOT NULL DEFAULT '',
  `action` varchar(64) NOT NULL,
  `resource_type` varchar(64) NOT NULL DEFAULT '',
  `resource_id` varchar(128) NOT NULL DEFAULT '',
  `result` varchar(16) NOT NULL COMMENT 'success/denied/failed',
  `reason` varchar(255) NOT NULL DEFAULT '',
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `user_agent` varchar(500) NOT NULL DEFAULT '',
  `before_json` json DEFAULT NULL,
  `after_json` json DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_partner_audit_actor_time` (`actor_id`, `created_at`),
  KEY `idx_partner_audit_channel_time` (`channel_id`, `created_at`),
  KEY `idx_partner_audit_action_time` (`action`, `created_at`),
  KEY `idx_partner_audit_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台安全与操作审计'
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `partner_audit_log`');
        $this->execute('DROP TABLE IF EXISTS `partner_admin_ip_whitelist`');
        $this->execute('DROP TABLE IF EXISTS `partner_admin_user`');
    }
}
