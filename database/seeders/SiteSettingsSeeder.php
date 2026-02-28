<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // General Settings
        SiteSetting::set('church_name_en', 'Finot Tsidik Sunday School', 'string', 'Church name in English');
        SiteSetting::set('church_name_am', 'ፍኖተ ጽድቅ ሰንበት ትምህርት ቤት', 'string', 'Church name in Amharic');
        SiteSetting::set('contact_email', 'info@finot.org', 'string', 'Primary contact email');
        SiteSetting::set('contact_phone', '+251-XXX-XXXX', 'string', 'Primary contact phone');
        SiteSetting::set('contact_address', 'Addis Ababa, Ethiopia', 'string', 'Church contact address');
        SiteSetting::set('default_language', 'en', 'string', 'Default application language');
        
        // Appearance Settings
        SiteSetting::set('primary_color', '#3b82f6', 'string', 'Primary theme color');
        SiteSetting::set('dark_mode_default', false, 'boolean', 'Default dark mode preference');
        
        // System Settings
        SiteSetting::set('maintenance_mode', false, 'boolean', 'Maintenance mode status');
        SiteSetting::set('pwa_enabled', true, 'boolean', 'PWA functionality status');
        SiteSetting::set('pwa_name', 'Finot CMS', 'string', 'PWA application name');
    }
}
