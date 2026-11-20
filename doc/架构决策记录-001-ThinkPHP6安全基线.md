# ADR-001：采用 ThinkPHP 6.1.2 本地安全补丁版

> 状态：已采用  
> 日期：2026-11-19

## 背景

现有 `admin` 使用 ThinkPHP 6，团队希望新后台保持相同技术体系。Composer 安全策略会阻止直接安装当前 ThinkPHP 6 官方版本：

- 6.1.3 至 6.1.5 命中严重反序列化/RCE 公告；
- 6.1.0 至 6.1.4 命中异常模板 XSS 公告；
- 6.0.16 及以前也在该 XSS 公告影响范围内。

## 决策

使用 ThinkPHP 6.1.2，并建立以下安全基线：

1. 将 `topthink/framework` 精确锁定为 `6.1.2`，不使用范围升级；
2. 使用 `cweagans/composer-patches` 自动回移官方 6.1.5 中的异常模板 XSS 修复；
3. 仅对已经回移修复的 `PKSA-qzw1-yq5b-y8j5` 添加 Composer 忽略说明；
4. 不忽略严重反序列化/RCE 公告；
5. 提交 `composer.lock`、补丁文件和 `patches.lock.json`；
6. 每次安装和发布运行 `composer audit` 并验证补丁内容；
7. 移除当前不需要且依赖存在公告的 `think-filesystem/Flysystem 1.x`。

补丁文件：`patches/topthink-framework-6.1.2-exception-xss.patch`。

## 运行安全要求

- 生产环境必须设置 `APP_DEBUG=false`；
- Web Server 根目录只能指向 `public/`；
- `.env.local` 不得提交；
- 登录、密钥、配置发布和库存操作必须有权限与审计；
- 升级 ThinkPHP 前必须重新核对公告，不能直接执行框架小版本升级。

## 已验证结果

- Composer 能在空环境中自动应用补丁；
- `composer audit` 无未处理安全公告；
- `partner` 与 `game` 数据库连接成功；
- `/api/v1/health` 返回 HTTP 200；
- PHP 语法和 ThinkPHP 路由检查通过。

## 参考

- CVE-2024-44902：https://github.com/advisories/GHSA-f4wh-359g-4pq7
- CVE-2024-34467：https://github.com/advisories/GHSA-969f-v7jv-pgj3
