// The entry's Comments panel: the Open, Closed or Off setting, and deciding waiting comments in place
// without leaving the entry.
(() => {
    // The note under the state select explains the option chosen, including what following the source gives.
    $(document).on('change.commentsField', '[data-comments-field-value]', function() {
        const description = $(this).find('option:selected').data('description') ?? '';

        $(this).closest('[data-comments-field-state]').find('[data-comments-field-description]').text(description);
    });

    const commentsFieldWaitingLabel = (pending, reported) => {
        const parts = [];

        if (pending) {
            parts.push(pending === 1
                ? Craft.t('comments', '1 waiting for review')
                : Craft.t('comments', '{count} waiting for review', { count: pending }));
        }

        if (reported) {
            parts.push(reported === 1
                ? Craft.t('comments', '1 reported')
                : Craft.t('comments', '{count} reported', { count: reported }));
        }

        return parts.join(' · ');
    };

    $(document).on('click.commentsField', '[data-comments-field-decide]', function() {
        const $button = $(this);
        const $card = $button.closest('[data-comments-field-card]');

        $card.find('button').prop('disabled', true);
        $button.addClass('loading');

        Craft.sendActionRequest('POST', 'comments/moderation/decide', {
            data: {
                commentId: $button.data('comment-id'),
                decision: $button.data('comments-field-decide'),
            },
        })
            .then(({ data }) => {
                Craft.cp.displaySuccess(data.message);
                $card.slideUp('fast', () => {
                    const $section = $card.closest('[data-comments-field-waiting]');
                    const key = $card.data('kind') === 'reported' ? 'reported' : 'pending';

                    // The header counts every waiting comment, not just the five shown, so step it down.
                    $section.data(key, Math.max(0, (Number($section.data(key)) || 0) - 1));
                    $card.remove();

                    const pending = Number($section.data('pending')) || 0;
                    const reported = Number($section.data('reported')) || 0;

                    $section.find('[data-comments-field-waiting-label]').text(commentsFieldWaitingLabel(pending, reported));

                    if (!pending && !reported && !$section.find('[data-comments-field-card]').length) {
                        $section.closest('[data-comments-field]').find('[data-comments-field-clear]').prop('hidden', false);
                        $section.remove();
                    }
                });
            })
            .catch(({ response }) => {
                Craft.cp.displayError(response?.data?.message);
                $card.find('button').prop('disabled', false);
                $button.removeClass('loading');
            });
    });
})();
