<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:ai-block {email : Email of the user} {--unblock : Restore AI access instead}')]
#[Description('Block or unblock AI features for a user')]
class BlockAiUsage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->components->error("No user found with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $user->forceFill(['ai_blocked_at' => $this->option('unblock') ? null : now()])->save();

        $this->components->info($this->option('unblock') ? "AI access restored for {$user->email}." : "AI access blocked for {$user->email}.");

        return self::SUCCESS;
    }
}
