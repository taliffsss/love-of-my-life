<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CarouselStore;
use App\Services\LetterStore;
use App\Services\PhotoCatalog;
use App\Services\SeoData;
use App\Services\SongStore;
use App\Services\SpacesFilesystem;
use Naluz\Config\Repository;
use Naluz\Foundation\ServiceProvider;
use Naluz\Storage\StorageManager;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CarouselStore::class, fn () => new CarouselStore(
            storage_path('app/carousel.json'),
            base_path('database/carousel.seed.json'),
        ));
        $this->app->singleton(PhotoCatalog::class, fn ($c) => new PhotoCatalog($c->make(CarouselStore::class), base_path('public/photos')));
        $this->app->singleton(SeoData::class, fn () => new SeoData([
            storage_path('app/carousel.json'),
            storage_path('app/letter.json'),
            storage_path('app/song.json'),
            base_path('database/carousel.seed.json'),
        ]));
        $this->app->singleton(LetterStore::class, fn () => new LetterStore(
            storage_path('app/letter.json'),
            (array) config('site.letter'),
            (string) config('site.first_name'),
        ));
        $this->app->singleton(SongStore::class, fn () => new SongStore(
            storage_path('app/song.json'),
            (array) config('site.song'),
        ));
    }

    public function boot(): void
    {
        $config = $this->app->make(Repository::class);
        $spaces = (array) $config->get('filesystems.disks.spaces', []);

        // The Spaces disk exists only once the credentials are in .env; until then uploads answer 503.
        if (($spaces['key'] ?? '') !== '' && ($spaces['secret'] ?? '') !== '' && ($spaces['bucket'] ?? '') !== '') {
            $this->app->make(StorageManager::class)->extend('spaces', new SpacesFilesystem(
                (string) $spaces['endpoint'],
                (string) $spaces['region'],
                (string) $spaces['bucket'],
                (string) $spaces['key'],
                (string) $spaces['secret'],
                (string) ($spaces['cdn_url'] ?? ''),
            ));
        }
    }
}
