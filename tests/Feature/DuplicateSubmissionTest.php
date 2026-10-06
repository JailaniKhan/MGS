<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The `_form_id` token is injected into every state-changing form by
 * resources/js/ui.js and consumed by PreventDuplicateSubmission. It exists
 * because one tap used to be able to save a record twice (double taps, and
 * the NativePHP Android shell replaying a captured POST body).
 */
class DuplicateSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function actingUser(): User
    {
        $user = User::create(['name' => 'D', 'email' => 'dup@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($user);

        return $user;
    }

    public function test_same_form_token_is_only_written_once(): void
    {
        $this->actingUser();

        $payload = [
            '_form_id' => 'token-abc-123',
            'name' => 'Ahmad',
            'phone' => '0700000001',
        ];

        $this->post('/customers', $payload)->assertRedirect(route('customers.index'));

        // A replay of the very same render (double tap / platform body replay)
        // must not create a second record.
        $replay = $this->post('/customers', $payload);
        $replay->assertRedirect();
        $replay->assertSessionHas('error');

        $this->assertSame(1, Customer::where('name', 'Ahmad')->count());
    }

    public function test_distinct_form_tokens_each_write_their_own_record(): void
    {
        $this->actingUser();

        $this->post('/customers', ['_form_id' => 'render-1', 'name' => 'Ahmad'])->assertRedirect(route('customers.index'));
        $this->post('/customers', ['_form_id' => 'render-2', 'name' => 'Ahmad'])->assertRedirect(route('customers.index'));

        // Same name, two deliberate submissions, two renders → two records.
        $this->assertSame(2, Customer::where('name', 'Ahmad')->count());
    }

    public function test_requests_without_a_token_are_untouched(): void
    {
        $this->actingUser();

        // API/JSON/fetch callers never carry a token — the guard must not
        // change their behaviour.
        $this->post('/customers', ['name' => 'Bashir'])->assertRedirect(route('customers.index'));
        $this->post('/customers', ['name' => 'Bashir'])->assertRedirect(route('customers.index'));

        $this->assertSame(2, Customer::where('name', 'Bashir')->count());
    }

    public function test_json_replay_is_answered_with_a_conflict(): void
    {
        $this->actingUser();

        $first = $this->postJson('/customers', ['_form_id' => 'json-token', 'name' => 'Wali']);
        $first->assertStatus(302); // the controller redirects non-JSON-accepting clients

        $this->postJson('/customers', ['_form_id' => 'json-token', 'name' => 'Wali'])
            ->assertStatus(409)
            ->assertJson(['ok' => false, 'duplicate' => true]);

        $this->assertSame(1, Customer::where('name', 'Wali')->count());
    }

    public function test_token_is_scoped_to_the_signing_user(): void
    {
        $this->actingUser();

        $legacy = Customer::create(['name' => 'Existing']);

        $this->post('/customers', ['_form_id' => 'shared-token', 'name' => 'FromFirstUser'])
            ->assertRedirect(route('customers.index'));

        // A different user presenting the same token is not a replay of their
        // own submission and must go through.
        $other = User::create(['name' => 'O', 'email' => 'other-dup@test.dev', 'password' => bcrypt('x')]);
        $this->actingAs($other);

        $this->post('/customers', ['_form_id' => 'shared-token', 'name' => 'FromSecondUser'])
            ->assertRedirect(route('customers.index'));

        $this->assertNotNull($legacy);
        // Both rows exist; read them without the tenant scope (the first user's
        // customer is invisible to the second user by design).
        $this->assertSame(1, Customer::withoutGlobalScopes()->where('name', 'FromFirstUser')->count());
        $this->assertSame(1, Customer::withoutGlobalScopes()->where('name', 'FromSecondUser')->count());
    }
}
