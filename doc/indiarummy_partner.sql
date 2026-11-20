/*
 Navicat Premium Dump SQL

 Source Server         : localhost
 Source Server Type    : MySQL
 Source Server Version : 50742 (5.7.42-log)
 Source Host           : localhost:3306
 Source Schema         : indiarummy_partner

 Target Server Type    : MySQL
 Target Server Version : 50742 (5.7.42-log)
 File Encoding         : 65001

 Date: 20/11/2026 15:08:09
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations`  (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of migrations
-- ----------------------------
INSERT INTO `migrations` VALUES (20261119150839, 'CreatePartnerSecurityTables', '2026-11-19 23:16:02', '2026-11-19 23:16:03', 0);
INSERT INTO `migrations` VALUES (20261120010000, 'CreatePartnerControlWorkflowTables', '2026-11-20 00:02:47', '2026-11-20 00:02:47', 0);

-- ----------------------------
-- Table structure for partner_admin_ip_whitelist
-- ----------------------------
DROP TABLE IF EXISTS `partner_admin_ip_whitelist`;
CREATE TABLE `partner_admin_ip_whitelist`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=平台后台白名单',
  `ip_or_cidr` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_partner_ip_scope`(`channel_id`, `ip_or_cidr`) USING BTREE,
  INDEX `idx_partner_ip_channel_status`(`channel_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '后台登录IP白名单' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of partner_admin_ip_whitelist
-- ----------------------------
INSERT INTO `partner_admin_ip_whitelist` VALUES (9, 0, '127.0.0.1/32', 1, 'Initial rule created with administrator', 8, '2026-11-20 01:16:35', '2026-11-20 01:16:35');

-- ----------------------------
-- Table structure for partner_admin_user
-- ----------------------------
DROP TABLE IF EXISTS `partner_admin_user`;
CREATE TABLE `partner_admin_user`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=平台管理员，其余为商户渠道ID',
  `username` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'platform_admin/merchant_admin/merchant_viewer',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0禁用，1启用',
  `failed_login_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime NULL DEFAULT NULL,
  `last_login_at` datetime NULL DEFAULT NULL,
  `last_login_ip` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `password_changed_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_partner_admin_username`(`username`) USING BTREE,
  INDEX `idx_partner_admin_channel_status`(`channel_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '渠道运营后台账号' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of partner_admin_user
-- ----------------------------
INSERT INTO `partner_admin_user` VALUES (8, 0, 'admin', '$argon2id$v=19$m=65536,t=4,p=1$dVkyQVRwTU9PTkdNd1NJLw$uorSo0AlhuQCYS0N0qVuTSlJd5ynaCIMH18x5Wa47Zk', '平台管理员', 'platform_admin', 1, 0, NULL, '2026-11-20 13:41:30', '127.0.0.1', '2026-11-20 01:16:35', '2026-11-20 01:16:35', '2026-11-20 13:41:30');

-- ----------------------------
-- Table structure for partner_audit_log
-- ----------------------------
DROP TABLE IF EXISTS `partner_audit_log`;
CREATE TABLE `partner_audit_log`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actor_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `channel_id` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `role_code` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `action` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resource_type` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resource_id` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `result` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'success/denied/failed',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip_address` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `before_json` json NULL,
  `after_json` json NULL,
  `metadata_json` json NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_partner_audit_actor_time`(`actor_id`, `created_at`) USING BTREE,
  INDEX `idx_partner_audit_channel_time`(`channel_id`, `created_at`) USING BTREE,
  INDEX `idx_partner_audit_action_time`(`action`, `created_at`) USING BTREE,
  INDEX `idx_partner_audit_request`(`request_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 46 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '后台安全与操作审计' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of partner_audit_log
-- ----------------------------
INSERT INTO `partner_audit_log` VALUES (13, '7325e30d17d25d19ece4645c2551cf1c', 0, 0, 'system', 'merchant.currencies.update', 'merchant', '10000', 'success', '', '127.0.0.1', 'Codex setup', '[]', '[{\"status\": 1, \"is_default\": 1, \"currency_code\": \"CNY\"}, {\"status\": 1, \"is_default\": 0, \"currency_code\": \"USD\"}]', NULL, '2026-11-20 01:03:57');
INSERT INTO `partner_audit_log` VALUES (14, 'dd57f97a5b2acf6dd11c5d32bc72988e', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:419:balanced', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 419, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:48\", \"created_by\": 0, \"preset_key\": \"balanced\", \"updated_at\": \"2026-11-20 01:06:48\"}', NULL, '2026-11-20 01:06:49');
INSERT INTO `partner_audit_log` VALUES (15, '124f7a5bf4727110c7d6ca3862e24afb', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:541:balanced', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 541, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:49\", \"created_by\": 0, \"preset_key\": \"balanced\", \"updated_at\": \"2026-11-20 01:06:49\"}', NULL, '2026-11-20 01:06:49');
INSERT INTO `partner_audit_log` VALUES (16, '794402c43912dd070ba646ed6c80bd9e', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:541:experience_first', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 541, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:49\", \"created_by\": 0, \"preset_key\": \"experience_first\", \"updated_at\": \"2026-11-20 01:06:49\"}', NULL, '2026-11-20 01:06:49');
INSERT INTO `partner_audit_log` VALUES (17, '46709bad698cd3d125f27891dd6b8830', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:541:high_volatility', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 541, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:49\", \"created_by\": 0, \"preset_key\": \"high_volatility\", \"updated_at\": \"2026-11-20 01:06:49\"}', NULL, '2026-11-20 01:06:49');
INSERT INTO `partner_audit_log` VALUES (18, '4c92e2e5469b6e78eda8c5121c10b9f3', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:541:profit_first', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 541, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:49\", \"created_by\": 0, \"preset_key\": \"profit_first\", \"updated_at\": \"2026-11-20 01:06:49\"}', NULL, '2026-11-20 01:06:49');
INSERT INTO `partner_audit_log` VALUES (19, '4ff2b7b66839303fb24bfe7345293436', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:635:balanced', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 635, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:50\", \"created_by\": 0, \"preset_key\": \"balanced\", \"updated_at\": \"2026-11-20 01:06:50\"}', NULL, '2026-11-20 01:06:50');
INSERT INTO `partner_audit_log` VALUES (20, 'c3f8ce9c6addb88dd100bdccc6920cff', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:635:experience_first', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 635, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:50\", \"created_by\": 0, \"preset_key\": \"experience_first\", \"updated_at\": \"2026-11-20 01:06:50\"}', NULL, '2026-11-20 01:06:50');
INSERT INTO `partner_audit_log` VALUES (21, 'db3b57306c73a3dfb4b49784757b83a5', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:635:high_volatility', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 635, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:50\", \"created_by\": 0, \"preset_key\": \"high_volatility\", \"updated_at\": \"2026-11-20 01:06:50\"}', NULL, '2026-11-20 01:06:50');
INSERT INTO `partner_audit_log` VALUES (22, 'b4baef9d18369c1e580e342f349a1fdb', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:635:profit_first', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 635, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:50\", \"created_by\": 0, \"preset_key\": \"profit_first\", \"updated_at\": \"2026-11-20 01:06:50\"}', NULL, '2026-11-20 01:06:50');
INSERT INTO `partner_audit_log` VALUES (23, '08c91071216df8f2691248f29f1de53b', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:692:balanced', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 692, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:50\", \"created_by\": 0, \"preset_key\": \"balanced\", \"updated_at\": \"2026-11-20 01:06:50\"}', NULL, '2026-11-20 01:06:50');
INSERT INTO `partner_audit_log` VALUES (24, '0c4a5ff3e5d659232aa5cd36a5ca7b76', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:692:growth', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 692, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:51\", \"created_by\": 0, \"preset_key\": \"growth\", \"updated_at\": \"2026-11-20 01:06:51\"}', NULL, '2026-11-20 01:06:51');
INSERT INTO `partner_audit_log` VALUES (25, '7c84308099d38addd2a67e57ddc717d0', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:692:profit_first', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 692, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:51\", \"created_by\": 0, \"preset_key\": \"profit_first\", \"updated_at\": \"2026-11-20 01:06:51\"}', NULL, '2026-11-20 01:06:51');
INSERT INTO `partner_audit_log` VALUES (26, '4db8b9dd867d8b54aa0ce59c734e147d', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:698:balanced', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 698, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:51\", \"created_by\": 0, \"preset_key\": \"balanced\", \"updated_at\": \"2026-11-20 01:06:51\"}', NULL, '2026-11-20 01:06:51');
INSERT INTO `partner_audit_log` VALUES (27, '0aa4f5100d39d9644f1fd6868a4d8a6c', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:698:big_prize', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 698, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:51\", \"created_by\": 0, \"preset_key\": \"big_prize\", \"updated_at\": \"2026-11-20 01:06:51\"}', NULL, '2026-11-20 01:06:51');
INSERT INTO `partner_audit_log` VALUES (28, 'e4d454d163dc2f2deb2178bd906eae22', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:698:experience_first', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 698, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:51\", \"created_by\": 0, \"preset_key\": \"experience_first\", \"updated_at\": \"2026-11-20 01:06:51\"}', NULL, '2026-11-20 01:06:51');
INSERT INTO `partner_audit_log` VALUES (29, '9ca17d34b614944987ab8497324c755e', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:698:profit_first', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 698, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:52\", \"created_by\": 0, \"preset_key\": \"profit_first\", \"updated_at\": \"2026-11-20 01:06:52\"}', NULL, '2026-11-20 01:06:52');
INSERT INTO `partner_audit_log` VALUES (30, '976320afe068ef67bb7b12cffa70bf91', 0, 0, 'system', 'game_control.grant', 'control_grant', '10000:699:balanced', 'success', '', '127.0.0.1', 'Codex setup', NULL, '{\"status\": 1, \"game_id\": 699, \"channel_id\": 10000, \"created_at\": \"2026-11-20 01:06:52\", \"created_by\": 0, \"preset_key\": \"balanced\", \"updated_at\": \"2026-11-20 01:06:52\"}', NULL, '2026-11-20 01:06:52');
INSERT INTO `partner_audit_log` VALUES (31, '82c2a180aea7675580c18c101d8089bd', 8, 0, 'platform_admin', 'auth.login', 'admin_user', '8', 'success', '', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0', NULL, NULL, NULL, '2026-11-20 01:17:36');
INSERT INTO `partner_audit_log` VALUES (32, '281046ea4b820dd230cc583c522d2482', 8, 0, 'platform_admin', 'round.detail.view', 'inventory_ledger', '849', 'success', '', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0', NULL, NULL, '{\"roundId\": 1793939605142, \"targetChannelId\": 0}', '2026-11-20 01:19:34');
INSERT INTO `partner_audit_log` VALUES (45, '8339db2ae1ca550493a2fcb5585b488b', 8, 0, 'platform_admin', 'auth.login', 'admin_user', '8', 'success', '', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36 Edg/157.0.0.0', NULL, NULL, NULL, '2026-11-20 13:41:33');

-- ----------------------------
-- Table structure for partner_control_preset_grant
-- ----------------------------
DROP TABLE IF EXISTS `partner_control_preset_grant`;
CREATE TABLE `partner_control_preset_grant`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED NOT NULL,
  `preset_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_partner_control_grant`(`channel_id`, `game_id`, `preset_key`) USING BTREE,
  INDEX `idx_partner_control_grant_status`(`channel_id`, `status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 18 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '商户可用游戏控制预设授权' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of partner_control_preset_grant
-- ----------------------------
INSERT INTO `partner_control_preset_grant` VALUES (1, 10000, 419, 'balanced', 1, 0, '2026-11-20 01:06:48', '2026-11-20 01:06:48');
INSERT INTO `partner_control_preset_grant` VALUES (2, 10000, 541, 'balanced', 1, 0, '2026-11-20 01:06:49', '2026-11-20 01:06:49');
INSERT INTO `partner_control_preset_grant` VALUES (3, 10000, 541, 'experience_first', 1, 0, '2026-11-20 01:06:49', '2026-11-20 01:06:49');
INSERT INTO `partner_control_preset_grant` VALUES (4, 10000, 541, 'high_volatility', 1, 0, '2026-11-20 01:06:49', '2026-11-20 01:06:49');
INSERT INTO `partner_control_preset_grant` VALUES (5, 10000, 541, 'profit_first', 1, 0, '2026-11-20 01:06:49', '2026-11-20 01:06:49');
INSERT INTO `partner_control_preset_grant` VALUES (6, 10000, 635, 'balanced', 1, 0, '2026-11-20 01:06:50', '2026-11-20 01:06:50');
INSERT INTO `partner_control_preset_grant` VALUES (7, 10000, 635, 'experience_first', 1, 0, '2026-11-20 01:06:50', '2026-11-20 01:06:50');
INSERT INTO `partner_control_preset_grant` VALUES (8, 10000, 635, 'high_volatility', 1, 0, '2026-11-20 01:06:50', '2026-11-20 01:06:50');
INSERT INTO `partner_control_preset_grant` VALUES (9, 10000, 635, 'profit_first', 1, 0, '2026-11-20 01:06:50', '2026-11-20 01:06:50');
INSERT INTO `partner_control_preset_grant` VALUES (10, 10000, 692, 'balanced', 1, 0, '2026-11-20 01:06:50', '2026-11-20 01:06:50');
INSERT INTO `partner_control_preset_grant` VALUES (11, 10000, 692, 'growth', 1, 0, '2026-11-20 01:06:51', '2026-11-20 01:06:51');
INSERT INTO `partner_control_preset_grant` VALUES (12, 10000, 692, 'profit_first', 1, 0, '2026-11-20 01:06:51', '2026-11-20 01:06:51');
INSERT INTO `partner_control_preset_grant` VALUES (13, 10000, 698, 'balanced', 1, 0, '2026-11-20 01:06:51', '2026-11-20 01:06:51');
INSERT INTO `partner_control_preset_grant` VALUES (14, 10000, 698, 'big_prize', 1, 0, '2026-11-20 01:06:51', '2026-11-20 01:06:51');
INSERT INTO `partner_control_preset_grant` VALUES (15, 10000, 698, 'experience_first', 1, 0, '2026-11-20 01:06:51', '2026-11-20 01:06:51');
INSERT INTO `partner_control_preset_grant` VALUES (16, 10000, 698, 'profit_first', 1, 0, '2026-11-20 01:06:52', '2026-11-20 01:06:52');
INSERT INTO `partner_control_preset_grant` VALUES (17, 10000, 699, 'balanced', 1, 0, '2026-11-20 01:06:52', '2026-11-20 01:06:52');

-- ----------------------------
-- Table structure for partner_control_preview
-- ----------------------------
DROP TABLE IF EXISTS `partner_control_preview`;
CREATE TABLE `partner_control_preview`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint(20) UNSIGNED NOT NULL,
  `channel_id` int(10) UNSIGNED NOT NULL,
  `game_id` int(10) UNSIGNED NOT NULL,
  `currency_code` varchar(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `preset_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_version` bigint(20) UNSIGNED NOT NULL,
  `payload_json` json NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime NULL DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_partner_control_preview_token`(`token_hash`) USING BTREE,
  INDEX `idx_partner_control_preview_actor`(`actor_id`, `expires_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '控制发布短期预览凭证' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of partner_control_preview
-- ----------------------------

-- ----------------------------
-- Table structure for partner_idempotency_request
-- ----------------------------
DROP TABLE IF EXISTS `partner_idempotency_request`;
CREATE TABLE `partner_idempotency_request`  (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `idempotency_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` bigint(20) UNSIGNED NOT NULL,
  `request_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `response_json` json NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_partner_idempotency_action_key`(`action`, `idempotency_key`) USING BTREE,
  INDEX `idx_partner_idempotency_actor_time`(`actor_id`, `created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '敏感写操作幂等记录' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of partner_idempotency_request
-- ----------------------------

SET FOREIGN_KEY_CHECKS = 1;
