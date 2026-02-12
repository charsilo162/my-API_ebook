<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Share;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShowcaseController extends Controller
{

    public function getShowcase()
        {
            $limit = 4;

            // 1. Try to get real Discount Deals
            $discounts = Book::where('is_active', true)
                ->whereHas('variants', function ($q) {
                    $q->whereNotNull('discount_price')->where('discount_price', '>', 0);
                })
                ->with(['variants'])
                ->latest()
                ->take($limit)
                ->get();

            // Determine Title and Data for the first section
            if ($discounts->isEmpty()) {
                // BACKUP: Get random books instead
                $firstSectionBooks = Book::where('is_active', true)
                    ->with(['variants'])
                    ->inRandomOrder()
                    ->take($limit)
                    ->get();
                $firstSectionTitle = "Recommended for You";
            } else {
                $firstSectionBooks = $discounts;
                $firstSectionTitle = "Discount Deals";
            }

            // 2. New Arrivals
            $newArrivals = Book::where('is_active', true)->with(['variants'])->latest()->take($limit)->get();

            // 3. Best Selling
            $bestSelling = Book::where('is_active', true)->with(['variants'])
                ->withCount('orderItems')->orderBy('order_items_count', 'desc')->take($limit)->get();

            // 4. Today's Deal
            $todaysDeals = Book::where('is_active', true)->with(['variants'])->inRandomOrder()->take($limit)->get();

            return response()->json([
                'sections' => [
                    ['title' => $firstSectionTitle, 'books' => $this->formatBooks($firstSectionBooks)],
                    ['title' => 'New Arrival', 'books' => $this->formatBooks($newArrivals)],
                    ['title' => 'Best Selling', 'books' => $this->formatBooks($bestSelling)],
                    ['title' => 'Today\'s Deal', 'books' => $this->formatBooks($todaysDeals)]
                ]
            ]);
        }
/**
 * Helper to keep formatting consistent across all sections
 */
private function formatBooks($booksCollection)
{
    return $booksCollection->map(function ($book) {
        // Find the first available format type to use as a default link
        $defaultType = $book->variants->first()->type ?? 'digital';

        return [
            'title' => $book->title,
            'author' => $book->author_name,
            'image' => $book->cover_image ? asset($book->cover_image) : asset('storage/images/d6.jpg'),
            'starting_price' => $book->variants->min('price') ?? 0,
            'formats' => $book->variants->map(fn($v) => ['type' => $v->type]),
            // Generate the dynamic URL here
            'url' => "/books/{$book->uuid}?type={$defaultType}"
        ];
    });
}
}