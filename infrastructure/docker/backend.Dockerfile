# Laravel Octane (FrankenPHP) 应用镜像
# 说明：FrankenPHP 官方镜像自带 PHP + Caddy，Octane 以 worker 模式常驻内存运行。
# 生产由 Helm Chart 注入环境变量与密钥。

FROM dunglas/frankenphp:1.13-php8.5

# 安装所需 PHP 扩展（pdo_pgsql/pgsql 为 PostgreSQL，redis 为缓存/队列/锁）
RUN install-php-extensions pdo_pgsql pgsql redis bcmath intl opcache

WORKDIR /app

# 先复制依赖清单，利用层缓存
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# 复制代码与配置
COPY . .

# 运行时配置缓存（失败不阻断构建）
RUN php artisan config:cache --ansi || true \
    && php artisan route:cache --ansi || true

EXPOSE 8000

# Octane + FrankenPHP，常驻内存；水平扩展由 HPA 控制副本数
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
