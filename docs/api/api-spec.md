# API 设计规范（Phase 0）

## 1. 客户端与路由前缀

三类客户端分组，独立认证/权限/文档，不混在一个未分类文档里：

| 客户端 | 前缀 | Phase 1 |
|---|---|---|
| App | `/api/app/v1` | 预留，不实现 |
| 微信小程序 | `/api/wechat/v1` | 后端接口实现（前端不在本项目范围） |
| Admin Web | `/api/admin/v1` | **实现** |

版本管理：URL 段版本（`/v1`）。

## 2. 统一响应格式

```json
{
  "code": 0,
  "message": "ok",
  "data": { },
  "trace_id": "req-xxx"
}
```

- `code = 0` 成功；非 0 失败，`message` 给可读原因。
- `trace_id` 贯穿日志，便于排查。

## 3. 统一分页

请求：`page`（默认 1）、`per_page`（默认 20，上限可配）。

```json
{
  "data": [ ],
  "meta": { "page": 1, "per_page": 20, "total": 100, "last_page": 5 }
}
```

## 4. 错误码分段

| 段 | 含义 |
|---|---|
| `0` | 成功 |
| `1xxx` | 通用错误（参数、资源不存在、状态非法） |
| `4xxx` | 认证/授权（未登录、Token 过期、无权限、锁定） |
| `5xxx` | 业务错误（状态机拒绝、签名校验失败、重复提交） |

具体码在 Phase 1 实现时固定成常量表，并同步进 OpenAPI 文档。

## 5. 认证与授权

- 认证：JWT（`Authorization: Bearer <token>`），有效期 **8 小时**；退出登录后 Token 进黑名单失效。
- 登录失败锁定：30 分钟内 7 次 → 30 分钟；1 小时内 10 次 → 24 小时；维度账号+IP。
- 权限：后端每个受保护接口强制校验（RBAC + 数据权限），前端权限仅控界面。
- 幂等：重要写操作支持 `Idempotency-Key` 头或等价机制。

## 6. 文档与 Change Log

- **源**：Scramble 自动生成 OpenAPI 3.1，`php artisan scramble:export` 导出 JSON。
- **展示**：纯 HTML 渲染——Scramble 服务端渲染文档页 + CI 用静态模板把 JSON 转成纯 HTML 静态站（独立域名、无登录、业务挂了仍可访问）。
- **三套文档**：App / 微信小程序 / Admin Web 分别维护（Phase 1 只产出 Admin Web 实际文档，其余留占位）。
- **Change Log**：`docs/api/CHANGELOG.md`，**按客户端分组**（`admin-web` / `app` / `wechat`），新增/修改/删除接口、参数/响应变更、Breaking Change 都要记录；版本化保存 OpenAPI 文件；CI 检查 OpenAPI diff 与日志是否同步。

## 7. 目录

```
docs/api/
  app/
  wechat/        # 微信小程序
  admin-web/
  CHANGELOG.md   # 按 admin-web / app / wechat 分组
  README.md
```
