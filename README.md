# LaravelAPI

基于 Laravel 的 API 框架，提供常用工具类、助手函数等服务，采用模块化架构设计。

## 📖 项目简介

LaravelAPI 是一个基于 Laravel 12 构建的企业级 API 框架，采用模块化架构，提供用户管理、权限管理、日志管理等核心功能，支持快速开发 RESTful API 服务。

## 🚀 技术栈

- **PHP** >= 8.3
- **Laravel** 12.0
- **Laravel Sanctum** - API 认证
- **Laravel Modules** - 模块化架构
- **MySQL** - 数据库
- **IP2Region** - IP 地址查询

## ✨ 核心功能

- ✅ 用户登录 / 授权
- ✅ 管理员列表
- ✅ 用户管理（支持审核流程）
- ✅ 日志管理（操作日志、登录日志、审计日志、通用日志）
- ✅ 通知管理（公告、站内信）
- ✅ 权限管理
- ✅ 系统参数配置
- ✅ 文件管理
- ✅ 数据字典

## 🛠️ 环境要求

- PHP >= 8.3
- Composer
- MySQL >= 5.7
- PHP 扩展：fileinfo, pdo_mysql

## 📦 安装步骤

### 1. 安装项目

```shell
composer create-project siushin/laravel-api
```

### 2. 配置环境

```shell
# 复制环境配置文件
cp .env.example .env

# 生成应用密钥
php artisan key:generate
```

### 3. 配置数据库

编辑 `.env` 文件，配置数据库连接：

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_api
DB_USERNAME=root
DB_PASSWORD=
```

### 4. 创建数据库

在 MySQL 中创建数据库：

```sql
CREATE DATABASE laravel_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. 初始化系统

```shell
# 运行迁移和填充（会自动执行）
composer install

# 或手动执行
php artisan migrate:fresh --seed
```

**默认管理员账号：** `admin` / `admin`

## ⚠️ 注意事项

> ⚠️ **重要提示**：执行 `composer create-project` 或 `composer install` 都会执行 **清空** 表并 **重新填充** 数据（`php artisan migrate:fresh --seed`）。如有重要数据，请自行备份。

## ⚠️⚠️⚠️注意事项

> 注意：执行命令 `composer create-project` 或 `composer install` 都会执行 **清空** 表并 **重新填充** 数据
`php artisan migrate:fresh --seed`。如有重要数据，请自行备份。

## 📚 文档链接

- [API 接口文档](https://s.apifox.cn/9e462aa5-5078-455c-b631-75b9d9e2a303)
- [开发文档](https://github.com/siushin/GPAdmin-doc)
- [前端项目](https://github.com/siushin/GPAdmin)

## 💻 常用命令

### 开发

```shell
# 启动开发服务器（包含服务器、队列、日志、前端构建）
composer run dev

# 启动 Web 服务器
php artisan serve

# 启动队列监听
php artisan queue:listen

# 查看日志
php artisan pail
```

### 数据库

```shell
# 运行迁移
php artisan migrate

# 回滚迁移
php artisan migrate:rollback

# 运行填充
php artisan db:seed

# 重置数据库并填充
php artisan migrate:fresh --seed
```

### 测试

```shell
# 运行测试
composer test

# 或
php artisan test
```

### 代码规范

```shell
# 代码格式化（使用 Laravel Pint）
./vendor/bin/pint
```

## 目录结构

| 目录名    | 描述                                                           |
|--------|--------------------------------------------------------------|
| Cases  |                                                              |
| Enums  | 枚举类，一般以 `Enum` 结尾                                            |
| Funcs  | 助手函数，分以 `Lara` 开头的基于Laravel的助手函数，以及以 `Func`开头的常用助手函数（方便全局搜索） |
| Traits | 特征，没有明显命名规范，自行查询源码或文档                                        |

## 📁 模块说明

项目采用模块化架构，主要模块包括：

- **Base** - 基础模块（用户、管理员、日志、通知等）
- **Sms** - 短信模块

## 📂 目录结构说明

项目目录结构遵循 Laravel 规范，模块化代码位于 `Modules/` 目录下：

| 目录名    | 描述                                                           |
|--------|--------------------------------------------------------------|
| Cases  | 案例/示例代码                                                      |
| Enums  | 枚举类，一般以 `Enum` 结尾                                            |
| Funcs  | 助手函数，分以 `Lara` 开头的基于 Laravel 的助手函数，以及以 `Func` 开头的常用助手函数（方便全局搜索） |
| Traits | 特征类，没有明显命名规范，自行查询源码或文档                                        |

## 🧑🏻‍💻 关于作者

十年开发经验，具有丰富的前、后端软件开发经验~

👤 作者：<https://github.com/siushin>

💻 个人博客：<http://www.siushin.com>

📮 邮箱：<a href="mailto:siushin@163.com">siushin@163.com</a>

## 💡 反馈交流

在使用过程中有任何想法、合作交流，请加我微信 `siushin` （备注 <mark>github</mark> ）：

<img src="/public/images/siushin-WeChat.jpg" alt="添加我微信备注「GPAdmin」" style="width: 180px;" />

## ☕️ 打赏赞助

如果你觉得知识对您有帮助，可以请作者喝一杯咖啡 ☕️

<div class="coffee" style="display: flex;align-items: center;margin-top: 20px;">
<img src="/public/images/siushin-WechatPay.jpg" alt="微信收款码" style="width: 180px;" />
<img src="/public/images/siushin-Alipay.jpg" alt="支付宝收款码" style="width: 180px;" />
</div>
