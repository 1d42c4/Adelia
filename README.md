# Adelia — PHP 8.5 edition

Adelia is a PHP imageboard with native JavaScript, 35 Vichan themes, static board/thread/catalog pages, image and configurable video uploads, and moderator tools to edit posts, replace attachments, pin threads and lock replies. It uses neither Composer nor Twig. The application has no ban system and does not collect poster IP addresses or IP hashes.

This edition targets PHP 8.5 and newer on a 64-bit runtime. It was verified on Windows with PHP 8.5.10 and nginx 1.30.4. PHP 8.4 and earlier are unsupported.

## Start the board

Double-click **Start Adelia.bat**. Keep it beside `imgboard.php` inside the board folder, or beside a subfolder named `Adelia`. It finds the board relative to its own location, so moving the folder or starting it from another working directory works. The launcher uses `C:\php\php.exe`, rebuilds `index.html`, starts the local server, and opens `http://127.0.0.1:8080/`. Keep its console open while using the board. Close the console or press Ctrl+C to stop it.

For the first run, follow the configuration steps below to create `settings.php`, generate a random secret and initialize your administrator account. This repository contains source and empty media directories; live settings, databases, uploads, generated pages and credentials are excluded.

The local server binds to the loopback interface. `local-router.php` exposes the board pages and media and blocks application code, configuration, database files, and scripts in upload directories.

## Requirements

- PHP 8.5+, 64 bit, with GD/FreeType, DOM, mbstring, fileinfo, curl, PDO, and the selected PDO database driver.
- The default configuration uses `pdo_sqlite` and `.adelia.db`; no separate database server is needed.
- Write access to the board directory, `src`, `thumb`, and `res` for the PHP process.
- JavaScript for static-page form tokens and management actions.

## Configuration

`settings.php` now returns a configuration array. Options are validated and loaded into `Adelia\Config`.

For a fresh installation:

1. Copy `settings.default.php` to `settings.php`.
2. Set `tripseed` to a random secret, for example the output of `php -r "echo bin2hex(random_bytes(32));"`.
3. Set `adminpass` to the initial administrator password. Optional `modpass` creates a moderator account.
4. Run `php imgboard.php` in the board directory. The database, initial accounts and static index are created.
5. Clear `adminpass` and `modpass` in settings after creation. Later runs never reset an existing account's password from these fields.

Common options:

| Option | Purpose |
| --- | --- |
| `board`, `boarddesc`, `boardtitle` | Board identifier, heading and browser title |
| `timezone` | PHP timezone identifier, initially `UTC` |
| `captcha`, `replycaptcha`, `managecaptcha`, `reportcaptcha` | `simple` or an empty string to disable that challenge |
| `report` | Enable reporting, initially `false` |
| `dbdriver`, `dbpath` | Initially `sqlite` and `.adelia.db` |
| `maxkb`, `maxkbdesc` | Upload size in KiB and the displayed description |
| `maxwop`, `maxhop`, `maxw`, `maxh` | Thumbnail dimensions |
| `threadsperpage`, `maxthreads`, `maxreplies` | Pagination and retention limits |
| `uploads`, `embeds` | Allowed media types and oEmbed endpoints |
| `thumbnail` | `gd`, `imagemagick`, or `ffmpeg` |
| `uploadviaurl` | Remote file downloads; initially `false` |

Keep `tripseed` unchanged after setup. It is used for tripcodes and editor conflict tokens. This edition uses HMAC tripcodes, so displayed tripcodes differ from original TinyIB. Old crypt/MD5 deletion-password formats and old flat-file databases are not supported.

The PDO implementation also contains MySQL and PostgreSQL connection/schema paths using `dbhost`, `dbport`, `dbname`, `dbusername`, `dbpassword`, or `dbdsn`. SQLite has been integration-tested. Set the appropriate port and install the matching PDO extension before using another database.

## nginx

`nginx.conf` is a standalone Windows example with a root of `C:/path/to/Adelia`; change it to your installation directory. Start the FastCGI listener with `start-php.ps1`, then start nginx with its own directory as the prefix:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "C:\path\to\Adelia\start-php.ps1"
C:\nginx\nginx.exe -p C:/nginx/ -c C:/path/to/Adelia/nginx.conf
```

nginx itself is not installed at `C:\nginx`; adjust that example to your nginx installation. The supplied configuration listens on `127.0.0.1:8080` and forwards PHP to `127.0.0.1:9000`. It executes only `imgboard.php` and `inc/captcha.php`. Run the Desktop server or nginx on port 8080 one at a time. Adjust the root, address and TLS configuration for a different deployment.

## Code and checks

Every PHP file declares strict types. Application classes are namespaced, methods have native parameter and return types, configuration uses readonly promoted properties and a computed property hook, and roles use an enum. PHP 8.5 features include the pipe operator, native URI parsing, `array_first`, `#[NoDiscard]`, and immutable configuration copies using clone-with. Password arguments use `#[SensitiveParameter]`.

The app has a single PDO data layer with prepared statements, explicit request normalization, session CSRF protection, one-use expiring CAPTCHA, password hashes, controlled external-process arguments, atomic static-page writes, and bounded remote downloads. PHP warnings and deprecations are surfaced as exceptions and logged.

Core entry points are `bootstrap.php`, `imgboard.php`, `inc/captcha.php`, and `local-router.php`. Application logic is under `app`. Translation packages, locales, reCAPTCHA, Apache rules, updater code, old database drivers, and compatibility fallbacks have been removed. The simple CAPTCHA font remains in `inc/fonts`.

Run the portable regression and source audit without modifying the board database:

```powershell
C:\php\php.exe tests/run.php
```

It checks an in-memory SQLite database, prepared queries, password verification, role privileges, input validation, CAPTCHA expiry/replay, subprocess arguments, strict declarations and method signatures. The application has also passed isolated nginx workflow tests and real browser form checks, including moderator editing, media replacement and mobile layouts. PHPStan level 5 has zero errors; `phpstan.neon` and `.php-cs-fixer.dist.php` make the checks repeatable with those development tools. Formatting follows PHP-FIG PER Coding Style with the PHP 8.5 migration rule set.

Image uploads, thumbnails and local video workflows were exercised during development. Optional remote providers, ImageMagick, FFmpeg and ExifTool require their own services/programs; their availability and behavior depend on the deployment configuration.

Adelia is a fork of TinyIB. Original copyright and MIT licensing are retained in `LICENSE`; Vichan/Tinyboard notices cover the reused styles and assets.

## Moderator editing

Open **Manage → Moderate Post**, enter a post number, then choose **Edit post / change image**. Administrators and moderators can edit the subject and message of any thread or reply, replace its attachment, or remove an attachment when the post remains valid. The author, tripcode, timestamp and deletion password are preserved. Text uses the same escaped quote, link and spoiler formatting as normal posts. Staff posts also use this format; raw HTML is not accepted.

**Pin thread / Unpin thread** controls board ordering. **Lock thread / Unlock thread** controls public replies. These buttons work from either the opening post or any reply and affect the containing thread. Saving rebuilds the thread, board index and catalog. Already-open public pages show edits after a refresh. A stale editor is rejected if another change has been saved in the meantime.

The app has no ban system and does not read or store poster IP addresses or IP hashes. Posting cooldowns and duplicate report checks use the browser session; clearing cookies resets those checks. Web-server and hosting-provider logs are separate.

For an existing database from the earlier edition, back up the board, remove the retired `banmessage`, `cloudflare` and `dbbans` options from settings, then run `php bin/upgrade-moderation.php` once before serving the new code. The upgrade adds editable source fields, removes old IP columns and the retired ban table, and cleans identifying ban records from the moderation log. New installations already use the new schema. If the old ban table had a custom name, pass that name as the script's first argument. The upgrade is repeatable and must be run from the CLI.

## Adelia naming

The PHP namespace is `Adelia`, the frontend module is `js/adelia.js`, and the local launcher is **Start Adelia.bat**. The default SQLite database is `.adelia.db`. Existing database rows and credentials are preserved when renaming an installed board. Browser theme and deletion-password preference cookies now use the `adelia_` prefix; previously saved browser preferences may need to be entered again. The app still uses `imgboard.php` as its entry point. When upgrading a renamed installation, keep its existing database path in settings or move the existing database to `.adelia.db` before the first run; do not initialize a new empty database over your existing board.
