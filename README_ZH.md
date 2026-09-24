# Console for PHP

[![Latest Stable Version](https://poser.pugx.org/wilkques/console/v/stable)](https://packagist.org/packages/wilkques/console)
[![License](https://poser.pugx.org/wilkques/console/license)](https://packagist.org/packages/wilkques/console)

[English](README.md) | 繁體中文

````
composer require wilkques/console
````

> **從 3.x 升級？** 像 `{--force}` 這種宣告過的旗標，預設值從 `true`
> 改成了 `false`；沒有宣告的選項現在會被拒絕；`handle()` 現在會
> 回傳一個 exit code，你的進入腳本必須把它傳給 `exit()`。請先讀
> [UPGRADING.md](UPGRADING.md)。

## 使用方式

1. 新增一個 PHP 指令檔。

    指令類別是依**路徑**被探索到的，它的完整類別名稱（FQCN）是從你根目錄
    `composer.json` 的 `psr-4` 對應表推導出來的——所以指令目錄必須放在
    psr-4 的根目錄底下，類別也必須宣告對應的 namespace。以
    `{"autoload": {"psr-4": {"App\\": "app/"}}}` 為例，把檔案放在
    `app/Console/DoSomethingCommand.php`：

    ```php
    <?php

    namespace App\Console;

    use Wilkques\Console\Command;

    class DoSomethingCommand extends Command
    {
        /**
         * signature
         *
         * @var string
         */
        public $signature = "do:something
                            {--debug : debug mode}
                            {--list : get list}";

        /**
         * description
         *
         * @var string
         */
        public $description = "do something";

        /**
         * handle this command
         *
         * @return int|null  null（或沒有回傳值）代表成功；任何 int 都會
         *                   被當成 process 的 exit code
         */
        public function handle()
        {
            // do something
        }
    }
    ```

1. 在終端機執行 `vi artisan`，並加入 PHP 程式碼
    ```php
    require_once 'vendor/autoload.php';

    $command = $argv;

    array_shift($command);

    // handle() 會回傳 exit code —— 把它傳給 exit()，這樣指令失敗時
    // CI、cron、shell script 才看得到。
    exit(
        console()
        ->setCommandRootPath("<set console dir path>") // 想換路徑的話
        ->setComposerPath("<composer.json path>") // 想換的話
        ->boot()
        ->handle($command)
    );
    ```

1. 在終端機執行 `php artisan do:something --debug`

## 引數（Argument）語法

位置引數用 `{name}` 在 signature 裡宣告，在 `handle()` 裡用
`$this->argument('name')` 讀回來：

```php
public $signature = 'member:get {username : the username to look up}';

public function handle()
{
    $username = $this->argument('username');
}
```

- `{name}` —— 必填。執行指令時沒帶這個引數會中止，訊息是
  `Not enough arguments (missing: "name").`，exit code 非 0。
- `{name?}` —— 選填。CLI 沒給的話會是 `null`。
- `{name=default}` —— 選填，沒給的話用預設值。
- `{name*}` —— 選填，把剩下所有位置值收集成一個陣列（必須是
  signature 裡的最後一個引數）。

```php
public $signature = 'deploy
    {environment : which environment to deploy}
    {branch? : branch to deploy, defaults to the current one}
    {--tag=latest : image tag}
    {servers* : one or more servers to deploy to}';
```

```
php artisan deploy production main web-1 web-2
// $this->argument('environment') === 'production'
// $this->argument('branch')      === 'main'
// $this->argument('servers')     === ['web-1', 'web-2']
```

如果傳的位置值比 signature 宣告的還多（而且最後一個引數不是 `*`
陣列引數），一樣會中止，訊息是
`Too many arguments, expected arguments "...".`

## 選項（Option）語法

選項用 `{--name}` 在 signature 裡宣告，用 `$this->option('name')`
讀回來：

- `{--force}` —— 布林旗標。預設是 **`false`**；命令列帶 `--force`
  就會變 `true`。
- `{--force=true}` —— 預設是 `true` 的布林旗標。
- `{--name=guest}` —— 帶值的選項，預設是 `guest`。
- `{--name=}` —— 帶值的選項，預設是 `null`。

在第一個 ` : ` 之後加描述——`{--force : skip confirmation}`。只有
**第一個** ` : ` 會拿來分隔 token 跟描述，所以預設值本身可以包含冒號：
`{--dsn=mysql:host=localhost : the DSN}` 會保留完整的值。

signature 沒有宣告過的選項會是錯誤：

```
$ php artisan do:something --not-declared=1
```

## Exit code

| 情境 | exit code |
|---|---|
| `handle()` 回傳 `null`／沒有回傳值 | `0` |
| `handle()` 回傳一個 int | 該 int |
| 說明畫面（沒帶指令） | `0` |
| 未知指令、引數錯誤、未宣告的選項 | `1` |
| 指令內未捕捉的例外 | 非 0 |

錯誤訊息會寫到 **stderr**，這樣就能跟指令的正常輸出分開重導向。
