<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\MediaCategory;
use App\Models\MediaItem;
use App\Models\Song;
use App\Models\SongCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EthiopianOrthodoxSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user for created_by
        $adminUser = User::where('email', 'admin@finotetsidik.org')->first();
        $userId = $adminUser ? $adminUser->id : User::first()->id;
        // ========== SONG CATEGORIES ==========
        $songCategories = [
            ['name' => 'Orthodox Hymns', 'description' => 'Traditional Ethiopian Orthodox Tewahedo Church hymns and mezmur.'],
            ['name' => 'Liturgical Chants', 'description' => 'Chants sung during Holy Mass and liturgical services.'],
            ['name' => 'Praise & Worship', 'description' => 'Contemporary praise songs inspired by Orthodox faith.'],
            ['name' => 'Holiday Mezmur', 'description' => 'Songs for major Orthodox feast days and holidays.'],
        ];

        $songCategoryIds = [];
        foreach ($songCategories as $cat) {
            $songCategoryIds[] = SongCategory::firstOrCreate(
                ['name' => $cat['name']],
                array_merge($cat, ['display_order' => 1, 'status' => 'Active', 'created_by' => $userId])
            )->id;
        }

        // ========== MEDIA CATEGORIES ==========
        $mediaCategories = [
            ['name' => 'Church Events', 'description' => 'Photos and videos from church events and gatherings.'],
            ['name' => 'Liturgy & Services', 'description' => 'Moments from Holy Mass, baptisms, and liturgical celebrations.'],
            ['name' => 'Sunday School', 'description' => 'Educational activities, children programs, and youth gatherings.'],
            ['name' => 'Pilgrimage & Tours', 'description' => 'Church pilgrimages, monastery visits, and spiritual tours.'],
        ];

        $mediaCategoryIds = [];
        foreach ($mediaCategories as $cat) {
            $mediaCategoryIds[] = MediaCategory::firstOrCreate(
                ['name' => $cat['name']],
                array_merge($cat, ['display_order' => 1, 'status' => 'Active', 'created_by' => $userId])
            )->id;
        }

        // ========== BLOG POSTS ==========
        $blogPosts = [
            [
                'title' => 'The Feast of Timket (Epiphany): A Celebration of Baptism',
                'content' => '<p>The Feast of Timket, also known as Epiphany, is one of the most significant and colorful celebrations in the Ethiopian Orthodox Tewahedo Church. Held annually on January 19 (or 20 in leap years), Timket commemorates the baptism of Jesus Christ in the Jordan River by John the Baptist.</p><p>Preparations begin days in advance, with the ark of the covenant (Tabot) being carried from churches to open-air spaces near bodies of water. The clergy, dressed in elaborate ceremonial robes, lead processions with song, prayer, and the rhythmic beating of drums.</p><p>On the morning of Timket, the faithful gather before dawn for the Kidase (Liturgy). Following the service, the priest blesses the water with a golden cross, symbolizing the baptism of Christ. The crowd then splashes the blessed water upon themselves, renewing their baptismal vows.</p><p>In the afternoon, the Tabot is returned to the church in a joyous procession. The celebration continues with traditional food, dance, and community fellowship.</p>',
                'tags' => 'Timket,Epiphany,Baptism,Holiday',
                'published_at' => now()->subMonths(3),
            ],
            [
                'title' => 'Meskel: Finding the True Cross',
                'content' => '<p>Meskel, meaning "Cross" in Ge\'ez, is a major Ethiopian Orthodox feast celebrated on September 27. It commemorates the discovery of the True Cross by Queen Helena (Eleni) in the fourth century.</p><p>According to tradition, Queen Helena was guided by smoke rising from the ground to the burial site of the cross upon which Jesus was crucified. This miracle is reenacted today through the building of large bonfires called "Demera."</p><p>On the eve of Meskel, communities gather in open squares adorned with yellow Meskel daisies. The Demera is lit as priests chant hymns and the faithful pray. The direction in which the central pole falls is believed to predict the fortune of the coming year.</p><p>The following morning, the ashes are used to mark crosses on the foreheads of believers, symbolizing their faith and devotion.</p>',
                'tags' => 'Meskel,True Cross,Queen Helena,Demera',
                'published_at' => now()->subMonths(5),
            ],
            [
                'title' => 'Fasika (Easter): The Triumph of Resurrection',
                'content' => '<p>Fasika, the Ethiopian Orthodox Easter, is the most important religious holiday in the church calendar. Following a rigorous 55-day Lenten fast (Hudade), the resurrection of Jesus Christ is celebrated with immense joy and spiritual fervor.</p><p>The fast is one of the longest and strictest in Christianity, where believers abstain from all animal products and maintain a vegan diet. The final week, known as Holy Week (Himamat), involves daily services recounting the passion, crucifixion, and burial of Christ.</p><p>On Easter eve, the faithful gather in churches for an all-night vigil. At dawn, the priest announces "Christ is risen!" and the congregation responds with jubilant song and dance. The fast is broken with a feast featuring traditional dishes like Doro Wat (spicy chicken stew).</p><p>Fasika is not merely a celebration but a profound spiritual renewal for millions of Ethiopian Orthodox Christians.</p>',
                'tags' => 'Fasika,Easter,Resurrection,Lent',
                'published_at' => now()->subMonths(1),
            ],
            [
                'title' => 'Enkutatash: Ethiopian New Year',
                'content' => '<p>Enkutatash, which means "gift of jewels," marks the Ethiopian New Year on September 11 (or 12 in leap years). The holiday has both religious and historical significance, tracing back to the Queen of Sheba.</p><p>According to legend, when the Queen of Sheba returned from her visit to King Solomon, her chiefs welcomed her back with gifts of jewels (enkutatash). Today, the celebration blends ancient tradition with the religious meaning of the Feast of John the Baptist.</p><p>Children dressed in new clothes go from house to house singing New Year songs and receiving bread or money. Families gather to share traditional meals and pray for blessings in the new year.</p><p>The season is marked by the blooming of the yellow Meskel daisy, symbolizing renewal and hope across the Ethiopian highlands.</p>',
                'tags' => 'Enkutatash,New Year,Queen of Sheba,Renewal',
                'published_at' => now()->subMonths(7),
            ],
            [
                'title' => 'The Spiritual Significance of Ethiopian Orthodox Fasting',
                'content' => '<p>Fasting is a cornerstone of spiritual life in the Ethiopian Orthodox Tewahedo Church. With over 250 fasting days in a year, Orthodox Christians dedicate a significant portion of their lives to abstinence and prayer.</p><p>The longest fast is Hudade (Lent), lasting 55 days before Easter. Other major fasts include Tsom Filseta (Fast of the Assumption, 15 days), Tsom Kihenet (Fast of the Prophets, 43 days before Christmas), and the Wednesday and Friday weekly fasts.</p><p>During fasting periods, believers abstain from all animal products, including meat, dairy, and eggs. Meals consist of vegetables, legumes, and grains. But fasting extends beyond diet\u2014it is a time for increased prayer, almsgiving, repentance, and spiritual reflection.</p><p>The church teaches that fasting purifies the soul, strengthens discipline, and draws the believer closer to God. It is seen not as deprivation but as a gift that opens the heart to divine grace.</p>',
                'tags' => 'Fasting,Hudade,Spiritual Life,Prayer',
                'published_at' => now()->subWeeks(2),
            ],
            [
                'title' => 'Lalibela: The Eighth Wonder of the World',
                'content' => '<p>The rock-hewn churches of Lalibela stand as one of the most extraordinary architectural and spiritual achievements in human history. Carved entirely from solid rock in the 12th century, these eleven churches are often called the "Eighth Wonder of the World."</p><p>King Lalibela, after whom the town is named, is said to have been instructed by angels to build a "New Jerusalem" for Ethiopian Christians who could not make the pilgrimage to the Holy Land. The churches were carved from top to bottom, directly into the mountainside.</p><p>The most famous is Biete Ghiorgis (Church of Saint George), a perfectly cross-shaped structure carved 15 meters deep into the ground. Pilgrims visit Lalibela year-round, but especially during Genna (Ethiopian Christmas) when thousands gather for night-long prayers and ceremonies.</p><p>In 1978, Lalibela was designated a UNESCO World Heritage Site, preserving this sacred place for future generations.</p>',
                'tags' => 'Lalibela,Churches,Pilgrimage,Heritage',
                'published_at' => now()->subMonths(4),
            ],
            [
                'title' => 'The Ark of the Covenant in Ethiopia',
                'content' => '<p>One of the most fascinating claims in religious history is the Ethiopian Orthodox Church\'s tradition that the original Ark of the Covenant\u2014the sacred container holding the Ten Commandments\u2014resides in the Chapel of the Tablet at the Church of Our Lady Mary of Zion in Axum.</p><p>According to the Kebre Negest (Glory of Kings), Menelik I, son of King Solomon and the Queen of Sheba, brought the Ark to Ethiopia for safekeeping. For centuries, it has been guarded by a single monk who serves as its lifelong protector.</p><p>No one except the guardian is permitted to see the Ark. It is kept in a small chapel built adjacent to the main church and is brought out only on the most sacred occasions, covered in rich cloths.</p><p>Whether one believes in the historical claim or not, the Ark\'s presence in Ethiopian tradition has shaped the identity, faith, and culture of the nation for millennia.</p>',
                'tags' => 'Ark of the Covenant,Axum,History,Faith',
                'published_at' => now()->subMonths(6),
            ],
            [
                'title' => 'Sunday School: Nurturing the Next Generation',
                'content' => '<p>The Ethiopian Orthodox Sunday School (Senbet Tirgu) plays a vital role in passing down the faith, language, and cultural heritage to the next generation. Every Sunday, children and youth gather to learn Ge\'ez, church history, biblical stories, and Orthodox teachings.</p><p>Sunday School is not merely religious instruction; it is a space where young people learn about their identity. Classes are divided by age groups, with older students studying advanced theology, church music (Zema), and liturgical practices.</p><p>The curriculum emphasizes memorization of prayers, learning the church calendar, understanding the lives of the saints, and developing moral character. Many of today\'s church leaders, deacons, and scholars began their journey in Sunday School.</p><p>As the church faces modern challenges, Sunday School remains a beacon of hope, ensuring that the ancient faith continues to thrive in the hearts of the young.</p>',
                'tags' => 'Sunday School,Education,Youth,Heritage',
                'published_at' => now()->subWeeks(3),
            ],
            [
                'title' => 'Genna: Ethiopian Christmas',
                'content' => '<p>Genna, the Ethiopian Christmas, is celebrated on January 7, following the ancient Julian calendar. Unlike the commercialized Christmas of the West, Genna is a deeply spiritual occasion marked by prayer, fasting, and communal worship.</p><p>The night before Genna, believers attend church for a long vigil that lasts until dawn. Dressed in traditional white shammas, the faithful enter churches for the Christmas Liturgy (Kidase). The atmosphere is one of reverence and joy as the birth of Christ is proclaimed.</p><p>Following the service, the 40-day fast of the Prophets (Tsom Kihenet) is broken. Families return home to share a meal of Genna bread (a thick, spicy bread) and traditional stew. In rural areas, young men play a traditional hockey-like game also called Genna, using wooden sticks and a ball.</p><p>The celebration extends for several days, with continued church services, visiting relatives, and acts of charity for the poor.</p>',
                'tags' => 'Genna,Christmas,Nativity,Holiday',
                'published_at' => now()->subMonths(8),
            ],
        ];

        foreach ($blogPosts as $post) {
            BlogPost::firstOrCreate(
                ['title' => $post['title']],
                array_merge($post, [
                    'slug' => Str::slug($post['title']),
                    'author_id' => $userId,
                    'publish_date' => $post['published_at']->format('Y-m-d'),
                    'status' => 'Published',
                    'featured_image' => null,
                ])
            );
        }

        // ========== SONGS ==========
        $songs = [
            [
                'title' => 'Tsome Kidus (Holy Fast)',
                'lyrics' => '<p>Tsome kidus, some kidus, yesemahim tsome kidus</p><p>Begziabher be Tsion, yetesemewu tsome kidus</p><p>Yesegnewu tsome kidus, yetesemewu tsome kidus</p><p>Begziabher be Tsion, yetesemewu tsome kidus</p>',
                'artist' => 'St. Yared Choir',
                'category_id' => $songCategoryIds[1],
                'audio_file' => 'tsome_kidus.mp3',
            ],
            [
                'title' => 'Kidus Kidus (Holy Holy Holy)',
                'lyrics' => '<p>Kidus, Kidus, Kidus, Wegegnua Amlak</p><p>Amlak Aba, Amlak Wold, Amlak Menfes Kidus</p><p>Be sema wey be medr, yesemamewu amlak</p><p>Kidus, Kidus, Kidus, Wegegnua Amlak</p>',
                'artist' => 'Debre Libanos Choir',
                'category_id' => $songCategoryIds[0],
                'audio_file' => 'kidus_kidus.mp3',
                'video_url' => 'https://www.youtube.com/watch?v=kidus_kidus_example',
            ],
            [
                'title' => 'Meskel Demera Song',
                'lyrics' => '<p>Demera, demera, enkuan demerachin new</p><p>Be Eleni negn, begize meskel new</p><p>Enkuan aderesachu, enkuan meskelachu</p><p>Be Eleni negn, begize meskel new</p>',
                'artist' => 'Ethiopian Orthodox Mezmur Group',
                'category_id' => $songCategoryIds[3],
                'audio_file' => 'meskel_demera.mp3',
            ],
            [
                'title' => 'Aba Amanuel (Father Emmanuel)',
                'lyrics' => '<p>Aba Amanuel, aba Amanuel</p><p>Yetebeku amlak, aba Amanuel</p><p>Betesebochen yastenagual, betesebochen yastenagual</p><p>Yetebeku amlak, aba Amanuel</p>',
                'artist' => 'Abyssinian Orthodox Voices',
                'category_id' => $songCategoryIds[2],
                'audio_file' => 'aba_amanuel.mp3',
            ],
            [
                'title' => 'Timket Hymn (Ketera)',
                'lyrics' => '<p>Ketera, ketera, yesegnewu ketera</p><p>Be Gondar be Addis, yetesemewu timket</p><p>Yesus be Yordanos, yetebekewu timket</p><p>Ketera, ketera, yesegnewu ketera</p>',
                'artist' => 'Gondar Church Choir',
                'category_id' => $songCategoryIds[3],
                'video_url' => 'https://www.youtube.com/watch?v=timket_ketera_example',
            ],
            [
                'title' => 'Eyesus Kristos (Jesus Christ)',
                'lyrics' => '<p>Eyesus Kristos, leul segne</p><p>Yemesgen beteseb, yemesgen beamlak</p><p>Eyesus Kristos, leul segne</p><p>Yemaytekem yetekem, yemaytekem yetekem</p>',
                'artist' => 'Holy Trinity Cathedral Choir',
                'category_id' => $songCategoryIds[0],
                'audio_file' => 'eyesus_kristos.mp3',
            ],
            [
                'title' => 'Fasika Mezmur (Easter Song)',
                'lyrics' => '<p>Tensu tensu, Eyesus tensu</p><p>Ke motu tensu, ke gehenem tensu</p><p>Tensu tensu, Eyesus tensu</p><p>Yegna fasika, yegna tensu</p>',
                'artist' => 'Easter Celebration Choir',
                'category_id' => $songCategoryIds[3],
                'audio_file' => 'fasika_mezmur.mp3',
                'video_url' => 'https://www.youtube.com/watch?v=fasika_mezmur_example',
            ],
            [
                'title' => 'Amlak Ameskalu (God of Patience)',
                'lyrics' => '<p>Amlak ameskalu, amlak fikir new</p><p>Yetebekewu amlak, amlak ameskalu</p><p>Be tsom be wedase, yesemamewu amlak</p><p>Amlak ameskalu, amlak fikir new</p>',
                'artist' => 'St. Mary Church Choir',
                'category_id' => $songCategoryIds[2],
                'audio_file' => 'amlak_ameskalu.mp3',
            ],
        ];

        foreach ($songs as $index => $song) {
            Song::firstOrCreate(
                ['title' => $song['title']],
                array_merge($song, [
                    'song_code' => 'SNG-'.str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                    'lyrics' => $song['lyrics'],
                    'subcategory_id' => null,
                    'is_active' => true,
                    'created_by' => $userId,
                ])
            );
        }

        // ========== MEDIA ITEMS ==========
        $mediaItems = [
            [
                'title' => 'Timket Blessing Ceremony at Jan Meda',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[0],
                'description' => 'The blessed water being sprinkled on the faithful during the Timket (Epiphany) celebration at Jan Meda, Addis Ababa. Thousands gather to renew their baptism.',
                'file_path' => 'media/photos/timket_jan_meda.jpg',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Meskel Demera Bonfire',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[0],
                'description' => 'The great Demera bonfire burning bright in Meskel Square as clergy and faithful gather to celebrate the Finding of the True Cross.',
                'file_path' => 'media/photos/meskel_demera.jpg',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Fasika Midnight Prayer',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[1],
                'description' => 'Faithful gathered inside an ancient rock-hewn church for the all-night Easter vigil, candles illuminating the sacred space.',
                'file_path' => 'media/photos/fasika_vigil.jpg',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Palm Sunday Procession',
                'type' => 'Video',
                'category_id' => $mediaCategoryIds[1],
                'description' => 'The grand Hosanna (Palm Sunday) procession through the streets of Axum, with clergy carrying palm fronds and singing hymns of praise.',
                'file_path' => 'media/videos/palm_sunday_procession.mp4',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Sunday School Graduation',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[2],
                'description' => 'Young graduates of the Sunday School program receiving certificates after completing their study of Ge\'ez and church history.',
                'file_path' => 'media/photos/sunday_school_graduation.jpg',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Lalibela Christmas Pilgrimage',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[3],
                'description' => 'Thousands of white-robed pilgrims gathered at Biete Ghiorgis during Genna (Ethiopian Christmas) for prayer and celebration.',
                'file_path' => 'media/photos/lalibella_genna.jpg',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Holy Trinity Cathedral Service',
                'type' => 'Video',
                'category_id' => $mediaCategoryIds[1],
                'description' => 'A beautiful recording of the Sunday morning Liturgy at Holy Trinity Cathedral, featuring the renowned church choir and traditional chanting.',
                'file_path' => 'media/videos/holy_trinity_service.mp4',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Youth Choir Rehearsal',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[2],
                'description' => 'The youth choir of St. Mary Church practicing traditional Zema (chant) under the guidance of an experienced deacon.',
                'file_path' => 'media/photos/youth_choir_rehearsal.jpg',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Axum Stelae Field',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[3],
                'description' => 'The ancient obelisks of Axum standing tall against the Ethiopian sky, a testament to the deep history of Christianity in the land.',
                'file_path' => 'media/photos/axum_stelae.jpg',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Timket Procession Video',
                'type' => 'Video',
                'category_id' => $mediaCategoryIds[0],
                'description' => 'Aerial footage of the massive Timket procession moving through the streets, with the Tabot covered in rich fabrics carried on the heads of priests.',
                'file_path' => 'media/videos/timket_procession.mp4',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Baptism Ceremony',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[1],
                'description' => 'An infant being baptized in the traditional Ethiopian Orthodox manner, with the priest performing the triple immersion in the name of the Trinity.',
                'file_path' => 'media/photos/baptism_ceremony.jpg',
                'visibility' => 'Public',
            ],
            [
                'title' => 'Enkutatash Celebration',
                'type' => 'Photo',
                'category_id' => $mediaCategoryIds[0],
                'description' => 'Children in traditional Ethiopian dress singing Enkutatash songs and receiving gifts, surrounded by the yellow blooms of the Meskel daisy.',
                'file_path' => 'media/photos/enkutatash_children.jpg',
                'visibility' => 'Public',
            ],
        ];

        foreach ($mediaItems as $item) {
            MediaItem::firstOrCreate(
                ['title' => $item['title']],
                array_merge($item, [
                    'subcategory_id' => null,
                    'file_size_kb' => $item['type'] === 'Photo' ? rand(500, 3000) : rand(5000, 25000),
                    'uploaded_by' => $userId,
                ])
            );
        }

        $this->command->info('Ethiopian Orthodox sample data seeded successfully!');
        $this->command->info('- '.count($blogPosts).' blog posts');
        $this->command->info('- '.count($songs).' songs');
        $this->command->info('- '.count($mediaItems).' media items');
    }
}
