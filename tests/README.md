# Rendering regression tests

`rendering.php` is a dependency-free integration test against an installed Craft site with Comments enabled. It boots Craft's web application so the bundled Twig templates, session, identity, element queries and plugin services are exercised together. No test dependencies are added to the plugin's production requirements.

Run it **only in a new disposable site/database**. The script creates fixture users and comments, selects the native Pro trial edition for multiple users, disables notification delivery in memory, and retains fixtures for before/after measurements. Mutation tests use a transaction and roll it back. Do not point it at a development site containing your own content or a production site.

Install this branch through a Composer path repository in the disposable site, install Craft and Comments with their native console installers, and run:

```sh
CRAFT_TEST_APP=/absolute/path/to/craft \
COMMENTS_TEST_DISPOSABLE=1 \
php /absolute/path/to/comments/tests/rendering.php
```

In Docker, pass those variables with `docker compose exec -T -e ...` and use the paths inside the container. The site's `bootstrap.php` must define `CRAFT_BASE_PATH` and `CRAFT_VENDOR_PATH` and load its Composer autoloader, as the standard Craft project does. Give each test site a unique application ID if they share a MySQL server (mutex locks are application-scoped). Use an admin with username `admin` in the disposable site. PHP CLI needs the same extensions as the Craft application.

To measure unchanged upstream code against identical fixtures, export the target branch to a separate directory and run a fresh PHP process with these additional variables:

```sh
COMMENTS_TEST_SOURCE=/absolute/path/to/upstream/src \
COMMENTS_TEST_BASELINE=1 \
CRAFT_TEST_APP=/absolute/path/to/craft \
COMMENTS_TEST_DISPOSABLE=1 \
php /absolute/path/to/comments/tests/rendering.php
```

Baseline mode skips assertions for the new batching behavior and mutation invalidation. When a baseline report exists, changed-mode tests compare signed-in state and HTML with it, normalizing request tokens and relative timestamps. Guest vote helpers receive an empty User object because upstream dereferences the user's ID without a null guard. Upstream's missing session IDs also make guest reaction state falsely absent; compare numeric counts/scores and signed-in state exactly, and expect the corrected guest session state to differ.

Fixtures include 23 displayed comments from ten registered authors plus a guest, three nested levels, an empty-reaction comment, 100 historical comments across approved/pending/spam/trashed statuses and another owner, 10,110 votes (including neutral rows), and 26 flags. Tests cover:

- Five reaction/score queries for 1, 9 and 23 loaded comments, with zero additional queries for repeated reads, including empty results.
- Late-loaded reply collections, pagination, multiple owners, array results and empty-thread HTML.
- Bundled Twig rendering of all nested replies, escaped text, both viewers, and anonymous users.
- Guest session changes, viewer switching, guest settings and editing permissions.
- Vote/flag counts, net scores, history scope, inclusive moderation thresholds and vote/flag sorting.
- Vote insertion, direction changes, movement, deletion; flag insertion/removal; moderation, author changes, soft/hard deletion and restoration.
- Standalone rendering after posting/editing, existing nested replies, and hidden pending responses.

The script writes `baseline.json` or `changed.json` in the test site's root, containing measured SELECT query counts, elapsed milliseconds, SQL, rendered output and (for the changed implementation) MySQL EXPLAIN plans. Query profiling and query caches are explicitly controlled; fixtures and application bootstrap are outside the measured regions. Use fresh PHP processes for before/after runs and report whether Twig compilation/storage caches are warm. These small synthetic fixtures do not establish production response latency or replace production SQL profiling.

Batching is lazy: each service fetches missing data for the currently registered collection. Craft's eager-loaded reply collections register their own IDs before template rendering. Custom templates that fetch replies later batch each later collection; they do not force the whole discussion into memory. Counts and author scores are request-scoped, and viewer records are additionally keyed by user ID or guest session. No rendered HTML or persistent reaction cache is introduced. `setCommentIds()` remains available with its original replacement semantics.

Author scores retain their existing scope: votes on comments whose plugin status is `approved`, across all owners/sites. The existing implementation does not filter `elements.dateDeleted`, so soft-deleting an otherwise approved element still contributes until its plugin status changes or it is hard-deleted. This performance change preserves that behavior rather than silently changing reputation rules.
