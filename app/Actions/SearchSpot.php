<?php

namespace App\Actions;

use App\Game\Leveling;
use App\Game\SearchOutcome;
use App\Models\Battle;
use App\Models\Character;
use App\Models\Field;
use App\Models\Item;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SearchSpot
{
    public function __construct(
        private readonly HuntMonster $hunt,
        private readonly DropGear $dropGear,
    ) {}

    /**
     * Search a bush/tree (or open a cache with the area key). Every search
     * pays its base exp; the roll may also start a fight or turn up loot.
     *
     * @return array{message: string, battle: Battle|null}
     *
     * @throws ValidationException when the spot cannot be searched yet
     */
    public function handle(Character $character, Field $field, string $spot): array
    {
        return DB::transaction(function () use ($character, $field, $spot) {
            $ninja = Character::query()->lockForUpdate()->findOrFail($character->id);
            $search = collect($field->searches)->firstWhere('spot', $spot);
            abort_if($search === null, 404);

            $this->ensureCanSearch($ninja, $field, $search);
            $this->consumeKey($ninja, $search['key']);
            DB::table('character_searches')->upsert(
                [['character_id' => $ninja->id, 'field_scene' => $field->scene, 'spot' => $spot, 'searched_at' => Carbon::now()]],
                ['character_id', 'field_scene', 'spot'],
                ['searched_at'],
            );

            [$level, $exp] = Leveling::gain($ninja->level, $ninja->exp, $search['exp']);
            $ninja->forceFill(['level' => $level, 'exp' => $exp])->save();

            $outcome = SearchOutcome::pick($search['rates'], random_int(1, SearchOutcome::ROLLS));

            return $this->apply($outcome, $ninja, $field, $spot, $search['exp']);
        });
    }

    /**
     * @param  array{spot: string, key: string|null, cooldown: int}  $search
     */
    private function ensureCanSearch(Character $ninja, Field $field, array $search): void
    {
        $last = DB::table('character_searches')
            ->where(['character_id' => $ninja->id, 'field_scene' => $field->scene, 'spot' => $search['spot']])
            ->value('searched_at');
        $readyAt = $last ? Carbon::parse($last)->addSeconds($search['cooldown']) : null;

        $problem = match (true) {
            $ninja->level < $field->level => "{$field->name} needs level {$field->level}.",
            $ninja->currentHp() < 1 => 'You are too hurt to search. Rest or drink a potion first.',
            $readyAt?->isFuture() === true => 'Nothing new here yet. Come back in '.(int) ceil(Carbon::now()->diffInSeconds($readyAt)).'s.',
            $search['key'] !== null && ! $ninja->inventory()->whereHas('item', fn ($q) => $q->where('code', $search['key']))->exists() => "You need the {$field->name} Key to open this.",
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['search' => $problem]);
        }
    }

    private function consumeKey(Character $ninja, ?string $code): void
    {
        if ($code === null) {
            return;
        }

        $stack = $ninja->inventory()->whereHas('item', fn ($q) => $q->where('code', $code))->lockForUpdate()->firstOrFail();
        $stack->quantity > 1 ? $stack->decrement('quantity') : $stack->delete();
    }

    /**
     * @return array{message: string, battle: Battle|null}
     */
    private function apply(string $outcome, Character $ninja, Field $field, string $spot, int $exp): array
    {
        $found = fn (string $what) => ['message' => "+{$exp} EXP. {$what}", 'battle' => null];
        $monsters = $field->monsters()->get();

        switch ($outcome) {
            case 'monster':
            case 'boss':
                $foes = $monsters->where('is_boss', $outcome === 'boss');
                $foe = ($foes->isEmpty() ? $monsters : $foes)->random();

                return ['message' => "A {$foe->name} jumps out!", 'battle' => $this->hunt->handle($ninja, $foe)];
            case 'money':
                $gold = ($field->level + 9) * config('game.search.gold_per_level');
                $ninja->forceFill(['gold' => $ninja->gold + $gold])->save();

                return $found("You found a pouch with {$gold} gold.");
            case 'key':
                $key = Item::query()->where('code', collect($field->searches)->whereNotNull('key')->value('key'))->first();

                if ($key) {
                    $this->give($ninja, $key);

                    return $found("You found the {$key->name}!");
                }

                return $found('You found nothing.');
            case 'item':
                if ($spot === 'cache') {
                    $piece = $this->dropGear->handle($ninja, $monsters->max('level') ?? $field->level);

                    return $found($piece ? "The cache held {$piece->name}!" : 'The cache was empty.');
                }

                $potion = Item::pharmacy()->where('price', '<=', max(config('game.search.potion_price_floor'), $field->level * 5))->inRandomOrder()->first();

                if ($potion) {
                    $this->give($ninja, $potion);

                    return $found("You found a {$potion->name}.");
                }

                return $found('You found nothing.');
            case 'baby':
                // Pets are not in the game yet: the baby gets away.
                $baby = $monsters->where('is_boss', false)->first();

                return $found("A baby {$baby?->name} peeks out and scurries away.");
            default:
                return $found('You found nothing.');
        }
    }

    private function give(Character $ninja, Item $item): void
    {
        $stack = $ninja->inventory()->where('item_id', $item->id)->lockForUpdate()->first();

        if ($stack === null) {
            $ninja->inventory()->create(['item_id' => $item->id, 'quantity' => 1]);
        } elseif ($stack->quantity < $item->max_stack) {
            $stack->increment('quantity');
        }
    }
}
