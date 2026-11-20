<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesListings;
use Tests\TestCase;

/** Admin area: users (edit, ban, delete option A), listings (hide/delete), orders (cancel), catalogue. */
class AdminTest extends TestCase
{
    use CreatesListings, RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['email' => 'admin@email.com']);
        $admin->is_admin = true;
        $admin->save();

        return $admin;
    }

    public function test_only_admins_can_open_the_admin_area(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.users.index'))->assertForbidden();

        $this->makeListing();
        $admin = $this->admin();
        foreach (['admin.dashboard', 'admin.users.index', 'admin.listings.index', 'admin.orders.index', 'admin.books.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
        $this->actingAs($admin)->get(route('home'))->assertSee(route('admin.dashboard'));
    }

    public function test_admin_edits_user_and_resets_password(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->createToken('api');

        $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertOk();
        $this->put(route('admin.users.update', $user), [
            'name' => 'New Name', 'email' => 'new@student.uitm.edu.my', 'is_admin' => '0',
            'password' => 'newpassword1', 'password_confirmation' => 'newpassword1',
        ])->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@student.uitm.edu.my', $user->email);
        $this->assertTrue(Hash::check('newpassword1', $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_admin_cannot_remove_own_role_or_the_last_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['name' => 'A', 'email' => $admin->email, 'is_admin' => '0'])
            ->assertSessionHas('error', 'You cannot remove your own admin role.');
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_delete_rules_option_a(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');
        $this->delete(route('admin.users.destroy', $order->buyer))->assertSessionHas('error');
        $this->assertNotNull($order->buyer->fresh());

        $seller = User::factory()->create();
        $listing = $this->makeListing($seller);
        $this->delete(route('admin.users.destroy', $seller))->assertRedirect(route('admin.users.index'));
        $this->assertNull($seller->fresh());
        $this->assertNull($listing->fresh());
    }

    public function test_ban_blocks_login_hides_listings_and_ends_sessions(): void
    {
        $admin = $this->admin();
        $seller = User::factory()->create(['email' => 'seller@student.uitm.edu.my']);
        $listing = $this->makeListing($seller);

        $this->actingAs($admin)->post(route('admin.users.ban', $seller))->assertSessionHas('success');
        $this->assertTrue($seller->fresh()->isBanned());
        $this->post(route('admin.users.ban', $admin))->assertSessionHas('error');

        // Listings disappear for everyone except the admin
        $this->get(route('books.show', $listing))->assertOk();
        auth()->logout();
        $this->get(route('home'))->assertDontSee('RESTful Web APIs');
        $this->get(route('books.show', $listing))->assertNotFound();

        // Logins fail, an open session is ended
        $this->post(route('login'), ['email' => 'seller@student.uitm.edu.my', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'This account has been suspended. Please contact the UniBook admin.']);
        $this->assertGuest();
        $this->postJson('/api/login', ['email' => 'seller@student.uitm.edu.my', 'password' => 'password'])->assertForbidden();
        $this->actingAs($seller->fresh())->get(route('orders.index'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($admin)->post(route('admin.users.unban', $seller));
        auth()->logout();
        $this->get(route('home'))->assertSee('RESTful Web APIs');

        // API search hides a banned seller's listings too
        $seller->forceFill(['banned_at' => now()])->save();
        $this->withToken(User::factory()->create()->createToken('t')->plainTextToken)->getJson('/api/listings')->assertJsonCount(0, 'data');
    }

    public function test_hidden_listing_cannot_be_found_or_booked(): void
    {
        $admin = $this->admin();
        $listing = $this->makeListing();
        $buyer = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.listings.hide', $listing));
        $this->assertNotNull($listing->fresh()->hidden_at);

        $this->actingAs($buyer)->get(route('home', ['q' => 'RESTful']))->assertDontSee(route('books.show', $listing));
        $this->get(route('books.show', $listing))->assertNotFound();
        $this->get(route('orders.create', $listing))->assertNotFound();
        $this->getJson('/api/listings/' . $listing->id)->assertNotFound();
        $this->actingAs($listing->user)->get(route('listings.mine'))->assertSee('Hidden by admin');

        $this->actingAs($admin)->post(route('admin.listings.unhide', $listing));
        $this->actingAs($buyer)->get(route('books.show', $listing))->assertOk();
    }

    public function test_admin_deletes_listing_only_without_active_order(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();

        $this->actingAs($admin)->delete(route('admin.listings.destroy', $order->listing))->assertSessionHas('error');
        $this->assertNotNull($order->listing->fresh());

        $free = $this->makeListing();
        $this->delete(route('admin.listings.destroy', $free))->assertSessionHas('success');
        $this->assertNull($free->fresh());
    }

    public function test_admin_cancels_stuck_order(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder(withMeetup: true);

        $this->actingAs($admin)->get(route('orders.show', $order))->assertOk()->assertSee('You are the admin');
        $this->post(route('admin.orders.cancel', $order))->assertSessionHas('success');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('available', $order->listing->fresh()->status);
    }

    public function test_admin_edits_catalogue_book(): void
    {
        $admin = $this->admin();
        $book = Book::create(['title' => 'Wrong Title', 'isbn' => '9780596529260', 'source' => 'google_books']);

        $this->actingAs($admin)->get(route('admin.books.edit', $book))->assertOk();
        $this->put(route('admin.books.update', $book), [
            'title' => 'RESTful Web Services', 'author' => 'Leonard Richardson', 'isbn' => '978-0-596-52926-0',
            'cover_url' => 'https://covers.openlibrary.org/b/isbn/9780596529260-M.jpg', 'cover_source' => 'open_library',
        ])->assertRedirect();

        $book->refresh();
        $this->assertSame('RESTful Web Services', $book->title);
        $this->assertSame('9780596529260', $book->isbn);
        $this->assertSame('open_library', $book->cover_source);

        $this->put(route('admin.books.update', $book), ['title' => 'X', 'cover_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('cover_url');

        $this->delete(route('admin.books.destroy', $book));
        $this->assertNull($book->fresh());
    }

    public function test_make_admin_command(): void
    {
        $this->artisan('unibook:make-admin', ['email' => 'admin@email.com', '--name' => 'Admin'])
            ->expectsQuestion('Password (at least 8 characters, hidden)', '')
            ->assertFailed();
        $this->assertNull(User::where('email', 'admin@email.com')->first());

        $this->artisan('unibook:make-admin', ['email' => 'admin@email.com', '--name' => 'Admin', '--password' => 'password123'])
            ->assertSuccessful();

        $admin = User::where('email', 'admin@email.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('password123', $admin->password));

        $this->artisan('unibook:make-admin', ['email' => 'admin@email.com', '--password' => 'short'])->assertFailed();
    }
}
