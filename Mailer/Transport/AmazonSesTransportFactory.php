<?php

declare(strict_types=1);

namespace XtrusioPlugin\XtrusioAmazonSesBundle\Mailer\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class AmazonSesTransportFactory extends AbstractTransportFactory
{
    protected function getSupportedSchemes(): array
    {
        return ['ses+api', 'ses'];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!in_array($scheme, $this->getSupportedSchemes(), true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" scheme is not supported; supported schemes are: %s.', $scheme, implode(', ', $this->getSupportedSchemes())));
        }

        $accessKey        = $this->getUser($dsn);
        $secretKey        = $this->getPassword($dsn);
        $region           = $dsn->getOption('region', $dsn->getHost() ?: 'us-east-1');
        $configurationSet = $dsn->getOption('configuration_set', '');

        return new AmazonSesTransport(
            $region,
            $accessKey,
            $secretKey,
            $configurationSet,
            $this->client,
            $this->dispatcher,
            $this->logger,
        );
    }
}
