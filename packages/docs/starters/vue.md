# Vue Starter

The `vue/` folder is the [React starter](/starters/react) again, in Vite and Vue, with one page drawing the thread with `<CommentsThread>` and one drawing its own interface with the composables. Locally it runs at `http://localhost:5274`, and its hosted copy is at [comments-starters.verbb.io/vue/](https://comments-starters.verbb.io/vue/).

## How It Reaches Craft

The dev server forwards `/actions/*` to the Craft site, so the browser sees one origin and Craft's session cookie identifies the person, as it does when the built app is served from the Craft site's own domain. `src/main.ts` installs `CommentsPlugin` with the endpoint, a `cookieSession()` from `src/craft.ts`, and the automatic theme, so every thread and composable shares them.

## The Thread Page

`src/pages/ThreadPage.vue` draws the thread for the post you choose, with a `key` of its URI. Its overrides are set once in `<script setup>`, so the thread gets the same ones on every render:

```vue
<script setup lang="ts">
import { CommentsThread, type CommentsFieldOverrides, type CommentsPartOverrides } from '@verbb/comments-vue';
import RatingDisplay from '../components/RatingDisplay.vue';
import RatingInput from '../components/RatingInput.vue';
import VerifiedBadge from '../components/VerifiedBadge.vue';

const parts: CommentsPartOverrides = { badge: VerifiedBadge };
const fields: CommentsFieldOverrides = { rating: { input: RatingInput, display: RatingDisplay } };
</script>
```

`src/components/VerifiedBadge.vue` declares only the `badge` prop, so the rest of the part's context stays off its element, and `RatingInput.vue` and `RatingDisplay.vue` draw the rating as stars. The page logs the thread's component events, such as `@comment-created` and `@vote-changed`, in a sidebar. [Part Overrides with Components](/vue/part-overrides) walks through them.

## Your Own Interface

`src/pages/CustomPage.vue`, at `/custom`, draws the same thread with the app's own components, `DiscussionComment.vue` and `FormField.vue`, on `useCommentsThread()` and `useCommentForm()`. [Composables for Your Own UI](/vue/composables) explains the composables it uses.
