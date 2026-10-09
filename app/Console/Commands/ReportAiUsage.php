<?php

namespace App\Console\Commands;

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:ai-usage {--limit=20 : Number of users to show}')]
#[Description('Show the users with the highest AI spend this month')]
class ReportAiUsage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $totals = AiUsage::query()
            ->currentMonth()
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as requests, sum(input_tokens + output_tokens) as tokens, sum(cost_micros) as cost_micros')
            ->groupBy('user_id')
            ->orderByDesc('cost_micros')
            ->limit((int) $this->option('limit'))
            ->get();

        $users = User::withTrashed()->with('subscription')->findMany($totals->pluck('user_id'))->keyBy('id');

        $this->table(
            ['User', 'Requests', 'Tokens', 'Cost', 'Budget', 'Blocked'],
            $totals->map(function (AiUsage $total) use ($users): array {
                $user = $users->get($total->user_id);

                return [
                    $user->email ?? $total->user_id,
                    $total->requests,
                    number_format((int) $total->tokens),
                    $this->usd((int) $total->cost_micros),
                    $user === null ? '-' : $this->usd($user->monthlyAiBudgetInMicros()),
                    $user?->isAiBlocked() ? 'yes' : '',
                ];
            }),
        );

        $this->components->info('Total this month: '.$this->usd((int) AiUsage::query()->currentMonth()->sum('cost_micros')));

        $unpriced = AiUsage::query()->currentMonth()->whereNull('cost_micros')->count();

        if ($unpriced > 0) {
            $this->components->warn("{$unpriced} requests have no cost because their model has no price. Run `php artisan app:sync-ai-pricing`.");
        }

        return self::SUCCESS;
    }

    private function usd(int $micros): string
    {
        return '$'.number_format($micros / 1_000_000, 4);
    }
}
