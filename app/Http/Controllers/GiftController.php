<?php

namespace App\Http\Controllers;

use App\Actions\ClaimDailySignIn;
use App\Game\Leveling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GiftController extends Controller
{
    /**
     * The Gifts panel: this week's daily sign-in rewards.
     */
    public function show(Request $request): Response
    {
        $character = $request->user()->character;
        $claimedToday = (bool) $character->signed_in_on?->isToday();
        // A streak survives only while the last sign-in was today or yesterday.
        $streak = $claimedToday || $character->signed_in_on?->isYesterday() ? $character->sign_in_streak : 0;
        $config = config('game.sign_in');

        return Inertia::render('gifts', [
            'streak' => $streak,
            'claimedToday' => $claimedToday,
            'rewards' => collect($config['coupons'])->map(fn (int $coupons, int $index) => [
                'day' => $index + 1,
                'coupons' => $coupons,
            ]),
            'exp' => intdiv(Leveling::expToNext($character->level) * $config['exp_percent'], 100),
        ]);
    }

    public function signIn(Request $request, ClaimDailySignIn $claim): RedirectResponse
    {
        $reward = $claim->handle($request->user()->character);
        $message = "Day {$reward['day']}: +{$reward['exp']} EXP, +{$reward['coupons']} gift coupons".($reward['levelUp'] ? '. Level up!' : '.');
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('gifts.show');
    }
}
