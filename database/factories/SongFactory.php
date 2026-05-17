<?php

namespace Database\Factories;

use App\Models\Song;
use App\Models\SongCategory;
use App\Models\SongSubcategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SongFactory extends Factory
{
    protected $model = Song::class;

    public function definition(): array
    {
        return [
            'song_code' => 'SNG-'.strtoupper($this->faker->unique()->bothify('??###')),
            'title' => $this->faker->sentence(3),
            'lyrics' => $this->faker->paragraphs(3, true),
            'category_id' => SongCategory::factory(),
            'subcategory_id' => SongSubcategory::factory(),
            'audio_file' => $this->faker->optional(0.5)->word().'.mp3',
            'video_url' => $this->faker->optional(0.3)->url(),
            'artist' => $this->faker->name(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withAudio(): static
    {
        return $this->state(fn (array $attributes) => [
            'audio_file' => 'audio_'.$this->faker->word().'.mp3',
        ]);
    }

    public function withVideo(): static
    {
        return $this->state(fn (array $attributes) => [
            'video_url' => $this->faker->url(),
        ]);
    }
}
