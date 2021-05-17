<?php

declare(strict_types=1);

namespace App\Provider\Healthchecks\ValueObject;

final class NullHealthchecksResponse implements HealthchecksResponseInterface
{
    public string $name = '';
    public string $tags = '';
    public string $desc = '';
    public int $grace = 0;
    public int $nPings = 0;
    public string $status = '';
    public string $lastPing = '';
    public string $nextPing = '';
}
