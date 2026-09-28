<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\CommerceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CustomerNotifier
{
    public function send(User $user, string $kind, string $title, string $body, string $url): void
    {
        $user->notify(new CommerceNotification($kind, $title, $body, $url));

        if (! config('mobile-arena.notifications.email_enabled')
            || in_array(config('mail.default'), ['log', 'array'], true)) {
            return;
        }

        $sendMail = function () use ($user, $kind, $title, $body, $url) {
            try {
                $user->notify(new CommerceNotification($kind, $title, $body, $url, true));
            } catch (Throwable $exception) {
                Log::warning('Mobile Arena email notification failed', ['kind' => $kind, 'user_id' => $user->id, 'error' => $exception->getMessage()]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($sendMail);
        } else {
            $sendMail();
        }
    }

    public function admins(string $kind, string $title, string $body, string $url): void
    {
        User::where('role', 'admin')->get()->each(
            fn (User $admin) => $admin->notify(new CommerceNotification($kind, $title, $body, $url))
        );
    }
}
