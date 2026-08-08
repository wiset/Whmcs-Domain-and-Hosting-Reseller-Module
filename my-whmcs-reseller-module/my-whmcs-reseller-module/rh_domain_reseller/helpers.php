<?php


// ── Internal: call parent API ─────────────────────────────────
function _rh_domain_call(string $action, array $params = [], string $token = ''): array
{
    $apiUrl = '';
    $apiKey = '';

    // 1. Safely decrypt values from tblregistrars using WHMCS native functions
    try {
        if (function_exists('get_query_val') && function_exists('decrypt')) {
            $rawUrl = get_query_val('tblregistrars', 'value', "registrar = 'rh_domain_reseller' AND setting = 'api_url'");
            $rawKey = get_query_val('tblregistrars', 'value', "registrar = 'rh_domain_reseller' AND setting = 'api_key'");
            
            if ($rawUrl) {
                $apiUrl = @decrypt($rawUrl) ?: $rawUrl; // Fallback to raw if not encrypted
            }
            if ($rawKey) {
                $apiKey = @decrypt($rawKey) ?: $rawKey; // Fallback to raw if not encrypted
            }
        }
    } catch (\Exception $e) {
        // Fallback to params if decryption fails
    }

    // 2. Fallback to passed params or absolute defaults if database fetch fails
    $apiUrl = rtrim($apiUrl ?: $params['api_url'] ?? 'https://realhostpro.com/modules/addons/rh_reseller_parent/api.php', '/');
    $apiKey = trim($apiKey ?: $params['api_key'] ?? '');

    $headers = [
        'X-RH-API-Key: ' . $apiKey,
        'Accept: application/json'
    ];
    if ($token) {
        $headers[] = 'X-RH-User-Token: ' . $token;
    }

    $postfields = array_merge($params, ['action' => $action]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $apiUrl . '?action=' . urlencode($action),
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postfields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['status' => 'error', 'message' => 'cURL: ' . $err];

    $data = json_decode($response, true);
    return $data ?: ['status' => 'error', 'message' => 'Bad JSON response'];
}

// ── Internal: get auth token using Reseller Admin credentials ──
function _rh_domain_getToken(): string
{
    // Define transient cache storage path inside WHMCS temporary directory
    $cacheFile = sys_get_temp_dir() . '/rh_reseller_token_cache.json';
    $cacheDuration = 1800; // 30 minutes in seconds

    // Check if cache file exists and is still valid
    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && isset($cacheData['token'], $cacheData['expires']) && time() < $cacheData['expires']) {
            return $cacheData['token'];
        }
    }

    $email    = '';
    $password = '';

    if (function_exists('get_query_val') && function_exists('decrypt')) {
        $rawEmail = get_query_val('tblregistrars', 'value', "registrar = 'rh_domain_reseller' AND setting = 'admin_email'");
        $rawPass  = get_query_val('tblregistrars', 'value', "registrar = 'rh_domain_reseller' AND setting = 'admin_password'");

        $email    = $rawEmail ? (@decrypt($rawEmail) ?: $rawEmail) : '';
        $password = $rawPass ? (@decrypt($rawPass) ?: $rawPass) : '';
    }

    if (!$email || !$password) {
        return '';
    }

    // Authenticate as the reseller against the parent panel
    $resp = _rh_domain_call('auth', ['email' => $email, 'password' => $password]);
    $token = $resp['token'] ?? '';

    if ($token) {
        // Save token to cache file with an expiration timestamp (30 mins from now)
        $cacheData = [
            'token'   => $token,
            'expires' => time() + $cacheDuration
        ];
        @file_put_contents($cacheFile, json_encode($cacheData));
    }

    return $token;
}

// ── Internal: build error result ──────────────────────────────
function _rh_domain_error(string $msg): array
{
    return ['error' => $msg];
}