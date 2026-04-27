<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Page::query()->insertOrIgnore([
            [
                'slug' => 'about',
                'title' => 'About Us',
                'title_am' => null,
                'content' => $this->aboutContent(),
                'content_am' => null,
                'status' => 'Published',
                'meta_description' => 'Learn about our organization, our mission, and our values.',
                'meta_description_am' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function aboutContent(): string
    {
        return <<<'HTML'
<p>Welcome to our organization. We are dedicated to serving our community through various programs and initiatives.</p>
<h2>Our Mission</h2>
<p>Our mission is to provide spiritual guidance, education, and support to our community members, fostering growth and development in all aspects of life.</p>
<h2>Our Vision</h2>
<p>We envision a community where every individual has the opportunity to grow spiritually, intellectually, and socially, contributing to the betterment of society.</p>
<h2>Our Values</h2>
<ul>
<li>Faith and spiritual growth</li>
<li>Education and continuous learning</li>
<li>Community service and outreach</li>
<li>Integrity and transparency</li>
<li>Respect and inclusivity</li>
</ul>
HTML;
    }
}
