<?php

namespace App\Common;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Config;
use \Stripe\Stripe as Stripe;
use \Stripe\Charge as Charge;
use \Stripe\Customer as Customer;
use \Stripe\Account as Account;
use \Stripe\Plan as Plan;
use \Stripe\Refund as Refund;
use \Stripe\Subscription as Subscription;
use \Stripe\Event as Event;
use \Stripe\Invoice as Invoice;
use \Stripe\retrieve as Retrive;

class StripeService
{
    public function __construct()
    {

        $serviceKeys = Config::get('services.stripe');

        $secretKey = array_get($serviceKeys, 'secret');
        $publishKey = array_get($serviceKeys, 'key');

        $this->responseArray = [];
        $this->secretkey = $secretKey;
        $this->stripe = Stripe::setApiKey($this->secretkey);
    }

    public function createCharge(Array $chargeArray = [])
    {

        if (array_get($chargeArray, 'amount') && array_get($chargeArray, 'amount') > 0) {

            $amount = array_get($chargeArray, 'amount', '300');
            $customerId = array_get($chargeArray, 'customerid');

            try {
                $chargeRespone = Charge::create(array
                (
                    "amount" => $amount,
                    "currency" => "usd",
                    "customer" => $customerId,
                ));
                if (isset($chargeRespone) && sizeof($chargeRespone) > 0) {
                    $this->responseArray['status'] = '200';
                    $this->responseArray['message'] = 'Charge Created!';
                    //$this->responseArray['data'] = $chargeRespone;
                } else {
                    $this->responseArray['status'] = '400';
                    $this->responseArray['message'] = 'Stripe Response Data missing.';
                }
            } catch (\Exception $e) {
                $this->responseArray['status'] = '500';
                $this->responseArray['message'] = $e->getMessage();
            }


        } else {
            $this->responseArray['status'] = '404';
            $this->responseArray['message'] = 'Amount is Missing.';
        }

        return $this->responseArray;
    }

    public function createCustomer(Array $customerDetails = [])
    {
        if (array_get($customerDetails, 'token')) {
            $token = array_get($customerDetails, 'token');

            try {
                $customerResponse = Customer::create(array(
                    "source" => $token,
                    "description" => array_get($customerDetails, 'description')
                ));


                if (isset($customerResponse) && sizeof($customerResponse) > 0) {
                    $this->responseArray['status'] = '200';
                    $this->responseArray['message'] = 'Customer Created!';
                    $this->responseArray['data'] = $customerResponse;
                } else {
                    $this->responseArray['status'] = '400';
                    $this->responseArray['message'] = 'Stripe Response Data missing.';
                }
            } catch (\Exception $e) {
                $this->responseArray['status'] = '500';
                $this->responseArray['message'] = $e->getMessage();
            }
        }

        return $this->responseArray;
    }

    /* retrive customer  */
    public function retrive_customer(Array $arr_data = [])
    {

        if (isset($arr_data['customer_id']) == false) {
            $this->arr_error_bag['status'] = 'failed';
            $this->arr_error_bag['msg'] = "Customer Token is Missing";
        } else {
            $customer = [];
            $customer_id = $arr_data['customer_id'];
            $bank_id = $arr_data['bank_id'];
            $amount = (integer)$arr_data['amount'];
            $micro_deposite1 = $arr_data['micro_deposite1'];
            $micro_deposite2 = $arr_data['micro_deposite2'];

            // get the existing bank account
            try {
                $customer = Customer::retrieve($customer_id);
                if ($customer) {
                    $arr = json_encode($customer);
                    $arr_new = json_decode($arr);
                }
                $bank_account = [];
                $bank_account = $customer->sources->retrieve($bank_id);
                // verify the account
                if (isset($bank_account) && sizeof($bank_account) > 0) {
                    $verifed = [];
                    //$verifed = $bank_account->verify(array('amounts' => array($amount)));
                    $verifed = $bank_account->verify(array('amounts' => array($micro_deposite1, $micro_deposite2)));
                    if (isset($verifed) && sizeof($verifed) > 0) {
                        return $verifed;
                    } else {
                        $this->arr_error_bag['status'] = 'failed';
                        $this->arr_error_bag['msg'] = 'Account not verified';
                    }
                } else {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = 'Invalid Account Deatils';
                }
            } catch (\Exception $e) {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = $e->getMessage();
            }


        }
        return $this->arr_error_bag;
    }

    /* charge a amount form customer  */
    public function charge_token(Array $arr_data = [])
    {

        if (isset($arr_data['amount']) == false) {
            $this->arr_error_bag['status'] = 'failed';
            $this->arr_error_bag['msg'] = "Amount is Missing";
        } else if ((double)($arr_data['amount']) <= 0) {
            $this->arr_error_bag['status'] = 'failed';
            $this->arr_error_bag['msg'] = "Amount should be greater than zero";
        } else {
            $amount = (($arr_data['amount'] * 100));
            $customer_id = $arr_data['customer_id'];
            //$CONNECTED_STRIPE_ACCOUNT_ID = $arr_data['CONNECTED_STRIPE_ACCOUNT_ID'];
            try {
                $charge_response = [];
                $charge_response = Charge::create(array
                (
                    "amount" => $amount,
                    "currency" => "usd",
                    "customer" => $customer_id,

                    /*"destination" => array(
                      "account" => json_encode($CONNECTED_STRIPE_ACCOUNT_ID),
                    ),*/
                ));
                if (isset($charge_response) && sizeof($charge_response) > 0) {
                    return $charge_response;
                } else {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = 'Stripe Response Data missing';
                }
            } catch (\Exception $e) {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = $e->getMessage();
            }
        }


        return $this->arr_error_bag;
    }

    /* create plan */
    public function create_plan(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {
            // dd($arr_data);
            $amount = isset($arr_data['amount']) ? $arr_data['amount'] : '';
            $interval = isset($arr_data['interval']) ? $arr_data['interval'] : '';
            $interval_count = isset($arr_data['interval_count']) ? $arr_data['interval_count'] : '';
            $name = isset($arr_data['name']) ? $arr_data['name'] : '';
            $currency = isset($arr_data['currency']) ? $arr_data['currency'] : '';
            $id = isset($arr_data['id']) ? $arr_data['id'] : '';
            if ($amount != '' && $interval != '' && $name != '' && $currency != '' && $id != '') {

                try {
                    if ($interval_count != '') {
                        $plan_response = Plan::create(array(
                                "amount" => (float)(($amount) * 100),
                                "interval" => $interval,//$interval,
                                'interval_count' => $interval_count,
                                "name" => $name,
                                "currency" => $currency,
                                "id" => $id
                            )
                        );
                    } else {
                        $plan_response = Plan::create(array(
                                "amount" => (float)(($amount) * 100),
                                "interval" => $interval, //$interval,
                                "name" => $name,
                                "currency" => $currency,
                                "id" => $id
                            )
                        );
                    }
                    // dd($plan_response);
                    if (isset($plan_response) && sizeof($plan_response) > 0) {
                        return $plan_response;
                    } else {
                        $this->arr_error_bag['status'] = 'failed';
                        $this->arr_error_bag['msg'] = 'Plan response is missing';
                    }


                } catch (\Exception $e) {

                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = $e->getMessage();

                    return $this->arr_error_bag;
                }


            } else {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = 'Plan details is missing';
            }

        }
        return $this->arr_error_bag;
    }

    /* retrive plan */
    public function retrive_plan(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {
            $plan_id = isset($arr_data['plan_id']) ? $arr_data['plan_id'] : '';
            if ($plan_id != '') {
                $arr_plan = [];
                try {
                    $arr_plan = Plan::retrieve($plan_id);
                    if (isset($arr_plan) && sizeof($arr_plan) > 0) {
                        return $arr_plan;
                    } else {
                        $this->arr_error_bag['status'] = 'failed';
                        $this->arr_error_bag['msg'] = 'Plan response is missing';
                    }
                } catch (\Exception $e) {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = $e->getMessage();
                }


            } else {

                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = 'Plan id is missing';
            }
        }
        return $this->arr_error_bag;
    }

    /* create subscription */
    public function create_subscription(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {
            $customer_id = isset($arr_data['customer_id']) ? $arr_data['customer_id'] : '';
            $plan_id = isset($arr_data['plan_id']) ? $arr_data['plan_id'] : '';

            if ($customer_id != '' && $plan_id != '') {
                $subcriptions = [];
                try {
                    $subcriptions = Subscription::create(array(
                        "customer" => $customer_id,
                        "items" => array(
                            array(
                                "plan" => $plan_id,
                            ),
                        )/*,
							  "receipt_email" => "mayurip@webwingtechnologies.com",*/
                    ));
                    if (isset($subcriptions) && sizeof($subcriptions) > 0) {
                        return $subcriptions;
                    }
                } catch (\Exception $e) {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = $e->getMessage();
                }


            } else {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = 'Customer and plan details is missing';
            }

        }
    }

    /* retrive subscription */
    public function retrive_subscription(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {
            $subscription_id = isset($arr_data['subscription_id']) ? $arr_data['subscription_id'] : '';
            if ($subscription_id != '') {
                $arr_subscription = [];
                try {
                    $arr_subscription = Subscription::retrieve($subscription_id);
                    if (isset($arr_subscription) && sizeof($arr_subscription) > 0) {
                        return $arr_subscription;
                    } else {
                        $this->arr_error_bag['status'] = 'failed';
                        $this->arr_error_bag['msg'] = 'Subscription response is missing';
                    }
                } catch (\Exception $e) {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = $e->getMessage();
                }

            } else {

                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = 'Subscription id is missing';
            }
        }
        return $this->arr_error_bag;
    }

    public function retrieve_event(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {
            $event_id = $arr_data['event_id'];

            if ($event_id != '') {
                $event = Event::retrieve($event_id);
                return $event;
            } else {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = 'Event id is missing';
            }


        } else {
            $this->arr_error_bag['status'] = 'failed';
            $this->arr_error_bag['msg'] = 'Event data is missing';
        }
        return $this->arr_error_bag;
    }

    /*cancel order*/
    public function cancel_process(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {
            $subscription_id = isset($arr_data['cancel_orders']['profile_id']) ?
                $arr_data['cancel_orders']['profile_id'] : '';

            if ($subscription_id != '') {
                $cancelsubscribe = [];
                $cancel_order = [];
                try {
                    $cancelsubscribe = Subscription::retrieve($subscription_id);
                    $cancelsubscribe->cancel();
                    if ($cancelsubscribe) {

                        /*$this->arr_error_bag['status'] = 'success';
                        $this->arr_error_bag['msg'] = 'success';*/
                        return $cancelsubscribe;
                    } else {
                        $this->arr_error_bag['status'] = 'failed';
                        $this->arr_error_bag['msg'] = 'Cancel Subscription response is missing';
                    }

                } catch (\Exception $e) {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = $e->getMessage();
                }

            } else {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = 'Subscription id is missing';
            }
        }
        return $this->arr_error_bag;
    }


    /* Refund order moeny */
    public function refund_process(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {
            $refund_id = isset($arr_data['refund_money']['transaction_id']) ?
                $arr_data['refund_money']['transaction_id'] : '';

            /*	$refund_id = 'ch_1BM8BJFUN97meIiHyWmvnjI6';*/

            if ($refund_id != '') {
                $refundsubscribe = [];
                try {
                    $refundsubscribe = Refund::create(array(
                        "charge" => $refund_id,));
                    if ($refundsubscribe) {
                        return $refundsubscribe;
                    } else {
                        $this->arr_error_bag['status'] = 'failed';
                        $this->arr_error_bag['msg'] = 'Cancel Subscription response is missing';
                    }
                } catch (\Exception $e) {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = $e->getMessage();
                }
            }
        }
        return $this->arr_error_bag;
    }

    /* seperate amount refund    */
    public function refund_amount(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {

            /*$charge_id = 'ch_1BMDYCFUN97meIiHDu9A3rjq';*/
            $charge_id = isset($arr_data['transaction_id']) ?
                $arr_data['transaction_id'] : '';

            $amount = $arr_data['amount'];

            if ($charge_id != '' && $amount != '') {
                $refund_amount = [];
                try {
                    $refund_amount = Refund::create(array(
                        "charge" => $charge_id,
                        "amount" => $amount,
                    ));
                    if ($refund_amount) {
                        return $refund_amount;
                    } else {
                        $this->arr_error_bag['status'] = 'failed';
                        $this->arr_error_bag['msg'] = 'Cancel Subscription response is missing';
                    }
                } catch (\Exception $e) {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = $e->getMessage();
                }
            } else {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = 'Charge id is missing';
            }
        }
        return $this->arr_error_bag;
    }

    public function retrieve_invoice($invoice_id)
    {
        if (isset($invoice_id) && $invoice_id != '') {
            $charge_id = isset($invoice_id) ? $invoice_id : '';
            if ($invoice_id != '') {
                $arr_invoice = [];
                try {
                    $arr_invoice = Invoice::retrieve($invoice_id);
                    if (isset($arr_invoice) && sizeof($arr_invoice) > 0) {
                        return $arr_invoice;
                    } else {
                        $this->arr_error_bag['status'] = 'failed';
                        $this->arr_error_bag['msg'] = 'Charge response is missing';
                    }
                } catch (\Exception $e) {
                    $this->arr_error_bag['status'] = 'failed';
                    $this->arr_error_bag['msg'] = $e->getMessage();
                }
            } else {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = 'Charge id is missing';
            }
        }
        return $this->arr_error_bag;
    }


    public function update_card(Array $arr_data = [])
    {
        if (isset($arr_data) && sizeof($arr_data) > 0) {
            try {

                $update_data = Customer::retrieve($arr_data['customer_id']);
                $update_data->source = $arr_data['token'];
                $update_data->save();
                $this->arr_error_bag['status'] = 'success';
            } catch (\Exception $e) {
                $this->arr_error_bag['status'] = 'failed';
                $this->arr_error_bag['msg'] = $e->getMessage();
            }

            return $this->arr_error_bag;


        }
    }


}

?>