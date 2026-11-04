# 设备侧实体 ID 使用无符号 32 位

前台用户 ID、设备 ID、配对 ID 固定为无符号 32 位整数（`unsignedInteger`），以对应设备固件侧的「4 个字节」约定。后台管理员 ID 不受此约束，可使用 bigint/ULID。

- **Consequences**: 这三类主键不能使用默认 bigint 或 ULID/UUID；DAO 与 API 返回需保证 32 位范围内。该约束不可见、易被后续开发者「顺手」改成 bigint，故记录。
