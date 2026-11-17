<?php

namespace Tests\Feature;

use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\FakeStockProvider;
use App\Modules\Products\Services\ProductCacheService;
use App\Support\PerfMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCachingTest extends TestCase
{
    use RefreshDatabase;

    private string $stateFile;

    /**
     * ملفات app/Listeners عندكم تعلن namespace غير مطابق لمسارها (App\Modules\Notifications\Listeners)،
     * فيحاول اكتشاف الأحداث تحميلها مرة ثانية عند تشغيل أكثر من اختبار فينهار PHP.
     * نعطّل الاكتشاف التلقائي داخل هذا الاختبار فقط (لا يؤثر على التطبيق نفسه).
     */
    public function createApplication()
    {
        \Illuminate\Foundation\Support\Providers\EventServiceProvider::disableEventDiscovery();

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateFile = tempnam(sys_get_temp_dir(), 'perf');
        config(['perf.state_file' => $this->stateFile]);
        $this->flags([]);   // كل الأعلام مفعّلة

        $this->app->bind(StockProvider::class, fn () => new FakeStockProvider());
        app(ProductCacheService::class)->resetStats();

        foreach (['A', 'B', 'C'] as $n) {
            Product::create(['name' => "Product $n", 'price' => 10, 'category' => 'Books']);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->stateFile);
        PerfMode::forget();
        parent::tearDown();
    }

    /** @param array<string,bool> $off أعلام نطفئها */
    private function flags(array $off): void
    {
        $all = array_fill_keys(config('perf.flags'), true);
        PerfMode::save(array_merge($all, array_fill_keys($off, false)));
    }

    public function test_etag_returns_304_when_nothing_changed(): void
    {
        $first = $this->getJson('/api/v1/products');
        $first->assertOk();
        $etag = $first->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->getJson('/api/v1/products', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_etag_changes_after_product_edit(): void
    {
        $etag = $this->getJson('/api/v1/products')->headers->get('ETag');

        Product::first()->update(['name' => 'Edited']);

        $res = $this->getJson('/api/v1/products', ['If-None-Match' => $etag]);
        $res->assertOk();
        $this->assertNotEquals($etag, $res->headers->get('ETag'));
        $this->assertSame('Edited', $res->json('data.0.name'));
    }

    public function test_no_etag_when_flag_is_off(): void
    {
        $this->flags(['etag']);

        $res = $this->getJson('/api/v1/products');
        $res->assertOk();
        $this->assertNull($res->headers->get('ETag'));
    }

    public function test_second_request_is_a_cache_hit(): void
    {
        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/products')->assertOk();

        $stats = app(ProductCacheService::class)->stats();
        $this->assertSame(1, $stats['misses']);
        $this->assertSame(1, $stats['hits']);
    }

    public function test_edit_is_visible_immediately_and_bumps_version(): void
    {
        $this->getJson('/api/v1/products')->assertOk();
        $before = app(ProductCacheService::class)->stats()['version'];

        Product::first()->update(['name' => 'Fresh']);

        $this->assertGreaterThan($before, app(ProductCacheService::class)->stats()['version']);
        $this->getJson('/api/v1/products')->assertJsonPath('data.0.name', 'Fresh');
    }

    public function test_delete_removes_product_from_cache(): void
    {
        $id = Product::first()->id;
        $this->getJson("/api/v1/products/$id")->assertOk();

        Product::find($id)->delete();

        $this->getJson("/api/v1/products/$id")->assertNotFound();
    }

    public function test_cache_is_bypassed_when_flag_is_off(): void
    {
        $this->flags(['cache']);

        $this->getJson('/api/v1/products')->assertOk();
        $this->getJson('/api/v1/products')->assertOk();

        $stats = app(ProductCacheService::class)->stats();
        $this->assertSame(0, $stats['hits'] + $stats['misses']);
    }

    public function test_cached_and_uncached_responses_are_identical(): void
    {
        $this->getJson('/api/v1/products?per_page=2'); // يملأ الكاش
        $cached = $this->getJson('/api/v1/products?per_page=2')->getContent();

        $this->flags(['cache']);
        $plain = $this->getJson('/api/v1/products?per_page=2')->getContent();

        $this->assertSame($plain, $cached);
    }
}
