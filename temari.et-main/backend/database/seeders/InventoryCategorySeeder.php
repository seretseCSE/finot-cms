<?php

namespace Database\Seeders;

use App\Models\InventoryCategory;
use Illuminate\Database\Seeder;

/**
 * Platform catalog of inventory categories every Ethiopian school shares
 * (school_id NULL). Schools add their own custom rows on top. Icons are
 * Lucide slugs for the item picker. Idempotent — safe to re-run.
 */
class InventoryCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Stationery & office supplies', 'pencil'],
            ['Teaching aids & materials', 'presentation'],
            ['Textbooks & reference books', 'book-open'],
            ['Furniture', 'armchair'],
            ['Laboratory equipment & supplies', 'flask-conical'],
            ['ICT & electronics', 'monitor'],
            ['Sports & physical education', 'volleyball'],
            ['Cleaning & sanitation', 'spray-can'],
            ['Medical & first aid', 'briefcase-medical'],
            ['Kitchen & cafeteria', 'chef-hat'],
            ['Maintenance & tools', 'wrench'],
            ['Uniforms & clothing', 'shirt'],
        ];

        foreach ($categories as [$name, $icon]) {
            InventoryCategory::query()->withTrashed()->firstOrCreate(
                ['school_id' => null, 'name' => $name],
                ['icon' => $icon, 'is_active' => true],
            );
        }
    }
}
