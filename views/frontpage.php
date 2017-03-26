<?php include 'top.php'; ?>

<div style="font-size:0.9em;width: 600px; margin-left: auto; margin-right: auto;">
    <img style="width:300px" src="img/logo.png"/>
    <h1>Buy 24/7 ALL ACCESS!</h1>

    <h3>Aside from an awesome social meeting point, this is some of the great benefits you get:<h3>

    <ul>
        <li>24/7 access to our premises at work-work</li>
        <li>Opportunity to use a great variety of tools and machines</li>
        <li>Ability to benefit from the many facilities at work-work</li>
        <li>Discount in the café</li>
        <li>Support one of the best maker communities in the area</li>
    </ul>
    <div style="padding: 10px">Price per month <?php echo $cost; ?> ,- inkl. <?php echo getenv('TAX_NAME'); ?></div>
    <div style="padding: 10px;font-size: 0.7em">(full time students and work-work only kr 250,-)</div>
</div>
<?php if (isset($error_email)) { ?>
<p class="error"><?php echo $error_email; ?></p>
<?php } ?>
<form action="/check_email" method="POST">
    <?= \Volnix\CSRF\CSRF::getHiddenInputString() ?>
    <label for="email">Enter e-mail to continue:</label>
    <input type="email" name="email">
    <input type="submit" value="Log in" />
</form>
<p class="info" style="color:grey;font-size: 0.7em">If you want to stop the subscription, send us an email at <a href="mailto:hackheim@hackheim.no">hackheim@hackheim.no<a/></p>
<?php include 'bottom.php'; ?>