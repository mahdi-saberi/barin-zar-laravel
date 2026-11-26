<?php

namespace Tests\Feature;

use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoldRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_persists_a_rate_in_consecutive_minutes(): void
    {
        $url = 'https://rates.example.test/gold';
        config(['barin.integrations' => ['rates.example.test']]);
        Entry::putData('settings', 'store', [
            'rate_url' => $url,
            'rate_path' => 'data.price',
            'rate_unit' => 'rial',
        ]);
        Http::preventStrayRequests();
        Http::fake([$url => Http::sequence()
            ->push(['data' => ['price' => 257780000]])
            ->push(['data' => ['price' => 257810000]])]);

        $this->travelTo(Carbon::parse('2026-03-20T20:45:00+00:00'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->getJson('/gold-rate')->assertOk()
            ->assertJsonPath('price', '۲۵٬۷۷۸٬۰۰۰')
            ->assertJsonPath('updated', '۱۴۰۵/۰۱/۰۱ ۰۰:۱۵')
            ->assertJsonPath('source', null);

        $this->travel(1)->minute();
        $this->artisan('schedule:run')->assertSuccessful();
        $this->getJson('/gold-rate')->assertOk()
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('price', '۲۵٬۷۸۱٬۰۰۰')
            ->assertJsonPath('updated', '۱۴۰۵/۰۱/۰۱ ۰۰:۱۶');
        Http::assertSentCount(2);
    }

    public function test_rate_endpoint_reads_the_saved_value_without_contacting_the_provider(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Entry::where(['kind' => 'rate', 'slug' => 'latest'])->delete();
        $this->getJson('/gold-rate')->assertOk()->assertJsonPath('value', null);

        Entry::putData('rate', 'latest', ['value' => 10500000, 'source' => 'sample']);
        $this->getJson('/gold-rate')->assertOk()
            ->assertJsonPath('price', '۱۰٬۵۰۰٬۰۰۰')
            ->assertJsonPath('source', 'نمونه')
            ->assertJsonPath('datetime', null);
        Http::assertNothingSent();
    }

    public function test_scheduler_reports_provider_failure_and_keeps_the_last_rate_and_quotes(): void
    {
        $url = 'https://rates.example.test/gold';
        config(['barin.integrations' => ['rates.example.test']]);
        Entry::putData('settings', 'store', ['rate_url' => $url, 'rate_path' => 'price', 'rate_unit' => 'rial']);
        Entry::putData('product', 'piece', ['name' => 'دمو', 'milligrams' => 1000, 'labor' => 0, 'profit_bps' => 0, 'tax_bps' => 0, 'discount_bps' => 0]);
        Http::preventStrayRequests()->fake([$url => Http::sequence()->push(['price' => '۲۵۷٬۸۰۰٬۰۰۰'])->pushStatus(401)]);

        $this->travelTo(Carbon::parse('2026-03-20T20:45:00+00:00'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame('updated', Entry::getData('rate', 'scheduled')['state']);
        $rate = Entry::getData('rate', 'latest');
        $this->travel(1)->minute();
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame($rate, Entry::getData('rate', 'latest'));
        $this->assertSame('error', Entry::getData('rate', 'scheduled')['state']);
        $this->assertStringContainsString('401', Entry::getData('rate', 'scheduled')['message']);
        $this->getJson('/gold-rate?products[]=piece')->assertOk()
            ->assertJsonPath('value', 25780000)->assertJsonPath('quotes.piece.total', 25780000);
    }
}
