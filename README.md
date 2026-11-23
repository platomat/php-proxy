# platomat/php-proxy

Standalone PHP **server + client** to relay HTTP fetches through a trusted egress host.

Install via Composer into any PHP app that needs outbound HTTP via a relay.

## Request shape

```json
{
  "meta": {
    "cache_ttl": 300,
    "timeout": 15,
    "follow_redirects": true,
    "ssl_verify": false
  },
  "payload": {
    "url": "https://example.com/path",
    "method": "GET",
    "headers": ["User-Agent: …", "Accept: …"],
    "body": null
  }
}
```

Auth: `Authorization: Bearer <api_key>` (header only).

## Server

```bash
composer install
cp config/config.sample.php config/config.php
cp data/allowlist.sample.txt data/allowlist.txt
# edit api_key + allowlist
php -S 0.0.0.0:8443 -t .   # or nginx/caddy with TLS on :8443
```

Point the vhost document root at this directory (`index.php`).

### Smoke test (curl)

Target host must be on the allowlist (e.g. `example.com` in `data/allowlist.txt`).

```bash
curl -sS -X POST 'http://127.0.0.1:8443/' \
  -H "Authorization: Bearer $(php -r 'echo (require "config/config.php")["api_key"];')" \
  -H 'Content-Type: application/json' \
  -d '{
    "meta": { "cache_ttl": 0, "timeout": 15, "ssl_verify": true },
    "payload": {
      "url": "https://example.com/",
      "method": "GET",
      "headers": ["User-Agent: curl-test", "Accept: text/html"],
      "body": null
    }
  }'
```

## Client

```php
use Platomat\Proxy\Client;

$client = new Client([
  'base_url' => 'https://proxy.example:8443',
  'api_key'  => '…',
]);

$res = $client->fetch('https://example.com/resource', 'GET', $headers, null, [
  'cache_ttl'  => 300,
  'ssl_verify' => true,
]);

if ($res->ok) {
  $body = $res->body;
}
```

## Logging

Server writes to PHP `error_log()` (Caddy/php-fpm journal or `php.ini` `error_log`).

```php
'log_level'  => 'info',   // off | error | warning | info | debug
'log_prefix' => 'php-proxy',
```

| Level | What |
|-------|------|
| `error` | fetch failures, empty allowlist, uncaught errors |
| `warning` | auth fail, bad request, host not allowlisted |
| `info` | each request, cache hit, successful fetch (status, duration, bytes) |
| `debug` | fetch start |

URLs in logs are scheme+host+path only (no query string).

## Security

- Bearer API key required
- **Allowlist must be non-empty** or the server refuses all fetches
- `http`/`https` only; private / reserved IPs rejected
- Optional response cache on the relay (`cache_dir` + `meta.cache_ttl`)
