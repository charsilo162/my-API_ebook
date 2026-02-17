<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookVariant;
use App\Models\Order;
use App\Models\User;
use App\Models\UserLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected ?string $secretKey;

    public function __construct()
    {
        $this->secretKey = config('paystack.secret_key');
    }

    public function initialize(Request $request)
    {
        $request->validate([
            'variant_id' => 'required|exists:book_variants,id',
        ]);

        $variant = BookVariant::with('book')->findOrFail($request->variant_id);
        $user = Auth::user();

        // Check if user already owns the digital version
        if ($variant->type === 'digital' && $user->library()->where('book_id', $variant->book_id)->exists()) {
            return response()->json(['error' => 'You already own this e-book.'], 400);
        }

        // Check stock for physical books
        if ($variant->type === 'physical' && $variant->stock_quantity <= 0) {
            return response()->json(['error' => 'This physical copy is out of stock.'], 400);
        }

        try {
            // Calculate Amount (use discount_price if available)
            $price = $variant->discount_price ?? $variant->price;
            $amountInKobo = (int)($price * 100);

            $response = Http::withToken($this->secretKey)
                ->post(config('paystack.payment_url') . '/transaction/initialize', [
                    'email' => $user->email,
                    'amount' => $amountInKobo,
                    'callback_url' => route('payment.callback'),
                    'metadata' => [
                        'variant_id' => $variant->id,
                        'user_id' => $user->id,
                        'custom_fields' => [
                            ['display_name' => "Book Title", 'variable_name' => "book_title", 'value' => $variant->book->title],
                            ['display_name' => "Type", 'variable_name' => "type", 'value' => $variant->type]
                        ]
                    ]
                ]);

            $body = $response->json();

            if (!$response->successful()) {
                throw new \Exception($body['message'] ?? 'Paystack initialization failed.');
            }

            return response()->json(['authorization_url' => $body['data']['authorization_url']]);

        } catch (\Exception $e) {
            Log::error("Paystack Init Error: " . $e->getMessage());
            return response()->json(['error' => 'Payment initialization failed.'], 500);
        }
    }


    public function callback(Request $request)
{
    $reference = $request->query('reference');
    
    try {
        $verifyUrl = config('paystack.payment_url') . "/transaction/verify/{$reference}";
        $response = Http::withToken($this->secretKey)->get($verifyUrl);
        $body = $response->json();

        if (!$response->successful() || $body['data']['status'] !== 'success') {
            throw new \Exception('Transaction verification failed.');
        }

        $data = $body['data'];
        $variantId = $data['metadata']['variant_id'];
        $userId = $data['metadata']['user_id'];

        DB::beginTransaction();

        $variant = BookVariant::findOrFail($variantId);
        $user = User::findOrFail($userId);

        // 1. Create the Permanent Order Record
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => $data['amount'] / 100,
            'payment_status' => 'paid',
            'order_type' => $variant->type === 'digital' ? 'instant' : 'shipping',
            'payment_reference' => $reference
        ]);

        // 2. Create Order Item
        $order->items()->create([
            'book_variant_id' => $variant->id,
            'price_at_purchase' => $data['amount'] / 100,
            'quantity' => 1
        ]);

        // 3. If Digital, Add to User Library for Dashboard access
        if ($variant->type === 'digital') {
            UserLibrary::firstOrCreate([
                'user_id' => $user->id,
                'book_id' => $variant->book_id,
                'book_variant_id' => $variant->id,
                'purchased_at' => now()
            ]);
        } else {
            // 4. If Physical, decrement stock
            $variant->decrement('stock_quantity');
        }

       DB::commit();

        $redirectUrl = $variant->type === 'digital' 
            ? "/dashboard/library?success=Book added to library" 
            : "/dashboard/my-orders?success=Physical book order placed";
            // log::info("Redirecting user to: " . config('frontend.base_url') . $redirectUrl);
        return redirect(config('frontend.base_url') . $redirectUrl);

    } catch (\Exception $e) {
       DB::rollBack();
        Log::error("Paystack Callback Error: " . $e->getMessage());
        return redirect(config('frontend.base_url') . '/checkout?error=Verification failed');
    }
}

}