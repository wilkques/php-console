# Upgrading

## Upgrading to 4.0 from 3.x

Version 4.0 fixes a batch of confirmed correctness bugs, several of which were
silently producing wrong results rather than failing loudly. Most command
classes need no changes, but **two of these changes alter runtime behaviour in
ways you may be unknowingly depending on** (items 1 and 2 below), and the entry
point needs a one-line change to propagate exit codes (item 3).

---

### 1. Valueless flags in a signature now default to `false`, not `true`

This is the most important change in the release.

`Parser::parser()` used to serve two jobs with opposite meanings: parsing the
tokens a user typed on the command line (`--force` present means "switch it
on") and parsing the tokens declared in a `$signature` (`{--force}` merely
declares that the option exists). Because both went through the same code, a
declared flag came out of the signature as `true`, so it was permanently on:

```php
// 3.x
public $signature = 'deploy {--force : skip confirmation}';

public function handle()
{
    if ($this->option('force')) {
        // ALWAYS taken in 3.x, even with no --force on the command line.
        // The only way to turn it off was to literally type --force=false
    }
}
```

In 4.0, signature parsing no longer goes through `Parser` at all, and a
valueless declared flag defaults to `false`:

| signature | 3.x default | 4.0 default |
|---|---|---|
| `{--force}` | `true` ❌ | **`false`** ✅ |
| `{--force=false}` | `false` | `false` (unchanged) |
| `{--force=true}` | `true` | `true` (unchanged) |
| `{--name=guest}` | `'guest'` | `'guest'` (unchanged) |
| `{--name=}` | `''` | **`null`** (see item 8) |

Passing the flag on the command line is unchanged — `php artisan deploy --force`
still yields `true`.

**What to do:** if you wrote `{--debug=false : ...}` purely to work around the
old behaviour, you can now simplify it to `{--debug : ...}`. Both forms keep
working, so no edit is required. But if you have a command that *relied* on a
declared flag being on by default, make that explicit with `{--flag=true}`.

---

### 2. Options not declared in the signature are now rejected

3.x silently merged any option you typed into the option bag, declared or not.
4.0 raises `Wilkques\Console\Exceptions\InvalidArgumentException`, matching what
Symfony/Laravel do.

```
# 3.x: silently accepted, available via option('totally-unknown')
# 4.0: aborts with a message and a non-zero exit code
php artisan member:get someone --totally-unknown=xyz
```

**What to do:** declare every option you intend to pass in the command's
`$signature`.

---

### 3. `Console::handle()` now returns an exit code — update your entry point

In 3.x a command's return value was discarded and the process always exited `0`,
so **a failing command looked successful to CI, cron, and shell scripts**.
`Console::handle()` now returns an `int` exit code, and your entry script must
pass it to `exit()`:

```php
// artisan — 3.x
console()->setCommandRootPath('app/Console/')->boot()->handle($command);

// artisan — 4.0
exit(console()->setCommandRootPath('app/Console/')->boot()->handle($command));
```

Exit code semantics:

| situation | code |
|---|---|
| command `handle()` returns `null`/nothing | `0` |
| command `handle()` returns an int | that int |
| help screen (no command given) | `0` |
| unknown command, bad arguments, undeclared option | `1` |
| uncaught exception inside a command | non-zero |

> **Expect previously-green pipelines to go red.** That is the point — they were
> green because failures were invisible, not because nothing failed.

---

### 4. Exceptions thrown inside a command are now caught

3.x let them escape as a raw PHP fatal with a stack trace and exit status 255.
4.0 catches them, prints a readable message to **stderr**, and returns a
non-zero exit code. The library no longer calls `exit()` or `die()` anywhere —
that also makes `Console` unit-testable.

---

### 5. Duplicate command names now throw

Two classes declaring the same command name used to silently overwrite each
other, with the winner decided by `readdir()` order — i.e. **the outcome
differed between machines**. 4.0 raises
`Wilkques\Console\Exceptions\DuplicateCommandException` at boot.

---

### 6. Non-command files in the command directory are now skipped

3.x instantiated *every* `.php` file under the command root. An abstract
`BaseCommand.php`, a trait, or an interface living alongside your commands
caused a fatal error on **every** artisan invocation, including bare
`php artisan`. 4.0 skips anything that isn't a concrete `Command` subclass.

**What to do:** nothing — but you can now keep base classes and traits in
`app/Console/` without breaking the CLI.

---

### 7. Command class names are now resolved correctly

`getCommandClass()` stripped the psr-4 prefix from *every* position in the path
instead of just the start, so any command whose file or directory name contained
the prefix resolved to a bogus class:

| file | 3.x | 4.0 |
|---|---|---|
| `app/Console/AppleCommand.php` | `App\Console\leCommand` ❌ | `App\Console\AppleCommand` ✅ |
| `app/Console/AppVersionCommand.php` | `App\Console\VersionCommand` ❌ | `App\Console\AppVersionCommand` ✅ |
| `app/Console/Apps/SyncCommand.php` | `App\Console\s\SyncCommand` ❌ | `App\Console\Apps\SyncCommand` ✅ |

If you avoided naming commands this way because "it didn't work", it works now.

---

### 8. Signature parsing rewritten — several syntaxes that silently broke now work

The single large regex was replaced with a two-step parse. Fixes:

- **Defaults containing a colon are no longer truncated.** `{--dsn=mysql:host=localhost}`
  gave `'mysql'`; `{--time=10:30}` gave `'10'`; `{--url=https://x}` gave `'https'`.
  All now keep their full value. Only the first ` : ` separates a token from its
  description.
- **Signatures starting with a newline now work.** This very natural style:
  ```php
  public $signature = '
      member:get
      {username : the account}';
  ```
  used to register the command name as the literal string `{username : the account}`
  (so `member:get` was unreachable) *and* silently drop the `username` argument.
- **`{--name=}` now defaults to `null`** instead of `''`, matching Laravel, so
  `$this->option('name') === null` works as a "not supplied" check.
- **An empty `$signature`** now raises `InvalidSignatureException` instead of
  registering a command under the name `""`.
- **`{arg*}` must be the last argument.** Declaring `'sync {files*} {target}'`
  used to silently set the required `target` to `null`; it now raises
  `InvalidSignatureException` at parse time.

---

### 9. `console()` is now a singleton

`Console` was never bound as shared, so `console()` returned a brand-new
instance every call. This silently broke the two-line form:

```php
console()->boot();              // booted one instance
console()->handle($command);    // dispatched against a different, empty one
```

Only the single chained expression worked. `console()` now returns the same
instance, so both forms behave identically.

---

### 10. A command named `0` now dispatches

`handle()` used a truthiness test (`if (!$type)`), so the literal command name
`"0"` was treated as "no command given" and printed the help screen.

---

### 11. `register()` fixed

Two bugs: it assigned a single flat row to `$helpers` (where `boot()` builds a
list), so calling it twice kept only the last and mixing it with `boot()`
corrupted the help listing; and passing an already-built command *instance* —
which its own docblock advertised — was a hard `TypeError`. Both are fixed.

---

### 12. Dependency floor raised: `wilkques/container` `>=5.0.0`

`Console::fireAbstract()` uses the container's `instance()` method, which only
exists from container v5. 3.x declared `>=4.0.0`, which would have let composer
install an incompatible combination.

Also note the package's own PHP floor is `>=5.5`. 3.4.0 declared `>=5.5` while
actually using PHP 7.1 syntax (short list destructuring); that has been
corrected so the declared floor is now truthful.
