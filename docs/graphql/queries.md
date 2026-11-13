# Queries

Comments adds GraphQL queries for fetching comments, so a headless front end can show the discussion for an entry or any other element. Read [Craft’s GraphQL documentation](https://craftcms.com/docs/5.x/development/graphql.html) first if you haven’t set up a GraphQL endpoint before.

## Enable Comments in Your Schema

Comments’ queries only appear in schemas that are allowed to read comments. In the control panel, go to **GraphQL** → **Schemas**, edit the schema your client uses (or the **Public Schema**), and under **Comments** enable **View comments**. Save the schema.

The same schema needs **View users** (under **Users**) before the `userId` and `user` fields appear on comments, votes and flags, and access to the sections or other element types your comments are made on before you can query those elements.

To create, vote on, flag or delete comments through the API, enable the matching **Comments** mutation permissions as well. [Mutations](docs:graphql/mutations) explains what each one allows.

## Querying Comments

### The `comments` Query

Returns a list of comments, each as a [Comment](docs:graphql/interfaces-types#the-commentinterface-interface) type. Use it to fetch the comments made on an entry, the latest comments across your site, or a single user’s comments.

The query accepts the same arguments as Craft’s structure element queries, such as `id`, `site`, `search`, `limit`, `offset`, `orderBy`, `level` and `descendantOf`, plus the Comments arguments below. Each one works like the [comment query parameter](docs:getting-elements/comment-queries) of the same name. As with comment queries, only approved comments are returned unless you pass `status`.

| Argument | Type | Description |
| --- | --- | --- |
| `ownerId` | `[QueryArgument]` | Narrows the results to comments made on the elements with these IDs. |
| `userId` | `[QueryArgument]` | Narrows the results to comments posted by the users with these IDs. |
| `commentDate` | `[String]` | Narrows the results based on the date the comments were posted, such as `">= 2024-01-01"`. |
| `name` | `[String]` | Narrows the results based on the name the comments were posted with. |
| `email` | `[String]` | Narrows the results based on the email address the comments were posted with. A public schema can’t filter, search or order comments by email address, and its `search` terms only match the comment text, author name and custom fields. |
| `comment` | `[String]` | Narrows the results based on the comments’ text. |
| `status` | `[String]` | Narrows the results based on the comments’ statuses: `approved`, `pending`, `spam` or `trashed`. Craft only adds this argument when the schema allows querying inactive elements. |

This example fetches the two newest comments on the entry with an ID of 2460.

```graphql
{
  comments(ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
    id
    commentDate @formatDateTime(format: "Y-m-d")
    name
    comment
    upvotes
    downvotes
  }
}
```

```json
{
  "data": {
    "comments": [
      {
        "id": "2471",
        "commentDate": "2024-03-12",
        "name": "Jane Smith",
        "comment": "Thanks for writing this up, the second example was exactly what I needed.",
        "upvotes": 4,
        "downvotes": 0
      },
      {
        "id": "2468",
        "commentDate": "2024-03-11",
        "name": "Alex Chen",
        "comment": "Does this also work for assets?",
        "upvotes": 1,
        "downvotes": 0
      }
    ]
  }
}
```

### Fetching a Comment Thread

Comments are stored in a structure, so top-level comments sit at level 1 and replies sit beneath them. Fetch the top-level comments with `level: 1` and request their `children` to build the thread. The `children` field accepts the same arguments as the `comments` query.

```graphql
query EntryComments($ownerId: [QueryArgument]) {
  comments(ownerId: $ownerId, level: 1, orderBy: "commentDate ASC") {
    id
    name
    comment
    commentDate @formatDateTime(format: "j M Y")
    children(orderBy: "commentDate ASC") {
      id
      name
      comment
      commentDate @formatDateTime(format: "j M Y")
    }
  }
}
```

Query variables:

```json
{
  "ownerId": [2460]
}
```

```json
{
  "data": {
    "comments": [
      {
        "id": "2468",
        "name": "Alex Chen",
        "comment": "Does this also work for assets?",
        "commentDate": "11 Mar 2024",
        "children": [
          {
            "id": "2469",
            "name": "Jane Smith",
            "comment": "It does, add the Comments field to the volume’s field layout.",
            "commentDate": "11 Mar 2024"
          }
        ]
      }
    ]
  }
}
```

### The `comment` Query

Returns a single comment, or `null` when nothing matches. It accepts the same arguments as the `comments` query.

```graphql
{
  comment(id: 2469) {
    id
    name
    comment
    parent {
      id
      name
    }
  }
}
```

```json
{
  "data": {
    "comment": {
      "id": "2469",
      "name": "Jane Smith",
      "comment": "It does, add the Comments field to the volume’s field layout.",
      "parent": {
        "id": "2468",
        "name": "Alex Chen"
      }
    }
  }
}
```

### Querying Custom Fields

Fields added to the comment form’s layout (**Comments** → **Settings** → **Comments Form** → **Form Layout**) are available on the `Comment` type. Request them by handle inside a `... on Comment` fragment. With a Plain Text field called `summary`, the query looks like this.

```graphql
{
  comments(ownerId: 2460, limit: 2) {
    name
    comment
    ... on Comment {
      summary
    }
  }
}
```

```json
{
  "data": {
    "comments": [
      {
        "name": "Jane Smith",
        "comment": "Thanks for writing this up, the second example was exactly what I needed.",
        "summary": "Helpful article"
      },
      {
        "name": "Alex Chen",
        "comment": "Does this also work for assets?",
        "summary": null
      }
    ]
  }
}
```
