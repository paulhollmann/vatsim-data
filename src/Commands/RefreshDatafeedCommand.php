<?php

namespace VatsimData\Commands;

use Illuminate\Console\Command;
use VatsimData\Datafeed;

class RefreshDatafeedCommand extends Command
{
    protected $signature = 'vatsimdata:refresh';

    protected $description = 'Refresh the VATSIM datafeed cache and pilot movement history';

    public function handle(): int
    {
        $feed = Datafeed::refresh();

        if ($feed === null) {
            $this->error('Unable to refresh the VATSIM datafeed.');

            return self::FAILURE;
        }

        $this->info(sprintf('Refreshed %d pilots and movement history.', count($feed->pilots)));

        return self::SUCCESS;
    }
}
