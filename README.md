# Partner Game Console

渠道游戏运营中心，为 IndiaRummy API 合作渠道提供玩家、游戏记录、数据统计、游戏配置和 RTP 库存调控能力。

## 项目状态

当前已完成 ThinkPHP 6.1.2 安全补丁版应用骨架、双数据库连接、账号安全与租户隔离，以及商户币种/预设授权、玩家、现代老虎机账本、统计、CSV 导出、游戏配置、玩家调控、预览发布、版本回滚和审计模块。渠道未发布配置时继承平台默认版本；游戏调控写操作默认关闭，只有签名内部管理 API 配置完成后才可热加载。

## 目录

```text
partner/
  app/       控制器、服务、模型、中间件和命令
  bootstrap/ 环境加载
  config/    ThinkPHP 与应用配置
  patches/   第三方依赖安全补丁
  public/    Web 根目录
  route/     路由
  view/      新版 Layui 管理页面
  doc/       需求、架构、数据与接口文档
```

## 文档入口

- [总体设计](doc/渠道游戏运营中心设计方案.md)
- [技术方案与开工清单](doc/技术方案与开工清单.md)
- [现有数据与接口盘点](doc/现有数据与接口盘点.md)
- [后台 API 契约草案](doc/后台API契约草案.md)
- [ThinkPHP 6 安全基线](doc/架构决策记录-001-ThinkPHP6安全基线.md)
- [内部游戏管理 API 部署](doc/内部游戏管理API部署.md)

## 开发环境

```text
PHP 8.2+
ThinkPHP 6.1.2（本地安全补丁）
Composer 2
MySQL 5.7（现有环境）
```

安装与验证：

```powershell
Copy-Item .env.example .env
# 编辑 .env，填写本机数据库连接和随机 APP_SECRET
composer install
composer security:verify
npm install
npm audit --omit=dev
php think migrate:run
php think route:list
php think run --host 127.0.0.1 --port 8000
```

健康检查：`GET /api/v1/health`

主要页面：`/dashboard`、`/players`、`/rounds`、`/statistics`、`/game-control`、`/player-control`、`/merchants`、`/audit`。

首次创建平台管理员（密码会在终端中隐藏输入并要求确认）：

```powershell
php think partner:admin:create admin --display-name="平台管理员" --role=platform_admin --channel=0 --ip=127.0.0.1/32
```

如果从局域网或反向代理访问，应把 `--ip` 替换为后台实际识别到的管理端出口 IP/CIDR。不要使用 `0.0.0.0/0`。创建完成后访问 `/login`。

生产环境必须设置 `APP_DEBUG=false`、`COOKIE_SECURE=true`，并由 Web Server 将站点根目录指向 `public/`。

## 核心边界

- IndiaRummy 是唯一游戏提供方，不设置厂商维度。
- 商户数据权限由服务端登录会话中的 `channel_id` 决定。
- 游戏控制策略以 `channel_id + game_id` 发布，同一渠道的该游戏所有币种共享；库存与统计按 `channel_id + game_id + currency_code` 隔离。
- 商户只能选择经平台校准和授权的控制预设。
- 玩家调控按渠道、UID、游戏和币种隔离，支持扶持、收割和目标 RTP；必须设置目标金额、最大局数、有效期和原因，并受库存及最大赔付约束。
- 影响在线运行的配置必须完成保存、运行时加载确认和审计。
- 玩家、记录和统计导出沿用服务端租户范围，带数量上限、审计及 CSV 公式注入防护。

## 上线前待配置

- 设置同一个 `PARTNER_CONTROL_API_SECRET` / `INTERNAL_GAME_API_SECRET`，并配置内部 API URL；
- 在商户管理中为每个渠道选择启用币种、默认币种和允许使用的调控预设；
- 创建正式平台管理员和商户管理员，配置真实管理端出口 IP/CIDR；
- 配置 HTTPS、`APP_DEBUG=false`、安全 Cookie，并确认数据库账号权限分离策略。

