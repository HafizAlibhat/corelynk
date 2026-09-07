<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\FeatureFlagModel;

/**
 * Feature-flag-controlled HTTPS redirect filter.
 *
 * When the `force_https` flag is enabled in the feature_flags table,
 * all non-HTTPS requests are permanently redirected to their HTTPS
 * equivalent.  When the flag is OFF (default), this filter is a no-op.
 *
 * Registered in Filters.php as a global `before` filter.
 */
class HttpsRedirectFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Only redirect when the admin has turned the flag on
        if (! FeatureFlagModel::isEnabled(FeatureFlagModel::FLAG_FORCE_HTTPS)) {
            return;
        }

        // Already HTTPS — nothing to do
        if ($request->isSecure()) {
            return;
        }

        // Build the HTTPS URL preserving path + query string
        $uri  = $request->getUri();
        $url  = 'https://' . $uri->getHost();
        $port = $uri->getPort();
        if ($port && $port !== 443) {
            $url .= ':' . $port;
        }
        $url .= $uri->getPath();
        $query = $uri->getQuery();
        if ($query !== '') {
            $url .= '?' . $query;
        }

        return redirect()->to($url)->setStatusCode(301);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
