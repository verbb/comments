if (typeof Craft.Comments === 'undefined') {
    Craft.Comments = {};
}

Craft.Comments.initCommentEditor = function(root) {
    if (!root || root.dataset.commentsEditorInitialized === 'true') {
        return;
    }

    root.dataset.commentsEditorInitialized = 'true';

    const $root = $(root);
    const $statusSelect = $root.find('[data-comments-status-menu]').first();
    const $statusInput = $root.find('[data-comments-status-input]').first();

    if ($statusSelect.length && $statusInput.length) {
        const onStatusSelect = function(data) {
            const $option = $(data);

            $statusInput.val($option.data('val'));
            $statusSelect
                .empty()
                .append($('<span>').addClass('status').addClass($option.data('color')))
                .append($('<span>').addClass('menu-btn-text').text($option.data('label')));
        };
        const statusMenu = $statusSelect.data('menubtn');

        if (statusMenu) {
            statusMenu.settings.onOptionSelect = onStatusSelect;
        } else if ($statusSelect.hasClass('menubtn')) {
            new Garnish.MenuBtn($statusSelect, {
                onOptionSelect: onStatusSelect,
            });
        }
    }

    // Notes and flags save straight away, without disturbing whatever is being edited in the form.
    $root.on('click.comments', '[data-comments-panel-action]', function(ev) {
        ev.preventDefault();

        const $button = $(this);
        const $panel = $button.closest('[data-comments-panel]');
        const $note = $panel.find('[data-comments-note-input]');
        const data = {
            commentId: $panel.data('comment-id'),
            reportId: $button.data('report-id'),
        };

        if ($note.length) {
            data.note = $note.val();

            if (!data.note.trim()) {
                $note.trigger('focus');

                return;
            }
        }

        $button.addClass('loading');

        Craft.sendActionRequest('POST', $button.data('comments-panel-action'), { data })
            .then(({ data: response }) => {
                const tabId = $panel.closest('[id]').attr('id');

                $panel.replaceWith(response.html);
                Craft.cp.displaySuccess(response.message);
                $root.find(`[href="#${tabId}"] .tab-label`).text(response.label);
            })
            .catch(({ response }) => {
                Craft.cp.displayError(response?.data?.message);
            })
            .finally(() => {
                $button.removeClass('loading');
            });
    });

    // Cmd/Ctrl+Enter in the note box adds the note; Enter alone starts a new line.
    $root.on('keydown.comments', '[data-comments-note-input]', function(ev) {
        if (ev.key === 'Enter' && (ev.metaKey || ev.ctrlKey)) {
            ev.preventDefault();
            $(this).closest('[data-comments-panel]').find('[data-comments-panel-action]').trigger('click');
        }
    });

    // Remove asks why first. The reason and note go with the form, so removing saves any edits too.
    $root.on('click.comments', '[data-comments-remove-toggle]', function() {
        const $panel = $root.find('[data-comments-remove-panel]').first();
        const opening = $panel.hasClass('hidden');

        $panel.toggleClass('hidden', !opening);
        $root.find('[data-comments-remove-toggle][aria-expanded]').attr('aria-expanded', opening ? 'true' : 'false');

        if (opening) {
            $panel.find('select').first().trigger('focus');
        }
    });

    // A decision saves the form with it, once. In Craft's slideout that goes through the slideout's own
    // submit, with its namespace, and the index behind it is refreshed so a decided comment leaves its source.
    $root.on('click.comments', '[data-comments-decision]', function(ev) {
        ev.preventDefault();

        const $button = $(this);
        const screen = $button.closest('.slideout').data('cpScreen');
        const $form = screen ? screen.$container : $button.closest('form');
        const name = screen && screen.namespace ? `${screen.namespace}[decision]` : 'decision';

        if ($button.hasClass('loading')) {
            return;
        }

        $form.find('input[data-comments-decision-input]').remove();
        $('<input/>', { type: 'hidden', name, value: $button.data('comments-decision'), 'data-comments-decision-input': true }).appendTo($form);

        if (!screen) {
            $button.addClass('loading');
            // Craft's editor reads which button submitted it.
            Craft.submitForm($form, { data: { customTrigger: $button } });

            return;
        }

        const refresh = () => {
            screen.off('submit', refresh);

            if (!Craft.elementIndex) {
                return;
            }

            Craft.elementIndex.updateElements();

            // Craft only refreshes source badges after bulk actions, so the counts are fetched here.
            Craft.sendActionRequest('GET', 'comments/moderation/queue-counts').then(({ data }) => {
                ['needs-review', 'reported'].forEach((key) => {
                    const $link = $(`#sidebar a[data-key="${key}"]`);
                    let $badge = $link.find('.badge');

                    if (!data[key]) {
                        $badge.remove();

                        return;
                    }

                    if (!$badge.length) {
                        $badge = $('<span class="badge" aria-hidden="true"/>').appendTo($link);
                    }

                    $badge.text(data[key]);
                });

                // The navigation's own count for Comments.
                $('#global-sidebar a').filter(function() {
                    return /\/comments\/?$/.test(this.getAttribute('href') || '');
                }).find('.badge').each(function() {
                    data.total ? $(this).text(data.total) : $(this).remove();
                });
            }).catch(() => {});
        };

        // The slideout marks its own Save as loading while it submits, so a second click waits for that.
        if (screen.$saveBtn && screen.$saveBtn.hasClass('loading')) {
            $form.find('input[data-comments-decision-input]').remove();

            return;
        }

        if (screen.$saveBtn && window.MutationObserver) {
            $button.addClass('loading');

            const observer = new MutationObserver(() => {
                if (!screen.$saveBtn.hasClass('loading')) {
                    $button.removeClass('loading');
                    observer.disconnect();
                }
            });

            observer.observe(screen.$saveBtn[0], { attributes: true, attributeFilter: ['class'] });
        }

        screen.on('submit', refresh);
        screen.submit();

        // The slideout has already read the form, and a later plain Save shouldn't carry the decision.
        $form.find('input[data-comments-decision-input]').remove();
    });

    // One spam comment at a time, confirmed, and never from the index.
    $root.on('click.comments', '[data-comments-delete-permanently]', function() {
        const $button = $(this);

        if (!window.confirm($button.data('confirm'))) {
            return;
        }

        $button.addClass('loading');

        Craft.sendActionRequest('POST', 'comments/moderation/delete-permanently', {
            data: { commentId: $button.data('comments-delete-permanently') },
        })
            .then(({ data: response }) => {
                Craft.cp.displaySuccess(response.message);
                window.location.href = Craft.getCpUrl('comments');
            })
            .catch(({ response }) => {
                Craft.cp.displayError(response?.data?.message);
                $button.removeClass('loading');
            });
    });

    const editorRoot = root.querySelector('[data-editor-root]');

    if (editorRoot) {
        // Editor's bundle is a module, so it runs after this. A slideout can load it later still.
        customElements.whenDefined('verbb-editor').then(function() {
            const contentInput = root.querySelector('input[name$="[editorContent]"]');
            const plainInput = root.querySelector('textarea[name$="[comment]"]');
            const options = JSON.parse(editorRoot.dataset.editorOptions);
            const editor = new window.VerbbEditor.Editor(editorRoot, {
                ...options,
                placeholder: editorRoot.dataset.editorPlaceholder,
                ariaLabel: editorRoot.dataset.editorLabel,
                content: JSON.parse(editorRoot.dataset.editorContent || '[]'),
                // Craft can serialize this form at any time, for autosaving and slideouts.
                onUpdate: function(event) {
                    contentInput.value = JSON.stringify(event.json);
                    plainInput.value = event.text;
                },
            });

            contentInput.value = JSON.stringify(editor.getJSON());
        });
    }
};

// Preview shows the comment where it lives on the site, with its parent and replies. The editor's header
// sits outside the form, so this listens on the page.
$(document).on('click.commentsPreview', '[data-comments-preview]', function(ev) {
    ev.preventDefault();

    const $button = $(this);
    const $container = $('<div class="modal comments-preview-modal"/>');
    const $header = $('<div class="header"/>').appendTo($container);

    $('<h1/>').text($button.data('comments-preview-title')).appendTo($header);
    $('<iframe/>', {
        src: $button.data('comments-preview'),
        title: $button.data('comments-preview-title'),
        class: 'comments-preview-frame',
    }).appendTo($('<div class="body"/>').appendTo($container));

    const $footer = $('<div class="footer"/>').appendTo($container);
    const $close = $('<button type="button" class="btn"/>').text(Craft.t('app', 'Close')).appendTo($('<div class="buttons right"/>').appendTo($footer));
    const modal = new Garnish.Modal($container, {
        onHide: () => modal.destroy(),
    });

    $close.on('click', () => modal.hide());
});
