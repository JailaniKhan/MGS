<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\InboundMessageService;
use Illuminate\Console\Command;

class WhatsAppSyncInbound extends Command
{
    protected $signature = 'whatsapp:sync-inbound';

    protected $description = 'Poll the OpenWA gateway for received WhatsApp messages and store them as incoming chat bubbles';

    public function handle(InboundMessageService $inbound): int
    {
        try {
            $stored = $inbound->sync();
        } catch (\Throwable $e) {
            report($e);
            $this->error('Inbound sync failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($stored > 0) {
            $this->info("Stored {$stored} inbound WhatsApp message(s).");
        }

        return self::SUCCESS;
    }
}
