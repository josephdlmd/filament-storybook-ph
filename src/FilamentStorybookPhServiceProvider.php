<?php

namespace Josephdlmd\FilamentStorybookPh;

use Illuminate\Support\ServiceProvider;
use Josephdlmd\FilamentStorybook\Stories\Documents\SampleDatabase;
use Josephdlmd\FilamentStorybook\StoryRegistry;

/** Layers the Philippine patterns on the core ones: their stories, sample data and rules. */
class FilamentStorybookPhServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The stories, and the rules storybook-instructions gives agents after the core ones.
        $this->app->extend(StoryRegistry::class, fn (StoryRegistry $registry): StoryRegistry => $registry
            ->discover(__DIR__.'/Stories', __NAMESPACE__.'\\Stories')
            ->addRules(__DIR__.'/../docs/rules.md'));

        SampleDatabase::extend([SampleData::class, 'extend']);
    }
}
