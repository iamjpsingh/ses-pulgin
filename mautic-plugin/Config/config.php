<?php

declare(strict_types=1);

return [
    'name'        => 'Amazon SES',
    'description' => 'Adds bounce, complaint, and delivery tracking for Amazon SES via SNS webhooks. Requires symfony/amazon-mailer for sending.',
    'version'     => '1.0.0',
    'author'      => 'JP Singh',
    'services'    => [
        'other' => [
            'mautic.transport.amazon_ses.webhook_subscriber' => [
                'class'     => \MauticPlugin\MauticAmazonSesBundle\EventSubscriber\SesWebhookSubscriber::class,
                'arguments' => [
                    'mautic.email.model.transport_callback',
                    'monolog.logger.mautic',
                ],
                'tag' => 'kernel.event_subscriber',
            ],
        ],
    ],
];
