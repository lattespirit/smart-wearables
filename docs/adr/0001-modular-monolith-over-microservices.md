# Phase 1 采用模块化单体而非微服务

PRD 提出微服务是为了「未来新增 MES/MOM 模块时方便扩展」，并非当前各领域有独立扩容或数据隔离需求；团队当前无专职 DevOps。因此 Phase 1 采用单个 Laravel 应用 + 领域模块（Identity / Authorization / Admin / User / Device / Firmware / Ota / Feedback / Analytics / System）的模块化单体，每个模块拥有自己的 migration、seeder 和表，模块之间只通过服务接口通信、禁止跨模块直接读写对方表。

- **Considered Options**: 12 个独立微服务（含 API Gateway）、多个 Laravel 应用骨架的 Monorepo、模块化单体。
- **Consequences**: 省去服务发现、分布式事务（Outbox/Saga）、gRPC 等当前用不上的复杂度；模块边界即未来服务边界，模块公开接口即未来 HTTP/gRPC 契约，抽服务时不需重划边界。若未来某模块出现独立扩容/独立团队/独立发布诉求，按模块逐个体外提取。
