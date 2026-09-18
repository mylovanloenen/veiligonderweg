<?php

namespace App\Console\Commands;

use App\Models\Incident;
use Illuminate\Console\Command;

class AnonymiseIncidents extends Command
{
    protected $signature = 'incidents:anonymise';

    protected $description = 'Ontkoppelt verlopen meldingen van accounts, wist tekst en verwijdert oude meldingen';

    public function handle(): int
    {
        $anonymiseAfter = now()->subDays((int) config('veiligonderweg.incidents.anonymise_after_days'));
        $deleteAfter = now()->subDays((int) config('veiligonderweg.incidents.delete_after_days'));

        $deleted = Incident::where('expires_at', '<', $deleteAfter)->delete();

        $anonymised = Incident::where('expires_at', '<', $anonymiseAfter)
            ->whereNull('anonymised_at')
            ->update(['user_id' => null, 'description' => null, 'anonymised_at' => now()]);

        $this->info("Geanonimiseerd: {$anonymised}, verwijderd: {$deleted}.");

        return self::SUCCESS;
    }
}
