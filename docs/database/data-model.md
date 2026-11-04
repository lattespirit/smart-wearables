# 数据模型与状态机（Phase 0）

> 完整 DDL 在 Phase 1 用 migration 落地。本文档锁定**约束、状态机、审计与软删除策略**，是 migration 的设计依据。

## 1. 全局约束

- **主键类型**：默认 `bigint` 自增（`id`）；例外见下。
- **设备侧 ID（uint32）**：`app_users.id`、`devices.id`、`pairings.id`（配对 ID）为**无符号 32 位**（ADR-0002）。
- **时间**：统一 UTC 存储；API 层按客户端时区展示/转换。
- **审计字段**：重要业务表含 `created_by`、`created_at`、`updated_by`、`updated_at`（系统生成）。
- **唯一/索引**：业务唯一约束（版本号+型号、账号、手机、邮箱、SN 等）用唯一索引；外键、普通索引按查询设计。
- **软删除**：按场景决定。**审计日志、已发布固件等不允许软删除/物理删除**；用户删除反馈是「动作」而非状态。
- **微信绑定**：App（email/password）与微信小程序（openid）关联到同一前台用户、共享设备信息；`app_users` 增加 openid/unionid，unionid 预留（未来微信开放平台：公众号/微信支付）。

## 2. 状态机（逐实体，PRD 为准）

规则：非法状态转换必须拒绝；状态变更走领域服务/状态转换服务，不在 Controller 里直接改字段；重要转换记审计；并发状态更新加保护。

### 后台管理员（Admin）
```
未启用 ──▶ 正常
正常  ⇄  禁用
正常  ⇄  锁定 ──▶ 解锁 ──▶ 正常
```
- 初始「未启用」，录入可改可删；转正常后不可回未启用。
- 锁定分档：30 分钟内错 7 次 → 锁 30 分钟；1 小时内错 10 次 → 锁 24 小时；计数隔天清零；维度 = 账号 + IP。

### 前台用户（End User）
```
正常  ⇄  禁用
正常  ⇄  锁定 ──▶ 解锁 ──▶ 正常
```
- 后台不可创建/删除；只改允许字段；锁定分档同上。

### 公钥（Public Key）
```
未启用 ──▶ 启用
启用  ⇄  禁用
启用/禁用 ──▶ 弃用（终态）
```
- 启用后禁止修改公钥内容；禁用可恢复启用；弃用不可变、已绑定固件不受影响。命名统一采用「启用 / 禁用 / 弃用」。

### 设备型号（Device Model）
```
未启用 ──▶（被固件引用时自动）启用
启用  ⇄  禁用
```
- 禁用后不能新增硬件版本、不能新增固件绑定；无固件绑定时可删除。
- ⚠️ 绑定时机矛盾待澄清（见 requirements）：PRD 一处「启用后固件才能指定」、另一处「有固件引用记录变成启用」。

### 硬件版本（Hardware Version）
```
未启用 ──▶（被固件绑定时自动）启用
启用  ⇄  停用
```
- 停用后不再允许新固件适配；已绑定固件存量关联保留、不影响运行中的 OTA 策略；无固件绑定时可删除。

### 固件（Firmware）
```
草稿 ──▶ 已发布
已发布  ⇄  停用
```
- 初始「草稿」（非未启用）；草稿可改可删；发布后禁止删除、禁止回草稿；停用可恢复发布。

### OTA 更新策略（OTA Strategy）
```
未启用 ──▶ 启用
启用  ⇄  停用
```
- 未启用可编辑可删；启用过不能改固件及关键引用、不可删除。

### 反馈（Feedback）
```
待处理 ──▶ 处理中 ──▶ 已解决 ──▶ 已关闭
```
- 用户删除是动作（软删除、后台不再展示给用户），非状态。多次回复。「回复后 7 天自动关闭」是否启用待确认。

### 国际化 Key（i18n Key）
```
未启用 ──▶ 启用
启用  ⇄  停用
```

## 3. 模块表归属速览

| 模块 | 关键表 |
|---|---|
| Identity | `login_attempts`、`revoked_tokens` |
| Authorization | `roles`、`permissions`、`role_permission`、`admin_role`、`data_permissions` |
| Admin | `admins` |
| User | `app_users`（邮箱+密码哈希、openid/unionid 绑定） |
| Device | `device_models`、`hardware_versions`、`devices`、`pairings`、`activation_records` |
| Firmware | `public_keys`、`firmwares` |
| Ota | `ota_strategies`、`ota_tasks`、`ota_events` |
| Feedback | `feedbacks`、`feedback_replies` |
| Analytics | `business_events`、聚合/统计表 |
| Notification | `notification_templates`、`push_records` |
| System | `i18n_keys`、`dictionaries`、`system_params`、`audit_logs` |

## 4. Migration 与 Seeder 规范

- 每表独立、可追踪 migration；改结构新增 migration；空库可顺序执行；禁止手工建表。
- **数据模型与 Seeder 保持同步**：每个 Model/表必须有对应 Seeder；schema 变更必须同步更新对应 Seeder，否则 CI 失败（CI 强制校验「Model↔Seeder 对应 + Seeder 幂等 + 空库 migrate+seed 可完成」）。
- 每 Model 有对应 Seeder，幂等可重复执行；基础数据与演示数据分离；生产不写演示数据。
- **一键清空 + 生成演示数据**：使用 Laravel 自带 `php artisan migrate:fresh --seed`（清空重建并填充），不额外新增命令；`DatabaseSeeder` 内区分基础数据与演示数据，演示数据仅本地/测试环境填充、生产不填充。
- RBAC 默认角色/权限由 Seeder 初始化；超管初始化用安全方式（默认密码不进仓库）。
- 各模块维护自己的 migration/seeder；**不做全局 Seeder 跨模块写库**。
