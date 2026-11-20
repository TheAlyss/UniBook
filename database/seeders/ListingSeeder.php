<?php

namespace Database\Seeders;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class ListingSeeder extends Seeder
{
    /**
     * Sample listings. Safe to run more than once (matched by seller + title).
     */
    public function run(): void
    {
        $listings = [
            ['hafiz', 2, 'ICT651', 35.00, 'Good', 'Web Services: Concepts, Architectures and Applications', 'Gustavo Alonso', '1st Edition', 'Springer', 'Some highlighting in Chapters 3 and 4. Cover still in good shape.'],
            ['nurul', 5, 'ICT651', 45.00, 'Like new', 'RESTful Web APIs', 'Leonard Richardson, Mike Amundsen', '1st Edition', "O'Reilly Media", 'Barely opened. No writing inside.'],
            ['amir', 7, 'ICT502', 50.00, 'Good', 'Database Systems: Design, Implementation, and Management', 'Carlos Coronel, Steven Morris', '13th Edition', 'Cengage', 'Some pencil notes in the margins, can be erased.'],
            ['siti', 3, 'ICT602', 40.00, 'Fair', 'Systems Analysis and Design', 'Alan Dennis, Barbara Wixom', '7th Edition', 'Wiley', 'Cover corner slightly torn, all pages intact.'],
            ['hafiz', 1, 'MAT183', 60.00, 'Good', 'Calculus: Early Transcendentals', 'James Stewart', '8th Edition', 'Cengage', 'Also useful for MAT283.'],
            ['aina', 4, 'ICT600', 15.00, 'Good', 'Web Programming Module (UiTM)', 'Faculty of Computer & Mathematical Sciences', '2024 Edition', 'UiTM Perlis', 'Printed faculty module. Not in Google Books, entered manually.'],
            ['nurul', 6, 'ICT502', 55.00, 'Like new', 'Computer Networking: A Top-Down Approach', 'James Kurose, Keith Ross', '8th Edition', 'Pearson', 'Bought last semester, only used a few times.'],
            ['amir', 14, 'ICT602', 48.00, 'Good', 'Software Engineering', 'Ian Sommerville', '10th Edition', 'Pearson', 'Name label on the first page.'],
        ];

        foreach ($listings as [$seller, $daysAgo, $course, $price, $condition, $title, $author, $edition, $publisher, $notes]) {
            $user = User::where('email', "{$seller}@student.uitm.edu.my")->firstOrFail();

            $listing = Listing::firstOrNew(['user_id' => $user->id, 'title' => $title]);
            $listing->fill([
                'author' => $author,
                'edition' => $edition,
                'publisher' => $publisher,
                'course_code' => $course,
                'category' => 'Textbook',
                'price' => $price,
                'condition' => $condition,
                'description' => $notes,
            ]);
            if (! $listing->exists) {
                $listing->created_at = now()->subDays($daysAgo);
            }
            $listing->save();
        }
    }
}
