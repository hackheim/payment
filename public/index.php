<?php
error_reporting(0);

require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Oasis\Mlib\Logging\ConsoleHandler;
use Oasis\Mlib\Logging\LocalFileHandler;
use Oasis\Mlib\Logging\MLogging;
use Respect\Validation\Validator as v;
use Mailgun\Mailgun;


$dotenv = new Dotenv\Dotenv('../');
$dotenv->load();
$dotenv->required(array(
    'PHINX_DBHOST', 
    'PHINX_DBNAME',
    'PHINX_DBUSER', 
    'PHINX_DBPASS',
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
    'MAILGUN_DOMAIN'
));

date_default_timezone_set(getenv('TIMEZONE'));
MLogging::addHandler(new LocalFileHandler("../logs/payment.log"));

$f3 = \Base::instance();

R::setup("mysql:host=".getenv('PHINX_DBHOST').";dbname=".getenv('PHINX_DBNAME'),getenv('PHINX_DBUSER'),getenv('PHINX_DBPASS'));
R::freeze(true);

\Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));


function is_user_logged_in()
{
    if (!isset($_COOKIE["session"]) || $_COOKIE["session"]=="")
        return false;

    $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );

    if ($member==null)
        return false;

    return true;
}

$f3->route('GET /',
    function() {
        echo (new View)->render('../views/frontpage.php');
    }
);

$f3->route('POST /check_email',
    function($f3) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        if (v::email()->validate($email))
        {
            $member  = R::findOne('member', ' email = ? ', [ $email ] );

            if ($member == null)
            {
                $member = R::dispense('member');
                $member->email = $email;
                $member->token = bin2hex(openssl_random_pseudo_bytes(64));
                $id = R::store($member);

                setcookie("session", $member->token, time()+3600*24, '/', getenv('DOMAIN'), getenv('SECURE_COOKIE')==='true', true);

                header('Location: details');
            }
            else
            {
                //TODO: send email with tokenurl for user to log in
                echo "Existing user! login not implemented yet";
            }
        }
        else
        {
            $f3->set('error_email', "Email not valid");
            echo (new View)->render('../views/frontpage.php');
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
    function() {
        if (!is_user_logged_in())
            header('Location: error');

        $member  = R::findOne( 'member', ' token = ? ', [ $_COOKIE["session"] ] );

        $member->organization_number = $_POST['organization_number'];
        $member->company_name = $_POST['company_name'];
        $member->phone = $_POST['phone'];

        R::store($member);

        header('Location: payment_form');
    }
);

$f3->route('GET /payment_form',
    function($f3) {
        if (is_user_logged_in())
        {
            $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );
            $f3->set('email', $member->email);
            $f3->set('cost', getenv('PRODUCT_COST')+getenv('PRODUCT_COST')*(getenv('PRODUCT_TAX_PERCENT')/100));
            
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
            if (!is_user_logged_in())
                header('Location: error');

            $member  = R::findOne('member', ' token = ? ', [ $_COOKIE["session"] ] );

            $customer = \Stripe\Customer::create(array(
                'email' => $member->email,
                'source'  => $_POST['stripeToken']
            ));

            \Stripe\Subscription::create(array(
              "customer" => $customer->id,
              "plan" => getenv('STRIPE_PLAN_NAME'),
              "tax_percent" => getenv('PRODUCT_TAX_PERCENT'),
            ));

            minfo(json_encode($customer, JSON_PRETTY_PRINT));

            $member->name = $_POST['stripeBillingName'];
            $member->address = $_POST['stripeBillingAddressLine1'];
            $member->zip = $_POST['stripeBillingAddressZip'];
            $member->state = $_POST['stripeBillingAddressState'];
            $member->city = $_POST['stripeBillingAddressCity'];
            $member->country = $_POST['stripeBillingAddressCountry'];
            $member->customer_id = $customer->id;

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
	
        minfo(json_encode($event, JSON_PRETTY_PRINT));       
        
    	if ($event->type === 'charge.succeeded')
    	{
            $charge = $event->data->object;
            $reference_number = $charge->id;
            $customer_id = $charge->customer;
            $member  = R::findOne('member', ' customer_id = ? ', [ $customer_id ] );

            //$reference_number = '1lol';//TODO: get from stripe
            $product_cost = getenv('PRODUCT_COST');
            $product_tax_percent = getenv('PRODUCT_TAX_PERCENT');
            $product_tax_amount = $total_tax_amount = $product_cost*($product_tax_percent/100);
            $total_amount = $product_cost + $product_tax_amount;
            $product_amount = $product_cost + $product_tax_amount;

            $currencyFormatter = new NumberFormatter(getenv('LOCALE'), NumberFormatter::CURRENCY);
            $currency = getenv('CURRENCY_NAME');

            $f3->set('logo', dirname(__FILE__).'/img/logo.png');

            $f3->set('currency_name', getenv('CURRENCY_NAME'));
            $f3->set('tax_name', getenv('TAX_NAME'));
            $f3->set('org_number', getenv('ORG_NUMBER'));
            $f3->set('org_name', getenv('ORG_NAME'));
            $f3->set('date_and_time', date("Y-m-d H:i:s"));
            

            $f3->set('customer_name', $member->name);
            $f3->set('customer_address', $member->address);
            $f3->set('customer_org_number', $member->organization_number);
            $f3->set('customer_number', $member->id);

            $f3->set('product_name', getenv('PRODUCT_NAME'));
            $f3->set('product_quantity', '1');
            $f3->set('product_cost', $currencyFormatter->formatCurrency($product_amount, $currency));
            
            $f3->set('product_tax_percent', $product_tax_percent);
            $f3->set('product_tax_amount', $currencyFormatter->formatCurrency($product_tax_amount, $currency));

            $f3->set('reference_number', $reference_number);
            $f3->set('receipt_total_amount', $currencyFormatter->formatCurrency($total_amount, $currency));
            $f3->set('receipt_total_tax_amount', $currencyFormatter->formatCurrency($total_tax_amount, $currency));
            $f3->set('receipt_text', '');

            $f3->set('credit_card_end', $charge->source->last4);
            $f3->set('credit_card_type', $charge->source->brand);

            $html = (new View)->render('../views/receipt.php');

            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->render();
            $filename = "../receipts/receipt_{$reference_number}.pdf";
            file_put_contents("../receipts/receipt_{$reference_number}.pdf", $dompdf->output());


            $mailgun = new Mailgun(getenv('MAILGUN_KEY'));

            $messageBldr = $mailgun->MessageBuilder();
            $messageBldr->setFromAddress('noreply@'.getenv('MAILGUN_DOMAIN'), array("first"=>getenv('ORG_NAME')));
            $messageBldr->addToRecipient($member->email, array("first" => $member->name));
            $messageBldr->setSubject(getenv('ORG_NAME').' - Receipt');
            $messageBldr->setTextBody('Thank you! See attached file for receipt.');
            $messageBldr->addAttachment('@/'.$filename);
            $mailgun->post(getenv('MAILGUN_DOMAIN')."/messages", $messageBldr->getMessage(), $messageBldr->getFiles());

            /*$result = $mailgun->sendMessage(getenv('MAILGUN_DOMAIN'),
            array('from'    => getenv('ORG_NAME').' <noreply@'.getenv('MAILGUN_DOMAIN'),
                  'to'      => $member->name.' <'.$member->email.'>',
                  'subject' => getenv('ORG_NAME').' - Receipt',
                  'text'    => 'Thank you! See attached file for receipt.'));*/
        }
    }
);

$f3->route('GET /error',
    function($f3) {
        if (isset($_GET['error']))
            $f3->set('error', $_GET['error']);
        echo (new View)->render('../views/error.php');
    }
);

$f3->run();
R::close();//Close db connection
