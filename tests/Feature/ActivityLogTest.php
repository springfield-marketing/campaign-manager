<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function record_logs_the_acting_user_and_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ActivityLog::record('export.created', 'Exported batch X', null, ['count' => 5]);

        $this->assertDatabaseHas('activity_log', [
            'user_id' => $user->id,
            'action' => 'export.created',
            'description' => 'Exported batch X',
        ]);
        $this->assertSame(['count' => 5], ActivityLog::first()->properties);
    }

    #[Test]
    public function logging_in_is_recorded_by_the_listener(): void
    {
        $user = User::factory()->create();

        // Authenticating through the guard fires the Login event our listener subscribes to.
        auth()->login($user);

        $this->assertDatabaseHas('activity_log', [
            'user_id' => $user->id,
            'action' => 'auth.login',
        ]);
    }

    #[Test]
    public function prune_deletes_entries_past_the_window(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        ActivityLog::record('test.recent', 'recent');
        $old = ActivityLog::create(['user_id' => $user->id, 'action' => 'test.old', 'description' => 'old']);
        DB::table('activity_log')->where('id', $old->id)->update(['created_at' => now()->subMonths(13)]);

        $this->artisan('activity-log:prune')->assertSuccessful();

        $this->assertDatabaseHas('activity_log', ['action' => 'test.recent']);
        $this->assertDatabaseMissing('activity_log', ['action' => 'test.old']);
    }

    #[Test]
    public function the_viewer_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Ivr]));
        $this->assertFalse(ActivityLogResource::canAccess());

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->assertTrue(ActivityLogResource::canAccess());
    }
}
