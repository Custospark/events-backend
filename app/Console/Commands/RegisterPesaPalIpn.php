<?php

namespace App\Console\Commands;

use App\Services\Payment\PesaPalGateway;
use Illuminate\Console\Command;

class RegisterPesaPalIpn extends Command
{
    protected $signature = 'pesapal:register-ipn';

    protected $description = 'Register the configured PesaPal IPN URL and print the notification ID.';

    public function handle(PesaPalGateway $gateway): int
    {
        $ipnId = $gateway->registerIpn();

        $this->info('PesaPal IPN registered for: '.config('pesapal.ipn_url'));
        $this->line('PESAPAL_IPN_ID='.$ipnId);

        return self::SUCCESS;
    }
}
