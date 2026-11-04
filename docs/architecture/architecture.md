# 系统架构设计（Phase 0）

> 本文档是 Phase 0 交付物，是后续 AI 编程实施（Cursor / Claude Code / Codex）的架构依据。原始业务需求以《基础管理后台智能穿戴 PRD》为准；本文档只做架构决策，不改变业务规则。所有与本项目冲突的内容以本文档和 ADR 为准。

## 1. 目标与范围

- **交付目标**：MVP 业务范围 + 生产级代码质量。可运行、可测试、可部署、可迭代，不是静态页面或 Mock。
- **客户端范围（Phase 1）**：交付 **Admin Web + Admin API**，以及**微信小程序后端接口（微信登录 + 订阅消息推送）**。三类客户端为 **App / 微信小程序 / Admin Web**（移除 User Web）；Phase 1 预留 `/api/app/v1` 前缀、不实现 App；`/api/wechat/v1`（微信小程序）只做后端接口。**微信小程序前端不在本项目范围**。
- **等保三级**：作为**安全设计目标**（按 GB/T 22239 落地安全机制、预留审计接口），正式测评待有真实部署环境后启动。
- **部署**：中国大陆（云厂商 TBD，候选阿里云 / 腾讯云）；本地与生产**均用 Kubernetes（保底 k8s）**、同一套 Helm Chart——本地 kind/k3d、生产托管 K8s（ACK/TKE）。
- **CI/CD**：GitHub Actions（CI + CD 到 k8s，**不引入 Jenkins 等额外 CI 工具**）。

### 客户端

| 客户端 | 前缀 | 认证 | 推送 | Phase 1 |
|---|---|---|---|---|
| Admin Web | `/api/admin/v1` | JWT（账号+密码） | — | **实现** |
| App | `/api/app/v1` | email/password（前台用户） | 厂商推送通道（待定） | 预留 |
| 微信小程序 | `/api/wechat/v1` | 微信登录 `code2session` → openid/unionid → 自有 JWT | 订阅消息 `subscribeMessage.send` | **后端接口实现**（前端不在本项目范围） |

微信小程序后端接口（Phase 1 实现，前端不在本项目范围）：登录走 `wx.login()` → `code` → 后端 `jscode2session` 换 openid/session_key/unionid → 关联前台用户（App email/password 账号可绑定同一用户、共享设备信息）→ 签发自有 JWT；推送走微信订阅消息，需用户订阅授权 + 模板，`access_token` 由后端统一管理与缓存。真实联调需小程序 appid/secret（可用微信测试号）。

## 2. 架构决策摘要

| 决策 | 结论 | 依据 |
|---|---|---|
| 架构形态 | 模块化单体（单 Laravel 应用 + 领域模块） | ADR-0001 |
| 设备侧 ID | 前台用户/设备/配对 ID = 无符号 32 位 | ADR-0002 |
| 业务事实来源 | 服务端事件为准，PostHog 延后 | ADR-0003 |

## 3. 架构形态：模块化单体

单个 Laravel 应用，内部按领域划分为 **11 个模块**（详见 [modules.md](./modules.md)）：

`Identity · Authorization · Admin · User · Device · Firmware · Ota · Feedback · Analytics · Notification · System`

约束：

1. 每个模块拥有自己的 **migration、seeder 和表**（表归属唯一）。
2. **禁止跨模块直接读写对方表**；跨模块访问一律通过目标模块的**服务接口**（in-process）。
3. 模块间不建立强耦合：模块 A 引用模块 B 的公开接口，不 import B 的 Model/表。
4. 数据库：**单个 PostgreSQL 库**（不拆库、不拆 schema）；模块边界靠代码约定 + 接口 + 表归属守住。
5. 认证、权限、审计贯穿所有模块，由 Identity / Authorization / System 模块提供接口，不做全局 Seeder 跨模块写库。

## 4. 数据所有权

- 模块的 migration/seeder 只创建自己的表、只初始化自己的基础数据。
- 共享数据（如管理员、角色）由**归属模块**提供读写接口，其它模块只读接口。
- 未来抽服务时，模块目录 + 迁移 + 接口 + 表归属 = 现成的服务边界。

## 5. 部署拓扑

### Phase 1（模块化单体）

```
                 ┌──────────────────────────────┐
  浏览器 ──────▶ │  Nginx (HTTPS / 静态站)        │
                 │  ├─ /            → admin-web  │
                 │  ├─ /api/admin/* → Laravel    │
                 │  └─ /docs        → 纯 HTML 文档│
                 └──────┬───────────────────────┘
                        │
        ┌───────────────┼────────────────────────┐
        ▼               ▼                        ▼
  ┌───────────┐  ┌────────────┐         ┌──────────────┐
  │ Laravel   │  │ PostgreSQL │         │ Redis        │
  │ (11 模块) │  │ (单库)     │         │ 缓存/队列/锁 │
  └─────┬─────┘  └────────────┘         └──────────────┘
        │
  ┌─────┴──────┐      ┌──────────────────┐
  │ Queue Worker│     │ S3 兼容对象存储   │
  │ + Scheduler │     │ (固件包/反馈图片) │
  └─────────────┘      └──────────────────┘
```

- Laravel 跑在 **Octane**（FrankenPHP worker 模式）；Queue Worker 与 Scheduler 为独立进程/独立 Pod（同代码库）。
- 设备/App 入站链路 Phase 1 只设计契约、不实现（见 [modules.md](./modules.md) Device/Ota/Analytics）。

### 部署选型（本地 + 生产统一 k8s）

本地与生产**均使用 Kubernetes**，用**同一套 Helm Chart / manifests**，只改环境值（镜像 tag、副本数、DB/Redis 连接、Secret），保证两者一致。

| 环境 | 选型 | 说明 |
|---|---|---|
| 本地 | **kind**（或 k3d / minikube / Docker Desktop k8s） | 轻量本地 k8s，跑同一套 Chart，`values.local.yaml` 覆盖 |
| 生产 | 托管 K8s（阿里云 ACK / 腾讯云 TKE） | 标准 k8s API、可迁移；免自建 control plane |

- **应用服务器：Laravel Octane（FrankenPHP worker 模式）**——长驻内存、免 php-fpm；FrankenPHP 单二进制、容器化最简单、PHP 8.5 兼容（备选 RoadRunner / Swoole）。Queue Worker 与 Scheduler 仍为独立进程/独立 Pod。
- **无状态服务入 k8s**：Octane app、queue worker、scheduler、nginx（静态站 + 反代）、admin-web、文档站 → Deployment + Service + Ingress + ConfigMap/Secret + HPA。
- **有状态服务**：生产走托管（PostgreSQL→RDS、Redis→云 Redis、对象存储→OSS/COS）；本地在集群内跑单实例 Postgres/Redis（`values.local.yaml` + 本地 PVC），保持拓扑一致。
- **交付物**：Dockerfile（Octane / nginx+admin-web）、Helm Chart（`values.local.yaml` / `values.prod.yaml`）、`kind` 本地集群配置。

### 部署流程（CI/CD）

CI/CD 用 **GitHub Actions 即可，不需要 Jenkins 等额外 CI 工具**：

1. **CI（PR/推送）**：后端 Pint / PHPStan / PHPUnit / migrate+seed / OpenAPI diff；前端 Biome / tsc / build。
2. **构建镜像**：Octane 应用镜像、nginx+admin-web 静态镜像，推送到镜像仓库（阿里云 ACR 或 GHCR）。
3. **CD（main 合入）**：`helm upgrade`（或 `kubectl apply -f`）部署到 k8s——本地 kind / 生产 ACK/TKE。
4. **可选 GitOps**：后续要多环境灰度/自动同步再引入 Argo CD；MVP 阶段 GitHub Actions 直连部署足够，不引入 Jenkins。

### 演进（未来抽服务）

当某模块出现独立扩容、独立团队、独立发布诉求时，按模块逐个提取为独立服务：

1. 模块目录 → 独立 Laravel 应用（或其它运行时）。
2. 模块公开接口 → HTTP/gRPC 契约。
3. 模块私有表 → 独立数据库（Schema 或库）。
4. 在网关层加路由与鉴权，逐步替换 in-process 调用为远程调用。

12 服务拆分、gRPC、Outbox/Saga、事件版本化等 **Phase 1 不落地**，作为演进路径保留。

## 6. 技术选型

| 领域 | 选型 | 锁定策略 |
|---|---|---|
| PHP | **8.5** | Docker 基镜像锁 tag/digest；若个别包卡 8.4 则降级并记录 |
| 后端框架 | **Laravel 13.x** | 安装时核对官方最新 13.x 补丁，`composer.lock` 锁死 |
| 应用服务器 | **Laravel Octane（FrankenPHP worker 模式）** | 长驻内存高性能、免 php-fpm；FrankenPHP 单二进制、PHP 8.5 兼容（备选 RoadRunner / Swoole） |
| 数据库 | **PostgreSQL** | 单库；版本锁 Docker tag |
| 缓存/队列/锁 | **Redis** | Laravel Queue + Redis |
| 认证 | **`php-open-source-saver/jwt-auth`** | JWT、8h 有效期、登出撤销（黑名单）；若 Laravel 13 兼容滞后退 `firebase/php-jwt` 自建 |
| 链路追踪 | **W3C Trace Context + 结构化 JSON 日志**（Phase 1）；**OpenTelemetry**（未来抽服务后接入） | 见下方「链路追踪」说明 |
| 前端 | **Ant Design Pro 6.0** + React + TypeScript | ⚠️ 6.0 为新大版本，锁 6.0.x；若出现阻断性缺口退回 5.x，不改变业务范围 |
| 微信小程序 | 只做后端接口（前端不在本项目范围） | 登录 `code2session` + 订阅消息推送；Phase 1 做后端接口 |
| API 文档 | **Scramble**（生成 OpenAPI 3.1 JSON + 服务端渲染 HTML） | CLI 导出 JSON；纯 HTML 展示，不用 Scalar/Swagger UI JS SPA |
| 对象存储 | **S3 兼容**（阿里云 OSS / 腾讯云 COS） | 固件包、反馈图片；**本地开发用 Laravel `local` 磁盘（`storage/app`），生产切 S3** |
| 容器/编排 | **Kubernetes 本地+生产统一**（本地 kind/k3d，生产托管 ACK/TKE，保底 k8s） | 同一套 Helm Chart；无状态入 k8s，有状态生产托管 / 本地集群内单实例 |
| CI | **GitHub Actions** | 后端 Pint/PHPStan/PHPUnit/migrate+seed/OpenAPI diff；前端 Biome/tsc/build |
| 埋点 | **PostHog 延后** | Phase 1 不接；先做服务端业务事件管道 |
| 云厂商 | **TBD** | 候选阿里云 / 腾讯云，部署文档留占位 |

### 链路追踪（trace_id）

- **Phase 1**：请求入口中间件读取上游 `traceparent`（W3C Trace Context），无则生成新 `trace_id`；用 Monolog processor 注入每条日志；统一响应返回 `trace_id`。日志为结构化 JSON 输出（本地落 `storage/logs`，生产输出 stdout 供采集）。
- **不引入重型 tracing 后端**（Jaeger / Tempo / SkyWalking / 云 APM 均延后），先保证 `trace_id` 贯穿请求、队列任务与日志。
- **未来抽服务后**：接入 OpenTelemetry（SDK + Collector + 后端），因 `trace_id` 已在请求与日志中贯穿，业务代码无需改动即可升级为全链路追踪。

## 7. 目录结构

```
backend/                       # Laravel 13 单体
  app/Modules/
    Identity/  Authorization/  Admin/  User/  Device/
    Firmware/  Ota/  Feedback/  Analytics/  Notification/  System/
apps/admin-web/                # Ant Design Pro 6
infrastructure/                # Dockerfile、Helm Chart、kind 配置、nginx
docs/
  architecture/  database/  api/  security/  deployment/  requirements/
  adr/
GLOSSARY.md
```

## 8. 菜单与权限结构

菜单由 RBAC 动态控制（见 [modules.md](./modules.md) Authorization）。顶级结构：

1. 前台用户管理（用户列表 / 用户详情）
2. 设备管理（设备型号 / 硬件版本 / 设备列表）
3. 固件管理（固件版本 / OTA 更新策略 / OTA 推送任务）
4. 用户反馈管理（反馈列表 / 反馈详情与处理 / 反馈统计）
5. 数据分析（用户 / 设备 / OTA 升级 / 业务操作 / 设备激活）
6. 系统管理（后台用户 / 角色 / 资源 / 公钥 / 国际化 / 日志 / 字典 / 参数）
7. 个人中心（个人资料 / 修改密码）

权限编码采用稳定可读命名，如 `admin.user.list`、`device.model.create`、`firmware.publish`、`ota.strategy.update`。
