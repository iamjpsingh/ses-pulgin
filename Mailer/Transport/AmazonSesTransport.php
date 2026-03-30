<?php

declare(strict_types=1);

namespace XtrusioPlugin\XtrusioAmazonSesBundle\Mailer\Transport;

use Xtrusio\EmailBundle\Mailer\Message\XtrusioMessage;
use Xtrusio\EmailBundle\Mailer\Transport\TokenTransportInterface;
use Xtrusio\EmailBundle\Mailer\Transport\TokenTransportTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AmazonSesTransport extends AbstractApiTransport implements TokenTransportInterface
{
    use TokenTransportTrait;

    private const SES_API_VERSION = '2';
    private const SES_MAX_BATCH = 50;

    public function __construct(
        private string $region,
        private string $accessKey,
        private string $secretKey,
        private string $configurationSet = '',
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($client, $dispatcher, $logger ?? new NullLogger());
        $this->setHost(sprintf('email.%s.amazonaws.com', $this->region));
    }

    public function __toString(): string
    {
        return sprintf('ses+api://%s@%s', $this->accessKey, $this->getEndpoint());
    }

    public function getMaxBatchLimit(): int
    {
        return self::SES_MAX_BATCH;
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $date    = gmdate('Ymd\THis\Z');
        $dateKey = gmdate('Ymd');

        $payload = $this->buildPayload($email, $envelope);
        $body    = json_encode($payload, JSON_THROW_ON_ERROR);

        $endpoint = $this->getEndpoint();
        $path     = '/v2/email/outbound-emails';

        $headers = $this->signRequest('POST', $path, $body, $date, $dateKey, $endpoint);

        $response = $this->client->request('POST', sprintf('https://%s%s', $endpoint, $path), [
            'headers' => $headers,
            'body'    => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $e) {
            throw new HttpTransportException('Could not reach Amazon SES.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $result = $response->toArray(false);
            throw new HttpTransportException(
                sprintf('Unable to send email via Amazon SES: %s (code %d)', $result['message'] ?? 'Unknown error', $statusCode),
                $response
            );
        }

        $result = $response->toArray(false);
        $sentMessage->setMessageId($result['MessageId'] ?? '');

        return $response;
    }

    private function buildPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'Content' => [
                'Raw' => [
                    'Data' => base64_encode($email->toString()),
                ],
            ],
            'FromEmailAddress' => $envelope->getSender()->toString(),
            'Destination'      => [
                'ToAddresses'  => array_map(fn (Address $a) => $a->toString(), $email->getTo()),
            ],
        ];

        if ($email->getCc()) {
            $payload['Destination']['CcAddresses'] = array_map(fn (Address $a) => $a->toString(), $email->getCc());
        }

        if ($email->getBcc()) {
            $payload['Destination']['BccAddresses'] = array_map(fn (Address $a) => $a->toString(), $email->getBcc());
        }

        if ($this->configurationSet) {
            $payload['ConfigurationSetName'] = $this->configurationSet;
        }

        if ($email instanceof XtrusioMessage && $email->getLeadIdHash()) {
            $payload['EmailTags'] = [
                ['Name' => 'hashId', 'Value' => $email->getLeadIdHash()],
            ];
        }

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $payload['EmailTags'][] = [
                    'Name'  => $header->getKey(),
                    'Value' => $header->getValue(),
                ];
            }
        }

        return $payload;
    }

    private function getEndpoint(): string
    {
        return sprintf('email.%s.amazonaws.com', $this->region);
    }

    /**
     * AWS Signature Version 4 signing.
     *
     * @return array<string, string>
     */
    private function signRequest(string $method, string $path, string $body, string $date, string $dateKey, string $host): array
    {
        $service       = 'ses';
        $algorithm     = 'AWS4-HMAC-SHA256';
        $credentialScope = sprintf('%s/%s/%s/aws4_request', $dateKey, $this->region, $service);
        $payloadHash   = hash('sha256', $body);

        $canonicalHeaders = sprintf("content-type:application/json\nhost:%s\nx-amz-date:%s\n", $host, $date);
        $signedHeaders    = 'content-type;host;x-amz-date';

        $canonicalRequest = implode("\n", [
            $method,
            $path,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $stringToSign = implode("\n", [
            $algorithm,
            $date,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->getSigningKey($dateKey, $this->region, $service);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $algorithm,
            $this->accessKey,
            $credentialScope,
            $signedHeaders,
            $signature
        );

        return [
            'Content-Type'  => 'application/json',
            'X-Amz-Date'    => $date,
            'Authorization' => $authorization,
        ];
    }

    private function getSigningKey(string $dateKey, string $region, string $service): string
    {
        $kDate    = hash_hmac('sha256', $dateKey, 'AWS4'.$this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
