<?php

namespace App\Http\Requests;

use App\Models\Listing;
use App\Support\Covers;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for creating and updating listings (website and API).
 *
 * - Website create/update: all required fields must be sent.
 * - API create: title may be left out when an ISBN is given (filled from Google Books).
 * - Author is optional: many UiTM modules / local books have no author printed.
 * - API update (PUT): only the fields that are sent are validated and changed.
 */
class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership is checked by ListingPolicy in the controller
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('course')) {
            $course = strtoupper(str_replace(' ', '', (string) $this->input('course')));
            $this->merge(['course' => $course === '' ? null : $course]);
        }
    }

    public function rules(): array
    {
        $isApi = $this->is('api/*');
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $required = ($isApi && $isUpdate) ? 'sometimes' : 'required';
        $bookField = ($isApi && ! $isUpdate) ? 'required_without:isbn' : $required;

        $rules = [
            'title' => [$bookField, 'string', 'max:255'],
            // Optional: UiTM modules and local books often show no author.
            'author' => ['nullable', 'string', 'max:255'],
            'edition' => ['nullable', 'string', 'max:50'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'category' => [$required, 'in:' . implode(',', Listing::CATEGORIES)],
            // Optional: only for course-related books.
            'course' => ['nullable', 'regex:/^[A-Z]{3}[0-9]{3}$/'],
            'price' => [$required, 'numeric', 'min:1', 'max:1000'],
            'condition' => [$required, 'in:' . implode(',', Listing::CONDITIONS)],
            'description' => ['nullable', 'string', 'max:1000'],
            'isbn' => ['nullable', 'string', 'max:20'],
            // A cover is either an https URL (Google Books / Open Library) or a seller photo we stored.
            'cover_url' => ['nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                $isHttps = filter_var($value, FILTER_VALIDATE_URL) && str_starts_with($value, 'https://');
                if (! $isHttps && ! Covers::isStoredPhoto($value) && ! Covers::isTempPhoto($value)) {
                    $fail('The cover must be an https image link or a photo uploaded to UniBook.');
                }
            }],
            'cover_source' => ['nullable', 'in:' . implode(',', Covers::SOURCES)],
            // Where the book details came from (saved in the books catalogue).
            'book_source' => ['nullable', 'in:catalog,google_books,open_library,manual'],
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
        ];

        if ($isUpdate) {
            // "reserved" is set by the system when a buyer books, not by the seller.
            // Ignored by the controller while the listing has an active booking.
            $rules['status'] = ['sometimes', 'in:available,sold'];
            $rules['sync'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required_without' => 'The title field is required when no ISBN is given.',
            'course.regex' => 'The course code must be 3 letters followed by 3 digits, for example ICT651.',
            'price.min' => 'The price must be at least RM1.00.',
            'price.max' => 'The price may not be more than RM1,000.00.',
            'condition.in' => 'Please choose a valid book condition.',
            'category.required' => 'Please choose a category.',
            'category.in' => 'Please choose a valid category: ' . implode(', ', Listing::CATEGORIES) . '.',
            'status.in' => 'Please choose a valid status.',
        ];
    }

    public function attributes(): array
    {
        return ['course' => 'course code'];
    }

    /** Validated data mapped to the listings table columns. */
    public function listingData(): array
    {
        $data = collect($this->validated())->except(['sync', 'book_source', 'book_id'])->all();

        // A photo from Scan Photo is only kept for good now: move it from the temporary folder
        // to public/uploads/covers (random scans that are never posted are pruned after 24 h).
        if (array_key_exists('cover_url', $data)) {
            $data['cover_url'] = Covers::promote($data['cover_url']);
        }

        if (array_key_exists('cover_url', $data) && blank($data['cover_url'])) {
            $data['cover_source'] = null; // no cover -> placeholder
        }

        if (array_key_exists('course', $data)) {
            $data['course_code'] = $data['course'];
            unset($data['course']);
        }

        return $data;
    }
}
