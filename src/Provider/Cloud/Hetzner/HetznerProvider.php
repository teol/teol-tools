<?php

declare(strict_types=1);

namespace App\Provider\Cloud\Hetzner;

use App\Provider\Cloud\CloudProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HetznerProvider implements CloudProviderInterface
{
    public const PROVIDER_NAME = 'hetzner';
    
    public const IMAGE_TYPE_SNAPSHOT = 'snapshot';
    public const IMAGE_TYPE_BACKUP = 'backup';
    public const IMAGE_TYPES = [
        self::IMAGE_TYPE_BACKUP,
        self::IMAGE_TYPE_SNAPSHOT,
    ];

    protected HttpClientInterface $httpClient;
    protected string $apiToken;
    protected string $apiBaseUrl;

    public function __construct(string $apiToken, string $apiBaseUrl, HttpClientInterface $httpClient)
    {
        $this->apiToken = $apiToken;
        $this->apiBaseUrl = $apiBaseUrl;
        $this->httpClient = $httpClient;
    }

    public function __toString(): string
    {
        return self::PROVIDER_NAME;
    }

    public function supports(string $name): bool
    {
        return (string) $this === $name;
    }

    public function getSnapshots(): array
    {
        //TODO: sort by creation date here to avoid to do it later
        $response = $this->httpClient->request('GET', $this->apiBaseUrl.'images?type=snapshot&status=available', $this->getDefaultOptions());
        $result = $response->getContent();
        if ($result) {
            return json_decode($result, true);
        }

        return [];
    }

    public function getSnapshot(int $id): array
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl.'images/'.$id, $this->getDefaultOptions());
        $result = $response->getContent();
        if ($result) {
            return json_decode($result, true);
        }

        return [];
    }

    public function createSnapshot(
        int $serverId,
        string $description,
        string $type = self::IMAGE_TYPE_SNAPSHOT,
        array $labels = []
    ): array {
        $options = $this->getDefaultOptions();
        $options['json'] = [
            'description' => $description,
            'type' => $type,
            //'labels' => $labels ?? null,
        ];
        
        $response = $this->httpClient->request('POST', $this->apiBaseUrl.'servers/'.$serverId.'/actions/create_image', $options);
        
        $result = $response->getContent(false);
        if ($result) {
            return json_decode($result, true);
        }

        return [];
    }

    public function deleteSnapshot(int $id): bool
    {
        $response = $this->httpClient->request('DELETE', $this->apiBaseUrl.'images/'.$id, $this->getDefaultOptions());
        if (204 === $response->getStatusCode()) {
            return true;
        }

        return false;
    }

    public function getSnapshotActions(int $imageId): array
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl.'images/'.$imageId.'/actions', $this->getDefaultOptions());
        $result = $response->getContent();
        if ($result) {
            return json_decode($result, true);
        }

        return [];
    }

    public function getSnapshotAction(int $imageId, int $actionId): array
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl.'images/'.$imageId.'/actions/'.$actionId, $this->getDefaultOptions());
        $result = $response->getContent();
        if ($result) {
            return json_decode($result, true);
        }

        return [];
    }

    public function deleteOldestSnapshot(int $serverId): bool
    {
        $snapshotList = $this->getSnapshots();
        dump($snapshotList);
        die;
        $toDeleteId = 1337; //TODO: to real id
        
        return true;
    }

    public function getServers(): array
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl.'servers', $this->getDefaultOptions());
        $result = $response->getContent();
        if ($result) {
            $result = json_decode($result, true);
            return $result['servers'];
        }

        return [];
    }

    public function getServer(int $id): array
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl.'servers/'.$id, $this->getDefaultOptions());
        $result = $response->getContent();
        if ($result) {
            return json_decode($result, true);
        }

        return [];
    }

    public function getServerAction(int $serverId, int $serverActionId): array
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl.'servers/'.$serverId.'/actions/'.$serverActionId, $this->getDefaultOptions());
        $result = $response->getContent();
        if ($result) {
            $result = json_decode($result, true);
            return $result['action'];
        }

        return [];
    }

    public function rebootServer(int $id): bool
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl.'servers/'.$id.'/actions/reboot', $this->getDefaultOptions());
        $result = $response->getContent();
        if ($result) {
            return json_decode($result, true);
        }

        return [];
    }

    protected function getDefaultOptions(): array
    {
        return [
            'headers' => [
                'Authorization' => 'Bearer '.$this->apiToken,
                'Content-Type' => 'application/json',
            ],
        ];
    }
}
