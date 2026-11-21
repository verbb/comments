# Validating Before Posting

`comments:before-submit` fires when someone presses **Post**, or presses ⌘↵ or Ctrl+↵, once the script's own checks have passed and before anything is sent. It's the one place for checks of your own, such as a minimum length or a word you don't want posted, and it can show its message in the form exactly as the server's errors appear.

## Asking for a Minimum Length

To ask for at least 20 characters, add this to a script on the page. It listens on `document`, so it can go before or after the plugin's script.

```js
document.addEventListener('comments:before-submit', (event) => {
    const { form, formData, controller } = event.detail;
    const text = String(formData.get('content') ?? '').trim();

    if (text.length < 20) {
        event.preventDefault();

        controller.showFormErrors(form, {
            fieldErrors: {
                content: ['Please write at least 20 characters.'],
            },
        });
    }
});
```

A short comment isn't sent. “Please write at least 20 characters.” appears under the comment box, styled and announced to screen readers like the server's own errors, and the cursor goes back to the box with the comment still in it. The error clears when the comment is next posted. The [Validate and Track Comments](https://verbb.io/craft-plugins/comments/docs/v4/user-guides/forms-data/validate-and-track-comments) user guide builds a check like this, along with analytics, step by step.

## What the Event Gives You

<!-- #region validate-detail -->
| Key | What it is |
| --- | --- |
| `form` | The form being posted. |
| `mode` | `create`, `reply` or `edit`. |
| `commentUid` | The comment being replied to or edited, or `null` for a new comment. |
| `formData` | The values about to be posted. |

In `formData`, the comment is `content`, a guest's details are `guest[name]` and `guest[email]`, and a custom field is `fields[handle]`, such as `fields[rating]`. `formData` is for reading. To send something different, change the form's own fields in your listener, because the post is built from the form once your listener has finished.
<!-- #endregion validate-detail -->

## Showing Errors

<!-- #region validate-errors -->
`controller.showFormErrors(form, errors)` shows errors on a form the same way the script shows the server's, and moves the cursor to the first. `fieldErrors` holds messages keyed by field, using the keys the data API uses:

| Key | Shown beside |
| --- | --- |
| `content` | The comment box |
| `guest.name` and `guest.email` | A guest's details |
| `fields.handle`, such as `fields.rating` | A custom field |

Each field shows its first message. `message` is shown at the top of the form when none of the field errors has a field to go beside. `controller.clearFormErrors(form)` removes the errors shown on a form, whether they came from the server or from `showFormErrors()`.
<!-- #endregion validate-errors -->

```js
// A custom field check, for a "rating" field that must be given on top-level comments.
document.addEventListener('comments:before-submit', (event) => {
    const { form, formData, mode, controller } = event.detail;

    if (mode === 'create' && !formData.get('fields[rating]')) {
        event.preventDefault();
        controller.showFormErrors(form, { fieldErrors: { 'fields.rating': ['Choose a rating.'] } });
    }
});
```

## The Server Has the Final Say

<!-- #region validate-server -->
Checks in the browser are a convenience for visitors, and anyone can skip them by posting to the data API directly. Comments validates everything again when it arrives, including the length limit, required fields and spam protection. A rule that matters, rather than one that saves the visitor a round trip, belongs on the server too, in a listener for the plugin's [`beforeSave` event](https://verbb.io/craft-plugins/comments/docs/v4/developers/events), so it also holds for comments posted through GraphQL or your own forms. A refused post from the server shows its errors in the same places, through the same `fieldErrors` keys.
<!-- #endregion validate-server -->

The React and Vue components take this event as `onBeforeSubmit` and `@before-submit`, and `<verbb-comments>` dispatches it from the element, with the same `detail`.
