<?php
require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Oasis\Mlib\Logging\ConsoleHandler;
use Oasis\Mlib\Logging\LocalFileHandler;
use Oasis\Mlib\Logging\MLogging;
use Respect\Validation\Validator as v;


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
    'LOCALE'
));

date_default_timezone_set(getenv('TIMEZONE'));
MLogging::addHandler(new LocalFileHandler("../logs"));

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
        //$email ="lol";
        if (v::email()->validate($email))
        {
            $member  = R::findOne('member', ' email = ? ', [ $email ] );

            if ($member == null)
            {
                $member = R::dispense('member');
                $member->email = $email;
                $member->token = bin2hex(openssl_random_pseudo_bytes(64));
                $id = R::store($member);

                setcookie("session", $member->token, time()+3600*24);
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
            if (is_user_logged_in())
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
          header('Location: error');
          merror("unable to sign up customer: " . $_POST['stripeEmail'].
            ", error:" . $e->getMessage());
        }
    }
);

$f3->route('GET /welcome',
    function() {
        if (!is_user_logged_in())
            header('Location: error');

        echo (new View)->render('../views/welcome.php');

        unset($_COOKIE['session']);
        setcookie('session', '', time() - 3600, '/');
    }
);

$f3->route('GET /callback',
    function($f3) {
        $input = @file_get_contents("php://input");
        $event_json = json_decode($input);

        // Verify the event by fetching it from Stripe
        $event = \Stripe\Event::retrieve($event_json->id);
        
        //$member  = R::findOne('member', ' customer_id = ? ', [ $customer_id ] );

        $reference_number = '1lol';//TODO: get from stripe
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
        

        $f3->set('customer_name', 'Ola Nordmann');
        $f3->set('customer_address', 'Slottsgaten 1');
        $f3->set('customer_org_number', '987654321');
        $f3->set('customer_number', '2');

        $f3->set('product_name', getenv('PRODUCT_NAME'));
        $f3->set('product_quantity', '1');
        $f3->set('product_cost', $currencyFormatter->formatCurrency($product_amount, $currency));
        
        $f3->set('product_tax_percent', $product_tax_percent);
        $f3->set('product_tax_amount', $currencyFormatter->formatCurrency($product_tax_amount, $currency));

        $f3->set('reference_number', $reference_number);
        $f3->set('receipt_total_amount', $currencyFormatter->formatCurrency($total_amount, $currency));
        $f3->set('receipt_total_tax_amount', $currencyFormatter->formatCurrency($total_tax_amount, $currency));
        $f3->set('receipt_text', '');

        $f3->set('credit_card_end', '1234');
        $f3->set('credit_card_type', 'VISA');

        $html = (new View)->render('../views/receipt.php');

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();
        file_put_contents("../receipts/receipt_{$reference_number}.pdf", $dompdf->output());
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