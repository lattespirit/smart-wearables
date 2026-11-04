# 模块划分（领域边界）

11 个领域模块。每个模块列出：职责、拥有的表（Phase 1 关键表，非最终 DDL）、对外服务接口、未来服务化路径。表名与字段以 Phase 1 的 migration 为准，此处是边界划分。

## 模块间通信规则

- 跨模块访问一律走目标模块的 **Service 接口**，禁止 import 对方 Model / 直接查对方表。
- 例：`Ota` 需要校验固件状态 → 调 `FirmwareService::assertPublishable(firmwareId)`，不直接查 `firmwares` 表。
- 例：`Analytics` 需要用户信息 → 调 `UserService::getProfile(userId)`，不 join `app_users` 表。

## 1. Identity（身份认证）

- **职责**：后台管理员登录（账号+密码）、JWT 签发/校验、登录失败锁定、会话管理与登出撤销（Token 黑名单）；微信小程序登录（`code2session` → openid/unionid → 关联前台用户 → 自有 JWT）；前台用户 email/password 凭证校验（供微信账号绑定）。
- **表**：`login_attempts`、`revoked_tokens`（或 jwt-auth 黑名单缓存键）。
- **接口**：`issueToken()`、`verifyToken()`、`revokeToken()`、`checkLockout()`、`wxLogin(code)`。
- **未来服务化**：独立 IAM 服务，签发 JWT / OAuth2。

## 2. Authorization（RBAC）

- **职责**：角色、权限、数据权限、资源管理、超级管理员全权限兜底。
- **表**：`roles`、`permissions`、`role_permission`、`role_hierarchy`、`admin_role`、`data_permissions`。
- **接口**：`hasPermission(adminId, perm)`、`assignRole()`、`listPermissions()`、`resolveDataScope(adminId)`。
- **超级管理员规则**：受保护的 `is_super_admin` 标识（或等价机制），授权层全权限兜底，不逐条分配权限；不允许被普通角色降权/禁用/删除。前端隐藏菜单仅是展示，后端强制校验。

## 3. Admin（后台管理员管理）

- **职责**：管理员 CRUD、个人资料、状态管理、角色分配（调 Authorization）。
- **表**：`admins`（账号/头像/姓名/手机/邮箱/性别/状态/简介/最近登录 IP/时间/审计字段）。
- **接口**：`create()`、`update()`、`setStatus()`、`getProfile()`、`changePassword()`。
- **规则**：角色层级限制——普通管理员不能通过 API 增删改查同级或上级账号；自身状态不可修改。

## 4. User（前台用户管理）

- **职责**：前台用户资料查看与受限编辑、状态管理、用户与设备关联视图。
- **表**：`app_users`（昵称/头像/手机/邮箱+密码哈希/类型/性别/状态/简介/国家/时区/注册来源/设备索引/配置索引/openid/unionid/最近登录/注册时间；ID = uint32）。
- **接口**：`getProfile()`、`updateAllowed()`、`setStatus()`、`listDevices(userId)`。
- **规则**：后台不允许创建/删除前台用户；只允许改 PRD 明确允许的字段；敏感信息脱敏。

## 5. Device（设备）

- **职责**：设备型号、硬件版本、设备列表、激活、绑定/解绑、左右配对、在线状态。
- **表**：`device_models`、`hardware_versions`、`devices`（SN 集/左右标识/型号/硬件版本/固件版本/配对状态/配对 ID/绑定用户/激活时间/出厂时间/最近活跃/在线状态；设备 ID = uint32）、`pairings`、`activation_records`。
- **接口**：`registerDevice()`（入站契约，Phase 1 仅设计）、`bind()`、`unbind()`、`pair()`、`unpair()`、`getOnlineStatus()`。
- **规则**：设备记录由设备端/前端上报自动生成，后台不可手动新增/删除；解绑与状态变更是高危操作；配对/解绑/重置/重新激活完整规则待硬件确认（见 requirements）。

## 6. Firmware（固件）

- **职责**：公钥管理、固件上传、SHA256、数字签名校验、固件版本、发布/停用。
- **表**：`public_keys`（版本号/算法 Ed25519·ECC P256/用途/PEM/SHA256 摘要/状态）、`firmwares`（固件包/版本号 F-/包类型/升级类型/SHA256/签名/公钥版本/状态）。
- **接口**：`computeSha256()`、`verifySignature()`、`publish()`、`deactivate()`、`assertPublishable()`。
- **规则**：上传→自动算 SHA256→离线签名→录入签名→保存时二次校验 SHA256+签名；发布后禁止删除/回草稿；私钥不进业务库/日志/API 响应。

## 7. Ota（OTA 升级）

- **职责**：更新策略、固件适配规则、灰度、白名单、升级检测、推送任务、升级进度与结果。
- **表**：`ota_strategies`（目标固件/型号/硬件版本/最低版本/升级方式/地区/灰度比例/白名单/生效窗口/状态）、`ota_tasks`（Phase 1 只建模型）、`ota_events`。
- **接口**：`createStrategy()`、`activateStrategy()`、`deactivateStrategy()`、`checkUpgrade(device)`（入站契约，Phase 1 设计）。
- **规则**：策略启用后不能改固件及关键引用、不能删除，可停用；防止升级到不兼容版本；完整调度规则待产品确认。

## 8. Feedback（用户反馈）

- **职责**：反馈工单、图片附件、回复、未读通知、状态流转。
- **表**：`feedbacks`（用户/关联设备/类型/标题/文本/截图最多 3 张/系统与客户端信息/状态）、`feedback_replies`。
- **接口**：`create()`、`reply()`、`transition()`、`markRead()`。
- **规则**：状态机 待处理 → 处理中 → 已解决 → 已关闭（用户删除是动作非状态）；支持多次回复。回复通知经微信订阅消息触达（调 Notification 模块，Phase 1 做后端）。

## 9. Analytics（数据分析）

- **职责**：服务端业务事件采集、用户/设备/OTA/激活统计与 API。
- **表**：`business_events`（event_id/event_name/event_version/event_time/received_at/user_id/device_id/device_sn/device_model/firmware_version/platform/app_version/country/timezone/properties/trace_id）、统计表（激活漏斗等，Phase 1 由事件实时聚合或物化）。
- **接口**：`recordEvent()`（幂等）、`activationFunnel()`、`otaSuccessRate()`、`dailyMetrics()`。
- **规则**：业务指标以服务端事件为准；事件唯一 ID、幂等、失败重试；不采密码/Token/私钥。

## 10. Notification（通知推送）

- **职责**：微信订阅消息推送、微信 `access_token` 统一获取/缓存/刷新、订阅消息模板、推送记录。
- **表**：`notification_templates`、`push_records`。
- **接口**：`sendSubscribeMessage(openid, templateId, data, page)`、`getAccessToken()`。
- **规则**：推送失败可重试；不采集敏感信息；`access_token` 后端统一缓存避免频繁刷新。

## 11. System（系统管理）

- **职责**：国际化、字典、参数、日志与审计。
- **表**：`i18n_keys`（key/中文/英文/位置/状态）、`dictionaries`、`system_params`、`audit_logs`（操作者/时间/IP/模块/类型/目标 ID/名称/前后数据/结果）。
- **接口**：`log()`（审计）、`getI18n()`、`getDict()`、`getParam()`。
- **规则**：审计日志不可删除，保留至少 180 天，支持查询/筛选/导出。

## API Gateway（非模块）

作为 Laravel 的路由 + 中间件层：统一入口、认证与基础访问控制、限流、请求 ID/Trace ID、统一错误响应。不建独立模块，避免无谓拆分。
