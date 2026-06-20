<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\IVR\Support\IvrReportData;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;

class CheckIvrBudget extends Command
{
    protected $signature = 'ivr:check-budget';

    protected $description = 'Raise an admin notification when IVR spend is projected to exceed the monthly minute quota';

    public function handle(IvrReportData $reports): int
    {
        $budget = $reports->forPeriod(now()->year, now()->month)['monthlyBudget'];

        if ($budget === null || ! ($budget['projected_over_quota'] ?? false)) {
            $this->info('IVR spend is on pace to stay within quota — no alert.');

            return self::SUCCESS;
        }

        $projected = number_format($budget['projected_minutes']);
        $quota = number_format($budget['minutes_quota']);
        $overage = number_format($budget['projected_overage']);
        $used = number_format($budget['minutes_used']);

        $users = User::all();

        Notification::make()
            ->title('IVR budget alert: projected to exceed quota')
            ->body("Projected month-end usage is {$projected} min against a {$quota} min quota (~{$overage} min over). {$used} min used so far this month.")
            ->icon('heroicon-o-exclamation-triangle')
            ->warning()
            ->sendToDatabase($users);

        $this->warn("Budget alert sent to {$users->count()} user(s): projected {$projected} vs quota {$quota} min.");

        return self::SUCCESS;
    }
}
