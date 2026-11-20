<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin: manage users. Deleting is only allowed for users without any order (the other party's
 * records must stay); users with orders are banned instead.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', '');

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->when($filter === 'admins', fn ($query) => $query->where('is_admin', true))
            ->when($filter === 'banned', fn ($query) => $query->whereNotNull('banned_at'))
            ->withCount(['listings', 'purchases', 'sales'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q', 'filter'));
    }

    public function show(User $user)
    {
        $user->load([
            'listings' => fn ($q) => $q->latest(),
            'purchases' => fn ($q) => $q->latest(),
            'sales' => fn ($q) => $q->latest(),
        ]);

        return view('admin.users.show', ['user' => $user, 'rating' => $user->sellerRating()]);
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['sometimes', 'boolean'],
        ], [
            'email.unique' => 'Another account already uses this email.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $makeAdmin = $request->boolean('is_admin');
        if ($user->isAdmin() && ! $makeAdmin) {
            if ($user->is($request->user())) {
                return back()->withInput()->with('error', 'You cannot remove your own admin role.');
            }
            if (User::where('is_admin', true)->count() <= 1) {
                return back()->withInput()->with('error', 'UniBook needs at least one admin.');
            }
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->is_admin = $makeAdmin;
        if (! empty($data['password'])) {
            $user->password = $data['password']; // hashed by the model cast
            $user->tokens()->delete();             // old API tokens stop working
        }
        $user->save();

        return redirect()->route('admin.users.show', $user)->with('success', 'User details saved.');
    }

    /** Option A: only users without orders can be deleted; their listings (and photos) go too. */
    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($user->isAdmin()) {
            return back()->with('error', 'Remove the admin role before deleting this account.');
        }
        if ($user->hasOrders()) {
            return back()->with('error', 'This user has orders, so the other buyers and sellers still need these records. Ban the user instead.');
        }

        DB::transaction(function () use ($user) {
            $user->listings()->get()->each->delete(); // model events also remove unused photos
            $user->tokens()->delete();
            $user->delete();
        });

        return redirect()->route('admin.users.index')->with('success', "User {$user->email} was deleted.");
    }

    public function ban(Request $request, User $user)
    {
        if ($user->is($request->user()) || $user->isAdmin()) {
            return back()->with('error', 'Admins cannot be banned. Remove the admin role first.');
        }

        $user->forceFill(['banned_at' => now()])->save();
        $user->tokens()->delete(); // API access stops at once; web sessions end on the next page

        return back()->with('success', "{$user->name} is banned: they cannot log in and their listings are hidden.");
    }

    public function unban(User $user)
    {
        $user->forceFill(['banned_at' => null])->save();

        return back()->with('success', "{$user->name} can log in again and their listings are visible.");
    }
}
