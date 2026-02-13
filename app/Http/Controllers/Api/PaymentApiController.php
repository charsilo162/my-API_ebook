<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\UserLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentApiController extends Controller
{
    protected string $secretKey;
    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('paystack.secret_key');
    }

    /**
     * Initialize Payment
     */
    public function initialize(Request $request)
    {
        log::info('all request', $request->all());
        $request->validate([
            'book_id'    => 'required|exists:books,uuid',
            'variant_id' => 'required|exists:book_variants,id',
            'type'       => 'required|in:digital,physical',
        ]);

        $user = $request->user();
       // $book = Book::findOrFail($request->book_id);
        $book = Book::where('uuid', $request->book_id)->firstOrFail();
        
        // Find the specific variant to get the correct price
        $variant = $book->variants()->findOrFail($request->variant_id);
        $amountInKobo = (int) ($variant->price * 100);

        try {
                    $paystackPayload = [
                        'email'        => $user->email,
                        'amount'       => $amountInKobo,
                        'callback_url' => route('payment.callback'),
                        'metadata'     => [
                            'user_id'    => $user->id,
                            'book_id'    => $book->id,
                            'variant_id' => $variant->id,
                            'order_type' => $request->type,
                        ]
                    ];

                    //Log::info('Sending to Paystack:', $paystackPayload);

                    $response = Http::withToken($this->secretKey)
                        ->post("{$this->baseUrl}/transaction/initialize", $paystackPayload);

                   // Log::info('Paystack Response:', $response->json() ?? ['no response']);

                    if (!$response->successful()) {
                       // Log::error('Paystack API Error Detail:', ['body' => $response->json()]);
                        throw new Exception("Paystack Error: " . ($response->json()['message'] ?? 'Unknown Error'));
                    }

                    // This URL is what Livewire uses to redirect the user
                    return response()->json([
                        'authorization_url' => $response->json()['data']['authorization_url']
                    ]);

                } catch (Exception $e) {
                    Log::error("Payment Init Failed: " . $e->getMessage());
                    return response()->json(['error' => $e->getMessage()], 500);
                }
    }

    /**
     * Payment Callback
     */
    public function callback(Request $request)
        {
            $reference = $request->query('reference');
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:8000');

            if (!$reference) {
                return redirect()->away($frontendUrl . '/categories?error=no_reference');
            }

            try {
                $response = Http::withToken($this->secretKey)
                    ->get("{$this->baseUrl}/transaction/verify/{$reference}");

                $body = $response->json();

                if (!$response->successful() || $body['data']['status'] !== 'success') {
                    throw new \Exception("Transaction not successful.");
                }

                $data = $body['data'];
                $meta = $data['metadata'];

                // Process the order in DB
                $order = $this->processOrder($data, $meta, $reference);

                // Redirect to Frontend based on type
                if ($meta['order_type'] === 'digital') {
                    return redirect()->away($frontendUrl . "/my-library?success=Book purchased!");
                }

                return redirect()->away($frontendUrl . "/orders/{$order->id}?success=Order processing");

            } catch (\Exception $e) {
                Log::error("Payment Callback Error: " . $e->getMessage());
                return redirect()->away($frontendUrl . "/categories?error=verification_failed");
            }
        }
    protected function processOrder($data, $meta, $reference)
        {
            return DB::transaction(function () use ($data, $meta, $reference) {
                // 1. Lock the variant to prevent race conditions during stock reduction
                $variant = \App\Models\BookVariant::lockForUpdate()->findOrFail($meta['variant_id']);

                // 2. Stock Management for Physical Books
                if ($meta['order_type'] === 'physical') {
                    if ($variant->stock_quantity <= 0) {
                        throw new \Exception("Item went out of stock during payment processing.");
                    }
                    $variant->decrement('stock_quantity', 1);
                }

                // 3. Determine the Order Status
                // Digital is 'completed' instantly. Physical starts as 'pending'.
                $orderStatus = ($meta['order_type'] === 'digital') ? 'completed' : 'pending';

                // 4. Create the Order
                $order = Order::create([
                    'user_id'        => $meta['user_id'],
                    'reference'      => $reference, // Now exists in DB
                    'total_amount'   => $data['amount'] / 100,
                    'payment_status' => 'paid',
                    'status'         => $orderStatus, // Now exists in DB
                    'order_type'     => $meta['order_type'],
                ]);

                // 5. Create Order Items
                $order->items()->create([
                    'book_id'         => $meta['book_id'],
                    'book_variant_id' => $meta['variant_id'],
                    'price'           => $data['amount'] / 100,
                    'type'            => $meta['order_type'],
                ]);

                // 6. Digital Logic: Add to Library immediately
                if ($meta['order_type'] === 'digital') {
                    \App\Models\UserLibrary::firstOrCreate([
                        'user_id'         => $meta['user_id'],
                        'book_id'         => $meta['book_id'],
                        'book_variant_id' => $meta['variant_id'],
                    ], [
                        'purchased_at'    => now()
                    ]);
                }

                return $order; 
            });
        }

}