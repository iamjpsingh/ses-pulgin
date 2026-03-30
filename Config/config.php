<?php

declare(strict_types=1);

return [
    'name'        => 'Amazon SES',
    'description' => 'Adds bounce, complaint, and delivery tracking for Amazon SES via SNS webhooks. Requires symfony/amazon-mailer for sending.',
    'version'     => '1.0.0',
    'author'      => 'Xtrusio',
    'services'    => [
        'other' => [
            'xtrusio.transport.amazon_ses.webhook_subscriber' => [
                'class'     => \XtrusioPlugin\XtrusioAmazonSesBundle\EventSubscriber\SesWebhookSubscriber::class,
                'arguments' => [
                    'xtrusio.email.model.transport_callback',
                    'monolog.logger.xtrusio',
                ],
                'tag' => 'kernel.event_subscriber',
            ],
        ],
    ],
];
