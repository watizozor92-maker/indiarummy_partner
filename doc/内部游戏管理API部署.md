# 内部游戏管理 API 部署

Partner Game Console 不调用旧的无签名 `mod=control` 写入口，而使用新增的 `mod=partnercontrol` 子集。

## 安全约定

- HMAC-SHA1 仅作为现有 Skynet `crypt` 能力下的消息认证码，不用于密码存储；
- 签名覆盖全部业务查询参数和请求体 SHA-1；
- 请求时间偏差最多 300 秒；
- 48 字节随机 nonce 通过 Redis `SET NX PX` 防重放；
- 只允许预览、渠道/币种/玩家调控重载、运行状态、利润查询、库存调整和控制重置；
- API 未配置 32 字符以上密钥时默认关闭；
- 网络层仍必须限制为后台服务器到 API 节点的内网访问，并优先使用 HTTPS 或受控专网。

## 服务端

首次部署玩家调控前，先在 `indiarummy_game` 库执行：

```sql
SOURCE D:/workspace/IndiaRummy_server/db/upgrade_slot_player_control.sql;
```

该脚本创建玩家调控任务表和逐局事件表。必须先完成建表再启动或重启游戏服，否则库存管理服务无法加载玩家调控任务。

启动 `IndiaRummy_server` 的 API 节点前设置：

```bash
export PARTNER_CONTROL_API_SECRET='<至少32个随机字符>'
```

服务端从 `etc/config.api` 读取该环境变量。不要把正式密钥写入 Git。

## 后台

在 `partner/.env.local` 中配置同一个密钥及内部 API 地址：

```dotenv
INTERNAL_GAME_API_URL="http://127.0.0.1:6920/"
INTERNAL_GAME_API_SECRET="<与服务端相同的密钥>"
```

只有完成以上配置并启动 API 节点后，精确预览、发布和玩家调控热加载才会开放。未配置时，配置预览/发布明确返回 `RUNTIME_NOT_CONFIGURED`；玩家调控任务会保存为“待加载”，并在游戏服下次启动加载或 API 恢复后重载。
