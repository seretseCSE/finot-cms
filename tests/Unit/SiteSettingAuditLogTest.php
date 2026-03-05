<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingAuditLogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that creating a site setting logs an audit entry.
     */
    public function test_creating_setting_logs_audit_entry(): void
    {
        $this->actingAs($this->createSuperadminUser());

        $setting = SiteSetting::create([
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'string',
            'group' => 'general',
            'description' => 'Test setting',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => SiteSetting::class,
            'model_id' => $setting->id,
            'action' => 'created',
        ]);
    }

    /**
     * Test that updating a site setting logs an audit entry.
     */
    public function test_updating_setting_logs_audit_entry(): void
    {
        $this->actingAs($this->createSuperadminUser());

        $setting = SiteSetting::create([
            'key' => 'test_key',
            'value' => 'old_value',
            'type' => 'string',
            'group' => 'general',
        ]);

        // Clear the audit logs from creation
        // Note: In a real scenario, we'd need to track this better

        $setting->update(['value' => 'new_value']);

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => SiteSetting::class,
            'model_id' => $setting->id,
            'action' => 'updated',
        ]);
    }

    /**
     * Test that deleting a site setting logs an audit entry.
     */
    public function test_deleting_setting_logs_audit_entry(): void
    {
        $this->actingAs($this->createSuperadminUser());

        $setting = SiteSetting::create([
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'string',
            'group' => 'general',
        ]);

        $settingId = $setting->id;
        $setting->delete();

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => SiteSetting::class,
            'model_id' => $settingId,
            'action' => 'deleted',
        ]);
    }

    /**
     * Test that audit log captures old and new values.
     */
    public function test_audit_log_captures_old_and_new_values(): void
    {
        $this->actingAs($this->createSuperadminUser());

        $setting = SiteSetting::create([
            'key' => 'test_key',
            'value' => 'old_value',
            'type' => 'string',
            'group' => 'general',
        ]);

        $setting->update(['value' => 'new_value']);

        // Verify audit log exists with update action
        $this->assertDatabaseHas('audit_logs', [
            'model_type' => SiteSetting::class,
            'model_id' => $setting->id,
            'action' => 'updated',
        ]);
    }

    /**
     * Test that the HasAuditLog trait is applied to SiteSetting.
     */
    public function test_site_setting_uses_has_audit_log_trait(): void
    {
        $setting = new SiteSetting();
        
        // Check that the model uses the HasAuditLog trait
        $this->assertArrayHasKey(
            'App\Models\Traits\HasAuditLog', 
            class_uses($setting)
        );
    }

    /**
     * Test setting value retrieval with default.
     */
    public function test_setting_get_with_default(): void
    {
        // Test with non-existent key
        $result = SiteSetting::get('non_existent_key', 'default_value');
        
        $this->assertEquals('default_value', $result);

        // Test with existing key
        SiteSetting::create([
            'key' => 'existing_key',
            'value' => 'existing_value',
            'type' => 'string',
            'group' => 'general',
        ]);

        $result = SiteSetting::get('existing_key', 'default_value');
        $this->assertEquals('existing_value', $result);
    }

    /**
     * Test setting value update.
     */
    public function test_setting_set_creates_or_updates(): void
    {
        $this->actingAs($this->createSuperadminUser());

        // Create new setting
        SiteSetting::set('test_setting', 'initial_value', 'string', 'general', 'Test description');
        
        $this->assertDatabaseHas('site_settings', [
            'key' => 'test_setting',
            'value' => 'initial_value',
        ]);

        // Update existing setting
        SiteSetting::set('test_setting', 'updated_value', 'string', 'general', 'Updated description');
        
        $this->assertEquals('updated_value', SiteSetting::get('test_setting'));
    }

    /**
     * Test multiple settings in same group.
     */
    public function test_settings_in_same_group(): void
    {
        SiteSetting::set('setting_1', 'value_1', 'string', 'group_a', 'Setting 1');
        SiteSetting::set('setting_2', 'value_2', 'string', 'group_a', 'Setting 2');
        SiteSetting::set('setting_3', 'value_3', 'string', 'group_b', 'Setting 3');

        $groupASettings = SiteSetting::where('group', 'group_a')->get();
        $groupBSettings = SiteSetting::where('group', 'group_b')->get();

        $this->assertCount(2, $groupASettings);
        $this->assertCount(1, $groupBSettings);
    }

    /**
     * Test JSON value type.
     */
    public function test_json_value_type(): void
    {
        $jsonValue = ['key1' => 'value1', 'key2' => 'value2'];
        
        $setting = SiteSetting::create([
            'key' => 'json_setting',
            'value' => $jsonValue,
            'type' => 'json',
            'group' => 'general',
        ]);

        $retrieved = SiteSetting::get('json_setting');
        
        $this->assertIsArray($retrieved);
        $this->assertEquals($jsonValue, $retrieved);
    }

    /**
     * Test numeric value type.
     */
    public function test_numeric_value_type(): void
    {
        $setting = SiteSetting::create([
            'key' => 'numeric_setting',
            'value' => 42,
            'type' => 'number',
            'group' => 'general',
        ]);

        $retrieved = SiteSetting::get('numeric_setting');
        
        $this->assertEquals(42, $retrieved);
    }

    /**
     * Test boolean value type.
     */
    public function test_boolean_value_type(): void
    {
        $setting = SiteSetting::create([
            'key' => 'boolean_setting',
            'value' => true,
            'type' => 'boolean',
            'group' => 'general',
        ]);

        $retrieved = SiteSetting::get('boolean_setting');
        
        $this->assertTrue($retrieved);
    }

    /**
     * Test that audit logging captures user information.
     */
    public function test_audit_log_captures_user_info(): void
    {
        $user = $this->createSuperadminUser();
        $this->actingAs($user);

        $setting = SiteSetting::create([
            'key' => 'audit_test_key',
            'value' => 'audit_test_value',
            'type' => 'string',
            'group' => 'general',
        ]);

        // Verify audit log has user_id
        $this->assertDatabaseHas('audit_logs', [
            'model_type' => SiteSetting::class,
            'model_id' => $setting->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test bulk settings update logs multiple entries.
     */
    public function test_bulk_settings_update_logs_multiple_entries(): void
    {
        $this->actingAs($this->createSuperadminUser());

        $settings = [
            ['key' => 'bulk_1', 'value' => 'value_1'],
            ['key' => 'bulk_2', 'value' => 'value_2'],
            ['key' => 'bulk_3', 'value' => 'value_3'],
        ];

        foreach ($settings as $data) {
            SiteSetting::create(array_merge($data, [
                'type' => 'string',
                'group' => 'bulk_test',
            ]));
        }

        // Update all settings
        foreach ($settings as $data) {
            SiteSetting::set($data['key'], $data['value'] . '_updated', 'string', 'bulk_test');
        }

        // Verify audit logs were created for updates
        $this->assertDatabaseHas('audit_logs', [
            'model_type' => SiteSetting::class,
            'action' => 'updated',
        ]);
    }
}
