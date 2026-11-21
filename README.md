# UniBook

A second-hand book marketplace for UiTM Perlis (Arau Campus) students, built for **ICT651 System Integration and Architecture**.

Students sell any used book (textbooks, references, novels, religious books, UiTM modules). A seller takes a photo of the book; UniBook reads the cover and finds the book details from book APIs. Buyers search by title, author, ISBN or course code, book a copy, and meet on campus to pay in cash.

## Features

- **Scan & Listing:** photo scan (OCR), book lookup, auto-filled listing form, cover selection.
- **Catalogue:** one search box (title, author, ISBN, course code) with category filter and price sort.
- **Orders & Meetups:** booking, campus meetup points with distance and route on a map, order status, seller ratings.
- **REST API** with token authentication for all three modules.

## Architecture

One Laravel application (modular monolith) with three modules behind one gateway (Sanctum tokens, rate limiting, one JSON response format `{success, message, data}`). Each external API is called by exactly one service class in `app/Services`.

| External API | Used for | Service class |
| --- | --- | --- |
| [OCR.space](https://ocr.space/ocrapi) | Reading text from the book photo | `OcrService` |
| [Google Books](https://developers.google.com/books) (fallback [Open Library](https://openlibrary.org/developers/api)) | Book details and covers | `BooksService`, `BookLookupService` |
| [OpenStreetMap](https://www.openstreetmap.org): Nominatim + OSRM | Meetup points, distance and route | `MapsService` |
| [OpenRouter](https://openrouter.ai) (free models) | Labelling the photo text (title, author, edition, publisher) only | `AiMatchService` |

Book lookup order: UniBook's own `books` catalogue → Google Books → Open Library → manual entry.

## Tech stack

Laravel 12 (PHP 8.2), MySQL, Blade + Bootstrap 5, Leaflet, Laravel Sanctum, PHPUnit, Postman.

## Main API endpoints

| Module | Endpoint | Purpose |
| --- | --- | --- |
| Gateway | `POST /api/login`, `POST /api/logout`, `GET /api/me` | Token login |
| A Scan & Listing | `POST /api/listings/scan`, `POST /api/listings` | Photo to book suggestions; create a listing |
| B Catalogue | `GET /api/listings?q=&category=`, `PUT/DELETE /api/listings/{id}` | Search, update, delete |
| C Orders | `POST /api/orders`, `PUT /api/orders/{id}`, `POST /api/meetups`, `GET /api/meetups/{id}/route` | Book, complete/cancel/rate, meetup point, route |

## Notes

- Payment is cash at the meetup; there is no payment gateway.
- This is a student prototype for coursework.
