<?php include 'top.php'; ?>
<?php if (!$anycards) { ?>
<p class="info">Currently no payment methods registered for <?php echo $email; ?>. Time to do something about it!</p>
<?php } else { ?>
<p class="info">There is a card registered for <?php echo $email; ?>. Replace it?</p>
<?php } ?>
<form action="/pay" method="POST">
  <?= \Volnix\CSRF\CSRF::getHiddenInputString() ?>
  <script
    src="https://checkout.stripe.com/checkout.js" class="stripe-button"
    data-key="<?php echo getenv('STRIPE_PUBLIC_KEY'); ?>"
    data-name="<?php echo getenv('ORG_NAME'); ?>"
    data-description="Tool hire, <?php echo $cost; ?>,- <?php echo getenv('CURRENCY_NAME'); ?> per month."
    data-label="Click here to add payment"
    data-locale="auto"
    data-email="<?php echo $email; ?>"
    data-currency="<?php echo getenv('CURRENCY_NAME'); ?>"
    data-zip-code="true"
    data-billing-address="true">
  </script>
</form>
<?php include 'bottom.php'; ?>