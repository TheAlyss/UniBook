<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\Request;

/** Admin: every listing, hide / show again, delete fake or spam listings. */
class ListingController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $visibility = (string) $request->query('visibility', '');

        $listings = Listing::with('user')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('author', 'like', "%{$q}%")
                ->orWhere('course_code', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))))
            ->when(array_key_exists($status, Listing::STATUSES), fn ($query) => $query->where('status', $status))
            ->when($visibility === 'hidden', fn ($query) => $query->whereNotNull('hidden_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.listings.index', compact('listings', 'q', 'status', 'visibility'));
    }

    public function hide(Listing $listing)
    {
        $listing->forceFill(['hidden_at' => now()])->save();

        return back()->with('success', "\"{$listing->title}\" is hidden from search and cannot be booked.");
    }

    public function unhide(Listing $listing)
    {
        $listing->forceFill(['hidden_at' => null])->save();

        return back()->with('success', "\"{$listing->title}\" is visible again.");
    }

    public function destroy(Listing $listing)
    {
        if ($listing->hasActiveOrder()) {
            return back()->with('error', 'A buyer has booked this book. Cancel the order first, then delete the listing.');
        }

        $listing->delete(); // also removes its photo if nothing else uses it

        return back()->with('success', "\"{$listing->title}\" was deleted.");
    }
}
