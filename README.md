### `Laravel Coral`

一个用于Laravel框架的模块化开发扩展包

---

### 特性

- **模块化架构**: 允许将应用拆分为独立的模块
- **自动路由注册**: 基于注解/属性的路由定义
- **中间件支持**: 提供基于属性的中间件定义方式
- **请求验证**: 内置路由参数验证功能
- **速率限制**: 方便地为接口添加速率限制功能
- **数据库事务**: 简化数据库事务处理
- **工作器管理**: 包含定时任务和后台工作器支持
- **视图管理**: 模块化的视图组织

### 自定义模块层

默认会扫描 `app/Modules`，并保持原有模块目录、`.ignore`、控制器、命令、工作器和视图注册方式不变。

如果需要额外的模块层，例如 `app/Plugins`，可以在应用配置中添加：

```php
// config/coral.php
return [
    'module_layers' => [
        app_path('Modules'),
        app_path('Plugins'),
    ],
];
```

`app_path('Plugins')` 会解析为 `App\Plugins` 命名空间，模块类为 `App\Plugins\{ModuleName}\Module`。

### 安装

```bash
composer require cloudtay/laravel-coral
```
