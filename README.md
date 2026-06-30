# Sougou API

这是搜狗线索管理后台的 Laravel API 项目，负责用户登录、账户管理、线索列表、线索导入导出、360 线索同步、百度线索推送和表单过滤配置。

## 技术栈

- PHP `^8.5`
- Laravel `^12.0`
- Laravel Sanctum 负责 API token
- MySQL 或 Laravel 支持的关系数据库
- `xgbnl/enum` 提供枚举展示能力
- `xgbnl/validation` 提供场景化 Request 校验
- `ext-xlswriter` 用于线索 Excel 导入导出

## 目录结构

- `routes/api.php`：API 路由入口。
- `app/Http/Controllers`：HTTP Controller，只做参数接收、调用 Interactor、返回响应。
- `app/Http/Requests`：表单校验。部分 Request 继承 `Elephant\Validation\Validation\Validator`，支持 `scenes()` 和 `aliases()`。
- `app/Http/Output`：输出转换层，把模型字段转换成前端需要的 camelCase 或枚举 view model。
- `app/UseCases/Interactor`：业务逻辑层。新增功能优先放这里，不要把业务堆在 Controller。
- `app/Models`：Eloquent 模型。
- `app/Enums`：业务枚举。枚举通常实现 `Enumerable` 和 `Presenter`，并用 `#[Description]` 定义展示名称。
- `app/Console/Commands`：计划任务和命令。`SyncMarketingLeadData.php` 负责 360 线索同步。
- `app/ThirdParty`：第三方接口封装。`Baidu/DeliveryMessage.php` 负责百度线索推送消息处理。
- `database/migrations`：数据库迁移。
- `config/openapi.php`：OpenAPI、百度推送签名等配置。

## 运行与验证

常用命令：

```bash
composer install
php artisan migrate
php artisan serve
php artisan test
php -l app/UseCases/Interactor/FormFilterInteractor.php
```

本地一体开发脚本：

```bash
composer run dev
```

注意：当前 Codex 环境可能没有 `php` 命令。如果无法执行 PHP 检查，需要在本机开发环境或容器里运行上面的命令。

## 权限模型

用户角色定义在 `app/Enums/Role.php`：

- `admin`：管理员，`abilities()` 返回 `['*']`。
- `viewer`：只读用户。

管理类接口通常在 Interactor 内调用 `ensureAdmin()` 或直接判断 `$user->role->isAdmin()`。

认证路由使用 `auth:sanctum` 中间件。新增后台接口时，通常放在 `routes/api.php` 的 auth group 内。

## 核心数据表

### users

后台用户表。角色字段映射到 `App\Enums\Role`。

### accounts

线索账户表。关键字段：

- `channel`：账户渠道，枚举 `App\Enums\AccountChannel`，值为 `qihu` 或 `baidu`。
- `username`：账号名。百度账户只需要这个字段。
- `e_id`、`userid`、`secret`：360 线索接口调用字段。
- `status`：启用状态，枚举 `App\Enums\Toggle`。

### marketing_leads

营销线索表。关键字段：

- `account_id`：线索所属账户。
- `owner_id`：线索分配给的用户。
- `clue_id`：第三方线索 ID，唯一。
- `username`：客户姓名。
- `phone`：客户手机号。
- `keyword`：关键词。
- `search_word`：搜索词。
- `clue_time`：线索时间。
- `site_name`：落地页名称。
- `is_faker`：是否导入的伪造线索。

### form_filters

表单过滤项表。用于替代旧的 `config/openapi.php` 静态过滤配置。

- `type`：过滤类型，枚举 `App\Enums\FormFilterType`，值为 `name` 或 `phone`。
- `value`：过滤内容。
- 唯一索引：`type + value`。

设计原则：姓名过滤和手机号过滤不是键值对，而是两组无序集合。业务判断时姓名和手机号各走各的规则。

## 表单过滤功能

相关文件：

- `app/Enums/FormFilterType.php`
- `app/Models/FormFilter.php`
- `app/Http/Requests/FormFilterRequest.php`
- `app/Http/Output/FormFilterOutputData.php`
- `app/Http/Controllers/FormFiltersController.php`
- `app/UseCases/Interactor/FormFilterInteractor.php`
- `database/migrations/2026_06_18_000000_create_form_filters_table.php`

API：

- `GET /api/form-filters`：分页列表，参数 `page`、`perPage`、可选 `type`。
- `POST /api/form-filters`：创建过滤项，参数 `type`、`value`。
- `DELETE /api/form-filters/{id}`：删除过滤项。

业务判断方法：

- `FormFilterInteractor::shouldSkipName(string $name)`：姓名包含任意过滤词即跳过。
- `FormFilterInteractor::shouldSkipPhone(string $phone)`：手机号精确匹配过滤项即跳过。

缓存：

- 过滤项按类型缓存 300 秒。
- 新增或删除过滤项时清理对应类型缓存。

迁移兼容：

- 创建 `form_filters` 表时，会把 `config/openapi.php` 中现有的 `filter_words` / `filter_keywords` 和 `filter_phone` 导入表里，避免已有过滤规则丢失。

## 360 线索同步

命令文件：

- `app/Console/Commands/SyncMarketingLeadData.php`

命令：

```bash
php artisan app:sync-marketing-lead-data
php artisan app:sync-marketing-lead-data --date=2026-06-18
php artisan app:sync-marketing-lead-data --start-date=2026-06-01 --end-date=2026-06-18
```

同步逻辑：

1. 查询 `channel = qihu` 且 `status = enabled` 的账户。
2. 调用 `App\ThirdParty\Openapi` 拉取线索。
3. 按账户关联用户轮询分配 `owner_id`。
4. 根据 `clue_id` 去重，包含软删除记录，但只判断 360 渠道账户下的线索。
5. 再按 `username + phone` 去重，包含软删除记录，但只判断 360 渠道账户下的线索。
6. 调用 `FormFilterInteractor` 判断客户姓名和手机号是否需要过滤。
7. 入库到 `marketing_leads`。

字段映射：

- `$lead['customer_name']` -> `username`
- `$lead['customer_tel']` -> `phone`
- `$lead['site_name']` -> `site_name`
- `$leadId` -> `clue_id`
- `$lead['ad_search_word']` -> `search_word`
- `$lead['ad_keyword']` -> `keyword`
- `$lead['create_time']` -> `clue_time`

## 百度线索推送

路由：

- `POST /api/baidu/delivery`

处理文件：

- `app/Http/Controllers/BaiduDeliveryController.php`
- `app/ThirdParty/Baidu/DeliveryMessage.php`

签名配置：

```env
BAIDU_CLUE_DELIVERY_SIGN=固定签名
```

`config/openapi.php` 读取：

```php
'baidu_clue_delivery_sign' => env('BAIDU_CLUE_DELIVERY_SIGN', ''),
```

处理逻辑：

1. 校验消息中的 `sign` 是否等于配置中的签名。
2. 校验 `clueId` 是否存在。
3. 查询 `channel = baidu` 且 `status = enabled` 的账户。
4. 如果未删除记录中 `clue_id` 已存在，直接返回成功；软删除记录不参与该重复判断。
5. 调用 `FormFilterInteractor` 判断 `username` 和 `phone` 是否过滤。
6. 按缓存 cursor 轮询分配百度账户。
7. 按账户关联用户轮询分配 `owner_id`。
8. 入库到 `marketing_leads`。

百度消息字段映射：

- `clueId` -> `clue_id`
- `username` -> `username`
- `phone` -> `phone`
- `keyword` -> `keyword`
- `search_word` -> `search_word`
- `clue_time` -> `clue_time`


## 落地页线索提交

公开接口路径：

- `GET /api/x9/k7/t`：获取一次性 token 和算术验证码。
- `POST /api/x9/k7/s`：提交姓名、手机号、验证码和 token，控制器成功返回 `提交成功`。

相关文件：

- `app/Http/Controllers/LandingLeadController.php`
- `app/Http/Middleware/LandingCors.php`
- `config/landing.php`
- 落地页：`/Users/daifei/Desktop/ff-promo/index.html`

安全拦截：

- `LANDING_ALLOWED_ORIGINS` 限制允许提交的落地页域名，同时做 `Origin` / `Referer` 校验。
- IP 限流：默认 60 秒 6 次。
- 手机号限流：默认 600 秒 1 次。
- 蜜罐字段：`website` 有值时拒绝。
- 一次性 token：验证码接口生成 token 和算术题，提交后 token 立即失效。
- 入库前校验未删除记录中是否存在相同 `username + phone`，存在则拒绝。

分配规则：

- 默认查询所有启用的搜狗账户；如需指定搜狗账户，配置 `LANDING_ACCOUNT_IDS=1,2,3`。
- 展开 `accounts -> users` 后按 `user_id` 去重。
- 按用户当天已有线索数从低到高排序，数量相同按 `user_id` 从小到大排序。
- 使用缓存保存当天分配队列，并用缓存锁避免并发提交时分配偏移。

上线前需要把落地页里的 `LANDING_API_BASE` 改成真实 API 地址，并把该落地页域名写入后端 `LANDING_ALLOWED_ORIGINS`。

## 线索管理

相关文件：

- `app/Http/Controllers/MarketingLeadsController.php`
- `app/UseCases/Interactor/MarketingLeadInteractor.php`
- `app/Http/Requests/MarketingLeadRequest.php`
- `app/Http/Output/MarketingLeadOutputData.php`

接口：

- `GET /api/marketing-leads`：线索列表。
- `POST /api/marketing-leads/import`：Excel 导入线索。
- `GET /api/marketing-leads/export`：导出线索。
- `DELETE /api/marketing-leads/{id}`：删除线索。
- `GET /api/dashboard/marketing-leads/stats`：Dashboard 统计。

导入分配规则：

- 前端传入 `accountIds` 和 Excel 文件。
- 后端先筛掉未删除记录中已存在的相同 `username + phone` 线索；软删除记录不参与该重复判断。
- 通过所选启用账户反查关联用户，按 `user_id` 去重；同一用户关联多个所选账户时，只参与一次分配，并使用其关联的最小 `account_id` 落库。
- 按用户当天已有线索数从低到高排序，数量相同再按 `user_id` 从小到大排序。
- Excel 每一行只生成一条 `marketing_leads`，按排序后的用户列表依次一对一分配，不再按账户数量复制多条。
- 后续调整导入分配时，优先查找 `MarketingLeadInteractor::importAssignments`。

权限：

- admin 可查看全部线索，并可导入、删除。
- viewer 只查看分配给自己的线索。

## 账户管理

相关文件：

- `app/Http/Controllers/AccountsController.php`
- `app/UseCases/Interactor/AccountInterfactor.php`
- `app/Http/Requests/AccountRequest.php`
- `app/Http/Output/AccountOutputData.php`

接口：

- `GET /api/accounts`
- `POST /api/accounts`
- `PATCH /api/accounts/{id}`

百度账户只要求账户名；360 账户要求 `eId`、`userid`、`secret`。

## 用户管理

相关文件：

- `app/Http/Controllers/UsersController.php`
- `app/UseCases/Interactor/UserInteractor.php`
- `app/Http/Requests/UserRequest.php`
- `app/Http/Output/UserOutputData.php`

接口：

- `GET /api/users`
- `POST /api/users`
- `PATCH /api/users/{id}`
- `DELETE /api/users/{id}`
- `GET /api/users/{id}/accounts`
- `PATCH /api/users/{id}/accounts`

用户账户绑定不区分渠道，统一查出可绑定账户。

## 新增功能约定

新增后台功能时，优先按以下路径补齐：

1. `database/migrations`：建表或改表。
2. `app/Enums`：需要固定选项时新增枚举。
3. `app/Models`：新增模型和 casts。
4. `app/Http/Requests`：新增校验。
5. `app/UseCases/Interactor`：业务逻辑。
6. `app/Http/Output`：前端展示字段转换。
7. `app/Http/Controllers`：调用 Interactor。
8. `routes/api.php`：补充路由。
9. web 项目同步补 action、type、view、page、menu。

字段输出给前端时，数据库 snake_case 字段通常在 OutputData 里转成 camelCase。

## 重要注意事项

- 不要再把新增过滤项写死在 `config/openapi.php`，应使用 `form_filters` 表。
- 修改线索字段时，需要同步更新模型 fillable、迁移、Interactor、OutputData 和 web 类型。
- 修改账户渠道时，需要同步更新 `AccountChannel`、后端 Request、前端 `accountTypes.ts` 和添加账户弹窗。
- 第三方推送入口要尽量幂等，`clue_id` 已存在时直接返回成功。
