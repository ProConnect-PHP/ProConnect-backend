<?php

namespace App\Console\Commands;

use App\Models\User\EmailVerificationToken;
use Illuminate\Console\Command;

class PruneEmailVerificationTokensCommand extends Command
{
    protected $signature = 'email-verification:prune';

    protected $description = 'Delete used and expired email verification tokens.';

    public function handle(): int
    {
        $deleted = EmailVerificationToken::query()
            ->whereNotNull('used_at')
            ->orWhere('expires_at', '<=', now())
            ->delete();

        $this->info("Pruned {$deleted} email verification token(s).");

        return self::SUCCESS;
    }
}
