<?php

namespace App\Libraries;

/**
 * Simple Exchange Rate helper using exchangerate.host (free, no API key)
 * Caches a small JSON file for short TTL to avoid excessive external calls.
 */
class ExchangeRates
{
    /**
     * Get live 1 <currency> -> PKR rates for the given currencies.
     * Returns array like ['USD' => 278.12, 'EUR' => 297.44 ...]
     * If API fails, returns available cached values or empty array.
     *
     * @param array $currencies
     * @param int $ttl cache seconds (default 300)
     * @return array
     */
    public static function getRates(array $currencies = ['USD','EUR','GBP'], int $ttl = 300): array
    {
        // Use CodeIgniter WRITEPATH for cache if available, else sys_get_temp_dir()
        $cacheDir = defined('WRITEPATH') ? rtrim(WRITEPATH, DIRECTORY_SEPARATOR) : sys_get_temp_dir();
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'exchange_rates_cache.json';

        // Return cached if recent
        if (is_readable($cacheFile)) {
            $meta = @json_decode(@file_get_contents($cacheFile), true);
            if (is_array($meta) && isset($meta['fetched_at']) && (time() - ($meta['fetched_at'] ?? 0) < $ttl)) {
                return $meta['rates'] ?? [];
            }
        }

        // Single API call: get PKR base rates for USD,EUR,GBP and invert
        $symbols = implode(',', array_map('rawurlencode', $currencies));
        $url = "https://api.exchangerate.host/latest?base=PKR&symbols={$symbols}";

        $json = false;
        // Try curl first
        if (function_exists('curl_version')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $json = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($json === false && $err) {
                $json = false;
            }
        } else {
            // Fallback to file_get_contents with stream context
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $json = @file_get_contents($url, false, $ctx);
        }

        $ratesOut = [];
        if ($json) {
            $data = @json_decode($json, true);
            if (is_array($data) && isset($data['rates']) && is_array($data['rates'])) {
                foreach ($currencies as $c) {
                    $val = $data['rates'][$c] ?? null; // this is PKR -> c (1 PKR = val c)
                    if ($val && $val > 0) {
                        // invert to get 1 CURRENCY -> PKR
                        $ratesOut[$c] = 1 / $val;
                    }
                }
            }
        }

        // If we got results, cache them
        if (!empty($ratesOut)) {
            $dump = json_encode(['fetched_at' => time(), 'rates' => $ratesOut]);
            @file_put_contents($cacheFile, $dump, LOCK_EX);
            return $ratesOut;
        }

        // Otherwise fallback to cached even if stale
        if (is_readable($cacheFile)) {
            $meta = @json_decode(@file_get_contents($cacheFile), true);
            if (is_array($meta) && isset($meta['rates'])) {
                return $meta['rates'];
            }
        }

        return [];
    }
}
