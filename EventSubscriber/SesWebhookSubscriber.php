<?php

declare(strict_types=1);

namespace XtrusioPlugin\XtrusioAmazonSesBundle\EventSubscriber;

use Xtrusio\EmailBundle\EmailEvents;
use Xtrusio\EmailBundle\Event\TransportWebhookEvent;
use Xtrusio\EmailBundle\Model\TransportCallback;
use Xtrusio\LeadBundle\Entity\DoNotContact as DNC;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SesWebhookSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TransportCallback $transportCallback,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::ON_TRANSPORT_WEBHOOK => ['onWebhookRequest', 0],
        ];
    }

    public function onWebhookRequest(TransportWebhookEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle requests with SNS headers or JSON content that looks like SES/SNS
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return;
        }

        // Detect SNS message type
        $snsType = $request->headers->get('x-amz-sns-message-type', '');

        // Also check if it's a direct SES event (without SNS wrapper)
        if (empty($snsType) && !isset($payload['Type']) && !isset($payload['eventType']) && !isset($payload['notificationType'])) {
            return;
        }

        // Handle SNS subscription confirmation
        if ('SubscriptionConfirmation' === $snsType || 'SubscriptionConfirmation' === ($payload['Type'] ?? '')) {
            $this->handleSubscriptionConfirmation($payload, $event);

            return;
        }

        // Handle SNS notification
        if ('Notification' === $snsType || 'Notification' === ($payload['Type'] ?? '')) {
            $message = json_decode($payload['Message'] ?? '{}', true);
            if (!is_array($message)) {
                $this->logger->warning('SES webhook: Unable to decode SNS Message field.');
                $event->setResponse(new JsonResponse(['status' => 'error', 'message' => 'Invalid Message field'], 400));

                return;
            }
            $payload = $message;
        }

        // Now process the SES event
        $this->processSesEvent($payload, $event);
    }

    private function handleSubscriptionConfirmation(array $payload, TransportWebhookEvent $event): void
    {
        $subscribeUrl = $payload['SubscribeURL'] ?? null;

        if (!$subscribeUrl) {
            $this->logger->warning('SES webhook: SubscriptionConfirmation without SubscribeURL.');
            $event->setResponse(new JsonResponse(['status' => 'error', 'message' => 'Missing SubscribeURL'], 400));

            return;
        }

        // Confirm the subscription by calling the SubscribeURL
        try {
            $context = stream_context_create([
                'http' => ['timeout' => 10],
                'ssl'  => ['verify_peer' => true],
            ]);
            $result = @file_get_contents($subscribeUrl, false, $context);

            if (false === $result) {
                $this->logger->error('SES webhook: Failed to confirm SNS subscription.', ['url' => $subscribeUrl]);
                $event->setResponse(new JsonResponse(['status' => 'error', 'message' => 'Subscription confirmation failed'], 500));

                return;
            }

            $this->logger->info('SES webhook: SNS subscription confirmed.', ['topic' => $payload['TopicArn'] ?? '']);
            $event->setResponse(new JsonResponse(['status' => 'ok', 'message' => 'Subscription confirmed']));
        } catch (\Throwable $e) {
            $this->logger->error('SES webhook: Exception confirming SNS subscription.', ['error' => $e->getMessage()]);
            $event->setResponse(new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500));
        }
    }

    private function processSesEvent(array $payload, TransportWebhookEvent $event): void
    {
        // SES sends eventType (v2) or notificationType (v1)
        $eventType = $payload['eventType'] ?? $payload['notificationType'] ?? null;

        if (null === $eventType) {
            return;
        }

        match ($eventType) {
            'Bounce'    => $this->processBounce($payload),
            'Complaint' => $this->processComplaint($payload),
            'Delivery'  => $this->processDelivery($payload),
            'Reject'    => $this->processReject($payload),
            default     => $this->logger->debug('SES webhook: Unhandled event type.', ['type' => $eventType]),
        };

        $event->setResponse(new JsonResponse(['status' => 'ok']));
    }

    private function processBounce(array $payload): void
    {
        $bounce = $payload['bounce'] ?? [];
        $bounceType    = $bounce['bounceType'] ?? 'Undetermined';
        $bounceSubType = $bounce['bounceSubType'] ?? '';

        $recipients = $bounce['bouncedRecipients'] ?? [];

        foreach ($recipients as $recipient) {
            $email       = $recipient['emailAddress'] ?? '';
            $status      = $recipient['status'] ?? '';
            $diagnostics = $recipient['diagnosticCode'] ?? '';

            if (empty($email)) {
                continue;
            }

            $comment = sprintf(
                'SES Bounce: %s/%s (status: %s). %s',
                $bounceType,
                $bounceSubType,
                $status,
                $diagnostics
            );

            $this->logger->info('SES bounce processed.', ['email' => $email, 'type' => $bounceType]);

            // Try to find by hashId first (if available in mail tags)
            $hashId = $this->extractHashId($payload);
            if ($hashId) {
                $this->transportCallback->addFailureByHashId($hashId, $comment, DNC::BOUNCED);
            } else {
                $this->transportCallback->addFailureByAddress($email, $comment, DNC::BOUNCED);
            }
        }
    }

    private function processComplaint(array $payload): void
    {
        $complaint     = $payload['complaint'] ?? [];
        $complaintType = $complaint['complaintFeedbackType'] ?? 'unknown';
        $recipients    = $complaint['complainedRecipients'] ?? [];

        foreach ($recipients as $recipient) {
            $email = $recipient['emailAddress'] ?? '';

            if (empty($email)) {
                continue;
            }

            $comment = sprintf('SES Complaint: %s', $complaintType);

            $this->logger->info('SES complaint processed.', ['email' => $email, 'type' => $complaintType]);

            $hashId = $this->extractHashId($payload);
            if ($hashId) {
                $this->transportCallback->addFailureByHashId($hashId, $comment, DNC::UNSUBSCRIBED);
            } else {
                $this->transportCallback->addFailureByAddress($email, $comment, DNC::UNSUBSCRIBED);
            }
        }
    }

    private function processDelivery(array $payload): void
    {
        $delivery   = $payload['delivery'] ?? [];
        $recipients = $delivery['recipients'] ?? [];

        foreach ($recipients as $email) {
            $this->logger->debug('SES delivery confirmed.', ['email' => $email]);
        }
    }

    private function processReject(array $payload): void
    {
        $reject = $payload['reject'] ?? [];
        $reason = $reject['reason'] ?? 'Unknown';

        $this->logger->warning('SES rejection.', ['reason' => $reason]);

        // Try to get recipients from the mail object
        $mail       = $payload['mail'] ?? [];
        $recipients = $mail['destination'] ?? [];

        foreach ($recipients as $email) {
            $comment = sprintf('SES Rejected: %s', $reason);
            $this->transportCallback->addFailureByAddress($email, $comment, DNC::BOUNCED);
        }
    }

    private function extractHashId(array $payload): ?string
    {
        // Check mail.tags for hashId (set during sending)
        $mail = $payload['mail'] ?? [];
        $tags = $mail['tags'] ?? [];

        if (isset($tags['hashId']) && is_array($tags['hashId'])) {
            return $tags['hashId'][0] ?? null;
        }

        // Check headers for X-Xtrusio-Hash
        $headers = $mail['headers'] ?? [];
        foreach ($headers as $header) {
            if ('X-Xtrusio-Hash' === ($header['name'] ?? '')) {
                return $header['value'] ?? null;
            }
        }

        return null;
    }
}
