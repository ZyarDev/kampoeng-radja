<?php

namespace Tests\Feature;

use App\Models\GaleriEvent;
use App\Models\GaleriEventFoto;
use App\Models\MediaBerita;
use App\Models\User;
use App\Models\Wahana;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_routes_are_available(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Home'));
        $this->get('/tentang-kami')->assertInertia(fn (Assert $page) => $page->component('TentangKami'));
        $this->get('/wahana')->assertInertia(fn (Assert $page) => $page->component('Wahana'));
        $this->get('/galeri-event')->assertInertia(fn (Assert $page) => $page->component('GaleriEvent'));
        $this->get('/media-berita')->assertInertia(fn (Assert $page) => $page->component('Berita'));
    }

    public function test_wahana_page_exposes_comma_separated_labels_for_client_side_filtering(): void
    {
        $user = User::factory()->create();
        Wahana::create([
            'created_by' => $user->id,
            'nama_wahana' => 'Contoh data pengujian',
            'deskripsi_singkat' => 'Deskripsi pengujian',
            'foto' => '/example.jpg',
            'label' => 'Air, Anak-anak',
            'is_unggulan' => false,
        ]);

        $this->get('/wahana')->assertInertia(fn (Assert $page) => $page
            ->component('Wahana')
            ->has('categories.0.labels', 2)
            ->has('photos', 1)
            ->where('photos.0.labels.0.slug', 'air')
            ->where('photos.0.cover_url', '/storage/example.jpg')
            ->where('photos.0.photos.0.url', '/storage/example.jpg')
            ->missing('photos.0.photo_path'));
    }

    public function test_home_exposes_media_photo_as_a_single_public_storage_url(): void
    {
        $user = User::factory()->create();
        MediaBerita::create([
            'created_by' => $user->id,
            'judul' => 'Berita Pengujian',
            'deskripsi' => 'Deskripsi berita pengujian.',
            'foto' => 'media-berita/contoh.jpg',
            'tanggal_publish' => '2026-08-12 10:00:00',
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('news', 1)
            ->where('news.0.foto_url', '/storage/media-berita/contoh.jpg')
            ->missing('news.0.foto')
            ->missing('news.0.image'));
    }

    public function test_public_media_page_exposes_cms_articles_newest_first(): void
    {
        $user = User::factory()->create();
        MediaBerita::create([
            'created_by' => $user->id,
            'judul' => 'Berita Lama',
            'deskripsi' => 'Deskripsi berita lama.',
            'foto' => 'media-berita/lama.jpg',
            'tanggal_publish' => '2026-08-10 10:00:00',
        ]);
        MediaBerita::create([
            'created_by' => $user->id,
            'judul' => 'Berita Terbaru',
            'deskripsi' => 'Deskripsi berita terbaru.',
            'foto' => 'media-berita/terbaru.jpg',
            'tanggal_publish' => '2026-08-12 10:00:00',
        ]);

        $this->get(route('berita'))->assertInertia(fn (Assert $page) => $page
            ->component('Berita')
            ->has('articles', 2)
            ->where('articles.0.title', 'Berita Terbaru')
            ->where('articles.0.description', 'Deskripsi berita terbaru.')
            ->where('articles.0.foto_url', '/storage/media-berita/terbaru.jpg')
            ->where('articles.0.tanggal_publish', '2026-08-12T10:00:00+00:00')
            ->where('articles.1.title', 'Berita Lama')
            ->missing('articles.0.category')
            ->missing('articles.0.image'));
    }

    public function test_gallery_events_are_exposed_with_their_photos_for_sorting(): void
    {
        $user = User::factory()->create();
        $event = GaleriEvent::create([
            'created_by' => $user->id,
            'nama_event' => 'Contoh data pengujian',
            'deskripsi' => 'Deskripsi pengujian',
            'tanggal_event' => now()->toDateString(),
        ]);
        GaleriEventFoto::create([
            'galeri_event_id' => $event->id,
            'created_by' => $user->id,
            'foto' => '/event.jpg',
            'caption' => 'Foto urutan kedua',
            'urutan' => 2,
        ]);
        GaleriEventFoto::create([
            'galeri_event_id' => $event->id,
            'created_by' => $user->id,
            'foto' => '/event-featured.jpg',
            'caption' => 'Foto utama',
            'urutan' => 1,
        ]);

        $this->get('/galeri-event')->assertInertia(fn (Assert $page) => $page
            ->component('GaleriEvent')
            ->has('events', 1)
            ->has('events.0.photos', 2)
            ->where('events.0.photos.0.url', '/storage/event-featured.jpg')
            ->where('events.0.photos.0.caption', 'Foto utama')
            ->where('events.0.photos.0.urutan', 1)
            ->where('events.0.photos.1.url', '/storage/event.jpg')
            ->missing('events.0.photos.0.photo_path'));
    }
}
