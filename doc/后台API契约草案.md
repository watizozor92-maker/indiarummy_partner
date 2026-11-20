# 后台 API 契约草案

> 版本：v1 实现基线  
> 范围：Partner Game Console 自身的浏览器端 API，不是对外游戏接入 API。

## 1. 通用约定

基础路径：

```text
/api/v1
```

成功响应：

```json
{
  "code": "OK",
  "message": "",
  "data": {},
  "requestId": "01J..."
}
```

失败响应：

```json
{
  "code": "CONTROL_VERSION_CONFLICT",
  "message": "配置已被其他管理员更新，请刷新后重试",
  "details": {},
  "requestId": "01J..."
}
```

分页：

```text
page=1&pageSize=20
```

响应携带 `items/page/pageSize/total`。跨月大数据查询后续可切换游标分页，接口中不得依赖不稳定的单字段时间排序，应至少使用 `create_time + id`。

时间统一采用 ISO 8601 并明确时区。金额在 JSON 中使用字符串，避免浮点精度损失。

## 2. 租户上下文

商户登录后由服务端会话生成：

```json
{
  "actorId": 123,
  "role": "merchant_admin",
  "channelId": 10000
}
```

商户接口即使收到 `channelId` 参数也不得用它决定数据范围。平台管理员通过专用筛选字段选择商户，并要求平台权限。

## 3. 会话

```text
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/session
POST /api/v1/auth/password
```

登录顺序：来源 IP 白名单、账号状态、密码、失败限制/验证码、角色和商户状态、创建会话、写登录审计。

## 4. 运营总览

```text
GET /api/v1/overview?from=&to=&currencyCode=&gameId=&channelId=
GET /api/v1/overview/trend?from=&to=&granularity=day&...
```

商户角色忽略/拒绝 `channelId`，自动使用会话渠道。

## 5. 玩家

```text
GET /api/v1/players
GET /api/v1/players/{uid}
GET /api/v1/players/{uid}/trend
GET /api/v1/players/{uid}/games
GET /api/v1/players/{uid}/rounds
GET /api/v1/players/{uid}/experience
GET /api/v1/players-export
```

列表排序字段必须白名单化，例如 `periodBet`、`periodPayout`、`profit`、`spins`、`lastActiveAt`。

## 6. 游戏记录

```text
GET /api/v1/rounds
GET /api/v1/rounds/{roundId}
GET /api/v1/rounds/{roundId}/replay
GET /api/v1/rounds-export
```

查询支持时间、玩家、游戏、币种、局号、模式、场景、最低投注/派彩/倍数。

## 7. 统计

```text
GET /api/v1/statistics/summary
GET /api/v1/statistics/timeseries
GET /api/v1/statistics/games
GET /api/v1/statistics/merchants
GET /api/v1/statistics-export
```

响应必须返回口径元数据：时区、币种、统计周期、是否完整、数据最后更新时间。

CSV 导出沿用页面筛选与服务端租户上下文，玩家上限 5000 名、记录上限 10000 条；超限要求缩小筛选范围。所有导出写入审计，并对电子表格公式注入做转义。

## 8. 游戏与控制

```text
GET  /api/v1/games
GET  /api/v1/games/{gameId}/control
GET  /api/v1/games/{gameId}/control/presets
POST /api/v1/games/{gameId}/control/preview
POST /api/v1/games/{gameId}/control/publish
GET  /api/v1/games/{gameId}/control/revisions
POST /api/v1/games/{gameId}/control/rollback
GET  /api/v1/games/{gameId}/control/runtime-status
```

当前实现路由使用 `/api/v1/game-control` 前缀，对应：

```text
GET  /api/v1/game-control
GET  /api/v1/game-control/{gameId}/presets
GET  /api/v1/game-control/{gameId}/revisions
GET  /api/v1/game-control/{gameId}/runtime-status
POST /api/v1/game-control/{gameId}/preview
POST /api/v1/game-control/{gameId}/publish
POST /api/v1/game-control/{gameId}/rollback
```

控制策略按 `channel_id + game_id` 发布，影响该渠道此游戏全部币种；库存、投注档位和统计按币种隔离。平台默认 `channel_id=0` 配置仍由高级维护流程管理，商户后台不允许直接发布或回滚默认配置。

发布请求至少包含：

```json
{
  "currencyCode": "INR",
  "presetKey": "balanced",
  "expectedVersion": 7,
  "previewToken": "signed-preview-token",
  "reason": "商户月度运营配置调整",
  "idempotencyKey": "uuid"
}
```

`previewToken` 绑定渠道、游戏、币种、预设、指标和过期时间，防止预览后篡改发布内容。

## 9. 库存

平台权限：

```text
GET  /api/v1/games/{gameId}/inventory
GET  /api/v1/games/{gameId}/inventory/ledger
POST /api/v1/games/{gameId}/inventory/adjust
POST /api/v1/games/{gameId}/inventory/reset
```

库存调整和重置要求二次确认、原因、幂等键和完整审计。商户默认只读，是否开放申请流程后续确认。

## 10. 商户

`POST /api/v1/merchants` 仅平台管理员可调用。创建时提交商户编码、名称、额度、状态、启用币种和默认币种；服务端沿用 `api_service Game::applykey20261103` 的规则，以 32 字节安全随机数生成 Base64 API Key，并只在创建响应中返回一次明文。审计记录只保存密钥末四位，不保存明文。

平台权限：

```text
GET  /api/v1/merchants
POST /api/v1/merchants
GET  /api/v1/merchants/{channelId}
PUT  /api/v1/merchants/{channelId}
GET  /api/v1/merchants/{channelId}/games
PUT  /api/v1/merchants/{channelId}/games
GET  /api/v1/merchants/{channelId}/currencies
PUT  /api/v1/merchants/{channelId}/currencies
GET  /api/v1/merchants/currency-options
```

渠道尚未发布自己的游戏控制版本时，控制列表以版本 `0` 展示平台默认配置的继承状态；首次成功发布才创建渠道版本。币种配置必须至少启用一个币种，并且只能有一个默认币种。

## 11. API 密钥与 IP 白名单

```text
GET    /api/v1/merchants/{channelId}/api-keys
POST   /api/v1/merchants/{channelId}/api-keys
POST   /api/v1/merchants/{channelId}/api-keys/{keyId}/rotate
DELETE /api/v1/merchants/{channelId}/api-keys/{keyId}

GET    /api/v1/merchants/{channelId}/ip-whitelist
POST   /api/v1/merchants/{channelId}/ip-whitelist
PUT    /api/v1/merchants/{channelId}/ip-whitelist/{id}
DELETE /api/v1/merchants/{channelId}/ip-whitelist/{id}
```

密钥创建/轮换响应只允许一次返回完整密钥，此后只返回末四位和元数据。

## 12. 审计

```text
GET /api/v1/audit-logs
GET /api/v1/audit-logs/{id}
```

审计项包含操作者、角色、渠道、来源 IP、请求 ID、动作、资源、操作原因、变更前后摘要、结果和时间。密钥、密码及完整敏感配置不得写入日志。

## 12.1 玩家调控

```text
GET  /api/v1/player-control
GET  /api/v1/player-control/options
GET  /api/v1/player-control/{id}
POST /api/v1/player-control
POST /api/v1/player-control/{id}/cancel
```

创建任务必须包含渠道上下文、UID、游戏、币种、`SUPPORT/SUPPRESS/RTP_TARGET` 模式、目标金额、最大局数、生效/到期时间和操作原因。商户请求中的渠道参数仍由服务端会话覆盖。

## 13. 初版错误码

| 错误码 | HTTP | 含义 |
| --- | ---: | --- |
| `AUTH_REQUIRED` | 401 | 未登录或会话失效 |
| `IP_NOT_ALLOWED` | 403 | 来源 IP 不在白名单 |
| `FORBIDDEN` | 403 | 无操作权限 |
| `TENANT_SCOPE_VIOLATION` | 403 | 请求资源不属于当前商户 |
| `RESOURCE_NOT_FOUND` | 404 | 资源不存在或不可见 |
| `VALIDATION_FAILED` | 422 | 参数或业务校验失败 |
| `CONTROL_VERSION_CONFLICT` | 409 | 配置版本冲突 |
| `PREVIEW_EXPIRED` | 409 | 预览令牌过期或不匹配 |
| `RUNTIME_LOAD_FAILED` | 502 | 已保存但运行时加载失败 |
| `RATE_LIMITED` | 429 | 请求频率超限 |
| `EXPORT_LIMIT_EXCEEDED` | 422 | 导出结果超过同步导出上限 |

