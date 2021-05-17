<?php

declare(strict_types=1);

namespace App\Command;

use App\Provider\Healthchecks\Healthchecks;
use Provider\Cloud\Hetzner\HetznerProvider;
use Provider\Cloud\CloudProviderInterface;
use Provider\Cloud\CloudProviderResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class HetznerSnapshotCreateCommand extends Command
{
    public const SNAPSHOT_NAME_REGEX = "/auto-\w{13}-\d{1,}-\d{2}-\d{2}-\d{4}_\d{2}-\d{2}-\d{2}/";
    
    protected static $defaultName = 'app:hetzner-snapshot:create';
    protected static $defaultDescription = 'Creates a new server snapshot on Hetzner';

    protected int $serverId;
    protected ?string $healthcheckCheckId = null;
    protected ?array $healthcheckConfig = null;
    protected Healthchecks $healthchecks;
    protected ContainerInterface $container;
    protected CloudProviderInterface $provider;
    protected CloudProviderResolver $providerResolver;
    protected SymfonyStyle $io;
    protected InputInterface $input;
    protected OutputInterface $output;

    public function __construct(
        CloudProviderResolver $providerResolver,
        Healthchecks $healthchecks,
        ContainerInterface $container
        //string $healthcheckCheckId
    ) {
        $this->providerResolver = $providerResolver;
        $this->healthchecks = $healthchecks;
        //$this->container = $container;
        //$this->healthcheckCheckId = $healthcheckCheckId;

        $this->chechealthcheckCheckIdkId = null;
        if ($container->hasParameter('healthchecks_config')) {
            $this->healthcheckConfig = $container->getParameter('healthchecks_config');
        }
        unset($container);

        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('id', InputArgument::REQUIRED, 'Server ID')
            ->addArgument('providerName', InputArgument::REQUIRED, 'Server provider')
            ->addArgument('limit', InputArgument::REQUIRED, 'Number of snapshots to keep')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $providerName = strtolower($input->getArgument('providerName'));
        $this->healthcheckCheckId = $this->healthcheckConfig['checks']['snapshot_'.$providerName.'_id'] ?? null;
        
        if ($this->healthcheckCheckId) {
            $this->healthchecks->init($this->healthcheckCheckId);
            // if ($this->healthchecks->isLocked()) {
            //     $this->io->note('The job has already been executed.');

            //     return 0;
            // }
            $this->healthchecks->start();
        }

        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
        $this->serverId = (int) $input->getArgument('id');
        $this->provider = $this->providerResolver->getProvider($providerName);
        $snapshotsToKeep = (int) $input->getArgument('limit');

        $this->io->info(sprintf('Server ID: %s, Provider: %s, Limit: %s', $this->serverId, $providerName, $snapshotsToKeep));

        $isSnapshotCreated = $this->handleSnapshotCreation();
        if (!$isSnapshotCreated) {
            $this->healthchecks->failure();
            return Command::FAILURE;
        }
        
        $this->deleteOldSnapshots($snapshotsToKeep);
        $this->io->success('Job done.');
        $this->healthchecks->ping();

        return Command::SUCCESS;
    }

    private function deleteOldSnapshots(int $snapshotsToKeep): void
    {
        $this->io->info('Fetching available snapshots...');
        $snapshots = $this->provider->getSnapshots();

        $this->io->info('Filtering snapshots...');
        $validSnapshotList = [];
        foreach ($snapshots['images'] as $k => $snapshot) {
            if ('snapshot' !== $snapshot['type']) {
                continue;
            }
            if ($this->serverId !== $snapshot['created_from']['id']) {
                continue;
            }
            if (!preg_match(self::SNAPSHOT_NAME_REGEX, $snapshot['description'])) {
                continue;
            }

            $creationDate = new \DateTime($snapshot['created'], new \DateTimeZone('Europe/Paris'));
            $validSnapshotList[$creationDate->getTimestamp()] = $snapshot;
        }
        ksort($validSnapshotList, SORT_NUMERIC);

        $snapshotsToDelete = $this->getSnapshotsToDelete($validSnapshotList, $snapshotsToKeep);
        $toDeleteCount = count($snapshotsToDelete);
        $this->io->info('Found '.$toDeleteCount.' snapshots to delete.');

        if ($toDeleteCount > 0) {
            //$progressBar = new ProgressBar($this->output, $toDeleteCount);
            //$progressBar->start();
            foreach ($snapshotsToDelete as $snapshot) {
                $this->io->info(sprintf('Deleting snapshot "%s"', htmlspecialchars($snapshot['description'])));
                //$progressBar->setMessage(sprintf('Deleting snapshot #%s', htmlspecialchars($snapshot['description'])));
                $isDeleted = $this->provider->deleteSnapshot($snapshot['id']);
                if (!$isDeleted) {
                    $this->io->warning(sprintf('Error during deletion of snapshot "%s"', htmlspecialchars($snapshot['description'])));
                }
                //$progressBar->advance();
            }
            //$progressBar->finish();
        }
    }

    private function handleSnapshotCreation(): bool
    {
        $this->io->info('Snapshot creation in progress...');
        $isSnapshotCreated = false;
        $progressBar = new ProgressBar($this->output, 100);
        $progressBar->start();
        //$progressBar->setMessage('Snapshot creation in progress...');

        $createdSnapshot = $this->provider->createSnapshot($this->serverId, $this->generateSnapshotDescription(), HetznerProvider::IMAGE_TYPE_SNAPSHOT);
        $serverActionId = $createdSnapshot['action']['id'] ?? null;

        if (null === $serverActionId) {
            return false;
        }
        $serverActionId = (int) $serverActionId;

        $progressBar->setProgress((int) $createdSnapshot['action']['progress']);

        while (false === $isSnapshotCreated) {
            $serverSnapshotAction = $this->provider->getServerAction($this->serverId, $serverActionId);

            if ($serverSnapshotAction['progress']) {
                $progressBar->setProgress($serverSnapshotAction['progress']);
            }

            if ($serverSnapshotAction['status'] == 'success') {
                $isSnapshotCreated = true;
                break;
            } elseif ($serverSnapshotAction['status'] == 'error') {
                break;
            } elseif ($serverSnapshotAction['status'] == 'running') {
                sleep(2);
                continue;
            }

            throw new \Exception('Unexpected server action status');
        }

        $progressBar->finish();

        return $isSnapshotCreated;
    }

    private function getSnapshotsToDelete(array $validSnapshotList, int $snapshotsToKeep): array
    {
        $toDelete = [];
        $i = count($validSnapshotList);
        foreach ($validSnapshotList as $snapshot) {
            if ($i <= $snapshotsToKeep) {
                break;
            }

            $toDelete[] = $snapshot;
            $i--;
        }

        return $toDelete;
    }

    private function generateSnapshotDescription(): string
    {
        $date = new \DateTime();

        return 'auto-'.uniqid().'-'.$this->serverId.'-'.$date->format('d-m-Y_H-i-s');
    }
}
