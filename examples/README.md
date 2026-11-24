# Examples

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| `http-endpoint.php` | A tool called over HTTP through a real Symfony kernel, with and without the secret | no |

Run from the package root (after `composer install`):

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/http-endpoint.php
```
