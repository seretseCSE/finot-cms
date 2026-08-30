<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\Event;
use App\Models\Song;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the public sitemap.xml for search engines';

    public function handle(): int
    {
        $this->info('Generating sitemap...');

        $sitemap = Sitemap::create();

        $sitemap->add(
            Url::create('/')
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0)
        );

        foreach (['about', 'contact', 'news', 'blog', 'events', 'songs', 'media', 'courses', 'library', 'fundraising', 'tours', 'shop'] as $page) {
            $sitemap->add(
                Url::create("/{$page}")
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.8)
            );
        }

        BlogPost::where('status', 'Published')
            ->orderByDesc('published_at')
            ->each(function (BlogPost $post) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('blog.show', $post->slug, false))
                        ->setLastModificationDate($post->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.7)
                );
            });

        Event::where('status', 'Published')
            ->orderByDesc('date_time')
            ->each(function (Event $event) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('events.show', $event, false))
                        ->setLastModificationDate($event->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                        ->setPriority(0.7)
                );
            });

        Announcement::where('is_active', true)
            ->orderByDesc('created_at')
            ->each(function (Announcement $item) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('announcements.show', $item->id, false))
                        ->setLastModificationDate($item->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.6)
                );
            });

        Song::orderByDesc('updated_at')
            ->each(function (Song $song) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('songs.show', $song->id, false))
                        ->setLastModificationDate($song->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.5)
                );
            });

        Course::where('status', 'Published')
            ->each(function (Course $course) use ($sitemap) {
                $sitemap->add(
                    Url::create(route('courses.show', $course->id, false))
                        ->setLastModificationDate($course->updated_at)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                        ->setPriority(0.6)
                );
            });

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated at public/sitemap.xml');

        return self::SUCCESS;
    }
}
