<h3>Get EPP Code</h3>
<p>The EPP Code is basically a password for a domain name. It is a security measure, ensuring that only the domain name owner can transfer a domain name. You will need it if you are wanting to transfer the domain to another registrar.</p>

<form action="{$smarty.server.PHP_SELF}?action=domaindetails&id={$domainid}&modop=custom&a=geteppcode" method="post">

    <input type="password" name="password" placeholder="Password" required>
    <!-- Button to submit the form -->
    <input type="submit" name="submit" value="Confirm">
</form>

<!-- if $temp is sucess-->
{if $temp == "success"}
    <div class="alert alert-success">
        <strong>Success!</strong> An EPP Code has been sent to your email address.
    </div>
{/if}

<!-- if $temp is error-->
{if $temp == "error"}
    <div class="alert alert-danger">
        <strong>Error!</strong> The password you entered is incorrect.
    </div>
{/if}
<!-- show email error if occurs -->
{if $emailerror}
    <div class="alert alert-danger">
        <strong>Error!</strong> An error occurred while sending the email. Please contact support.
    </div>
{/if}



