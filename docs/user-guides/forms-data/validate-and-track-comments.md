# Validate and Track Comments

Once comments are running on your site, two requests usually follow. The editors want a few house rules checked before a comment is sent, such as a minimum length so nobody posts "great!" on a long article. And whoever looks after the site's analytics wants to know how many readers comment, vote and follow discussions, alongside everything else they measure.

Comments' thread tells your own JavaScript what's happening in it through **browser events**, and that's how you do both. In this guide you'll add two house rules to the comment form of a local news site called the Coastal Courier, showing your own error messages in the form the same way Comments shows its own, then send comments, votes, follows and reports to Google Tag Manager. The last section does the same in a React or Vue app.

By the end, a reader who tries to post fewer than 20 characters, or a whole comment in capitals, sees a polite message under the comment box and can fix it before anything is sent. Each comment posted, vote cast, discussion followed and comment reported appears in your analytics as an event, without any personal information.

## What You'll Need

You'll need Comments installed and its thread on your article template, output with `craft.comments.render(entry)`, as [Adding Comments to an Entry](docs:get-started/adding-comments-to-an-entry) describes. You'll also need a JavaScript file that your layout loads on article pages. This guide uses `web/js/comments-rules.js`, linked from the layout like this:

```twig
<script src="/js/comments-rules.js"></script>
```

For the tracking section, you'll need Google Tag Manager on the site, which provides the `window.dataLayer` array the examples push to. Any other analytics tool works the same way, with its own call in place of `dataLayer.push()`.

## How the Thread Tells Your Code What's Happening

As things happen in a thread, Comments dispatches events on the thread's root element, the `<section>` that `render()` outputs. They're ordinary DOM events with names starting `comments:`, such as `comments:comment-created` once a comment has been posted, and they bubble up the page, so a listener on `document` hears them from every thread on the page. That's handy, because your script doesn't need to find the thread or wait for it to load. It can add its listeners straight away.

Each event carries information in `event.detail`. Every event's `detail` includes `controller`, the thread's controller, which has methods for acting on the thread, and `instanceId`, the thread root's `id`. Each event adds its own details too, such as `commentUid` for the comment it's about.

A few events happen just before something, rather than after, and can stop it. These are **cancellable** events, and calling `event.preventDefault()` in a listener stops what was about to happen. `comments:before-submit` is the one you'll use here. It's dispatched when someone presses **Post**, **Reply** or **Save**, after Comments' own checks have passed and before anything is sent to your site.

## Requiring a Minimum Length

Put this in `web/js/comments-rules.js`:

```js
document.addEventListener('comments:before-submit', (event) => {
    const { form, formData, controller } = event.detail;
    const text = String(formData.get('content') ?? '').trim();

    if (text.length < 20) {
        event.preventDefault();

        controller.showFormErrors(form, {
            fieldErrors: {
                content: ['Please write at least 20 characters, so other readers know what you mean.'],
            },
        });
    }
});
```

Here's what it does, line by line.

`event.detail` holds the `form` being posted, `formData` with the values about to be sent, and the thread's `controller`. The comment's text is the `content` value in `formData`. That's true whether the reader typed into a plain comment box or into Editor, the rich text editor Comments uses when rich authoring is on, because Comments copies Editor's text into the form before this event. The text is converted to a string and trimmed, so a comment of spaces doesn't count.

When the text is under 20 characters, `event.preventDefault()` stops the comment being sent, and the comment stays in the form for the reader to finish.

`controller.showFormErrors()` shows your message in the form, the same way Comments shows an error from your site. `fieldErrors` holds messages keyed by the field they belong to, and `content` is the comment box, so the message appears under it, in Comments' error style. Screen readers announce it, and the cursor moves back into the comment box. The message goes away the next time the reader posts.

Save the file and reload an article. Write "Nice one" in the comment box and press **Post**. The comment isn't posted, and "Please write at least 20 characters, so other readers know what you mean." appears under the box. Write a longer comment, press **Post** again, and it goes through as usual.

If nothing happens, check that `comments-rules.js` is loading, in your browser's developer tools, and look in the console for errors. The listener is on `document`, so it works wherever the script is on the page, before or after Comments' own script.

## Adding a Second Rule

The Courier's editors also want to stop comments written entirely in capitals. Rather than adding a second listener, collect the problems in one, so the reader sees what's wrong in one go. Replace the file's contents with:

```js
document.addEventListener('comments:before-submit', (event) => {
    const { form, formData, controller } = event.detail;
    const text = String(formData.get('content') ?? '').trim();
    const letters = text.replace(/[^a-z]/gi, '');
    const errors = [];

    if (text.length < 20) {
        errors.push('Please write at least 20 characters, so other readers know what you mean.');
    }

    if (letters.length >= 10 && letters === letters.toUpperCase()) {
        errors.push('Please don’t write your whole comment in capitals.');
    }

    if (errors.length) {
        event.preventDefault();
        controller.showFormErrors(form, { fieldErrors: { content: errors } });
    }
});
```

`letters` is the comment with everything but letters removed. When it has at least ten letters and they're all capitals, the rule adds its message. The check on ten letters lets through short shouts that are fine, such as "NASA" in an ordinary comment.

Each field shows the first of its messages, so a comment that breaks both rules shows the length message first, and the capitals message once that's fixed.

Reload, and try "THIS COUNCIL DECISION IS OUTRAGEOUS AND WRONG". It's long enough, but the capitals message appears instead.

### Errors for Other Fields

The same keys work for the other parts of the form. A guest's name and email are `guest.name` and `guest.email`, and a custom field is `fields.` followed by its handle, such as `fields.suburb`, so a message appears beside that field. In `formData`, they're named `guest[name]`, `guest[email]` and `fields[suburb]`. For a problem that doesn't belong to one field, pass `message` instead of `fieldErrors`, and it appears at the top of the form.

To apply a rule only to some forms, check `event.detail.mode`, which is `create` for a new comment, `reply` for a reply and `edit` for an edit. A rule for replies only might start with `if (event.detail.mode !== 'reply') return;`.

If a rule is really "this field must be filled in", make the field required in its field layout instead, as [Ask Commenters for More with Custom Fields](docs:user-guides/forms-data/ask-commenters-for-more-with-custom-fields) shows, and Comments checks it for you on the form and on the server.

### Rules That Must Hold Go in PHP Too

Checks in the browser help readers, by catching a problem before they wait for an answer. They can't enforce anything, though. Someone who wants to get around them can switch off JavaScript, change your script, or send a comment straight to your site without using the form at all.

So for a rule that really matters, such as banning links in comments, check it on the server too, in a [`beforeSave`](docs:developers/events#the-beforeSave-event) event listener in a module, where nobody can skip it. Add an error to the comment before refusing it, and the reader sees your message beside the comment box. The browser rule then gives readers a quick, friendly message, and the server rule makes sure.

## Tracking Comments, Votes, Follows and Reports

Tracking uses events that are dispatched after something has happened. Add these listeners to the same file:

```js
function track(name, values = {}) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event: name, ...values });
}

document.addEventListener('comments:comment-created', (event) => {
    track('comment_posted', {
        comment_state: event.detail.moderationState,
        comment_thread: event.detail.instanceId,
    });
});

document.addEventListener('comments:vote-changed', (event) => {
    track('comment_voted', {
        comment_vote: event.detail.vote ?? 'removed',
    });
});

document.addEventListener('comments:follow-changed', (event) => {
    track(event.detail.following ? 'discussion_followed' : 'discussion_unfollowed');
});

document.addEventListener('comments:report-changed', (event) => {
    if (event.detail.reported) {
        track('comment_reported');
    }
});
```

`track()` pushes an event to Google Tag Manager's data layer, creating it first if the page doesn't have one yet. For Google Analytics without Tag Manager, call `gtag('event', name, values)` inside `track()` instead.

`comments:comment-created` is dispatched once a new comment or reply has been posted. Its `moderationState` is `approved` when the comment is live, or `pending` when it's waiting for a moderator, which is useful for seeing how many comments wait for approval. `comments:vote-changed` has `vote`, which is `upvote` or `downvote`, or `null` when a reader takes their vote back. A like counts as an `upvote`. `comments:follow-changed` has `following`, which is `true` when a reader starts following the discussion, and `comments:report-changed` has `reported`, which is `false` when a reader withdraws a report, so the listener only tracks new ones.

Notice what isn't sent. The events don't include the comment's text, the reader's name or email, and the tracking doesn't add them. Analytics tools aren't the place for what readers write or who they are, and in many countries sending that without consent breaks privacy law. The thread's `instanceId`, such as `comments-109-1`, says which article the comment was on, using the entry's ID, without saying anything about the reader.

In Google Tag Manager, create a **Custom Event** trigger for each event name, such as `comment_posted`, and a tag that sends it on to your analytics, with data layer variables for `comment_state` or `comment_vote` if you want them as parameters.

## Testing It

1. Open an article, write a short comment and press **Post**. Your message appears and nothing is sent. In your browser's developer tools, the **Network** panel shows no request to `comments/api/create`.
2. Write a long comment in capitals and check the second message. Then write a proper comment and post it.
3. In the developer tools' console, type `dataLayer` and press **Enter**. The last entries include `comment_posted` with `comment_state` set to `approved` or `pending`, depending on your moderation settings.
4. Vote on a comment, take the vote back, follow the discussion, and report a comment, checking `dataLayer` after each.
5. Use Tag Manager's **Preview** mode to check each trigger fires and its tag sends the event.

Post a reply as well, to check the rules apply to the reply form, and edit a comment of your own, which sends `comments:before-submit` with the `mode` set to `edit`.

## Other Events

The events here are the ones most sites need, and there are others for other jobs. `comments:request-error` is dispatched when a request to your site fails, with a `message` and often a `code`, which is useful for logging problems readers run into. `comments:before-action` is dispatched when someone presses a control in the thread, such as **Reply** or **Delete**, and can stop it. The package docs' [Browser Events](https://docs.verbb.io/comments/browser/browser-events) lists every event and its `detail`, and [Validating Before Posting](https://docs.verbb.io/comments/browser/validating-before-posting) goes further into validation.

## In a React or Vue App

When your site draws the thread with `<CommentsThread>` from `@verbb/comments-react` or `@verbb/comments-vue`, every browser event is also a prop or component event on the thread, so you don't need listeners on `document`. In React, the prop is the event's name in camel case after `on`, such as `onBeforeSubmit` and `onCommentCreated`, and each handler gets the same event:

```jsx
import { CommentsThread } from '@verbb/comments-react';

function checkRules(event) {
    const text = String(event.detail.formData.get('content') ?? '').trim();

    if (text.length < 20) {
        event.preventDefault();
        event.detail.controller?.showFormErrors(event.detail.form, {
            fieldErrors: { content: ['Please write at least 20 characters, so other readers know what you mean.'] },
        });
    }
}

function trackComment(event) {
    window.dataLayer?.push({ event: 'comment_posted', comment_state: event.detail.moderationState });
}

export function Discussion({ uri }) {
    return <CommentsThread key={uri} ownerUri={uri} onBeforeSubmit={checkRules} onCommentCreated={trackComment} />;
}
```

In Vue, they're component events with the event's name, such as `@before-submit="checkRules"` and `@comment-created="trackComment"`, and the handlers are the same functions.

To hear an event from every thread in a React app, such as tracking in one place for the whole site, `useCommentsEvent('comments:comment-created', handler)` listens without needing a prop on each thread. [React Hooks](https://docs.verbb.io/comments/react/hooks) explains it, and [Comments on a Headless Site on the Same Domain](docs:user-guides/front-end-headless/comments-on-a-headless-site-on-the-same-domain) sets up an app to try these in.
