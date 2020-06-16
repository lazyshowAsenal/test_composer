# feng_log_component

系统业务配置服务,仅用于PHP项目,

## 使用方式


1. 修改composer.json文件,添加如下内容

```

"repositories": [
    {
        "type": "git",
        "url": "git@47.94.217.232:server/feng_log_component.git"
    }
]


```

2. 添加依赖

```

composer require feng/log

```

> 后续如果更新则使用如下命令：

```

composer update feng/log

```


3. 配置 AppServiceProvider 添加 registerFengLogger 方法, 并在 register 中调用


```

    public function register()
    {

        $this->registerFengLogger();
    }

    public function registerFengLogger()
    {

        $this->app->register(\Feng\Log\Providers\LumenLoggerServiceProvider::class);

        if (!class_exists('FengLog')) {
            class_alias('Feng\Log\Facades\FengLog', 'FengLog');
        }
    }

```
> 注意：如果是laravel，需要修改注册provider
```
        $this->app->register(\Feng\Log\Providers\LaravelLoggerServiceProvider::class);
```


> 注意：如果未开启 AppServiceProvider，需要在bootstrap/app.php文件中解注释(laravel无需开启)

```

$app->register(App\Providers\AppServiceProvider::class);

```



4. 定义LARAVEL_START, 在 bootstap/app.php 文件，如下示例：

```

<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('LARAVEL_START')) {
    define('LARAVEL_START', microtime(true));
}

```

5. 编写日志

```


在代码中引入Log

use Log


Log::info("这是一条INFO日志");
Log::error("这个一条错误日志");
Log::warning("这是一条警告日志");
Log::custom('custom_file_name')->info("这是一个custom日志");


```

6. 日志文件在 storage 目录下

```

logs/
├── base
│   └── base-2018-12-06.log
├── custom
│   └── custom.custom_file_name-2018-12-06.log
├── error
│   └── error-2018-12-06.log
├── request
│   └── request-2018-12-06.log
└── sql
    └── sql-2018-12-06.log


```
