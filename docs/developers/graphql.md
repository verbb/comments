# GraphQL

Comments supports accessing comments via GraphQL. Be sure to read about [Craft's GraphQL support](https://docs.craftcms.com/v3/graphql.html).

## Example query and response

### Query payload

```
{
    comments (ownerId: 2460, limit: 2, orderBy: "commentDate DESC") {
        commentDate @formatDateTime (format: "Y-m-d")
        name
        email
        comment
    }
}
```

### The response

```
{
    "data": {
        "comments": [
            {
                "commentDate": "2019-01-23",
                "name": "Test User",
                "email": "some-user@gmail.com",
                "comment": "Hey there, this is a great article, love your work!"
            },
            {
                "commentDate": "2019-01-23",
                "name": "John Smith",
                "email": "john-user@gmail.com",
                "comment": "Thanks, appreciate your comments! 😀"
            },
        ]
    }
}
```

### The `comments` query
This query is used to query for comments.

#### The `id` argument
Narrows the query results based on the elements’ IDs.

#### The `uid` argument
Narrows the query results based on the elements’ UIDs.

#### The `status` argument
Narrows the query results based on the elements’ statuses.

#### The `archived` argument
Narrows the query results to only elements that have been archived.

#### The `trashed` argument
Narrows the query results to only elements that have been soft-deleted.

#### The `site` argument
Determines which site(s) the elements should be queried in. Defaults to the primary site.

#### The `siteId` argument
Determines which site(s) the elements should be queried in. Defaults to the primary site.

#### The `unique` argument
Determines whether only elements with unique IDs should be returned by the query.

#### The `enabledForSite` argument
Narrows the query results based on whether the elements are enabled in the site they’re being queried in, per the `site` argument.

#### The `search` argument
Narrows the query results to only elements that match a search query.

#### The `relatedTo` argument
Narrows the query results to elements that relate to *any* of the provided element IDs. This argument is ignored, if `relatedToAll` is also used.

#### The `relatedToAll` argument
Narrows the query results to elements that relate to *all* of the provided element IDs. Using this argument will cause `relatedTo` argument to be ignored.

#### The `fixedOrder` argument
Causes the query results to be returned in the order specified by the `id` argument.

#### The `inReverse` argument
Causes the query results to be returned in reverse order.

#### The `dateCreated` argument
Narrows the query results based on the elements’ creation dates.

#### The `dateUpdated` argument
Narrows the query results based on the elements’ last-updated dates.

#### The `offset` argument
Sets the offset for paginated results.

#### The `limit` argument
Sets the limit for paginated results.

#### The `orderBy` argument
Sets the field the returned elements should be ordered by

#### The `withStructure` argument
Explicitly determines whether the query should join in the structure data.

#### The `structureId` argument
Determines which structure data should be joined into the query.

#### The `level` argument
Narrows the query results based on the elements’ level within the structure.

#### The `hasDescendants` argument
Narrows the query results based on whether the elements have any descendants.

#### The `ancestorOf` argument
Narrows the query results to only elements that are ancestors of another element.

#### The `ancestorDist` argument
Narrows the query results to only elements that are up to a certain distance away from the element specified by `ancestorOf`.

#### The `descendantOf` argument
Narrows the query results to only elements that are descendants of another element.

#### The `descendantDist` argument
Narrows the query results to only elements that are up to a certain distance away from the element specified by `descendantOf`.

#### The `leaves` argument
Narrows the query results based on whether the elements are “leaves” (element with no descendants).

#### The `editable` argument
Whether to only return comments that the user has permission to edit.

#### The `ownerId` argument
Narrows the query results based on the owner element the comment was made on, per the owners’ IDs.

#### The `commentDate` argument
Narrows the query results based on the comments’ commented date.

#### The `name` argument
Narrows the query results based on the comments’ full name.

#### The `email` argument
Narrows the query results based on the comments’ email.

#### The `comment` argument
Narrows the query results based on the comments’ actual comment text.
