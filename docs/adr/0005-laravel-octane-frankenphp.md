# Laravel 应用服务器采用 Octane（FrankenPHP）

后端应用服务器采用 Laravel Octane 的 FrankenPHP worker 模式，而非传统 php-fpm。Octane 常驻内存、免每次请求重新引导框架，吞吐显著更高；选 FrankenPHP 而非 Swoole/RoadRunner 是因为它单二进制、容器化最简单、PHP 8.5 兼容且官方支持 worker 模式。

- **Considered Options**: Swoole（PECL 扩展、协程心智负担）、RoadRunner（Go 二进制 + 持久 worker，成熟）。
- **Consequences**: Queue Worker 与 Scheduler 仍为独立进程/Pod；Octane 常驻内存要求请求间不得保存可变全局状态。
