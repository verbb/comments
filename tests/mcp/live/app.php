<?php
/** Disposable test application only: no outbound mail and an observer for notification jobs. */
return [
    'components' => [
        'mailer' => function() {
            $config = \craft\helpers\App::mailerConfig();
            $config['useFileTransport'] = true;
            return Craft::createObject($config);
        },
    ],
    'on beforeRequest' => function() {
        \yii\base\Event::on(\yii\queue\Queue::class, \yii\queue\Queue::EVENT_BEFORE_PUSH, function($event) {
            if ($event->job instanceof \verbb\comments\queue\jobs\SendNotification) {
                file_put_contents(Craft::getAlias('@runtime/mcp-notifications.jsonl'), json_encode([
                    'type' => $event->job->type,
                    'commentId' => $event->job->commentId,
                    'actorId' => \verbb\comments\Comments::$plugin->getService()->getUser()?->id,
                ]) . "\n", FILE_APPEND);
            }
        });
    },
];
