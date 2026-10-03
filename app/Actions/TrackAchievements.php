<?php

namespace App\Actions;

use App\Models\Achievement;
use App\Models\Character;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class TrackAchievements
{
    /**
     * Complete every achievement whose counter reached its target, grant
     * their titles and toast them. Points achievements can follow from
     * others, so it repeats until nothing new completes.
     *
     * @return Collection<int, Achievement> the newly completed
     */
    public function handle(Character $character): Collection
    {
        $completed = collect();

        do {
            $done = $character->achievements()->pluck('achievements.id');
            $counters = $this->counters($character);
            $new = Achievement::query()->whereNotIn('id', $done)->get()
                ->filter(fn (Achievement $achievement) => $counters[$achievement->counter] >= $achievement->target);

            foreach ($new as $achievement) {
                $character->achievements()->attach($achievement, ['completed_at' => now()]);

                if ($achievement->title !== null) {
                    $character->grantTitle($achievement->title);
                }
            }
            $completed = $completed->merge($new);
        } while ($new->isNotEmpty());

        if ($completed->isNotEmpty()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Achievement completed: '.$completed->pluck('name')->join(', ').'!']);
        }

        return $completed;
    }

    /**
     * Current value of every counter of config('game.achievements.counters').
     *
     * @return array<string, int>
     */
    public function counters(Character $character): array
    {
        return [
            'level' => $character->level,
            'gold_spent' => $character->gold_spent,
            'bosses_defeated' => $character->bosses_defeated,
            'sign_in_days' => $character->sign_in_days,
            'points' => (int) $character->achievements()->sum('points'),
        ];
    }
}
