<?php

declare(strict_types=1);

namespace Provider\Cloud;

use App\Exception\ProviderNotFoundException;
use Provider\Cloud\CloudProviderInterface;

class CloudProviderResolver
{
    /**
     * @var CloudProviderInterface[]
     */
    private iterable $providers = [];

    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
    }

    public function addProvider(CloudProviderInterface $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    public function getProvider(string $service): CloudProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($service)) {
                return $provider;
            }
        }

        throw new ProviderNotFoundException(sprintf('No provider could be found with name %s.', $service));
    }

    public function getProviders(): iterable
    {
        return $this->providers;
    }
}
