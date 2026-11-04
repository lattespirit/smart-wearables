# Smart Wearables — 智能穿戴设备管理平台

面向智能穿戴设备生态的业务管理平台：App 用户、穿戴设备、固件与 OTA 升级、用户反馈、数据分析，以及后台账号与 RBAC 权限系统。

架构与设计见 [`docs/architecture/architecture.md`](docs/architecture/architecture.md)；领域术语见 [`GLOSSARY.md`](GLOSSARY.md)；架构决策见 [`docs/adr/`](docs/adr/)。

## 目录结构

```
backend/                 # Laravel 13 + Octane（FrankenPHP）模块化单体，11 个领域模块
  app/Modules/           # Identity · Authorization · Admin · User · Device · Firmware
                         #   · Ota · Feedback · Analytics · Notification · System
apps/admin-web/          # Ant Design Pro 6（Umi Max 4 + antd v6 + ProComponents v3）
infrastructure/
  docker/                # backend.Dockerfile、admin-web.Dockerfile、nginx.conf
  helm/                  # Helm Chart（values.local.yaml / values.prod.yaml）
  kind/                  # kind 本地集群配置
docs/                    # 架构 / 数据库 / API / 安全 / 部署 / 待确认事项
.github/workflows/       # backend.yml + frontend.yml CI
```

## 技术栈

PHP 8.5 · Laravel 13 · Laravel Octane（FrankenPHP）· PostgreSQL · Redis · Ant Design Pro 6 · TypeScript · Scramble（API 文档，待接入）· Kubernetes（本地 kind / 生产托管 ACK/TKE）· GitHub Actions

## 本地运行

### 环境要求

- PHP 8.5 + Composer 2
- Node.js ≥ 20 + npm（前端使用 npm，非 pnpm）
- Docker Desktop + kind + Helm（本地 k8s，见 [ADR-0006](docs/adr/0006-local-and-prod-unified-k8s.md)）

### 后端（直接运行，需本地 PostgreSQL/Redis）

```sh
cd backend
composer install
cp .env.example .env          # 已配置 pgsql + redis
php artisan migrate:fresh --seed
php artisan octane:start --server=frankenphp   # 需先下载 frankenphp 二进制
```

> 首次运行需 `php artisan octane:install --server=frankenphp`（会从 GitHub Releases 下载 FrankenPHP 二进制；若 GitHub 不通，参考下方「FrankenPHP 二进制」）。

### 前端

```sh
cd apps/admin-web
npm install
npm run dev           # 本地开发（无 mock）
npm run build         # 构建 dist/
```

### 本地 k8s（推荐，与生产一致）

```sh
kind create cluster --config infrastructure/kind/config.yaml
helm install smart-wearables infrastructure/helm -f infrastructure/helm/values.local.yaml
```

## 测试与 CI

```sh
# 后端（测试用 SQLite 内存，无需数据库）
cd backend
vendor/bin/pint --test          # 代码风格
vendor/bin/phpstan analyse       # 静态分析（Larastan）
php artisan test                 # 单元/特性测试

# 前端
cd apps/admin-web
npm run lint                     # Biome + tsc
npm run build
```

CI（GitHub Actions）在 push / PR 时运行：`.github/workflows/backend.yml` + `frontend.yml`。

## 部署

生产部署到托管 Kubernetes（阿里云 ACK / 腾讯云 TKE），有状态服务走托管（RDS / 云 Redis / OSS）。详见 [`docs/architecture/architecture.md`](docs/architecture/architecture.md) 的「部署选型」与「部署流程」。

## FrankenPHP 二进制（GitHub 不通时）

`octane:install` 默认从 `github.com` 下载二进制；若该域名不通，可经 `api.github.com` 下载（此方式只需 API 与 CDN 可达）：

```sh
curl -s https://api.github.com/repos/php/frankenphp/releases/latest \
  | jq -r '.assets[] | select(.name=="frankenphp-mac-arm64") | .url' \
  | xargs curl -L -H "Accept: application/octet-stream" -o backend/frankenphp
chmod +x backend/frankenphp
```

## 常用命令

- 一键清空 + 生成演示数据：`php artisan migrate:fresh --seed`
- API 文档（Scramble，待接入）：`php artisan scramble:export`
