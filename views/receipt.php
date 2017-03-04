<p>
    <img width="150mm" src="<?php echo $logo; ?>" />
</p>
<p>
<?php echo $receipt_text; ?>
</p>

<pre>
Organization number:    <?php echo $org_number; ?>

Company:                <?php echo $org_name; ?>

Date and time:          <?php echo $date_and_time; ?>

Receipt No:             <?php echo $reference_number; ?>

</pre>

<pre>
Customer name:          <?php echo $customer_name; ?>

Customer address:       <?php echo $customer_address; ?>

Customer org No:        <?php echo $customer_org_number; ?>

Customer Number:        <?php echo $customer_number; ?>

</pre>

<pre>
Product:                <?php echo $product_name; ?>

Quantity:               <?php echo $product_quantity; ?>

Price:                  <?php echo $product_cost; ?> <?php echo $currency_name; ?>

<?php echo $tax_name; ?> <?php echo $product_tax_percent; ?>%:                <?php echo $product_tax_amount; ?> <?php echo $currency_name; ?>

</pre>

<pre>
Total amount:           <?php echo $receipt_total_amount; ?> <?php echo $currency_name; ?>

Of which <?php echo $tax_name; ?>:           <?php echo $receipt_total_tax_amount; ?> <?php echo $currency_name; ?>

</pre>

<pre>
Payed with card: **** **** **** <?php echo $credit_card_end; ?> (<?php echo $credit_card_type; ?>)

</pre>