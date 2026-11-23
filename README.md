# platomat/php-proxy

Standalone PHP **server + client** to relay HTTP fetches through a trusted egress host.

Not a WordPress plugin. Install via Composer into any PHP app that needs outbound HTTP via a relay.

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

## Security

- Bearer API key required
- **Allowlist must be non-empty** or the server refuses all fetches
- `http`/`https` only; private / reserved IPs rejected
- Optional response cache on the relay (`cache_dir` + `meta.cache_ttl`)
