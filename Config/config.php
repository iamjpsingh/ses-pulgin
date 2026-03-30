<?php

declare(strict_types=1);

return [
    'name'        => 'Amazon SES',
    'description' => 'Enables Amazon SES as an email transport with bounce, complaint, and delivery tracking via SNS webhooks.',
    'version'     => '1.0.0',
    'author'      => 'Xtrusio',
    'services'    => [
        'other' => [
            'xtrusio.transport.amazon_ses' => [
                'class'     => \XtrusioPlugin\XtrusioAmazonSesBundle\Mailer\Transport\AmazonSesTransport::class,
                'arguments' => [
                    '%xtrusio.amazon_ses_region%',
                    '%xtrusio.amazon_ses_access_key%',
                    '%xtrusio.amazon_ses_secret_key%',
                    '%xtrusio.amazon_ses_configuration_set%',
                ],
                'tag' => 'xtrusio.email_transport',
            ],
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
    'parameters' => [
        'xtrusio.amazon_ses_region'             => 'us-east-1',
        'xtrusio.amazon_ses_access_key'         => '',
        'xtrusio.amazon_ses_secret_key'         => '',
        'xtrusio.amazon_ses_configuration_set'  => '',
    ],
];
