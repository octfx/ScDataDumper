<?php

declare(strict_types=1);

namespace Octfx\ScDataDumper\Commands\Concerns;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

trait GeneratesCache
{
    /**
     * Generate cache files if needed
     *
     * @param  string  $scDataPath  Path to Star Citizen data directory
     * @param  OutputInterface  $output  Output interface
     * @return int Command exit code
     */
    protected function generateCache(string $scDataPath, OutputInterface $output): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            return Command::FAILURE;
        }

        $cacheCommand = $application->find('generate:cache');
        $cacheInput = new ArrayInput(['path' => $scDataPath]);
        $cacheInput->setInteractive(false);

        return $cacheCommand->run($cacheInput, $output);
    }
}
