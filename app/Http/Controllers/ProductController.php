<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::all();
        return view('product.index', compact('products'));
    }

    public function checkout()
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));

        //simule les produits dans le panier
        $products = Product::all();
        $lineItems = [];
        $totalPrice = 0;
        foreach ($products as $product) {
            $totalPrice += $product->price;
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $product->name,
                        'images' => [$product->image],
                    ],
                    'unit_amount' => $product->price * 100, //parce que c'est en centime
                ],
                'quantity' => 1,
            ];
        }
        $checkout_session = $stripe->checkout->sessions->create([
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => route('checkout.success', [], true) . "?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('checkout.cancel', [], true),
        ]);

        $order = new Order();
        $order->status = 'unpaid'; // en production il vaut mieux des valeurs enum plutot que des valeurs hard coded
        $order->total_price = $totalPrice;
        $order->session_id = $checkout_session->id;
        //en production on aura aussi les autres items(produits)
        $order->save();

        return redirect($checkout_session->url);
    }

    public function success(Request $request)
    {
        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET_KEY'));
        $sessionId = $request->get('session_id');

        /*
        ** Recherchez la session Checkout à partir de cet ID
        ** et créez une page de confirmation de paiement
        ** qui affiche les informations de commande.
        */

        try {
            //recup la session grâce à sessionId
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            if (!$session) {
                throw new NotFoundHttpException();
                echo json_encode(['error' => 'pas de session']);
            }

            //après avoir recup la session, get le client
            //$customer = $stripe->customers->retrieve($session->customer);
            $customer = $session->customer_details; //updated since the video

            $order = Order::where('session_id', $session->id)
                ->first();
            /*
            ** When you use get() you call collection
            ** When you use first() or find($id) then you get single record 
            ** that you can update.
            */

            if (!$order) {
                throw new NotFoundHttpException();
            }
            //check si c'est une session pour un order de ma bdd
            if ($order->status === 'unpaid') {
                $order->status = 'paid';
                $order->save();
            }


            return view('product.checkout-success', compact('customer'));
        } catch (\Throwable $th) {
            throw new NotFoundHttpException();
            echo json_encode(['error' => $th->getMessage()]);
        }
    }

    public function cancel()
    {
        return view('product.checkout-cancel');
    }

    public function webhook()
    {

        // The library needs to be configured with your account's secret key.
        // Ensure the key is kept out of any version control system you might be using.
        $stripe = new \Stripe\StripeClient('sk_test_...');

        // This is your Stripe CLI webhook secret for testing your endpoint locally.
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response('', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return response('', 400);
        }

        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $sessionID = $session->id;

                $order = Order::where('session_id', $session->id)
                    ->first();
                /*
                ** When you use get() you call collection
                ** When you use first() or find($id) then you get single record 
                ** that you can update.
                */

                //check si c'est une session pour un order de ma bdd
                if ($order && $order->status === 'unpaid') {
                    $order->status = 'paid';
                    $order->save();
                    //send email to customer
                }

            default:
                echo 'Received unknown event type ' . $event->type;
        }

        return response('');
    }
}
