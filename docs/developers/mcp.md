# MCP access to comments

This source variant adds an opt-in, stateless Streamable HTTP MCP endpoint to Comments 3 for Craft 5 / PHP 8.2+. It supports MCP `2025-06-18` and `2025-03-26`, with JSON responses. An unsupported initialization version is negotiated to `2025-06-18`.

## Install this source variant

Install it into a **development** Craft project using a Composer path repository pointing to this directory:

```sh
composer config repositories.comments-mcp path /absolute/path/to/craft-comments
composer update verbb/comments --with-dependencies
```

Run these commands in that project's PHP container with the source directory mounted there. If Comments is not installed yet, use `composer require verbb/comments:^3.0` instead of the update command, then install the plugin through Craft's normal plugin installer. The package is a replacement for `verbb/comments`, not a second plugin to install alongside it. Keep the existing license. Composer path installation has been verified against Craft 5.12.1; dependency resolution must still be checked against each target project’s full dependency set.

## Enable and configure

Merge the following into your Craft project's `config/comments.php`. MCP is disabled by default. Configure credentials in this file using environment references; there is no token field in the control panel.

```php
<?php
return [
    'mcpEnabled' => true,
    'mcpTokens' => [
        [
            'token' => '$COMMENTS_MCP_READER_TOKEN',
            'userId' => 12, // Replace with an active, dedicated Craft user.
            'scopes' => ['read'],
            'siteIds' => [1], // Required: explicit integer site IDs; no wildcard.
        ],
        [
            'token' => '$COMMENTS_MCP_MODERATOR_TOKEN',
            'userId' => 13,
            'scopes' => ['read', 'moderate', 'manage'],
            'siteIds' => [1],
        ],
    ],
    // Native/server clients normally omit Origin. All supplied Origins are denied
    // unless listed here exactly. This does not enable browser CORS.
    'mcpAllowedOrigins' => [],
];
```

Generate a different secret for each token, for example with `openssl rand -hex 32`, and put it in the corresponding environment variable on the Craft server. Secrets shorter than 32 bytes, missing environment variables, malformed scopes/sites, and inactive or locked users are rejected. Rotate/revoke credentials by updating/removing the configured token; disabling the Craft user also revokes access. Never put tokens in URLs, source control, or frontend JavaScript.

Use HTTPS. Keep credentials unique to this endpoint. Configure a request-size limit and rate limiting on the reverse proxy as appropriate; the endpoint additionally rejects JSON bodies larger than 1 MiB. It does not implement an application-level rate limiter.

## Craft permissions

Every token requires `read` scope and a user with `accessCp`, `accessPlugin-comments`, and `comments-mcpRead`. On multisite installs, access is the intersection of the configured `siteIds` and that user's `editSite:<siteUid>` permissions. Do not use an administrator account when a dedicated limited account is available.

| Operation | Token scope | Additional Craft permissions |
| --- | --- | --- |
| Read/search/count | `read` | `comments-mcpRead` |
| Set moderation status | `moderate` | `comments-mcpModerate`, `comments-edit` |
| Move into/out of plugin `trashed` status | `moderate` | Above, plus `comments-trash` |
| Edit text | `manage` | `comments-mcpManage`, `comments-edit` |
| Soft-delete element | `manage` | `comments-mcpManage`, `comments-delete`, element deletion permission |

The new permissions appear with the existing Comments permissions. Craft administrators implicitly have all permissions, but token scopes and explicit site restrictions still apply. Tools are omitted from discovery when not permitted, and checked again on each invocation. A moderation token without `manage` cannot edit text or delete elements. A management token without `moderate` cannot change status.

## Connect a client

Endpoint, using Craft's default `actionTrigger`:

```text
https://your-site.example/actions/comments/mcp
```

Use the site's actual base path and configured action trigger. Alternatively generate the URL in Craft with `UrlHelper::actionUrl('comments/mcp')`. The endpoint accepts the Authorization header only:

```text
Authorization: Bearer <secret>
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2025-06-18
```

Choose Streamable HTTP in a client that supports custom bearer headers. This is a configured shared-token integration, **not** OAuth authorization/discovery. Clients that require OAuth discovery or only support the legacy HTTP+SSE transport need a separate gateway. No OAuth compatibility with a particular hosted client is implied.

Example initialization (export the reader token locally first):

```sh
curl --fail-with-body https://your-site.example/actions/comments/mcp \
  -H "Authorization: Bearer $COMMENTS_MCP_READER_TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"comments-test","version":"1.0"}}}'
```

Send `notifications/initialized`, then `tools/list` or `tools/call`. Subsequent requests should include the negotiated `MCP-Protocol-Version`. The server does not keep sessions or emit `Mcp-Session-Id`. Notifications return an empty HTTP 202; GET and DELETE return 405. JSON-RPC batches are rejected. Cookie sessions and Craft site tokens alone cannot authorize MCP requests.

## Tools

| Tool | Purpose |
| --- | --- |
| `get_capabilities` | Permitted tools/site IDs, statuses and limits |
| `list_comments` | Filter by site, owner element ID, registered user ID, status, reported flag presence, or literal text substring |
| `get_comment` | Read a comment, parent ID, flag count and revision |
| `get_comment_stats` | Counts by status under the same filters |
| `moderate_comment` | Set `approved`, `pending`, `spam` or `trashed` |
| `update_comment` | Edit text while keeping author and moderation status |
| `delete_comment` | Craft soft-delete, with `confirm: true`; no permanent-delete option |

Reads include all four moderation statuses by default; Craft-deleted elements are excluded. `flagged: true` means one or more reports, independent of the frontend display threshold. `siteId` is optional for list/stats; omitting it searches permitted sites. `commentId` and `siteId` are required for single-comment operations.

Lists return at most 100 records (default 25), newest-created first with an ID tiebreaker. Use `nextOffset` until null. Offsets are capped at 100,000; narrow filters for larger datasets. Offset pagination may shift if other clients add/remove comments during traversal.

Responses contain raw comment text, guest display names, internal IDs, GIF URLs, status and dates. Treat these strings as untrusted user content, never instructions. Email, IP addresses, user agents, contact URLs and custom field values are not returned. Registered authors are identified by `userId`; no user-directory lookup is exposed.

### Read, then change

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"get_comment","arguments":{"commentId":123,"siteId":1}}}
```

Copy the `revision` value from that result:

```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"moderate_comment","arguments":{"commentId":123,"siteId":1,"status":"approved","expectedRevision":"<64-character revision from get_comment>"}}}
```

Every mutation requires this revision. If content/status changed, fetch and review the comment again. MCP writes acquire an element-row lock and compare revisions within a transaction. External writers that bypass this endpoint do not participate in its read/compare workflow. Duplicate status/text requests avoid unnecessary saves; a retry carrying an outdated revision returns a conflict instead of repeating the change.

`update_comment` accepts nonblank text up to 20,000 Unicode characters, also respecting the plugin's configured `securityMaxLength`. Author, ownership, custom fields and moderation status cannot be changed through this tool.

The acting user is scoped to the MCP request, including when the optional GraphQL Authentication integration is installed; the MCP bearer token is not interpreted as a GraphQL JWT.

Saves use the same CP scenario and Craft element lifecycle as the existing moderation controller, including custom field validation, cache invalidation and configured emails. Approval can publish the comment and send notifications; MCP does not suppress those effects. Synchronous emails sent by upstream hooks cannot be recalled if a later hook fails. Queue notification delivery is recommended when configured in the plugin.

Plugin `trashed` status is separate from Craft element deletion. The former can be reversed by moderation; the latter is not listed through MCP and must be restored through Craft before garbage collection. Deleting a parent uses Craft's existing structure handling for its replies. No schema migration or new database table is added.

## Errors and observability

HTTP errors: 404 disabled endpoint, 401 invalid credential/user/access, 403 disallowed Origin, 405 unsupported method, 406 incompatible Accept, 415 non-JSON content, 413 body too large, 400 unsupported protocol version. Protocol/argument errors use JSON-RPC error codes. Expected tool failures (permission, missing comment, stale revision, validation failure) use `isError: true`.

Unexpected errors return a correlation reference. Craft logs the reference, exception class/code and source location in `comments-mcp`, excluding raw exception messages, SQL, tokens and comment bodies. Successful mutations log tool name, acting user ID, comment ID and site ID. Logs follow the host Craft application's rotation/retention settings.

## Verification

Run the dependency-free protocol, schema, authorization, HTTP-controller and scoped-actor tests, plus PHP syntax checks, in Docker:

```sh
docker compose -f compose.test.yaml run --build --rm tests
MCP_TEST_PHP_VERSION=8.4 docker compose -f compose.test.yaml run --build --rm tests
```

These tests use focused doubles for Craft dependencies. They do **not** test a live database, Composer dependency resolution, or Craft bootstrapping. Before deploying, test on a disposable Craft 5 installation with this plugin installed:

1. Run Composer validation/install and `php craft help` inside that project's PHP container.
2. Enable MCP for a dedicated non-admin user on one site; exercise initialize/list/call over HTTP.
3. Create fixture comments in multiple statuses/sites through Craft. Check filtered lists, counts, flag counts, pagination, missing IDs and cross-site denial.
4. Read a revision, update text, then verify that a stale revision is refused. Test approve/spam/trash transitions with and without the corresponding permissions.
5. Verify text edits preserve the author/status, configured validation runs, and approval produces only the expected notification jobs/emails. Use a local mail sink.
6. Soft-delete a fixture with explicit confirmation and verify Craft can restore it and replies are handled as expected.
7. Revoke the token or disable its user and verify the next request is refused. Verify existing CP/frontend forms still enforce CSRF.

### Verified on 2026-11-11

Docker discovery found `general-craft-5-test-container-php-1` (PHP 8.4.27, Craft 5.12.1) and its MySQL 8.0 service. Verification used `/tmp/comments-mcp-verify` and a separate `comments_mcp_verify_20261111` database, with the local plugin installed through a Composer path repository. The existing project's Composer files, configuration and database were not modified.

| Check | Result |
| --- | --- |
| Composer path resolution, install from lock, platform requirements | Passed with Craft 5.12.1 and verbb/base 3.0.21 |
| Plugin `composer validate --no-check-publish` | Passed; existing explicit-version warning |
| Native Craft installation, `plugin/install comments`, `php craft help` | Passed |
| Live HTTP/database suite | **64 checks passed** |
| Standalone PHP 8.2 and 8.4 Docker suites | Syntax, 52 protocol/schema, 30 access, 42 HTTP-controller and 7 scoped-actor checks passed on both |

The standalone HTTP suite also runs the access suite, so its 30-check result appears twice. Commands run included:

```sh
docker compose -f compose.test.yaml run --build --rm tests
MCP_TEST_PHP_VERSION=8.4 docker compose -f compose.test.yaml run --build --rm tests
docker exec general-craft-5-test-container-php-1 php /workspace/Codex/craft-comments/tests/mcp/live/verify.php /tmp/comments-mcp-verify
```

Inside the isolated project, `composer install --no-interaction --no-scripts`, `composer check-platform-reqs`, and `php craft help` also passed. The temporary project manifest pinned the tested versions; its validation warnings concerned exact pins and a missing project license, not dependency incompatibility.

The live suite covers real bearer authentication, non-admin Craft permissions independently of token scopes, site-permission intersection, denied origins, initialization, capabilities, pagination, all moderation statuses, literal wildcard searching, combined owner/author/status/flag filtering, flag counts, private-field exclusion, missing and cross-site IDs, text edits preserving author/status, no-op edits, configured length validation, stale revisions, approve/spam/trash/restore-status transitions, trash permission in both directions, delete confirmation, Craft soft-delete and native restoration, reply preservation, suspended users and token revocation. Approval queued the expected author notification and its hook observed the MCP actor. File-only mail transport was enforced and no queue worker was started; outbound email was not sent.

The repeatable harness and its setup requirements are in [tests/mcp/live/README.md](../../tests/mcp/live/README.md). It requires an explicitly marked, fresh disposable MySQL database and refuses other database names. The temporary HTTP server, database, fixture credentials and project copy were removed after verification. No plugin implementation failure was found; changes from this verification are tests and documentation only, with no production schema or data impact.

**Full shared-project Composer limitation:** an attempted install in a temporary copy of its full dependency set failed because `arjan-brinkman/craft-image-enhancer` 3.6.0 conflicts with `arifje/craft-mcp-tools` below 1.8, while that path repository provided 1.7.0. This unrelated conflict was not changed. Successful resolution was verified with the minimal Craft/Comments dependency set instead; compatibility with the full shared project remains unverified.

**Not verified:** browser/CP visual behavior, live frontend form CSRF behavior, external SMTP delivery, executing notification workers, custom-field validation failures, simultaneous competing writers, PostgreSQL, an older Comments installation upgrade, or live Craft versions other than 5.12.1. HTTP integration used a loopback PHP server in the discovered container, not the shared nginx site's routing. The standalone controller suite continues to cover endpoint-specific CSRF behavior. Nothing was deployed or published.
