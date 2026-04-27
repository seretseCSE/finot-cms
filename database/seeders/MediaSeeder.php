<?php

namespace Database\Seeders;

use App\Models\MediaCategory;
use App\Models\MediaItem;
use App\Models\MediaSubcategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing media data
        DB::table('media_items')->delete();
        DB::table('media_subcategories')->delete();
        DB::table('media_categories')->delete();

        $creator = User::first();

        // Create media categories
        $categories = [
            ['name' => 'Sermons', 'description' => 'Recorded sermons and messages from our services', 'display_order' => 1],
            ['name' => 'Music', 'description' => 'Choir performances, hymns, and worship music', 'display_order' => 2],
            ['name' => 'Teachings', 'description' => 'Bible studies, teachings, and educational content', 'display_order' => 3],
            ['name' => 'Events', 'description' => 'Recordings of church events and special occasions', 'display_order' => 4],
            ['name' => 'Testimonies', 'description' => 'Personal testimonies and faith stories', 'display_order' => 5],
        ];

        foreach ($categories as $categoryData) {
            MediaCategory::create(array_merge($categoryData, [
                'created_by' => $creator->id,
            ]));
        }

        // Create media subcategories
        $sermonsCategory = MediaCategory::where('name', 'Sermons')->first();
        $musicCategory = MediaCategory::where('name', 'Music')->first();
        $teachingsCategory = MediaCategory::where('name', 'Teachings')->first();

        $subcategories = [
            ['category_id' => $sermonsCategory->id, 'name' => 'Sunday Services', 'created_by' => $creator->id],
            ['category_id' => $sermonsCategory->id, 'name' => 'Special Services', 'created_by' => $creator->id],
            ['category_id' => $musicCategory->id, 'name' => 'Choir Performances', 'created_by' => $creator->id],
            ['category_id' => $musicCategory->id, 'name' => 'Worship Songs', 'created_by' => $creator->id],
            ['category_id' => $teachingsCategory->id, 'name' => 'Bible Studies', 'created_by' => $creator->id],
            ['category_id' => $teachingsCategory->id, 'name' => 'Youth Teachings', 'created_by' => $creator->id],
        ];

        foreach ($subcategories as $subcategoryData) {
            MediaSubcategory::create($subcategoryData);
        }

        // Create media items
        $sundayServices = MediaSubcategory::where('name', 'Sunday Services')->first();
        $choirPerformances = MediaSubcategory::where('name', 'Choir Performances')->first();
        $bibleStudies = MediaSubcategory::where('name', 'Bible Studies')->first();

        $mediaItems = [
            [
                'title' => 'Sunday Service - Faith in Difficult Times',
                'description' => 'A powerful message about maintaining faith during life\'s challenges',
                'type' => 'Video',
                'file_path' => 'media/sermons/faith-difficult-times.mp4',
                'file_size_kb' => 54200,
                'category_id' => $sermonsCategory->id,
                'subcategory_id' => $sundayServices->id,
                'visibility' => 'Public',
                'tags' => 'sermon, faith, sunday',
            ],
            [
                'title' => 'Sunday Service - The Power of Prayer',
                'description' => 'Understanding the transformative power of consistent prayer',
                'type' => 'Video',
                'file_path' => 'media/sermons/power-of-prayer.mp4',
                'file_size_kb' => 45800,
                'category_id' => $sermonsCategory->id,
                'subcategory_id' => $sundayServices->id,
                'visibility' => 'Public',
                'tags' => 'sermon, prayer, sunday',
            ],
            [
                'title' => 'Easter Choir Performance',
                'description' => 'Choir performance during Easter Sunday service',
                'type' => 'Video',
                'file_path' => 'media/music/easter-choir.mp4',
                'file_size_kb' => 156300,
                'category_id' => $musicCategory->id,
                'subcategory_id' => $choirPerformances->id,
                'visibility' => 'Public',
                'tags' => 'choir, easter, music',
            ],
            [
                'title' => 'Bible Study - Book of Psalms',
                'description' => 'Weekly Bible study focusing on selected Psalms',
                'type' => 'Video',
                'file_path' => 'media/teachings/psalms-study.mp4',
                'file_size_kb' => 62400,
                'category_id' => $teachingsCategory->id,
                'subcategory_id' => $bibleStudies->id,
                'visibility' => 'Public',
                'tags' => 'bible study, psalms, teaching',
            ],
            [
                'title' => 'Christmas Service Highlights',
                'description' => 'Highlights from our Christmas Eve service',
                'type' => 'Video',
                'file_path' => 'media/events/christmas-highlights.mp4',
                'file_size_kb' => 345700,
                'category_id' => MediaCategory::where('name', 'Events')->first()->id,
                'visibility' => 'Public',
                'tags' => 'christmas, service, celebration',
            ],
        ];

        foreach ($mediaItems as $mediaData) {
            MediaItem::create(array_merge($mediaData, [
                'uploaded_by' => $creator->id,
            ]));
        }

        $this->command->info('5 media categories, 6 subcategories, and 5 media items seeded successfully!');
    }
}
