{literal}
    <style>
        button {
            background-color: #e85526;
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 12px;
        }
    </style>
{/literal}

<h1>Website Forwarding</h1>

<!-- Form with one field: the URL to forward to -->
<form action="{$smarty.server.PHP_SELF}?action=domaindetails&id={$domainid}&modop=custom&a=forwarddomain" method="post">
    <fieldset>
        <div class="form-group">
            <input autofocus class="form-control" name="url" placeholder="URL to forward to" type="text" required/>
        </div>
        <div class="form-group">
            <button type="submit" name="submit">Forward</button>
        </div>
    </fieldset>
</form>
<!-- Display urlError if it exists -->
{if $urlErr}
    <div class="alert alert-danger" role="alert">
        {$urlErr}
    </div>
{/if}