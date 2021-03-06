<?php include 'top.php'; ?>

<form action="/details" method="POST">
  <?= \Volnix\CSRF\CSRF::getHiddenInputString() ?>
  <h1>Great! Let us know some basic stuff</h1>
  <label for="phone" class="<?php echo isset($errors['phone']) ? 'error' : ''; ?>">Phone</label><br />
  <input type="text" name="phone" value="+47"><br />
  <p class="note">Including country code +XX NNN...</p>
  <label for="coupon" class="<?php echo isset($errors['coupon']) ? 'error' : ''; ?>">Coupon code</label><br />
  <input type="text" name="coupon" value=""><br />
  <p class="note">Enter STUDENT if you are a full time student or PEON if you have a full time desk at work-work. (*)</p>
  <p id="company_form_link">
    <a href="#" style="font-size: 0.7em" 
      onclick="document.getElementById('company_form_section').style.display='block';document.getElementById('company_form_link').style.display='none';">
      Are you a <strong>company</strong>? Click here to add extra information
    </a>
  </p>
  <div id="company_form_section" style="display:none">
  <label for="company_name" class="<?php echo isset($errors['company_name']) ? 'error' : ''; ?>">Company name:</label><br />
  <input type="text" name="company_name">
  <p class="note">Leave empty if you are a private person</p>
  <label for="organization_number" class="<?php echo isset($errors['organization_number']) ? 'error' : ''; ?>">Organization Number:</label><br />
  <input type="text" name="organization_number">
  <p class="note">Leave empty if you are a private person</p>
  </div>
  <input type="submit" value="Submit" /><br />
  <p class="note">* You will need to show documentation to prove student or work-work-peon status</p>
</form>

<?php include 'bottom.php'; ?>