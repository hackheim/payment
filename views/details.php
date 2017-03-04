<?php include 'top.php'; ?>

<form action="/details" method="POST">
  <h1>Great! Let us know some basic stuff</h1>
  <label for="phone">Phone</label><br />
  <input type="text" name="phone" value="+47"><br />
  <p class="note">Including country code +XX NNN...</p>
  <label for="company_name">Company name:</label><br />
  <input type="text" name="company_name">
  <p class="note">Leave empty if you are a private person</p>
  <label for="organization_number">Organization Number:</label><br />
  <input type="text" name="organization_number">
  <p class="note">Leave empty if you are a private person</p>
  <input type="submit" value="Submit" /><br />
</form>

<?php include 'bottom.php'; ?>