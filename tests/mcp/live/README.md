# Live Craft MCP verification

`verify.php` runs 64 assertions through real HTTP requests and native Craft database APIs. It is intentionally separate from the dependency-free `../run.sh` suite. Run it inside Docker, with curl enabled, against a **fresh disposable Craft Pro / MySQL installation** only.

Preparation:

1. Discover the PHP and database services with `docker ps`. Create a temporary project copy and a separate database named `comments_mcp_verify_<suffix>`. Never reset or reuse the shared project's database. Use a minimal Composer project requiring the desired Craft version, `vlucas/phpdotenv`, and this plugin via a path repository; allow Craft's and Yii's Composer installers.
2. Configure the temporary project's database connection. Set `CRAFT_RUN_QUEUE_AUTOMATICALLY=false`. Copy `app.php` from this directory to its `config/app.php` **before installation or fixture creation**. This forces file-only mail and observes queued notification types/actors. Do not start a queue worker.
3. Install Craft with its native `php craft install/craft` command and a generated disposable administrator password, then run `php craft plugin/install comments --interactive=0`. Use `http://localhost:8877/` as the fixture site URL. Run `php craft project-config/set system.edition pro --interactive=0` and `php craft project-config/write --interactive=0` before starting a fresh test process.
4. Create `.mcp-disposable` in that temporary project. The suite checks this marker, the database-name prefix, an empty Comments table, one initial site, Pro edition, and file-only mail transport. It creates dedicated active non-admin users and writes temporary random tokens to `config/comments.php`; never point it at a project with configuration or data to preserve.
5. In the temporary project, start `php -d sendmail_path=/bin/false -S 127.0.0.1:8877 -t web web/index.php`. In another Docker exec process, run:

   ```sh
   php /path/to/craft-comments/tests/mcp/live/verify.php /path/to/disposable-project
   ```

The suite fails fast. Restore a disposable baseline or create a new disposable installation before rerunning; fixtures are committed so the HTTP process can see them. Never restore that baseline over another database. The notification observer records only fixture IDs and notification types in `storage/runtime/mcp-notifications.jsonl`.

Afterward, stop this specific loopback server, drop only the disposable database and its grant, and remove the temporary project (including tokens, environment file and logs) and any disposable baseline dumps. Do not run garbage collection or fixture cleanup against the shared installation. Notification workers and external delivery are outside this suite's scope.
