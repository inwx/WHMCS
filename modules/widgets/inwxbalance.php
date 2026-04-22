<?php

use WHMCS\Module\AbstractWidget;

require_once __DIR__ . '/../registrars/inwx/helpers.php';
require_once __DIR__ . '/../registrars/inwx/inwx.php';

add_hook('AdminHomeWidgets', 1, function () {
    return new InwxBalanceWidget();
});

class InwxBalanceWidget extends AbstractWidget
{
    protected $title = 'INWX Account Balance';
    protected $description = 'Current credit balance of the configured INWX account.';
    protected $weight = 150;
    protected $columns = 1;
    protected $cache = true;
    protected $cacheExpiry = 60;

    public function getData()
    {
        $config = inwx_getModuleConfig();
        $config['TestMode'] = !empty($config['TestMode']) && $config['TestMode'] !== '0' && $config['TestMode'] !== 'off';
        if (empty($config['CookieFilePath'])) {
            $config['CookieFilePath'] = '/tmp/inwx_whmcs_cookiefile';
        }

        return inwx_GetAccountBalance($config);
    }

    public function generateOutput($data)
    {
        $endpoint = '../modules/registrars/inwx/balance.php';
        $initialJson = json_encode($data);

        return <<<HTML
<div id="inwx-balance-widget" data-endpoint="{$endpoint}" data-interval="60000">
    <div class="inwx-balance-body">{$this->renderBody($data)}</div>
    <div class="inwx-balance-footer text-muted small" style="margin-top:8px;">
        <span class="inwx-balance-updated">Updated just now</span>
        <button type="button" class="btn btn-xs btn-default pull-right inwx-balance-refresh">Refresh now</button>
    </div>
</div>
<script>
(function(){
    var root = document.getElementById('inwx-balance-widget');
    if (!root || root.dataset.bound) { return; }
    root.dataset.bound = '1';

    var endpoint = root.getAttribute('data-endpoint');
    var interval = parseInt(root.getAttribute('data-interval'), 10) || 60000;
    var body = root.querySelector('.inwx-balance-body');
    var updated = root.querySelector('.inwx-balance-updated');
    var btn = root.querySelector('.inwx-balance-refresh');
    var lastFetch = Date.now();

    function fmt(n) {
        n = parseFloat(n);
        if (!isFinite(n)) { n = 0; }
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function render(data) {
        if (!data || data.error) {
            body.innerHTML = '<div class="alert alert-danger" style="margin:0;">' +
                (data && data.error ? escapeHtml(data.error) : 'Unknown error') + '</div>';
            return;
        }
        var cur = escapeHtml(data.currency || 'EUR');
        var tag = data.endpoint ? ' <span class="label label-default">' + escapeHtml(data.endpoint) + '</span>' : '';
        body.innerHTML =
            '<div style="font-size:22px;font-weight:600;margin-bottom:6px;">' +
                fmt(data.available) + ' ' + cur + tag +
            '</div>' +
            '<div class="small text-muted">Available for transactions</div>' +
            '<table class="table table-condensed" style="margin-top:10px;margin-bottom:0;">' +
                row('Total', fmt(data.total) + ' ' + cur) +
                row('Locked', fmt(data.locked) + ' ' + cur) +
                row('Credit limit', fmt(data.creditLimit) + ' ' + cur) +
            '</table>';
    }

    function row(label, value) {
        return '<tr><td>' + escapeHtml(label) + '</td><td class="text-right">' + value + '</td></tr>';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function refreshLabel() {
        var secs = Math.floor((Date.now() - lastFetch) / 1000);
        var text;
        if (secs < 5) { text = 'Updated just now'; }
        else if (secs < 60) { text = 'Updated ' + secs + 's ago'; }
        else { text = 'Updated ' + Math.floor(secs/60) + 'm ago'; }
        updated.textContent = text;
    }

    function fetchData() {
        btn.disabled = true;
        fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(data){
                lastFetch = Date.now();
                render(data);
                refreshLabel();
            })
            .catch(function(err){
                body.innerHTML = '<div class="alert alert-danger" style="margin:0;">Request failed: ' + escapeHtml(err.message) + '</div>';
            })
            .then(function(){ btn.disabled = false; });
    }

    btn.addEventListener('click', fetchData);
    setInterval(fetchData, interval);
    setInterval(refreshLabel, 5000);
})();
</script>
HTML;
    }

    private function renderBody(array $data): string
    {
        if (!empty($data['error'])) {
            return '<div class="alert alert-danger" style="margin:0;">' . htmlspecialchars($data['error'], ENT_QUOTES) . '</div>';
        }

        $fmt = static function ($v) {
            return number_format((float) $v, 2, '.', ',');
        };
        $currency = htmlspecialchars($data['currency'] ?? 'EUR', ENT_QUOTES);
        $endpointTag = !empty($data['endpoint'])
            ? ' <span class="label label-default">' . htmlspecialchars($data['endpoint'], ENT_QUOTES) . '</span>'
            : '';

        return '<div style="font-size:22px;font-weight:600;margin-bottom:6px;">'
            . $fmt($data['available'] ?? 0) . ' ' . $currency . $endpointTag . '</div>'
            . '<div class="small text-muted">Available for transactions</div>'
            . '<table class="table table-condensed" style="margin-top:10px;margin-bottom:0;">'
            . '<tr><td>Total</td><td class="text-right">' . $fmt($data['total'] ?? 0) . ' ' . $currency . '</td></tr>'
            . '<tr><td>Locked</td><td class="text-right">' . $fmt($data['locked'] ?? 0) . ' ' . $currency . '</td></tr>'
            . '<tr><td>Credit limit</td><td class="text-right">' . $fmt($data['creditLimit'] ?? 0) . ' ' . $currency . '</td></tr>'
            . '</table>';
    }
}
