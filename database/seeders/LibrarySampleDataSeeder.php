<?php

namespace Database\Seeders;

use App\Models\LibraryCategory;
use App\Models\LibraryResource;
use App\Models\LibrarySubcategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class LibrarySampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $userId = User::first()->id;

        $categories = [
            ['name' => 'መዝሙር', 'description' => 'Sacred hymns and mezmur of the Ethiopian Orthodox Tewahedo Church.', 'display_order' => 1],
            ['name' => 'ቅዳሴ', 'description' => 'Liturgical texts and prayers for Holy Mass and sacraments.', 'display_order' => 2],
            ['name' => 'ትምህርት', 'description' => 'Teachings of the Church Fathers, scripture studies, and spiritual instruction.', 'display_order' => 3],
        ];

        $subcategoriesByCat = [
            'መዝሙር' => ['Traditional Hymns', 'Liturgical Chants', 'Praise Songs'],
            'ቅዳሴ' => ['Holy Mass', 'Baptism & Sacraments', 'Holy Week'],
            'ትምህርት' => ['Church Fathers', 'Scripture Study', 'Fasting & Prayer'],
        ];

        foreach ($categories as $catData) {
            $category = LibraryCategory::create([
                'name' => $catData['name'],
                'description' => $catData['description'],
                'display_order' => $catData['display_order'],
                'status' => 'Active',
                'created_by' => $userId,
            ]);

            foreach ($subcategoriesByCat[$catData['name']] as $i => $subName) {
                LibrarySubcategory::create([
                    'category_id' => $category->id,
                    'name' => $subName,
                    'display_order' => $i + 1,
                    'status' => 'Active',
                    'created_by' => $userId,
                ]);
            }
        }

        $hymnsCat = LibraryCategory::where('name', 'መዝሙር')->first();
        $chantsSub = LibrarySubcategory::where('category_id', $hymnsCat->id)->where('name', 'Liturgical Chants')->first();

        LibraryResource::create([
            'title' => 'Tsome Kidus (Holy Fast)',
            'category_id' => $hymnsCat->id,
            'subcategory_id' => $chantsSub->id,
            'description' => 'A sacred hymn of fasting and repentance, sung during the Holy Lent season.',
            'content' => "<p>The sacred hymn of fasting calls us to turn our hearts toward God.</p>

<p><strong>Tsome Kidus</strong></p>

<p>Tsome kidus, tsome kidus, yesemahim tsome kidus<br>
Begziabher be Tsion, yetesemewu tsome kidus<br>
Yesegnewu tsome kidus, yetesemewu tsome kidus<br>
Begziabher be Tsion, yetesemewu tsome kidus</p>

<p>This hymn, attributed to the tradition of St. Yared, is chanted during the Great Fast (Hudade) in the Ethiopian Orthodox Tewahedo Church. It calls the faithful to holy fasting, repentance, and spiritual renewal.</p>

<h3>The Meaning of Fasting</h3>

<p>Fasting is a cornerstone of spiritual life in the Ethiopian Orthodox Tewahedo Church. With over 250 fasting days in a year, Orthodox Christians dedicate a significant portion of their lives to abstinence and prayer.</p>

<p>The longest fast is Hudade (Lent), lasting 55 days before Easter. Other major fasts include Tsom Filseta (Fast of the Assumption, 15 days), Tsom Kihenet (Fast of the Prophets, 43 days before Christmas), and the Wednesday and Friday weekly fasts.</p>

<p>During fasting periods, believers abstain from all animal products, including meat, dairy, and eggs. Meals consist of vegetables, legumes, and grains. But fasting extends beyond diet—it is a time for increased prayer, almsgiving, repentance, and spiritual reflection.</p>

<p>The church teaches that fasting purifies the soul, strengthens discipline, and draws the believer closer to God. It is seen not as deprivation but as a gift that opens the heart to divine grace.</p>

<h3>The Threefold Discipline</h3>

<p>Drawing from the teachings of the early Church Fathers, Orthodox fasting encompasses three essential disciplines:</p>

<p><strong>1. Fasting of the Body:</strong> Abstinence from certain foods, especially animal products, as a form of self-denial and discipline.</p>

<p><strong>2. Fasting of the Eyes:</strong> Guarding what we look at, avoiding worldly distractions and temptations.</p>

<p><strong>3. Fasting of the Heart:</strong> Forgiving others, letting go of anger, and cultivating love and compassion.</p>

<p>As the hymn declares, the fast that is heard by God is not merely abstaining from food, but turning away from sin and turning toward righteousness.</p>

<h3>Scriptural Foundation</h3>

<p>The practice of fasting is firmly rooted in Holy Scripture. Our Lord Jesus Christ Himself fasted forty days and forty nights in the wilderness before beginning His public ministry (Matthew 4:2). He taught His disciples about fasting, saying, \"When you fast, do not be like the hypocrites, with a sad countenance... But you, when you fast, anoint your head and wash your face, so that you do not appear to men to be fasting, but to your Father who is in the secret place\" (Matthew 6:16-18).</p>

<p>The Prophet Isaiah declares the true fast: \"Is this not the fast that I have chosen: To loose the bonds of wickedness, to undo the heavy burdens, to let the oppressed go free, and that you break every yoke? Is it not to share your bread with the hungry, and that you bring to your house the poor who are cast out?\" (Isaiah 58:6-7).</p>

<p>May the sacred hymn of fasting inspire us to draw nearer to God through prayer, fasting, and almsgiving.</p>",
            'content_am' => "<p>የቅዱስ ጾም መዝሙር ልባችንን ወደ እግዚአብሔር እንድንመልስ ይጠራናል።</p>

<p><strong>ጾመ ቅዱስ</strong></p>

<p>ጾመ ቅዱስ፣ ጾመ ቅዱስ፣ ይሰማህም ጾመ ቅዱስ<br>
በእግዚአብሔር በጽዮን፣ የተሰማው ጾመ ቅዱስ<br>
ይሰግኑ ጾመ ቅዱስ፣ የተሰማው ጾመ ቅዱስ<br>
በእግዚአብሔር በጽዮን፣ የተሰማው ጾመ ቅዱስ</p>

<p>ይህ መዝሙር በቅዱስ ያሬድ ትውፊት የተጠበቀ ሲሆን በታላቁ ጾም (ሁዳዴ) ወቅት በኢትዮጵያ ኦርቶዶክስ ተዋሕዶ ቤተ ክርስቲያን ይዜማል።</p>

<p>እግዚአብሔር የሚቀበለው ጾም ከምግብ መታቀብ ብቻ ሳይሆን ከኃጢአት መራቅና ወደ ጽድቅ መመለስ መሆኑን ያስታውሰናል።</p>",
            'icon' => '🕯️',
            'is_featured' => true,
            'is_active' => true,
            'file_type' => 'inline',
            'file_size_kb' => 0,
            'uploaded_by' => $userId,
        ]);

        $this->command->info('Library sample data seeded successfully!');
        $this->command->info('- ' . count($categories) . ' categories');
        $this->command->info('- 9 subcategories');
        $this->command->info('- 1 resource (Tsome Kidus)');
    }
}
