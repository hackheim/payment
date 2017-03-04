<?php include 'top.php'; ?>

<div style="width: 600px; margin-left: auto; margin-right: auto">
    <h1>Join us</h1>

    <h3>Hva får du?<h3>

    <ul>
        <li>Gir deg tilgang til lokalene våre på work-work</li>
        <li>Et utvalg verktøy</li>
        <li>Mulighet til å dra nytte av de mange fasilitetene på work-work</li>
        <li>Rabatt i kafèen</li>
        <li>Støtt Trondheim sitt kuleste community</li>
    </ul>
</div>
<?php if (isset($error_email)) { ?>
<p class="error"><?php echo $error_email; ?></p>
<?php } ?>
<form action="/check_email" method="POST">
    <label for="email">Enter e-mail to setup payment:</label>
    <input type="email" name="email">
    <input type="submit" value="Log in" />
</form>

<?php include 'bottom.php'; ?>