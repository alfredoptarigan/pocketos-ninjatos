<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Title;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TitleController extends Controller
{
    /**
     * Owned titles, and the titles this rework can award with how to earn them.
     */
    public function index(Request $request): Response
    {
        $character = $request->user()->character;
        $owned = $character->titles()->orderBy('titles.id')->get();
        $sources = $this->sources();

        return Inertia::render('character/titles', [
            'owned' => $owned->map(fn (Title $title) => $title->summary()),
            'locked' => Title::query()->whereIn('code', array_keys($sources))->whereNotIn('id', $owned->modelKeys())->orderBy('id')->get()
                ->map(fn (Title $title) => [...$title->summary(), 'how' => $sources[$title->code]]),
            'worn' => $character->title_id,
        ]);
    }

    public function wear(Request $request, Title $title): RedirectResponse
    {
        $character = $request->user()->character;
        // Only earned titles: others are a 404.
        $owned = $character->titles()->findOrFail($title->id);
        $character->forceFill(['title_id' => $owned->id])->save();

        return to_route('titles.index');
    }

    public function takeOff(Request $request): RedirectResponse
    {
        $request->user()->character->forceFill(['title_id' => null])->save();

        return to_route('titles.index');
    }

    /**
     * How each obtainable title is earned, by title code.
     *
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = Achievement::query()->whereNotNull('title')->get()
            ->mapWithKeys(fn (Achievement $achievement) => [$achievement->title => "Achievement \"{$achievement->name}\": {$achievement->goal()}"])
            ->all();

        foreach (config('game.collection.tiers') as $rarity => $tiers) {
            foreach ($tiers as [$count, , , $code]) {
                if ($code !== null) {
                    $sources[$code] = "Record {$count} {$rarity} outfits in the avatar collection.";
                }
            }
        }

        return $sources;
    }
}
