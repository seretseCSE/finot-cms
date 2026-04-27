<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing blog posts
        DB::table('blog_posts')->delete();

        $authors = User::where('email', 'like', '%@finot.org')->get();
        if ($authors->count() < 3) {
            $authors = User::limit(3)->get();
        }

        $blogPosts = [
            [
                'title' => 'Welcome to Our Church Community',
                'title_am' => 'እንኳን ወደ ቤተ ክርስቲያናችን በደህና መጡ',
                'content' => 'We are excited to welcome you to our vibrant church community. This blog will serve as a platform to share our faith journey, upcoming events, and inspirational messages that strengthen our spiritual lives together.',
                'content_am' => 'በቤተ ክርስቲያናችን የሚያነቃቂውን ህዝብ እንኳን በደህና መግባችን፡ ይህ ብሎግ አስተዋጽኦችን፣ የሚቀለሉ ፕሮግራሞችን እና እምነታችንን አንድተኛ ለማየት የሚያገለግል መርቀጫ ነው።',
                'tags' => 'welcome, community, faith',
                'featured_image' => 'blog/welcome-church.jpg',
                'status' => 'Published',
                'publish_date' => now()->subDays(30),
                'published_at' => now()->subDays(30),
            ],
            [
                'title' => 'Upcoming Easter Celebrations',
                'title_am' => 'የሚቀገሉት ፋሲካ በዓላት',
                'content' => 'Join us as we prepare to celebrate the resurrection of our Lord Jesus Christ. We have special services planned throughout Holy Week, including prayer vigils, communion services, and a joyous Easter Sunday celebration.',
                'content_am' => 'ጌታችን ክርስቶስ ኢየሱስ ክርስቶስ ከሙታታቸው ተነሥቶ እንዘምት በመሰለም ተቀላቀሉ። በቅዱሳት ሳምንቱ ቀን ውስጥ የሚካለኩ የጸሎት ጊዜያት፣ የቅዳሴ አገልግሎቶች እና በፋሲካ እለት የሚካለኩ የደስታ በዓላት አስተዋጽኦችን አስተካክለናል።',
                'tags' => 'easter, celebration, holy week',
                'featured_image' => 'blog/easter-celebration.jpg',
                'status' => 'Published',
                'publish_date' => now()->subDays(14),
                'published_at' => now()->subDays(14),
            ],
            [
                'title' => 'Youth Ministry New Programs',
                'title_am' => 'የብዙነን ፕሮግራሞች አዲስ ፕሮግራሞች',
                'content' => 'Our youth ministry is launching exciting new programs including Bible study groups, leadership training, and community service projects. These initiatives aim to help our young people grow spiritually and develop valuable life skills.',
                'content_am' => 'የብዙነን ፕሮግራሞች ግብርና በማህደራዊ እንቅስትና የህዝቃል ጥናት ቡድኖች፣ የመሪነት ስልጠና እና የህዝብ አገልግሎት ፕሮጀክቶችን እንጀምት። እነዚህ ተልዕኮች ወጣቶቻችንን በ spiritually እንዲያድጉ እና በእጅድ የሚጠቅሙ የህይወት ክህደቶችን እንዲያውቁ የሚረዱ ናቸ።',
                'tags' => 'youth, ministry, programs, leadership',
                'featured_image' => 'blog/youth-ministry.jpg',
                'status' => 'Published',
                'publish_date' => now()->subDays(7),
                'published_at' => now()->subDays(7),
            ],
            [
                'title' => 'Community Outreach Initiative',
                'title_am' => 'የህዝብ አገልግሎት ጥምር',
                'content' => 'Our church is launching a comprehensive community outreach program to serve the needy in our area. We will be providing food assistance, educational support, and healthcare services to those who need it most.',
                'content_am' => 'ቤተ ክርስቲያናችን በአካባቢው ያሉትን ያስፈላጭ ህዝብ ለመርዳት ውሉድ የህዝብ አገልግሎት ፕሮግራም እናሰርቷል። ምግብር፣ የትምህርት ድጋፍ እና የጤና አገልግሎቶችን ለበጋጋዞቹ ማህደር እንሰጥ።',
                'tags' => 'outreach, community, service, charity',
                'featured_image' => 'blog/community-service.jpg',
                'status' => 'Published',
                'publish_date' => now()->subDays(3),
                'published_at' => now()->subDays(3),
            ],
            [
                'title' => 'Weekly Bible Study Guide',
                'title_am' => 'የሳምንቱ ቀን የመጽሐፍ ቅዱስ ጥናት መመሪያ',
                'content' => 'Join our weekly Bible study sessions every Wednesday evening. This week we will be exploring the Book of Psalms, focusing on finding strength and comfort in God\'s word during challenging times.',
                'content_am' => 'በእያደው ረቡድ ቀን የሚካለኩትን የመጽሐፍ ቅዱስ ጥናት ይሳቡ። በዚህ ሳምንት የመዝሙር መጽሐፍን እንመለማመዳለል፣ በከባቢ ጊዜያት በእግዚአብሔር ቃል ውስጥ ኃይልና ማረፊያ ለመፈለግ ተሰማማን።',
                'tags' => 'bible study, psalms, weekly, spiritual growth',
                'featured_image' => 'blog/bible-study.jpg',
                'status' => 'Published',
                'publish_date' => now()->subDay(),
                'published_at' => now()->subDay(),
            ],
            [
                'title' => 'Church Renovation Update',
                'title_am' => 'የቤተ ክርስቲያና ማሻሻልና ዜና',
                'content' => 'The renovation of our main sanctuary is progressing well. We have completed the roofing work and are now working on the interior decorations. We expect to finish by the end of next month and welcome everyone back to our beautifully restored worship space.',
                'content_am' => 'የዋናችን ቤተ ክርስቲያና ማሻሻልና በደንታ እየሚሄድ ነው። የኡር ሽፍለት ስራ ተጠናቅቆ አሁን የውስጥ ጌጣጦች ላይ እየሰራሁ ነኝ። በወሩት ወር መጨረሻን እንጠብቃለን እና ለሰይሙ የተሻሻለን የአምልጋን ቦታ ሁሉንን እንቃለላለን።',
                'tags' => 'renovation, sanctuary, update, facilities',
                'featured_image' => 'blog/renovation.jpg',
                'status' => 'Published',
                'publish_date' => now(),
                'published_at' => now(),
            ],
        ];

        foreach ($blogPosts as $index => $postData) {
            $author = $authors[$index % $authors->count()];

            BlogPost::create(array_merge($postData, [
                'author_id' => $author->id,
                'slug' => Str::slug($postData['title']),
            ]));
        }

        $this->command->info('6 blog posts seeded successfully!');
    }
}
