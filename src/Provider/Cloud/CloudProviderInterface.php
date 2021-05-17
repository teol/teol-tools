<?php

declare(strict_types=1);

namespace Provider\Cloud;

interface CloudProviderInterface
{
    public function supports(string $name): bool;
    
    public function getSnapshots(): array;

    public function getSnapshot(int $id): array;

    public function createSnapshot(
        int $serverId,
        string $description,
        string $type,
        array $labels = []
    ): array;

    public function deleteSnapshot(int $id): bool;

    //public function getSnapshotActions(int $imageId): array;

    //public function getSnapshotAction(int $imageId, int $actionId): array;

    public function deleteOldestSnapshot(int $serverId): bool;

    public function getServers(): array;

    public function getServer(int $id): array;

    public function rebootServer(int $id): bool;
}
