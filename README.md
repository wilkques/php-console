# Console for PHP

[![Latest Stable Version](https://poser.pugx.org/wilkques/console/v/stable)](https://packagist.org/packages/wilkques/console)
[![License](https://poser.pugx.org/wilkques/console/license)](https://packagist.org/packages/wilkques/console)

````
composer require wilkques/console
````

> **Upgrading from 3.x?** Declared flags like `{--force}` changed default from
> `true` to `false`, undeclared options are now rejected, and `handle()` now
> returns an exit code your entry script must pass to `exit()`. Read
> [UPGRADING.md](UPGRADING.md) first.

## How to use

1. Add a PHP command file.

    Command classes are discovered by **path**, and their fully-qualified class
    name is derived from your root `composer.json`'s `psr-4` map — so the
    command directory must sit under a psr-4 root, and the class must declare
    the matching namespace. With `{"autoload": {"psr-4": {"App\\": "app/"}}}`,
    put this at `app/Console/DoSomethingCommand.php`:

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
         * @return int|null  null (or no return) means success; any int is the
         *                   process exit code
         */
        public function handle()
        {
            // do something
        }
    }
    ```

1. in terminal run `vi artisan` & Add PHP code
    ```php
    require_once 'vendor/autoload.php';

    $command = $argv;

    array_shift($command);

    // handle() returns the exit code — pass it to exit() so a failing
    // command is visible to CI, cron and shell scripts.
    exit(
        console()
        ->setCommandRootPath("<set console dir path>") // if you want change path
        ->setComposerPath("<composer.json path>") // if you want change
        ->boot()
        ->handle($command)
    );
    ```

1. in terminal run `php artisan do:something --debug`

## Argument syntax

Positional arguments are declared with `{name}` in the signature, and are
read back inside `handle()` via `$this->argument('name')`:

```php
public $signature = 'member:get {username : the username to look up}';

public function handle()
{
    $username = $this->argument('username');
}
```

- `{name}` — required. Running the command without it aborts with
  `Not enough arguments (missing: "name").` and a non-zero exit code.
- `{name?}` — optional. Resolves to `null` if not given on the CLI.
- `{name=default}` — optional, with a default value used when not given.
- `{name*}` — optional, collects every remaining positional value into an
  array (must be the last argument in the signature).

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

Passing more positional values than the signature declares (and the last
argument isn't a `*` array argument) also aborts, with
`Too many arguments, expected arguments "...".`

## Option syntax

Options are declared with `{--name}` inside the signature and read back with
`$this->option('name')`:

- `{--force}` — a boolean flag. Defaults to **`false`**; passing `--force` on
  the command line makes it `true`.
- `{--force=true}` — a boolean flag that defaults to `true`.
- `{--name=guest}` — takes a value, defaulting to `guest`.
- `{--name=}` — takes a value, defaulting to `null`.

Add a description after the first ` : ` — `{--force : skip confirmation}`. Only
the *first* ` : ` separates the token from its description, so defaults may
themselves contain colons: `{--dsn=mysql:host=localhost : the DSN}` keeps its
full value.

An option that the signature does not declare is an error:

```
$ php artisan do:something --not-declared=1
```

## Exit codes

| situation | exit code |
|---|---|
| `handle()` returns `null` / nothing | `0` |
| `handle()` returns an int | that int |
| help screen (no command given) | `0` |
| unknown command, bad arguments, undeclared option | `1` |
| uncaught exception inside a command | non-zero |

Errors are written to **stderr**, so they can be redirected separately from a
command's normal output.

