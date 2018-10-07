<?php

namespace App\Http\Controllers;

use App\Common\StripeService;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Config;


class StripeController extends Controller
{

    public function __construct(StripeService $stripeService)
    {
        $this->stripe = $stripeService;
    }

    public function aa(Request $request)
    {

        $stripeToken = $request->input('stripeToken');
        $stripeTokenType = $request->input('stripeTokenType');
        $stripeEmail = $request->input('stripeEmail');

        $chargeDetails = array(
            'token' => $stripeToken,
            'type' => $stripeTokenType,
            'email' => $stripeEmail,
            'amount' => '300',
            'description' => 'Audio Video Plan'
        );

        $customerDetails = $this->stripe->createCustomer($chargeDetails);

        if (array_get($customerDetails, 'status') === '200') {

            $customerDetails = array_get($customerDetails, 'data');
            $customerId = $customerDetails->id;

            array_set($chargeDetails, 'customerid', $customerId);

            $chargeResponse = $this->stripe->createCharge($chargeDetails);

            return str_replace('\/', '/', json_encode($chargeResponse));

        }

        return str_replace('\/', '/', json_encode($customerDetails));

    }
}
