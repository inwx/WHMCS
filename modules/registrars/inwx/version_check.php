<?php

use Illuminate\Database\Capsule\Manager as DB;

const INWX_VERSION_CURRENT = '0.0.0-semantic-release';
const INWX_VERSION_GITHUB_API = 'https://api.github.com/repos/inwx/WHMCS/releases/latest';
const INWX_VERSION_CACHE_TTL = 86400;
const INWX_VERSION_HTTP_TIMEOUT = 5;

function inwx_versionNormalize(string $version): string
{
    $version = ltrim($version, 'vV');
    $plus = strpos($version, '+');
    if ($plus !== false) {
        $version = substr($version, 0, $plus);
    }
    return $version;
}

function inwx_versionGetConfig(string $key): ?string
{
    try {
        $row = DB::table('tblconfiguration')->where('setting', '=', $key)->first();
    } catch (Throwable $e) {
        return null;
    }
    if (!$row) {
        return null;
    }
    return isset($row->value) ? (string) $row->value : null;
}

function inwx_versionSetConfig(string $key, string $value): void
{
    try {
        $exists = DB::table('tblconfiguration')->where('setting', '=', $key)->exists();
        if ($exists) {
            DB::table('tblconfiguration')->where('setting', '=', $key)->update(['value' => $value]);
        } else {
            DB::table('tblconfiguration')->insert(['setting' => $key, 'value' => $value]);
        }
    } catch (Throwable $e) {
        // ignore; notification is best-effort
    }
}

function inwx_versionFetchLatestFromGithub(): ?array
{
    $ch = curl_init(INWX_VERSION_GITHUB_API);
    if ($ch === false) {
        return null;
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, INWX_VERSION_HTTP_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, INWX_VERSION_HTTP_TIMEOUT);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github+json',
        'User-Agent: inwx-whmcs-version-check',
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($body) || $status !== 200) {
        return null;
    }
    $data = json_decode($body, true);
    if (!is_array($data) || empty($data['tag_name'])) {
        return null;
    }

    return [
        'version' => inwx_versionNormalize((string) $data['tag_name']),
        'url' => isset($data['html_url']) ? (string) $data['html_url'] : 'https://github.com/inwx/WHMCS/releases/latest',
    ];
}

function inwx_versionGetLatestCached(): ?array
{
    $now = time();
    $lastCheck = (int) (inwx_versionGetConfig('inwx_version_lastcheck') ?? 0);
    $cachedVersion = inwx_versionGetConfig('inwx_version_latest');
    $cachedUrl = inwx_versionGetConfig('inwx_version_url');

    if ($cachedVersion && ($now - $lastCheck) < INWX_VERSION_CACHE_TTL) {
        return [
            'version' => $cachedVersion,
            'url' => $cachedUrl ?: 'https://github.com/inwx/WHMCS/releases/latest',
        ];
    }

    $fresh = inwx_versionFetchLatestFromGithub();
    if ($fresh === null) {
        inwx_versionSetConfig('inwx_version_lastcheck', (string) $now);
        if ($cachedVersion) {
            return [
                'version' => $cachedVersion,
                'url' => $cachedUrl ?: 'https://github.com/inwx/WHMCS/releases/latest',
            ];
        }
        return null;
    }

    inwx_versionSetConfig('inwx_version_lastcheck', (string) $now);
    inwx_versionSetConfig('inwx_version_latest', $fresh['version']);
    inwx_versionSetConfig('inwx_version_url', $fresh['url']);

    return $fresh;
}

function inwx_versionGetUpdateInfo(): ?array
{
    $latest = inwx_versionGetLatestCached();
    if ($latest === null) {
        return null;
    }

    $current = inwx_versionNormalize(INWX_VERSION_CURRENT);
    if (version_compare($latest['version'], $current, '<=')) {
        return null;
    }

    return [
        'current' => $current,
        'latest' => $latest['version'],
        'url' => $latest['url'],
    ];
}

function inwx_hook_AdminAreaHeaderOutput($vars): string
{
    $info = inwx_versionGetUpdateInfo();
    if ($info === null) {
        return '';
    }

    $current = htmlspecialchars($info['current'], ENT_QUOTES, 'UTF-8');
    $latest = htmlspecialchars($info['latest'], ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars($info['url'], ENT_QUOTES, 'UTF-8');
    $storageKey = 'inwxModuleUpdateDismissed_' . $latest;

    return <<<HTML
<div id="inwx-module-update-banner" style="display:none;margin:10px;padding:12px 16px;border:1px solid #f0ad4e;background:#fcf8e3;color:#8a6d3b;border-radius:4px;font-size:13px;">
  <strong>INWX WHMCS module update available:</strong>
  version <code>{$latest}</code> is out (you have <code>{$current}</code>).
  <a href="{$url}" target="_blank" rel="noopener">View release</a>
  <button type="button" id="inwx-module-update-dismiss" style="float:right;background:none;border:0;font-size:18px;line-height:1;cursor:pointer;color:#8a6d3b;" aria-label="Dismiss">&times;</button>
</div>
<script>
(function(){
  try {
    var key = "{$storageKey}";
    if (window.localStorage && window.localStorage.getItem(key) === "1") { return; }
    var banner = document.getElementById("inwx-module-update-banner");
    if (!banner) { return; }
    banner.style.display = "block";
    var btn = document.getElementById("inwx-module-update-dismiss");
    if (btn) {
      btn.addEventListener("click", function(){
        banner.style.display = "none";
        try { window.localStorage.setItem(key, "1"); } catch (e) {}
      });
    }
  } catch (e) {}
})();
</script>
HTML;
}
