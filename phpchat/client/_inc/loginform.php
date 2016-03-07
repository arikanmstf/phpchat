<style type="text/css">
.on-login-hidden{display: none !important}
</style>
<div class="login-form">
    <form action="<?= $_SERVER['PHP_SELF']?> " method="post">
        <p>Please enter your name to continue:</p>
        <label for="name">Name:</label>
        <input  autocomplete="off" type="text" name="name" id="name" />
        <input type="submit" name="enter" id="enter" value="Enter" />
    </form>
</div>