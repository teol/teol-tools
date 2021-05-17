<?php

declare(strict_types=1);

namespace App\Provider\Healthchecks;

use App\Provider\Healthchecks\ValueObject\HealthchecksResponse;
use App\Provider\Healthchecks\ValueObject\HealthchecksResponseInterface;
use App\Provider\Healthchecks\ValueObject\NullHealthchecksResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class Healthchecks
{
    protected const E_PING = 'https://hc-ping.com';
    protected const E_GET_CHECK = 'https://healthchecks.io/api/v1/checks/%s';

    private HttpClientInterface $client;
    private SerializerInterface $serializer;
    private ?string $apiKey;
    private ?string $checkId = null;

    public function __construct(
        HttpClientInterface $client,
        SerializerInterface $serializer,
        ?string $apiKey
    ) {
        $this->client = $client;
        $this->serializer = $serializer;
        $this->apiKey = $apiKey;
    }

    public function init(?string $checkId): bool
    {
        if (!$this->apiKey) {
            throw new \LogicException('API key is missing.');
        }

        $this->checkId = $checkId ?? $this->checkId;

        return true;
    }

    public function ping(): bool
    {
        $this->init(null);

        return (bool) file_get_contents(self::E_PING.'/'.$this->checkId);
    }

    public function start(): bool
    {
        $this->init(null);

        return (bool) file_get_contents(self::E_PING.'/'.$this->checkId.'/start');
    }

    public function failure(): bool
    {
        $this->init(null);

        return (bool) file_get_contents(self::E_PING.'/'.$this->checkId.'/failure');
    }

    public function isLocked(): bool
    {
        $response = $this->getCheck();

        if ($response instanceof HealthchecksResponse) {
            $time = new \DateTime('now', new \DateTimeZone('UTC'));

            return null !== $response->lastPing
                && null !== $response->nextPing
                && $time->getTimestamp() <= $response->nextPing->getTimestamp();
        }

        return false;
    }

    protected function getCheck(): HealthchecksResponseInterface
    {
        $this->init(null);

        try {
            $response = $this->client->request(
                'GET',
                sprintf(self::E_GET_CHECK, $this->checkId),
                [
                    'headers' => [
                        'X-Api-Key' => $this->apiKey,
                    ],
                ]
            );

            return $this->unserialize($response);
        } catch (\Throwable $e) {
            throw new HealthchecksException($e->getMessage(), $e->getRequest(), $e->getResponse() ?? null, $e ?? null, $e->getHandlerContext() ?? []);
        }
    }

    protected function unserialize(ResponseInterface $response): HealthchecksResponseInterface
    {
        try {
            if (!$content = $response->getContent()) {
                throw new \RuntimeException('Invalid response object format.');
            }

            $result = $this->serializer->deserialize($content, HealthchecksResponse::class, 'json');
        } catch (\Throwable $e) {
        }

        return $result ?? new NullHealthchecksResponse();
    }
}
