// A moderator's reply to a comment, from its page, its slideout or an entry's Comments panel. Buttons and
// menu items carry the comment's ID and site, and the form comes from `comments/moderation/reply`.
(() => {
    if (window.Craft?.Comments?.replyInitialized) {
        return;
    }

    Craft.Comments = Craft.Comments || {};
    Craft.Comments.replyInitialized = true;

    Craft.Comments.openReply = function(commentId, siteId) {
        return new Craft.CpScreenSlideout('comments/moderation/reply', {
            params: { commentId, siteId },
        });
    };

    $(document).on('click.commentsReply', '[data-comments-reply]', function(ev) {
        ev.preventDefault();

        const $trigger = $(this);

        Craft.Comments.openReply($trigger.data('comments-reply'), $trigger.data('comments-reply-site'));
    });
})();
