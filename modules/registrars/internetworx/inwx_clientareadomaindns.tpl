<h3>{$LANG.domaindnsmanagement}</h3>

{include file="$template/includes/alert.tpl" type="info" msg=$LANG.domaindnsmanagementdesc}

{if $error}
    {include file="$template/includes/alert.tpl" type="error" msg=$error}
{/if}

{if $external}
    <br/>
    <br/>
    <div class="text-center">
        {$code}
    </div>
    <br/>
    <br/>
    <br/>
    <br/>
{else}
    <form class="form-horizontal" method="post" action="{$smarty.server.PHP_SELF}?action=domaindns">
        <input type="hidden" name="sub" value="save"/>
        <input type="hidden" name="domainid" value="{$domainid}"/>

        <table class="table table-striped">
            <thead>
            <tr>
                <th>{$LANG.domaindnshostname}</th>
                <th>{$LANG.domaindnsrecordtype}</th>
                <th>{$LANG.domaindnsaddress}</th>
                <th>{$LANG.domaindnspriority}</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$dnsrecords item=dnsrecord}
                <tr>
                    <td><input type="hidden" name="dnsrecid[]" value="{$dnsrecord.recid}"/><input type="text"
                                                                                                  name="dnsrecordhost[]"
                                                                                                  value="{$dnsrecord.hostname}"
                                                                                                  size="10"
                                                                                                  class="form-control"/>
                    </td>
                    <td>
                        <select name="dnsrecordtype[]" class="form-control">
                            {foreach $records as $record}
                                <option value={$record@key}{if $dnsrecord.type eq $record@key} selected="selected"{/if}>{$record}</option>
                            {/foreach}
                        </select>
                    </td>
                    <td><input type="text" name="dnsrecordaddress[]" value="{$dnsrecord.address}" size="40"
                               class="form-control"/></td>
                    <td>
                        {if $dnsrecord.type eq "MX"}<input type="text" name="dnsrecordpriority[]"
                                                           value="{$dnsrecord.priority}" size="2"
                                                           class="form-control" />{else}
                            <input type="hidden" name="dnsrecordpriority[]" value="N/A"/>
                            {$LANG.domainregnotavailable}{/if}
                    </td>
                </tr>
            {/foreach}
            <tr>
                <td><input type="text" name="dnsrecordhost[]" size="10" class="form-control"/></td>
                <td>
                    <select name="dnsrecordtype[]" class="form-control">
                        {foreach $records as $record}
                            <option value={$record@key}>{$record}</option>
                        {/foreach}
                    </select>
                </td>
                <td><input type="text" name="dnsrecordaddress[]" size="40" class="form-control"/></td>
                <td><input type="text" name="dnsrecordpriority[]" size="2" class="form-control"/></td>
            </tr>
            </tbody>
        </table>

        <p class="text-right">
            * {$LANG.domaindnsmxonly}
        </p>

        <p class="text-center">
            <input type="submit" value="{$LANG.clientareasavechanges}" class="btn btn-primary"/> <input type="reset"
                                                                                                        value="{$LANG.clientareacancel}"
                                                                                                        class="btn btn-default"/>
        </p>

    </form>
{/if}
