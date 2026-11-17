<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class MediaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    public function test_own_level_limits_to_files_the_user_uploaded(): void
    {
        $user = $this->userWith(['media' => 'own']);
        $mine = File::factory()->create(['user_id' => $user->id]);
        $foreign = File::factory()->create(['user_id' => User::factory()->create()->id]);
        $orphan = File::factory()->create(['user_id' => null]);
        $gate = Gate::forUser($user);

        $this->assertTrue($gate->allows('viewAny', File::class));
        $this->assertTrue($gate->allows('delete', $mine));
        $this->assertFalse($gate->allows('delete', $foreign));
        $this->assertFalse($gate->allows('delete', $orphan));
    }

    public function test_a_file_without_owner_needs_any(): void
    {
        $orphan = File::factory()->create(['user_id' => null]);

        $this->assertTrue(Gate::forUser($this->userWith(['media' => 'any']))->allows('delete', $orphan));
    }

    public function test_none_denies(): void
    {
        $user = $this->userWith([]);
        $file = File::factory()->create(['user_id' => $user->id]);

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', File::class));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $file));
    }
}
