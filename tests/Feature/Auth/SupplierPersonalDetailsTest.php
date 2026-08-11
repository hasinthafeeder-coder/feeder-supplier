<?php

namespace Tests\Feature\Auth;

use Feeder\Core\Enums\ApplicationType;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Services\FileService;
use Feeder\Core\Services\UuidService;
use Feeder\Core\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SupplierPersonalDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for this test.');
        }

        parent::setUp();

        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 10)->unique();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('email', 150)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');
            $table->string('user_type', 30)->default('OWNER');
            $table->string('status', 30)->default(UserStatus::REGISTERING->value);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 10)->unique();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('nic', 12)->unique();
            $table->string('address')->nullable();
            $table->string('profile_photo', 255)->nullable();
            $table->string('profile_photo_uuid', 10)->nullable();
            $table->timestamps();
        });
    }

    public function test_supplier_personal_details_are_saved_and_profile_photo_is_uploaded(): void
    {
        $userUuid = UuidService::generate();

        $user = User::query()->create([
            'uuid' => $userUuid,
            'email' => 'supplier@example.com',
            'phone' => '0712345678',
            'password' => bcrypt('password123'),
            'user_type' => 'OWNER',
            'status' => UserStatus::REGISTERING->value,
            'phone_verified_at' => now(),
        ]);

        $fileService = Mockery::mock(FileService::class);
        $fileService->shouldReceive('upload')
            ->once()
            ->withArgs(function ($file, $application, $entityType, $entityUuid, $category, $uploadedBy) use ($user): bool {
                return $file instanceof UploadedFile
                    && $application === ApplicationType::SUPPLIER->value
                    && $entityType === 'USER'
                    && $entityUuid === $user->uuid
                    && $category === 'PROFILE_PHOTO'
                    && $uploadedBy === $user->uuid;
            })
            ->andReturn([
                'file' => [
                    'uuid' => 'PHOTO12345',
                ],
            ]);

        $this->app->instance(FileService::class, $fileService);

        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/personal', [
            'user_uuid' => $user->uuid,
            'first_name' => 'Nimal',
            'last_name' => 'Perera',
            'nic' => '123456789V',
            'address' => 'No. 1, Main Street',
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('profile.profile_photo', 'PHOTO12345');
        $response->assertJsonPath('profile.profile_photo_uuid', 'PHOTO12345');
        $response->assertJsonPath('profile.profile_photo_uploaded', true);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'first_name' => 'Nimal',
            'last_name' => 'Perera',
            'nic' => '123456789V',
            'address' => 'No. 1, Main Street',
            'profile_photo' => 'PHOTO12345',
            'profile_photo_uuid' => 'PHOTO12345',
        ]);
    }
}
