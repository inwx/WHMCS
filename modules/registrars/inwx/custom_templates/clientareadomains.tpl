<div class="card">
  <div class="card-body">
    <h3 class="card-title">{lang key='domaindnsmanagement'}</h3>

      {include file="$template/includes/alert.tpl" type="info" msg="{lang key='domaindnsmanagementdesc'}"}

      {if $error}
          {include file="$template/includes/alert.tpl" type="error" msg=$error}
      {/if}

      {if $external}
        <div class="text-center px-4">
            {$code}
        </div>
      {else}

        <form method="post" action="{$smarty.server.PHP_SELF}?action=domaindns">
          <input type="hidden" name="sub" value="save" />
          <input type="hidden" name="domainid" value="{$domainid}" />

          <table class="table table-striped">
            <thead>
            <tr>
              <th>{lang key='domaindnshostname'}</th>
              <th>{lang key='domaindnsrecordtype'}</th>
              <th>{lang key='domaindnsaddress'}</th>
              <th>{lang key='domaindnspriority'}</th>
            </tr>
            </thead>
            <tbody>
            {foreach $dnsrecords as $dnsrecord}
              <tr>
                <td><input type="hidden" name="dnsrecid[]" value="{$dnsrecord.recid}" /><input type="text" name="dnsrecordhost[]" value="{$dnsrecord.hostname}" size="10" class="form-control" /></td>
                <td>
                  <select name="dnsrecordtype[]" class="form-control">
                  {foreach $records as $record}
                    <option value={$record@key}{if $dnsrecord.type eq $record@key} selected="selected"{/if}>{$record}</option>
                  {/foreach}
                  </select>
                </td>
                <td><input type="text" name="dnsrecordaddress[]" value="{$dnsrecord.address}" size="40" class="form-control" /></td>
                <td>
                    {if $dnsrecord.type eq "MX"}<input type="text" name="dnsrecordpriority[]" value="{$dnsrecord.priority}" size="2" class="form-control" />{else}<input type="hidden" name="dnsrecordpriority[]" value="N/A" />{lang key='domainregnotavailable'}{/if}
                </td>
              </tr>
            {/foreach}
            <tr>
              <td><input type="text" name="dnsrecordhost[]" size="10" class="form-control" /></td>
              <td>
                <select name="dnsrecordtype[]" class="form-control">
                {foreach $records as $record}
                  <option value={$record@key}>{$record}</option>
                {/foreach}
                </select>
              </td>
              <td><input type="text" name="dnsrecordaddress[]" size="40" class="form-control" /></td>
              <td><input type="text" name="dnsrecordpriority[]" size="2" class="form-control" /></td>
            </tr>
            </tbody>
          </table>

          <p class="text-right text-muted">
            <small>* {lang key='domaindnsmxonly'}</small>
          </p>

          <div class="text-center">
            <button type="submit" class="btn btn-primary">
                {lang key='clientareasavechanges'}
            </button>
            <button type="reset" class="btn btn-default">
                {lang key='clientareacancel'}
            </button>
          </div>

        </form>

      {/if}

  </div>
</div>
