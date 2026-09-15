<?php
/**
 * RH Hosting Reseller — WHMCS Server Module
 * ─────────────────────────────────────────────
 * Install on CHILD WHMCS
 * Path: /modules/servers/rh_hosting_reseller/rh_hosting_reseller.php
 */

if (!defined('WHMCS')) die('Direct access not permitted');

// ── Module metadata ───────────────────────────────────────────
function rh_hosting_reseller_MetaData(): array
{
    return [
        'DisplayName'            => 'RH Hosting Reseller',
        'APIVersion'             => '1.2',
        'RequiresServer'         => true,
        'DefaultNonSSLPort'      => '80',
        'DefaultSSLPort'         => '443',
        'ServiceSingleSignOnLabel'=> 'Login to Realhost Panel',
    ];
}

// ── Config fields shown on the Server record ──────────────────
// ── Internal: cache products list to local module JSON file ──
function _rh_hosting_cacheProducts(array $server): array
{
    $resp = _rh_hosting_call($server, 'getCatalogue', [], false);
    $products = $resp['products'] ?? [];
    
    $options = [];
    foreach ($products as $prod) {
        // Skip hidden or retired products if the property exists
        if (!empty($prod['hidden']) || (isset($prod['retired']) && $prod['retired'])) {
            continue;
        }

        $pid  = $prod['id'] ?? $prod['pid'] ?? '';
        $name = $prod['name'] ?? 'Product #' . $pid;
        
        if ($pid) {
            $options[$pid] = $name . ' (PID: ' . $pid . ')';
        }
    }

    // Save directly inside the module directory
    $cacheFile = __DIR__ . '/products_cache.json';
    @file_put_contents($cacheFile, json_encode($options, JSON_PRETTY_PRINT));

    return $options;
}

// ── Internal: get cached products list from module directory ──
function _rh_hosting_getCachedProducts(): array
{
    $cacheFile = __DIR__ . '/products_cache.json';

    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (is_array($data) && !empty($data)) {
            return $data;
        }
    }

    return [];
}

// ── Config fields shown on the Server record / Product config ─
function rh_hosting_reseller_ConfigOptions(array $params = []): array
{
    // Load products from the local module JSON file
    $productOptions = _rh_hosting_getCachedProducts();

    if (empty($productOptions)) {
        $productOptions = ['' => '— Please Test Server Connection first to load products —'];
    } else {
        $productOptions = ['' => '— Select Parent Product —'] + $productOptions;
    }

    return [
        1 => [
            'FriendlyName' => 'Parent Product PID',
            'Type'         => 'dropdown',
            'Options'      => $productOptions,
            'Description'  => 'Select the corresponding product from the parent panel catalogue.',
        ],
        2 => [
            'FriendlyName' => 'Billing Cycle Override',
            'Type'         => 'dropdown',
            'Options'      => 'monthly,quarterly,semiannually,annually,biennially,triennially',
            'Description'  => 'Force a specific billing cycle. Leave empty to inherit from order.',
        ],
    ];
}
// ── Internal: get server credentials from WHMCS server record ─
function _rh_hosting_getServer(array $params): array
{
    $hostname = trim($params['serverhostname'], '/');
    // Ensure hostname doesn't contain protocol if user accidentally left it
    $hostname = preg_replace('#^https?://#', '', $hostname);
    
    // Build clean API URL automatically
    $apiUrl = 'https://' . $hostname . '/modules/addons/rh_reseller_parent/api.php';

    return [
        'api_url'     => $apiUrl,
        'api_key'     => trim($params['serveraccesshash'] ?? ''), // Child API Key stored in "Access Hash"
        'admin_email' => trim($params['serverusername'] ?? ''),   // Reseller Admin Email stored in "Username"
        'admin_pass'  => trim($params['serverpassword'] ?? ''),   // Reseller Admin Password stored in "Password"
    ];
}

// ── Internal: authenticate and cache reseller token for 30 mins ─
function _rh_hosting_getToken(array $server): string
{
    // Generate unique cache file name based on server URL/API key to support multi-server setups safely
    $cacheKey    = md5($server['api_url'] . $server['api_key'] . $server['admin_email']);
    $cacheFile   = sys_get_temp_dir() . '/rh_hosting_token_' . $cacheKey . '.json';
    $cacheExpiry = 1800; // 30 minutes in seconds

    if (file_exists($cacheFile)) {
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if ($cacheData && isset($cacheData['token'], $cacheData['expires']) && time() < $cacheData['expires']) {
            return $cacheData['token'];
        }
    }

    if (empty($server['admin_email']) || empty($server['admin_pass'])) {
        return '';
    }

    // Call parent authentication endpoint
    $resp = _rh_hosting_call($server, 'auth', [
        'email'    => $server['admin_email'],
        'password' => $server['admin_pass'],
    ], false);

    if (($resp['status'] ?? '') !== 'success') {
        return '';
    }

    $token = $resp['token'] ?? '';
    if ($token) {
        $cacheData = [
            'token'   => $token,
            'expires' => time() + $cacheExpiry
        ];
        @file_put_contents($cacheFile, json_encode($cacheData));
    }

    return $token;
}

// ── Internal: make an API call to the parent ──────────────────
function _rh_hosting_call(array $server, string $action, array $params = [], bool $withToken = true, string $token = ''): array
{
    $headers = [
        'X-RH-API-Key: ' . $server['api_key'],
        'Accept: application/json',
    ];

    if ($withToken) {
        if (!$token) {
            $token = _rh_hosting_getToken($server);
        }
        if ($token) {
            $headers[] = 'X-RH-User-Token: ' . $token;
        }
    }

    $postfields = array_merge($params, ['action' => $action]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $server['api_url'] . '?action=' . urlencode($action),
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postfields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 120,
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['status' => 'error', 'message' => 'cURL: ' . $err];

    $data = json_decode($response, true);
    return $data ?: ['status' => 'error', 'message' => 'Invalid JSON: ' . substr($response, 0, 100)];
}

// ── Internal: fetch products from parent panel for dropdown ──
function _rh_hosting_getProductsList(array $server): array
{
    $resp = _rh_hosting_call($server, 'getProducts', [], false);
    $products = $resp['services'] ?? [];
    
    $options = ['' => '— Select Parent Product —'];
    foreach ($products as $prod) {
        $pid   = $prod['id'] ?? $prod['pid'] ?? '';
        $name  = $prod['name'] ?? $prod['groupname'] . ' - ' . $prod['name'] ?? 'Product #' . $pid;
        if ($pid) {
            $options[$pid] = $name . ' (PID: ' . $pid . ')';
        }
    }
    return $options;
}

// ══════════════════════════════════════════════════════════════
// SERVER MODULE FUNCTIONS
// ══════════════════════════════════════════════════════════════

/**
 * Create — called when a new order is accepted and needs provisioning.
 */
function rh_hosting_reseller_CreateAccount(array $params): string
{
    try {
        // 1. Enforce that the custom fields array exists and 'parent_service_id' field is attached to the product
        if (!isset($params['customfields']) || !array_key_exists('parent_service_id', $params['customfields'])) {
            return 'error|Setup Error: The "parent_service_id" custom field has not been created or assigned to this product in WHMCS setup.';
        }

        $server    = _rh_hosting_getServer($params);
        $token     = _rh_hosting_getToken($server);
        $pid       = $params['configoption1']; // parent product PID
        $domain    = $params['domain'];
        $cycle     = $params['configoption2'] ?: ($params['billingcycle'] ?? 'monthly');
        $serviceid = trim($params['customfields']['parent_service_id']);

        // 2. (Optional safeguard) If you want to require them to put a placeholder or if it's meant to be auto-filled, 
        // handle it here. But since this field is meant to *store* the returned ID, we ensure the field structure exists.
        
        if (!$pid) return 'error|Parent Product PID not configured on server record.';
        if (!$token) return 'error|Could not authenticate reseller account with parent panel.';

        // 3. Call parent API to create the service
        $resp = _rh_hosting_call($server, 'createService', [
            'serviceid'    => $pid,
            'domain'       => $domain,
            'billingcycle' => $cycle,
        ], true, $token);

        if (($resp['status'] ?? '') === 'success') {
            $newParentServiceId = $resp['serviceid'] ?? $resp['orderid'] ?? null;

            // 4. Automatically save the returned parent service/order ID into the child's custom field
            if ($newParentServiceId && function_exists('localAPI')) {
                localAPI('UpdateClientProduct', [
                    'serviceid'    => $params['serviceid'],
                    'customfields' => base64_encode(serialize(['parent_service_id' => $newParentServiceId])),
                ]);
            }

            return 'success';
        }

        if (($resp['status'] ?? '') === 'pending') {
            return 'error|Payment pending on parent panel. Order ID: ' . ($resp['orderid'] ?? '');
        }

        return 'error|' . ($resp['message'] ?? 'Unknown error from parent');
    } catch (\Exception $e) {
        return 'error|Exception: ' . $e->getMessage();
    }
}

/**
 * Suspend — called when an account is suspended.
 */
function rh_hosting_reseller_SuspendAccount(array $params): string
{
    try {
        $server    = _rh_hosting_getServer($params);
        $token     = _rh_hosting_getToken($server);
        $serviceid = $params['customfields']['parent_service_id'] ?? null;

        if (!$serviceid) return 'error|parent_service_id custom field not set on this service.';
        if (!$token)     return 'error|Could not authenticate with parent panel.';

        $resp = _rh_hosting_call($server, 'suspendService', ['serviceid' => $serviceid], true, $token);
        return ($resp['status'] ?? '') === 'success' ? 'success' : 'error|' . ($resp['message'] ?? 'Failed');
    } catch (\Exception $e) {
        return 'error|' . $e->getMessage();
    }
}

/**
 * Unsuspend — reactivate a suspended account.
 */
function rh_hosting_reseller_UnsuspendAccount(array $params): string
{
    try {
        $server    = _rh_hosting_getServer($params);
        $token     = _rh_hosting_getToken($server);
        $serviceid = $params['customfields']['parent_service_id'] ?? null;

        if (!$serviceid) return 'error|parent_service_id custom field not set.';
        if (!$token)     return 'error|Could not authenticate with parent panel.';

        $resp = _rh_hosting_call($server, 'unsuspendService', ['serviceid' => $serviceid], true, $token);
        return ($resp['status'] ?? '') === 'success' ? 'success' : 'error|' . ($resp['message'] ?? 'Failed');
    } catch (\Exception $e) {
        return 'error|' . $e->getMessage();
    }
}

/**
 * Terminate — cancel and remove an account.
 */
function rh_hosting_reseller_TerminateAccount(array $params): string
{
    try {
        $server    = _rh_hosting_getServer($params);
        $token     = _rh_hosting_getToken($server);
        $serviceid = $params['customfields']['parent_service_id'] ?? null;

        if (!$serviceid) return 'error|parent_service_id custom field not set.';
        if (!$token)     return 'error|Could not authenticate with parent panel.';

        $resp = _rh_hosting_call($server, 'terminateService', ['serviceid' => $serviceid], true, $token);
        return ($resp['status'] ?? '') === 'success' ? 'success' : 'error|' . ($resp['message'] ?? 'Failed');
    } catch (\Exception $e) {
        return 'error|' . $e->getMessage();
    }
}

/**
 * ChangePackage — upgrade or downgrade to a different product.
 */
function rh_hosting_reseller_ChangePackage(array $params): string
{
    try {
        $server    = _rh_hosting_getServer($params);
        $token     = _rh_hosting_getToken($server);
        $serviceid = $params['customfields']['parent_service_id'] ?? null;
        $newPid    = $params['configoption1'];

        if (!$serviceid || !$newPid) return 'error|parent_service_id or Parent PID missing.';
        if (!$token) return 'error|Could not authenticate.';

        $resp = _rh_hosting_call($server, 'upgradeService', ['serviceid' => $serviceid, 'newpid' => $newPid], true, $token);
        return ($resp['status'] ?? '') === 'success' ? 'success' : 'error|' . ($resp['message'] ?? 'Failed');
    } catch (\Exception $e) {
        return 'error|' . $e->getMessage();
    }
}

/**
 * ChangePassword — change the cPanel/hosting password.
 */
function rh_hosting_reseller_ChangePassword(array $params): string
{
    try {
        $server    = _rh_hosting_getServer($params);
        $token     = _rh_hosting_getToken($server);
        $serviceid = $params['customfields']['parent_service_id'] ?? null;

        if (!$serviceid) return 'error|parent_service_id custom field not set.';
        if (!$token) return 'error|Could not authenticate.';

        $resp = _rh_hosting_call($server, 'changeServicePW', [
            'serviceid'       => $serviceid,
            'servicepassword' => $params['password'],
        ], true, $token);

        return ($resp['status'] ?? '') === 'success' ? 'success' : 'error|' . ($resp['message'] ?? 'Failed');
    } catch (\Exception $e) {
        return 'error|' . $e->getMessage();
    }
}

/**
 * LoginLink — generate an SSO link to the parent client area.
 */
function rh_hosting_reseller_ServiceSingleSignOn(array $params): array
{
    try {
        $server = _rh_hosting_getServer($params);
        $token  = _rh_hosting_getToken($server);

        if (!$token) return ['success' => false, 'errorMessage' => 'Could not authenticate with parent panel.'];

        $resp = _rh_hosting_call($server, 'loginRealhost', [], true, $token);

        if (($resp['status'] ?? '') === 'success' && !empty($resp['url'])) {
            return ['success' => true, 'redirectTo' => $resp['url']];
        }

        return ['success' => false, 'errorMessage' => $resp['message'] ?? 'SSO failed'];
    } catch (\Exception $e) {
        return ['success' => false, 'errorMessage' => $e->getMessage()];
    }
}

/**
 * TestConnection — verify the server config is correct.
 */
function rh_hosting_reseller_TestConnection(array $params): array
{
    try {
        $server = _rh_hosting_getServer($params);
        $resp   = _rh_hosting_call($server, 'auth', [
        'email'    => $server['admin_email'],
        'password' => $server['admin_pass'],
    ], false);

        if (($resp['status'] ?? '') === 'success') {
            // Successfully connected! Automatically refresh and update the local module JSON file
            _rh_hosting_cacheProducts($server);
            return ['success' => true, 'error' => ''];
        }

        return ['success' => false, 'error' => $resp['message'] ?? 'Ping failed'];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * AdminServicesTabFields — show parent service info in admin.
 */
function rh_hosting_reseller_AdminServicesTabFields(array $params): array
{
    $serviceId = $params['serviceid'] ?? 0;
    $parentServiceId = null;

    if ($serviceId && class_exists('WHMCS\Database\Capsule')) {
        $fieldId = WHMCS\Database\Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $params['packageid'] ?? 0)
            ->where('fieldname', 'LIKE', '%parent_service_id%')
            ->value('id');

        if ($fieldId) {
            $parentServiceId = WHMCS\Database\Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $fieldId)
                ->where('relid', $serviceId)
                ->value('value');
        }
    }

    if (empty($parentServiceId)) {
        $parentServiceId = $params['customfields']['parent_service_id'] ?? null;
    }

    $server = _rh_hosting_getServer($params);
    $token  = _rh_hosting_getToken($server);

    if (!$token || !$parentServiceId) {
        return ['Parent Info' => 'parent_service_id custom field not set or auth failed. (Value found: ' . ($parentServiceId ?: 'None') . ')'];
    }

    $resp = _rh_hosting_call($server, 'getServiceDetails', ['serviceid' => $parentServiceId], true, $token);
    $svc  = $resp['service'] ?? null;

    if (!$svc) {
        return ['Parent Info' => 'Could not load service ID #' . $parentServiceId . ' from parent panel.'];
    }

    return [
        'Parent Service ID'   => $svc['id']           ?? $parentServiceId,
        'Parent Type'         => strtoupper($svc['service_type'] ?? 'HOSTING'),
        'Parent Group'        => $svc['groupname']    ?? '—',
        'Parent Domain'       => $svc['domain']       ?? '—',
        'Parent Username'     => $svc['username']     ?? '—',
        'Parent Password'     => $svc['password']     ?? '—',
        'Parent Status'       => $svc['status']       ?? '—',
        'Parent Package'      => $svc['name']         ?? '—',
        'Parent Next Due'     => $svc['nextduedate']  ?? '—',
    ];
}
/**
 * ClientArea — Renders custom service management templates based on product type.
 */
 function rh_hosting_reseller_ClientArea(array $params): array
{
    $serviceId = (int)($params['serviceid'] ?? 0);
    $parentServiceId = null;

    // 1. Resolve parent_service_id (custom field first, fallback to params)
    if ($serviceId && class_exists('WHMCS\Database\Capsule')) {
        $fieldId = WHMCS\Database\Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', $params['packageid'] ?? 0)
            ->where('fieldname', 'LIKE', '%parent_service_id%')
            ->value('id');

        if ($fieldId) {
            $parentServiceId = WHMCS\Database\Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $fieldId)
                ->where('relid', $serviceId)
                ->value('value');
        }
    }

    if (empty($parentServiceId)) {
        $parentServiceId = $params['customfields']['parent_service_id'] ?? null;
    }

    $server = _rh_hosting_getServer($params);
    $token  = _rh_hosting_getToken($server);

    if (!$token) {
        return [
            'templatefile' => 'templates/error',
            'vars'         => ['error' => 'Could not authenticate with the master server. Please contact support.'],
        ];
    }

    if (!$parentServiceId) {
        return [
            'templatefile' => 'templates/error',
            'vars'         => ['error' => 'This service is not linked to a parent account. Please contact support.'],
        ];
    }

    $resp = _rh_hosting_call($server, 'getServiceDetails', ['serviceid' => $parentServiceId], true, $token);
    $svc  = $resp['service'] ?? null;

    if (!$svc) {
        return [
            'templatefile' => 'templates/error',
            'vars'         => ['error' => 'Could not load service details from the server. Please try again shortly.'],
        ];
    }

    // 2. Determine which template to render
    $serviceType = strtolower(trim($svc['service_type'] ?? ($resp['service_type'] ?? 'hosting')));

    $allowedTemplates = [
        'hosting' => 'templates/hosting',
        'vps'     => 'templates/vps',
        'license' => 'templates/license',
    ];

    // Fall back to the dedicated default.tpl for unmapped/unknown service types
    $templateFile = $allowedTemplates[$serviceType] ?? 'templates/default';

    $typeLabels = [
        'hosting' => 'Hosting Account',
        'vps'     => 'Virtual Private Server',
        'license' => 'License',
    ];

    // 3. Live account status (from the remote/parent account)
    $accountStatus = (string)($svc['status'] ?? 'Unknown');
    $accountActive = strtolower($accountStatus) === 'active';

    // 4. Resource usage (guard against unlimited/zero limits)
    $diskUsage = (float)($svc['diskusage'] ?? 0);
    $diskLimit = (float)($svc['disklimit'] ?? 0);
    $bwUsage   = (float)($svc['bwusage'] ?? 0);
    $bwLimit   = (float)($svc['bwlimit'] ?? 0);

    $diskPercent = $diskLimit > 0 ? (int)min(100, round(($diskUsage / $diskLimit) * 100)) : null;
    $bwPercent   = $bwLimit > 0 ? (int)min(100, round(($bwUsage / $bwLimit) * 100)) : null;

    // 5. Control panel login URLs (only build them when we actually have a hostname)
    $hostname  = $svc['serverhostname'] ?? '';
    $cpanelUrl = $hostname ? 'https://' . $hostname . ':2083/login/' : '';
    $whmUrl    = $hostname ? 'https://' . $hostname . ':2087/login/' : '';

    $isReseller = (strtolower($svc['hostingtype'] ?? '') === 'reseller')
        || (stripos($svc['groupname'] ?? '', 'reseller') !== false);

    // 6. WHMCS-native service info belongs to the *child* (billed) service, not
    //    the remote account — pull it from $params first, fall back to $svc.
    $serviceName         = $params['packagename'] ?? ($svc['name'] ?? ($typeLabels[$serviceType] ?? 'Service'));
    $serviceBillingCycle = $params['billingcycle'] ?? ($svc['billingcycle'] ?? '');
    $serviceRegDate      = $params['regdate'] ?? ($svc['regdate'] ?? '');
    $serviceNextDueDate  = $params['nextduedate'] ?? ($svc['nextduedate'] ?? '');
    $serviceNotes        = $svc['notes'] ?? '';

    return [
        'templatefile' => $templateFile,
        'vars'         => [
            // raw payload, kept for anything not explicitly mapped below
            'parentService' => $svc,

            'service_type'     => $serviceType,
            'serviceTypeLabel' => $typeLabels[$serviceType] ?? 'Service',

            // WHMCS-native (child/billed) service details
            'serviceName'         => $serviceName,
            'serviceBillingCycle' => $serviceBillingCycle ? ucfirst($serviceBillingCycle) : 'N/A',
            'serviceRegDate'      => $serviceRegDate ?: 'N/A',
            'serviceNextDueDate'  => $serviceNextDueDate ?: 'N/A',
            'serviceNotes'        => $serviceNotes,

            // Live remote account details
            'serviceDomain'   => htmlspecialchars($params['domain'] ?? '', ENT_QUOTES),
            'serviceUsername' => htmlspecialchars($svc['username'] ?? '', ENT_QUOTES),
            'servicePassword' => htmlspecialchars($svc['password'] ?? '', ENT_QUOTES),
            'serverIp'        => htmlspecialchars($svc['serverip'] ?? '', ENT_QUOTES),
            'serverHostname'  => htmlspecialchars($hostname, ENT_QUOTES),

            'accountStatus' => $accountStatus,
            'accountActive' => $accountActive,

            'diskusage'   => $diskUsage,
            'disklimit'   => $diskLimit,
            'diskPercent' => $diskPercent,
            'bwusage'     => $bwUsage,
            'bwlimit'     => $bwLimit,
            'bwPercent'   => $bwPercent,

            'ns1' => $svc['ns1'] ?? '',
            'ns2' => $svc['ns2'] ?? '',

            'licensekey' => $svc['licensekey'] ?? '',

            'cpanelUrl'  => $cpanelUrl,
            'whmUrl'     => $whmUrl,
            'isReseller' => $isReseller,
        ],
    ];
}
