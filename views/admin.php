<?php include 'top.php'; ?>
<div style="text-align: center">
    <h1>Admin</h1>
    <?php foreach ($contacts as $contact) { ?>
    <p><?php echo $contact->name; ?></p>
    <?php } ?>
    <h2><a href="updatecontacts">Update contacts</a></h2>

    <?php foreach ($stripecharges as $charge) { ?>
    <p><a href="https://dashboard.stripe.com/payments/<?php echo $charge->charge_id; ?>" target="_BLANK"><?php echo $charge->time; ?>, <?php echo $charge->member->name; ?>, <?php echo $charge->filename; ?><a/></p>
    <?php } ?>
    <h2><a href="uploadreceipts">Upload receipts</a></h2>

    <a href="/">Back</a>
</div>
<?php include 'bottom.php'; ?>