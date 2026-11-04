# 客户端阵容：App / 微信小程序 / Admin Web（移除 User Web）

客户端由「App / User Web / Admin Web」调整为「App / 微信小程序 / Admin Web」：移除 User Web，改用微信小程序，并要求微信生态基础能力（微信登录、订阅消息推送）。理由：面向中国大陆市场，微信小程序是主要分发与触达渠道。

- **Consequences**: 新增 `/api/wechat/v1` 前缀；Identity 实现微信登录（`code2session`）；新增 Notification 模块承载订阅消息推送。Phase 1 交付 Admin Web + 微信小程序**后端接口**（登录+推送），小程序前端不在本项目范围。
