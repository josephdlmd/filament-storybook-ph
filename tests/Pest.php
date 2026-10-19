<?php

use Josephdlmd\FilamentStorybook\Mcp\TestStories;
use Josephdlmd\FilamentStorybookPh\Tests\Fixtures\User;
use Josephdlmd\FilamentStorybookPh\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;

uses(TestCase::class)
    ->beforeEach(function (): void {
        $this->actingAs(User::create(['name' => 'Tester', 'email' => 'tester@example.com', 'password' => 'secret']));

        // No Vite build: a panel's theme is Filament's compiled one, where testStoriesWithTheme() puts it.
        $this->app->instance(Illuminate\Foundation\Vite::class, new class extends Illuminate\Foundation\Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): Illuminate\Support\HtmlString
            {
                return new Illuminate\Support\HtmlString('<link rel="stylesheet" href="/css/filament/filament/app.css">');
            }
        });
    })
    ->in('Feature');

/** Runs storybook-test under Filament's compiled theme, which apps load; the tests have no published one, and unstyled buttons fail WCAG 2.2's target size. */
function testStoriesWithTheme(array $arguments = []): string
{
    $theme = public_path('css/filament/filament/app.css');
    @mkdir(dirname($theme), recursive: true);
    copy(__DIR__.'/../vendor/filament/filament/dist/theme.css', $theme);

    try {
        return (string) (new TestStories)->handle(new Request($arguments))->content();
    } finally {
        unlink($theme);
    }
}

/** Runs a test with the app's error pages as it sets them, putting back the ones the test app ships (its own 503) after. */
function withErrorPages(Closure $test): void
{
    $pages = resource_path('views/errors');
    $shipped = $pages.'-shipped';
    File::moveDirectory($pages, $shipped);

    try {
        $test();
    } finally {
        File::deleteDirectory($pages);
        File::moveDirectory($shipped, $pages);
    }
}
