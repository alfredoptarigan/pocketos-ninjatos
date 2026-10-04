<?php

namespace App\Http\Controllers;

use App\Actions\RecordOutfit;
use App\Game\AvatarCollection;
use App\Models\Outfit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CollectionController extends Controller
{
    private const RARITY_ORDER = ['orange', 'blue', 'grey'];

    /**
     * The avatar collection: every collectible outfit of the ninja's sex,
     * which are owned and recorded, and the tier reached per rarity.
     */
    public function index(Request $request): Response
    {
        $character = $request->user()->character;
        $levels = $character->outfits()->pluck('character_outfits.level', 'outfits.id');
        $recorded = $character->outfits()->whereNotNull('character_outfits.recorded_at')->pluck('outfits.id');

        return Inertia::render('character/collection', [
            'outfits' => Outfit::query()->whereNotNull('collection')->where('sex', $character->sex())->orderBy('name')->get()
                ->sortBy(fn (Outfit $outfit) => array_search($outfit->rarity, self::RARITY_ORDER, true))
                ->values()
                ->map(fn (Outfit $outfit) => [
                    ...$outfit->summary($levels->get($outfit->id, 0)),
                    'attributes' => $outfit->collection,
                    'owned' => $levels->has($outfit->id),
                    'recorded' => $recorded->contains($outfit->id),
                ]),
            'tiers' => AvatarCollection::for($character)->tiers,
            'rules' => config('game.collection'),
        ]);
    }

    public function record(Request $request, Outfit $outfit, RecordOutfit $record): RedirectResponse
    {
        $record->handle($request->user()->character, $outfit);

        return to_route('collection.index');
    }
}
