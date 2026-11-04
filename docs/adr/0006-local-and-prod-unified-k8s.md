# 本地与生产统一使用 Kubernetes

本地开发也使用 Kubernetes（kind/k3d），与生产（托管 ACK/TKE）共用同一套 Helm Chart，只改 values 覆盖环境差异。传统做法是本地用 Docker Compose、生产上 k8s，但两套编排会漂移；统一 k8s 消除「本地能跑、k8s 上挂」的差异。

- **Consequences**: 本地需跑 kind 集群；有状态服务本地在集群内单实例、生产走托管；交付同一份 Helm Chart（`values.local.yaml` / `values.prod.yaml`）。
