<?php

namespace App\Console\Commands;

use App\Http\Controllers\Auth\DemoLoginController;
use App\Models\User;
use Illuminate\Console\Command;

class PurgeDemoAccounts extends Command
{
    protected $signature = 'demo:purge {--hours=24 : Antigüedad mínima de las cuentas demo a eliminar}';

    protected $description = 'Elimina las cuentas de demostración antiguas y todos sus datos (borrado en cascada).';

    public function handle(): int
    {
        $deleted = User::query()
            ->where('email', 'like', '%@'.DemoLoginController::EMAIL_DOMAIN)
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->delete();

        $this->info("Cuentas demo eliminadas: {$deleted}");

        return self::SUCCESS;
    }
}
