<?php

namespace App\Jobs;

use App\Enums\SubjectStatus;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\FinishWhatYouStart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * The daily inactivity check (scheduled in routes/console.php): every user who wants reminders and has started
 * subjects that went quiet gets one "finish what you start" notification listing them. Each subject is mentioned
 * once per quiet spell: nudged_at is set, and it only counts again after new activity.
 */
class SendNudges implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $days = (int) config('app.nudge_after_days');

        User::query()
            ->where('wants_nudges', true)
            ->whereNotNull('email_verified_at')
            ->whereHas('subjects', fn ($subjects) => $subjects->where('status', SubjectStatus::Active))
            ->with(['subjects' => fn ($subjects) => $subjects->where('status', SubjectStatus::Active)->withActivity()])
            ->chunkById(100, function (Collection $users) use ($days) {
                foreach ($users as $user) {
                    $this->nudge($user, $days);
                }
            });
    }

    private function nudge(User $user, int $days): void
    {
        $stalled = $user->subjects->filter(fn (Subject $subject) => $subject->isStalled($days))->values();

        if ($stalled->isEmpty()) {
            return;
        }

        $user->notify(new FinishWhatYouStart($stalled));

        Subject::query()->whereKey($stalled->modelKeys())->update(['nudged_at' => now()]);
    }
}
