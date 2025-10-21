<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear roles básicos
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'emprendedor']);
        Role::create(['name' => 'turista']);
        
        // Fake storage y mail
        Storage::fake('public');
        Mail::fake();
        Notification::fake();
    }

    /** @test */
    public function user_can_register_successfully()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '123456789',
            'country' => 'Perú',
            'birth_date' => '1990-01-01',
            'address' => 'Test Address',
            'gender' => 'masculino',
            'preferred_language' => 'es',
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'active',
                        ],
                        'access_token',
                        'token_type',
                        'email_verified',
                    ]
                ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);
    }

    /** @test */
    public function user_can_register_with_profile_photo()
    {
        $file = UploadedFile::fake()->image('avatar.jpg');
        
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '123456789',
            'country' => 'Perú',
            'birth_date' => '1990-01-01',
            'address' => 'Test Address',
            'gender' => 'masculino',
            'preferred_language' => 'es',
            'foto_perfil' => $file,
        ];

        $response = $this->postJson('/api/register', $userData);

        $response->assertStatus(201);
        Storage::disk('public')->assertExists('fotos_perfil/' . $file->hashName());
    }

    /** @test */
    public function register_validation_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    /** @test */
    public function user_can_login_successfully()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user',
                        'roles',
                        'permissions',
                        'administra_emprendimientos',
                        'access_token',
                        'token_type',
                        'email_verified',
                    ]
                ]);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Credenciales inválidas',
                ]);
    }

    /** @test */
    public function login_fails_for_inactive_user()
    {
        $user = User::factory()->inactive()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Usuario inactivo',
                ]);
    }

    /** @test */
    public function login_fails_for_unverified_email()
    {
        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => ['verification_required']
                ]);
    }

    /** @test */
    public function authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                        'roles',
                        'permissions',
                        'administra_emprendimientos',
                        'emprendimientos',
                        'email_verified',
                    ]
                ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_get_profile()
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    /** @test */
    public function authenticated_user_can_update_profile()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $updateData = [
            'name' => 'Updated Name',
            'phone' => '987654321',
            'address' => 'Updated Address',
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Perfil actualizado correctamente',
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '987654321',
            'address' => 'Updated Address',
        ]);
    }

    /** @test */
    public function authenticated_user_can_update_profile_with_photo()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('new-avatar.jpg');
        
        $updateData = [
            'name' => 'Updated Name',
            'foto_perfil' => $file,
        ];

        $response = $this->putJson('/api/profile', $updateData);

        $response->assertStatus(200);
        Storage::disk('public')->assertExists('fotos_perfil/' . $file->hashName());
    }

    /** @test */
    public function authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Sesión cerrada correctamente',
                ]);
    }

    /** @test */
    public function user_can_request_password_reset()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Se ha enviado un enlace de recuperación a su correo electrónico',
                ]);
    }

    /** @test */
    public function forgot_password_fails_with_invalid_email()
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'No se pudo enviar el correo de recuperación',
                ]);
    }

    /** @test */
    public function user_can_resend_verification_email()
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/resend-verification');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Se ha enviado un nuevo correo de verificación',
                ]);
    }

    /** @test */
    public function resend_verification_fails_for_already_verified_user()
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/resend-verification');

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'El correo electrónico ya ha sido verificado',
                ]);
    }

    /** @test */
    public function google_redirect_returns_url()
    {
        $response = $this->getJson('/api/auth/google');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => ['url']
                ]);
    }
}