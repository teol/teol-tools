<?php

declare(strict_types=1);

namespace App\Provider\Healthchecks\ValueObject;

final class HealthchecksResponse implements HealthchecksResponseInterface
{
    public string $name;
    public string $tags;
    public string $desc;
    public int $grace;
    public int $nPings;
    public string $status;
    public ?\DateTime $lastPing;
    public ?\DateTime $nextPing;
}
