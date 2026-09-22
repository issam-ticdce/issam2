<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Startup;
use App\Models\User;
use App\Notifications\ContentReviewed;
use App\Notifications\ContentSubmitted;
use App\Notifications\NewInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function startup(array $attributes = []): Startup
    {
        return Startup::create($attributes + [
            'name' => 'Test Startup',
            'description' => ['fr' => 'Description publiée'],
            'email' => 'startup@example.com',
            'is_published' => true,
        ]);
    }

    private function product(Startup $startup, array $attributes = []): Product
    {
        return Product::create($attributes + [
            'startup_id' => $startup->id,
            'type' => 'service',
            'name' => ['fr' => 'Mon service', 'ar' => 'خدمتي'],
            'is_published' => true,
        ]);
    }

    public function test_root_redirects_to_a_language(): void
    {
        $this->get('/', ['Accept-Language' => 'de-DE'])->assertRedirect('/fr'); // langue non gérée → français
        $this->get('/', ['Accept-Language' => 'en-GB,en'])->assertRedirect('/en');
        $this->get('/', ['Accept-Language' => 'ar'])->assertRedirect('/ar');
    }

    public function test_public_pages_render_in_three_languages(): void
    {
        $startup = $this->startup();
        $product = $this->product($startup);

        foreach (['fr', 'ar', 'en'] as $locale) {
            $this->get("/$locale")->assertOk();
            $this->get("/$locale/startups")->assertOk()->assertSee('Test Startup');
            $this->get("/$locale/startups/{$startup->slug}")->assertOk();
            $this->get("/$locale/products")->assertOk();
            $this->get("/$locale/products/{$product->slug}")->assertOk();
        }

        $this->get('/ar')->assertSee('dir="rtl"', false);
        $this->get("/ar/products/{$product->slug}")->assertSee('خدمتي');
        $this->get('/de')->assertNotFound();
    }

    public function test_unpublished_content_is_hidden(): void
    {
        $hidden = $this->startup(['name' => 'Cachée', 'is_published' => false]);
        $product = $this->product($hidden);

        $this->get('/fr/startups')->assertDontSee('Cachée');
        $this->get("/fr/startups/{$hidden->slug}")->assertNotFound();
        $this->get("/fr/products/{$product->slug}")->assertNotFound();
    }

    public function test_search_works_with_arabic_text(): void
    {
        $this->product($this->startup());

        $this->get('/ar/products?q='.urlencode('خدمتي'))->assertOk()->assertSee('خدمتي');
    }

    public function test_changes_to_published_content_wait_for_approval(): void
    {
        Notification::fake();
        $startup = $this->startup();
        $member = User::factory()->create(['role' => 'startup', 'startup_id' => $startup->id]);

        $startup->submitForReview(['description' => ['fr' => 'Nouvelle description'], 'is_featured' => true]);

        // La version publique ne change pas, et les champs réservés au TICDCE sont ignorés.
        $this->get("/fr/startups/{$startup->slug}")->assertSee('Description publiée')->assertDontSee('Nouvelle description');
        $this->assertFalse($startup->fresh()->is_featured);
        $this->assertSame('pending', $startup->fresh()->review_status);
        Notification::assertSentOnDemand(ContentSubmitted::class);

        // L'aperçu est réservé aux membres de la startup et au TICDCE.
        $this->get("/apercu/startups/{$startup->slug}")->assertForbidden();
        $this->actingAs($member)->get("/apercu/startups/{$startup->slug}")->assertOk()->assertSee('Nouvelle description');

        $startup->fresh()->approve();

        $this->get("/fr/startups/{$startup->slug}")->assertSee('Nouvelle description');
        Notification::assertSentTo($member, ContentReviewed::class);
    }

    public function test_rejection_keeps_public_version(): void
    {
        Notification::fake();
        $startup = $this->startup();
        $startup->submitForReview(['description' => ['fr' => 'Proposition']]);
        $startup->reject('Merci de préciser');

        $startup->refresh();
        $this->assertSame('rejected', $startup->statusKey());
        $this->assertSame('Merci de préciser', $startup->rejection_reason);
        $this->assertSame('Description publiée', $startup->tr('description'));
    }

    public function test_visitor_can_send_an_inquiry(): void
    {
        Notification::fake();
        $startup = $this->startup();
        $product = $this->product($startup);

        $this->post("/fr/startups/{$startup->slug}/inquiries", [
            'type' => 'quote',
            'product_id' => $product->id,
            'name' => 'Client',
            'email' => 'client@example.com',
            'message' => 'Bonjour, je voudrais un devis.',
        ])->assertRedirect()->assertSessionHas('inquiry_sent');

        $this->assertDatabaseHas('inquiries', ['startup_id' => $startup->id, 'product_id' => $product->id, 'type' => 'quote']);
        Notification::assertSentOnDemand(NewInquiry::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'startup@example.com');
    }

    public function test_inquiry_rejects_bots_and_foreign_products(): void
    {
        $startup = $this->startup();
        $other = $this->product($this->startup(['name' => 'Autre']));
        $valid = ['type' => 'contact', 'name' => 'A', 'email' => 'a@example.com', 'message' => 'Un message assez long.'];

        $this->post("/fr/startups/{$startup->slug}/inquiries", $valid + ['website' => 'spam'])->assertSessionHasErrors('website');
        $this->post("/fr/startups/{$startup->slug}/inquiries", $valid + ['product_id' => $other->id])->assertSessionHasErrors('product_id');
        $this->assertSame(0, Inquiry::count());
    }

    public function test_panels_are_restricted_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'startup', 'startup_id' => $this->startup()->id]);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($member)->get('/admin')->assertForbidden();
        $this->actingAs($member)->get('/espace')->assertOk();
    }

    public function test_startup_cannot_open_another_startups_product(): void
    {
        $mine = $this->startup();
        $member = User::factory()->create(['role' => 'startup', 'startup_id' => $mine->id]);
        $foreign = $this->product($this->startup(['name' => 'Concurrent']));
        $own = $this->product($mine);

        $this->actingAs($member)->get("/espace/produits/{$own->slug}/edit")->assertOk();
        $this->actingAs($member)->get("/espace/produits/{$foreign->slug}/edit")->assertNotFound();
        $this->actingAs($member)->get("/espace/ma-startup/{$foreign->startup->slug}")->assertNotFound();
    }
}
