<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Item;
use App\Models\Outfit;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QaAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_maxed_qa_ninja_with_everything_to_try()
    {
        Outfit::factory()->create(['key' => '0_47', 'sex' => 0]);
        Outfit::factory()->create(['key' => '1_72', 'sex' => 1]);
        Equipment::factory()->count(2)->create();
        Item::factory()->create();
        Title::create(['code' => 'EffortTitle48', 'name' => 'Bankai Blue Beast', 'category' => 6, 'bonus' => []]);

        $this->artisan('game:qa-account', ['--password' => 'secret-pass'])->assertSuccessful();

        $user = User::where('email', 'qa@pocketo.test')->sole();
        $this->assertTrue(Hash::check('secret-pass', $user->password));
        $this->assertNotNull($user->email_verified_at);

        $ninja = $user->character;
        $this->assertSame(config('game.combat.max_level'), $ninja->level);
        $this->assertGreaterThanOrEqual(1_000_000, $ninja->gold);
        $this->assertGreaterThanOrEqual(10_000, $ninja->coupons);
        $this->assertSame(['0_47'], $ninja->outfits()->pluck('key')->all()); // its own sex
        $this->assertSame(2, $ninja->gear()->count());
        $this->assertSame(1, $ninja->inventory()->count());
        $this->assertSame(1, $ninja->titles()->count());
    }

    public function test_running_it_again_refreshes_the_same_account()
    {
        $this->artisan('game:qa-account', ['--password' => 'one'])->assertSuccessful();
        $this->artisan('game:qa-account', ['--password' => 'two'])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'qa@pocketo.test')->count());
        $this->assertTrue(Hash::check('two', User::where('email', 'qa@pocketo.test')->sole()->password));
    }

    public function test_a_second_qa_account_needs_its_own_ninja_name()
    {
        $this->artisan('game:qa-account')->assertSuccessful();

        $this->artisan('game:qa-account', ['--email' => 'qa-female@pocketo.test', '--avatar' => '1_26'])->assertFailed();
        $this->artisan('game:qa-account', ['--email' => 'qa-female@pocketo.test', '--avatar' => '1_26', '--name' => 'QA Kunoichi'])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'qa-female@pocketo.test')->sole()->character->sex());
    }

    public function test_it_refuses_to_run_in_production()
    {
        $this->app['env'] = 'production';

        $this->artisan('game:qa-account')->assertFailed();

        $this->assertSame(0, User::count());
    }
}
