<?php
error_reporting(0);

require '../vendor/autoload.php';

use Volnix\CSRF\CSRF as CSRF;
use Dompdf\Dompdf;
use Oasis\Mlib\Logging\ConsoleHandler;
use Oasis\Mlib\Logging\LocalFileHandler;
use Oasis\Mlib\Logging\MLogging;
use Respect\Validation\Validator as v;
use Mailgun\Mailgun;

session_set_cookie_params(time()+3600*24, '/', getenv('DOMAIN'), getenv('SECURE_COOKIE')==='true', true);
session_start();// for CSRF

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();
$dotenv->required(array(
    'DATABASE_URL',
    'STRIPE_SECRET_KEY',
    'STRIPE_PUBLIC_KEY',
    'STRIPE_PLAN_NAME', 
    'TIMEZONE',
    'ORG_NUMBER',
    'ORG_NAME',
    'CURRENCY_NAME',
    'TAX_NAME',
    'PRODUCT_NAME',
    'PRODUCT_COST',
    'PRODUCT_TAX_PERCENT',
    'LOCALE',
    'DOMAIN',
    'SECURE_COOKIE',
    'MAILGUN_KEY',
    'MAILGUN_DOMAIN',
    'URL'
));

date_default_timezone_set(getenv('TIMEZONE'));
MLogging::addHandler(new LocalFileHandler("../logs", "payment.log"));

$f3 = \Base::instance();

R::setup(getenv('DATABASE_URL'));
R::freeze(true);

\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));


function is_user_logged_in()
{
    if (!isset($_COOKIE["session"]) || $_COOKIE["session"]=="")
        return false;

    $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );

    if ($member==null || new DateTime($member->token_timeout) < new DateTime())
        return false;

    return true;
}

$f3->route('GET /',
    function($f3) {
        $price = getenv('PRODUCT_COST')+getenv('PRODUCT_COST')*(getenv('PRODUCT_TAX_PERCENT')/100);
        $f3->set('cost', $price);

        echo (new View)->render('../views/frontpage.php');
    }
);

$f3->route('POST /check_email',
    function($f3) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

        if (v::email()->validate($email) && CSRF::validate($_POST))
        {
            $member  = R::findOne('member', ' email = ? ', [ $email ] );

            if ($member == null)
            {
                $member = R::dispense('member');
                $member->email = $email;
                $member->token = bin2hex(openssl_random_pseudo_bytes(64));
                $member->token_timeout = (new DateTime())->add(new DateInterval('PT60M'));
                $id = R::store($member);

                setcookie("session", $member->token, time()+3600*24, '/', getenv('DOMAIN'), getenv('SECURE_COOKIE')==='true', true);

                header('Location: details');
            }
            else
            {
                $member->token = bin2hex(openssl_random_pseudo_bytes(64));
                $member->token_timeout = (new DateTime())->add(new DateInterval('PT60M'));
                R::store($member);
                
                $url = getenv('URL')."login_by_token?token=".$member->token;

                $mailgun = new Mailgun(getenv('MAILGUN_KEY'));
                $messageBldr = $mailgun->MessageBuilder();
                $messageBldr->setFromAddress('noreply@'.getenv('MAILGUN_DOMAIN'), array("first"=>getenv('ORG_NAME')));
                $messageBldr->addToRecipient($member->email, array("first" => $member->name));
                $messageBldr->setSubject('Login URL');
                $messageBldr->setTextBody($url);
                $mailgun->post(getenv('MAILGUN_DOMAIN')."/messages", $messageBldr->getMessage());

                $f3->set('info', "An email with a login URL will soon arrive");
            
                echo (new View)->render('../views/info.php');
            }
        }
        else
        {
            $f3->set('error_email', "Email not valid");
            echo (new View)->render('../views/frontpage.php');
        }
    }
);

$f3->route('GET /login_by_token',
    function($f3) {
        if (!isset($_GET['token']) || strlen($_GET['token'])==0)
            header('Location: error');

        $token = $_GET['token'];

        $member  = R::findOne('member', ' token = ? ', [ $_GET['token'] ] );

        if ($member != null && new DateTime($member->token_timeout) >= new DateTime())
        {
            $member->token = bin2hex(openssl_random_pseudo_bytes(64));
            $member->token_timeout = (new DateTime())->add(new DateInterval('PT60M'));
            R::store($member);
            setcookie("session", $member->token, time()+3600*24, '/', getenv('DOMAIN'), getenv('SECURE_COOKIE')==='true', true);

            if ($member->phone!=null && strlen($member->phone)>0)
                header('Location: payment_form');
            else
                header('Location: details');
        }
        else
        {
            header('Location: error');
        }
    }
);

$f3->route('GET /details',
    function() {
        if (!is_user_logged_in())
            header('Location: error');

        echo (new View)->render('../views/details.php');
    }
);

$f3->route('POST /details',
    function($f3) {
        if (!is_user_logged_in() && CSRF::validate($_POST))
            header('Location: error');
        $errors = array();
        $member  = R::findOne( 'member', ' token = ? ', [ $_COOKIE["session"] ] );

        if (strlen(trim($_POST['coupon']))>0)
            try {
                $cp = \Stripe\Coupon::retrieve($_POST['coupon']);
                $member->coupon = $cp->id;
            } catch (Exception $e) {
                $errors['coupon'] = "Wrong!";
            }
        

        $phone = preg_replace('![^\d+]+!', '', $_POST['phone']);
        $organization_number = preg_replace('![^\d]+!', '', $_POST['organization_number']);
        $company_name = preg_replace('![^a-zA-Z0-9æøåÆØÅ\-&() ]+!', '', $_POST['company_name']);

        if (!v::startsWith('+')->length(11, 12)->validate($phone))
            $errors['phone'] = "Wrong!";

        if (!v::optional(v::numeric()->length(9))->validate($organization_number))
            $errors['organization_number'] = "Wrong!";

        if (sizeof($errors)>0)
        {
            $f3->set('errors', $errors);
            echo (new View)->render('../views/details.php');

        }
        else
        {
            $member->organization_number = $organization_number;
            $member->company_name = $company_name;
            $member->phone = $phone;

            R::store($member);

            header('Location: payment_form');
        }
    }
);

$f3->route('GET /payment_form',
    function($f3) {
        if (is_user_logged_in())
        {
            $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );
            $f3->set('email', $member->email);
            $f3->set('admin', $member->admin);
            $f3->set('anycards', $member->customer_id!=null && strlen($member->customer_id)>0);


            $price = getenv('PRODUCT_COST')+getenv('PRODUCT_COST')*(getenv('PRODUCT_TAX_PERCENT')/100);

            if ($member->coupon!=null && strlen($member->coupon)>0)
                try {
                    $cp = \Stripe\Coupon::retrieve($member->coupon);
                    if ($cp->amount_off!=null)
                        $price -= ($cp->amount_off+$cp->amount_off*(getenv('PRODUCT_TAX_PERCENT')/100))/100;
                    else
                        $price = $price - $price*($cp->percent_off/100);

                } catch (Exception $e) {
                }

            $f3->set('cost', $price);
            
            echo (new View)->render('../views/paymentform.php');
        }
        else
            header('Location: error');
    }
);


$f3->route('POST /pay',
    function() {
        try
        {
            if (!is_user_logged_in() && CSRF::validate($_POST))
                header('Location: error');

            $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );

            if ($member->customer_id==null || $member->customer_id=='')
            {
                $customer = \Stripe\Customer::create(array(
                    'email' => $member->email,
                    'coupon' => !isset($member->coupon) || trim($member->coupon)==='' ? null : trim($member->coupon),
                    'source'  => $_POST['stripeToken']
                ));

                $member->customer_id = $customer->id;
            }
            else
            {
                $customer = \Stripe\Customer::retrieve($member->customer_id);
                $card = $customer->sources->create(array("source" => $_POST['stripeToken']));
                $customer->default_source = $card->id;
                $customer->save();
               
                $subscriptions = \Stripe\Subscription::all(array('customer'=>$member->customer_id));
            }

            minfo(json_encode($customer, JSON_PRETTY_PRINT));

            if ($subscriptions==null || sizeof($subscriptions->data)==0)
                \Stripe\Subscription::create(array(
                  "customer" => $customer->id,
                  "plan" => getenv('STRIPE_PLAN_NAME'),
                  "tax_percent" => getenv('PRODUCT_TAX_PERCENT'),
                ));

            $member->name = substr($_POST['stripeBillingName'], 0, 254);
            $member->address = substr($_POST['stripeBillingAddressLine1'], 0, 254);
            $member->zip = substr($_POST['stripeBillingAddressZip'], 0, 254);
            $member->state = substr($_POST['stripeBillingAddressState'], 0, 254);
            $member->city = substr($_POST['stripeBillingAddressCity'], 0, 254);
            $member->country = substr($_POST['stripeBillingAddressCountry'], 0, 254);
            

            R::store($member);

            header('Location: welcome');
        }
        catch(Exception $e)
        {
          merror("unable to sign up customer: " . $_POST['stripeEmail'].
            ", error:" . $e->getMessage());
          header('Location: error');
        }
    }
);

$f3->route('GET /welcome',
    function() {
        unset($_COOKIE['session']);
        setcookie("session", '', time() - 3600, '/', getenv('DOMAIN'), getenv('SECURE_COOKIE')=='true', true);
	
        echo (new View)->render('../views/welcome.php');
    }
);

$f3->route('POST /callback',
    function($f3) {
        $input = @file_get_contents("php://input");
        $event_json = json_decode($input);

        // Verify the event by fetching it from Stripe
        $event = \Stripe\Event::retrieve($event_json->id);

        if ($event==null)
            exit('no matching event for event id '.$event_json->id);
	
        minfo(json_encode($event, JSON_PRETTY_PRINT));//Log all events
        
    	if ($event->type === 'charge.succeeded')
    	{
            $charge = $event->data->object;
            $reference_number = $charge->id;
            $customer_id = $charge->customer;
            $member  = R::findOne('member', ' customer_id = ? ', [ $customer_id ] );
            $time = date("Y-m-d H:i:s");

            if ($member==null)
                exit('no matching customer for event id '.$event->id);

            $product_tax_percent = getenv('PRODUCT_TAX_PERCENT');
            $amount = $charge->amount/100;
            $product_tax_amount = $total_tax_amount = $amount - $amount/((100+$product_tax_percent)/100);

            $currencyFormatter = new NumberFormatter(getenv('LOCALE'), NumberFormatter::CURRENCY);
            $currency = getenv('CURRENCY_NAME');

            $f3->set('logo', dirname(__FILE__).'/img/logo.png');

            $f3->set('currency_name', getenv('CURRENCY_NAME'));
            $f3->set('tax_name', getenv('TAX_NAME'));
            $f3->set('org_number', getenv('ORG_NUMBER'));
            $f3->set('org_name', getenv('ORG_NAME'));
            $f3->set('date_and_time', $time);
            

            $f3->set('customer_name', $member->name);
            $f3->set('customer_address', $member->address);
            $f3->set('company_name', $member->company_name);
            $f3->set('customer_org_number', $member->organization_number);
            $f3->set('customer_number', $member->id);

            $f3->set('product_name', getenv('PRODUCT_NAME'));
            $f3->set('product_quantity', '1');
            $f3->set('product_cost', $currencyFormatter->formatCurrency($amount, $currency));
            $f3->set('product_tax_percent', $product_tax_percent);
            $f3->set('product_tax_amount', $currencyFormatter->formatCurrency($product_tax_amount, $currency));

            $f3->set('reference_number', $reference_number);
            $f3->set('receipt_total_amount', $currencyFormatter->formatCurrency($amount, $currency));
            $f3->set('receipt_total_tax_amount', $currencyFormatter->formatCurrency($total_tax_amount, $currency));
            $f3->set('receipt_text', getenv('RECEIPT_TEXT'));

            $f3->set('credit_card_end', $charge->source->last4);
            $f3->set('credit_card_type', $charge->source->brand);

            $html = (new View)->render('../views/receipt.php');

            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->render();

            $filename = "receipt_{$reference_number}.pdf";
            $filepath = "../receipts/".$filename;

            $stripe_charge = R::dispense('stripecharge');
            $stripe_charge->member_id = $member->id;
            $stripe_charge->charge_id = $charge->id;
            $stripe_charge->amount = $charge->amount;
            $stripe_charge->amount_refunded = $charge->amount_refunded;
            $stripe_charge->filename = $filename;
            $stripe_charge->time = $time;
            R::store($stripe_charge);

            file_put_contents($filepath, $dompdf->output());

            $mailgun = new Mailgun(getenv('MAILGUN_KEY'));

            $messageBldr = $mailgun->MessageBuilder();
            $messageBldr->setFromAddress('noreply@'.getenv('MAILGUN_DOMAIN'), array("first"=>getenv('ORG_NAME')));
            $messageBldr->addToRecipient($member->email, array("first" => $member->name));
            $messageBldr->setSubject('Receipt '.$time);
            $messageBldr->setTextBody('Thank you! See attached file for receipt.');
            $messageBldr->addAttachment('@'.$filepath);
            $mailgun->post(getenv('MAILGUN_DOMAIN')."/messages", $messageBldr->getMessage(), $messageBldr->getFiles());



            $member->valid_until = (new DateTime($time))->add(new DateInterval('P1M'));
            R::store($member);
        }
    }
);

$f3->route('GET /admin',
    function($f3) {
        $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );

        if (!is_user_logged_in() || !$member->admin)
            header('Location: error');

        $contacts_to_add  = R::find('member', ' fiken_customer_id is null and valid_until is not null limit 100');
        $f3->set('contacts', $contacts_to_add);

        $stripecharges  = R::find('stripecharge', ' fiken_transaction is null');
        $f3->set('stripecharges', $stripecharges);

        echo (new View)->render('../views/admin.php');
    }
);

$f3->route('GET /updatecontacts',
    function($f3) {
        $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );

        if (!is_user_logged_in() || !$member->admin)
            header('Location: error');

        $client = new GuzzleHttp\Client();
        //https://fiken.no/api/doc/#create-general-journal-entry-service

        $members  = R::find('member', ' fiken_customer_id is null and valid_until is not null limit 100');

        $base = 'https://fiken.no/api/v1/companies/'.getenv('FIKEN_COMPANY');
        $auth = [getenv('FIKEN_EMAIL'), getenv('FIKEN_PASSWORD')];


        foreach ($members as $member)
        {
            $res = $client->request('POST', $base.'/contacts', ['auth' => $auth, 'json' => 
                [
                    'name' => $member->name,
                    'email' => $member->email,
                    'phoneNumber' => $member->phone,
                    'memberNumber' => $member->id,
                    'language' => 'ENGLISH',
                    'organizationIdentifier' => $member->organization_number,
                    'address' => [
                        'postalCode' => $member->zip,
                        'postalPlace' => $member->city,
                        'address1' => $member->address,
                    ],
                    'customer' => true
                ]]);
            
            $fiken_contact = json_decode($client->request('GET', 
                                            $res->getHeader('location')[0], 
                                            ['auth' => $auth])->getBody());

            minfo(json_encode($fiken_contact, JSON_PRETTY_PRINT));

            $member->fiken_customer_id = $fiken_contact->customerNumber;
            R::store($member);
        }

       header('Location: admin');
    }
);

$f3->route('GET /uploadreceipts',
    function($f3) {
        $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );

        if (!is_user_logged_in() || !$member->admin)
            header('Location: error');

        $client = new GuzzleHttp\Client();

        $stripecharges  = R::find('stripecharge', ' fiken_transaction is null');

        $base = 'https://fiken.no/api/v1/companies/'.getenv('FIKEN_COMPANY');
        $auth = [getenv('FIKEN_EMAIL'), getenv('FIKEN_PASSWORD')];


        foreach ($stripecharges as $charge)
        {
            $res = $client->request('POST', $base.'/createGeneralJournalEntriesService', ['auth' => $auth, 'json' => 
                [
                  "description" => "Stripe charge: ".$charge->member->name.
                                    " https://dashboard.stripe.com/payments/".$charge->charge_id,
                  "journalEntries" => [
                        [
                            "description" => "Stripe: ".$charge->member->name." (".$charge->charge_id.")",
                            "date" => date('Y-m-d', strtotime($charge->time)),
                            "lines" => [
                                [
                                    "debit" => $charge->amount,
                                    "debitAccount" => "1578",
                                    "creditAccount" => "1500:".$charge->member->fiken_customer_id,
                                ],
                                [
                                    "debit" => $charge->amount,
                                    "debitAccount" => "1500:".$charge->member->fiken_customer_id,
                                    "creditAccount" => "3000",
                                    "creditVatCode" => 3
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
            
            $journal = json_decode($client->request('GET', 
                                            $res->getHeader('location')[0], 
                                            ['auth' => $auth])->getBody());

            minfo(json_encode($journal, JSON_PRETTY_PRINT));

            $charge->fiken_transaction = $journal->_links->self->href;
            R::store($charge);

            $filepath = "../receipts/".$charge->filename;
            $client->request('POST', $journal->entries[0]->_links->{'https://fiken.no/api/v1/rel/attachments'}->href, [
                'auth' => $auth,
                'multipart' => [
                    [
                        'name'     => 'AttachmentFile',
                        'contents' => fopen($filepath, 'r')
                    ],
                    [
                        'name'     => 'JournalAttachment',
                        'contents' => '{"filename":"'.$charge->filename.'"}'
                    ]
                ]
            ]);
        }

        header('Location: admin');
    }
);

$f3->route('GET /error',
    function($f3) {
        if (isset($_GET['error']))
            $f3->set('error', $_GET['error']);
        echo (new View)->render('../views/error.php');
    }
);

$f3->route('GET /policy',
    function() {
        echo (new View)->render('../views/policy.php');
    }
);

$f3->run();
R::close();//Close db connection
