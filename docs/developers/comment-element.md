# Comment Element

You can access comments from your templates via `craft.comments.all` which returns an [ElementCriteriaModel](http://buildwithcraft.com/docs/templating/elementcriteriamodel) object.

```twig
{% set comments = craft.comments.all({
    userId: currentUser.id,
    limit: 10,
    status: 'pending'
}) %}

{% for comment in comments %}
    {{ comment.comment }}
{% endfor %}
```

### Parameters

`craft.comments.all` supports the following parameters:

Parameter | Description
--- | ---
`elementId` |
`elementType` |
`userId` |
`status` |
`name` |
`email` |
`url` |
`ipAddress` |
`userAgent` |
`comment` |
`order` |
