<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ProfileTest extends TestCase
{
    use RefreshDatabase, WithFaker;
    
    protected $apiBaseUrl = '/api/profile';
    protected $user;
    protected $token;
    protected $headers;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
        
        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_show_user_profile()
    {
        $response = $this->getJson($this->apiBaseUrl . '/' . $this->user->id, $this->headers);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'user' => [
                         'id',
                         'name',
                         'email'
                     ]
                 ])
                 ->assertJson([
                     'success' => true,
                     'user' => [
                         'id' => $this->user->id,
                         'name' => $this->user->name,
                         'email' => $this->user->email,
                     ]
                 ]);
    }

    public function test_show_profile_without_authentication()
    {
        $response = $this->getJson($this->apiBaseUrl . '/' . $this->user->id);

        $response->assertStatus(401);
    }

    public function test_show_profile_with_invalid_token()
    {
        $headers = [
            'Authorization' => 'Bearer invalid_token',
            'Accept' => 'application/json',
        ];

        $response = $this->getJson($this->apiBaseUrl . '/' . $this->user->id, $headers);

        $response->assertStatus(401);
    }

    public function test_can_update_profile_name_and_email()
    {
        $newData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $newData, $this->headers);

        // Debug jika ada error 500
        if ($response->getStatusCode() === 500) {
            dump('Response body:', $response->getContent());
            dump('Request data:', $newData);
        }

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Profil berhasil diperbarui',
                     'user' => [
                         'id' => $this->user->id,
                         'name' => $newData['name'],
                         'email' => $newData['email'],
                     ]
                 ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => $newData['name'],
            'email' => $newData['email'],
        ]);
    }

    public function test_can_update_profile_with_password()
    {
        $currentPassword = 'oldpassword123';
        $newPassword = 'newpassword123';
        
        // Update user dengan password yang diketahui
        $this->user->update(['password' => Hash::make($currentPassword)]);

        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData, $this->headers);

        // Debug jika ada error 500
        if ($response->getStatusCode() === 500) {
            dump('Response body:', $response->getContent());
            dump('Request data:', $updateData);
        }

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Profil dan password berhasil diperbarui',
                 ]);

        // Verify password was updated
        $this->user->refresh();
        $this->assertTrue(Hash::check($newPassword, $this->user->password));
    }

    public function test_update_profile_with_wrong_current_password()
    {
        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData, $this->headers);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Password lama tidak sesuai',
                 ]);
    }

    public function test_update_profile_password_confirmation_mismatch()
    {
        $currentPassword = 'oldpassword123';
        $this->user->update(['password' => Hash::make($currentPassword)]);

        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'current_password' => $currentPassword,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword123',
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors'
                 ]);
    }

    public function test_update_profile_with_duplicate_email()
    {
        $otherUser = User::factory()->create();

        $updateData = [
            'name' => $this->faker->name,
            'email' => $otherUser->email,
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => [
                         'email'
                     ]
                 ]);
    }

    public function test_update_profile_with_invalid_data()
    {
        $updateData = [
            'name' => '', // required
            'email' => 'invalid-email', // invalid format
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => [
                         'name',
                         'email'
                     ]
                 ]);
    }

    public function test_update_profile_with_short_password()
    {
        $currentPassword = 'oldpassword123';
        $this->user->update(['password' => Hash::make($currentPassword)]);

        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'current_password' => $currentPassword,
            'password' => '123', // too short
            'password_confirmation' => '123',
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => [
                         'password'
                     ]
                 ]);
    }

    public function test_cannot_update_other_user_profile()
    {
        $otherUser = User::factory()->create();

        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $otherUser->id, $updateData, $this->headers);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Akses tidak diizinkan',
                 ]);
    }

    public function test_update_profile_without_authentication()
    {
        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData);

        $response->assertStatus(401);
    }

    public function test_update_profile_with_nonexistent_user()
    {
        $nonexistentId = 99999;
        
        // Create token for nonexistent user (simulate edge case)
        $headers = [
            'Authorization' => 'Bearer ' . JWTAuth::fromUser($this->user),
            'Accept' => 'application/json',
        ];

        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $nonexistentId, $updateData, $headers);

        $response->assertStatus(403); // Will be caught by authorization check first
    }

    public function test_update_profile_missing_current_password_when_changing_password()
    {
        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            // missing current_password
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors' => [
                         'current_password'
                     ]
                 ]);
    }

    public function test_update_profile_missing_password_confirmation()
    {
        $currentPassword = 'oldpassword123';
        $this->user->update(['password' => Hash::make($currentPassword)]);

        $updateData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'current_password' => $currentPassword,
            'password' => 'newpassword123',
            // missing password_confirmation
        ];

        $response = $this->putJson($this->apiBaseUrl . '/' . $this->user->id, $updateData, $this->headers);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'errors'
                 ]);
    }
}