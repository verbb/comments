# Security

The security panel in Settings allows you to check posted comments for certain values, and act accordingly if a match is found.

Values are matched against every attribute that the [Comment](docs:developers/comment) provides. This means that values can be words used in the comment, an email address, a URL, User Agent, or IP Address. Matching is case insensitive.

**Comment Moderation**

If a match is found, the comment will be marked as `pending`.

**Comment Spamlist**

If a match is found, the comment will be marked as `spam`.

**Comment Banned**

If a match is found, the comment will be blocked all-together, and not submitted.