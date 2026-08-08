<?php
/**
 * RH Domain Reseller — WHMCS Registrar Module
 * ─────────────────────────────────────────────
 * Install on CHILD WHMCS
 * Path: /modules/registrars/rh_domain_reseller/rh_domain_reseller.php
 */

if (!defined('WHMCS')) die('Direct access not permitted');
require_once ROOTDIR . '/modules/registrars/rh_domain_reseller/helpers.php';

use WHMCS\Domain\Registrar\Domain;
use WHMCS\Domain\TopLevel\ImportItem;
use WHMCS\Results\ResultsList;

// ── Module config (shown in Setup > Domain Registrars) ────────
function rh_domain_reseller_getConfigArray(): array
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'RH Domain Reseller',
        ],
        'api_url' => [
            'FriendlyName' => 'Parent API URL',
            'Type'         => 'text',
            'Size'         => 100,
            'Default'      => 'https://realhostpro.com/modules/addons/rh_reseller_parent/api.php',
            'Description'  => 'Full URL to the parent panel API endpoint.',
        ],
        'api_key' => [
            'FriendlyName' => 'Child API Key',
            'Type'         => 'text',
            'Size'         => 64,
            'Description'  => 'The API key generated for this child panel in the parent admin.',
        ],
        'admin_email' => [
            'FriendlyName' => 'Reseller Admin Email',
            'Type'         => 'text',
            'Size'         => 100,
            'Description'  => 'Account email on the parent panel used for background cron tasks & TLD syncs.',
        ],
        'admin_password' => [
            'FriendlyName' => 'Reseller Admin Password',
            'Type'         => 'text',
            'Size'         => 100,
            'Description'  => 'Password for the reseller admin email above.',
        ],
        'default_ns1' => [
            'FriendlyName' => 'Default Nameserver 1',
            'Type'         => 'text',
            'Size'         => 100,
            'Default'      => 'ns1.realhostpro.com',
        ],
        'default_ns2' => [
            'FriendlyName' => 'Default Nameserver 2',
            'Type'         => 'text',
            'Size'         => 100,
            'Default'      => 'ns2.realhostpro.com',
        ],
    ];
}


// ══════════════════════════════════════════════════════════════
// REGISTRAR MODULE FUNCTIONS
// ══════════════════════════════════════════════════════════════

function rh_domain_reseller_RegisterDomain(array $params): array
{
    try {
        // Fetch configuration dynamically from WHMCS database safely
        $cfg = [];
        if (class_exists('WHMCS\Database\Capsule')) {
            $settings = WHMCS\Database\Capsule::table('tblregistrars')
                ->where('registrar', 'rh_domain_reseller')
                ->pluck('value', 'setting');
            foreach ($settings as $setting => $value) {
                $cfg[$setting] = $value;
            }
        }
        
        $domain = $params['sld'] . '.' . $params['tld'];
        $years  = $params['regperiod'];
        $token  = _rh_domain_getToken();

        if (!$token) return _rh_domain_error('Could not authenticate with parent panel.');

        $ns = implode(',', array_filter([
            $params['ns1'] ?? $cfg['default_ns1'] ?? '',
            $params['ns2'] ?? $cfg['default_ns2'] ?? '',
            $params['ns3'] ?? '',
            $params['ns4'] ?? '',
            $params['ns5'] ?? '',
        ]));

        // Compile contact details for the parent registrar module
        $postData = [
            'domain'      => $domain,
            'years'       => $years,
            'nameservers' => $ns,
            // Registrant contact details passed from WHMCS
            'firstname'   => $params['firstname'] ?? '',
            'lastname'    => $params['lastname'] ?? '',
            'companyname' => $params['companyname'] ?? '',
            'email'       => $params['email'] ?? '',
            'address1'    => $params['address1'] ?? '',
            'address2'    => $params['address2'] ?? '',
            'city'        => $params['city'] ?? '',
            'state'       => $params['state'] ?? '',
            'postcode'    => $params['postcode'] ?? '',
            'country'     => $params['country'] ?? '',
            'phonenumber' => $params['phonenumber'] ?? '',
        ];

        $resp = _rh_domain_call('createDomain', $postData, $token);

        if (($resp['status'] ?? '') === 'success') {
            return ['success' => true];
        }

        return _rh_domain_error($resp['message'] ?? 'Registration failed');
    } catch (\Exception $e) {
        logModuleCall('rh_domain_reseller', 'RegisterDomain_Error', $params, $e->getMessage());
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_TransferDomain(array $params): array
{
    try {
        $domain   = $params['sld'] . '.' . $params['tld'];
        $epp      = $params['eppcode'] ?? '';
        $token    = _rh_domain_getToken();

        if (!$token) return _rh_domain_error('Could not authenticate with parent panel.');

        $resp = _rh_domain_call('transferDomain', ['domain' => $domain, 'epp' => $epp], $token);

        if (($resp['status'] ?? '') === 'success') return ['success' => true];
        return _rh_domain_error($resp['message'] ?? 'Transfer failed');
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_RenewDomain(array $params): array
{
    try {
         
         
        $domain   = $params['sld'] . '.' . $params['tld'];
        $years    = $params['regperiod'];
        $token    = _rh_domain_getToken();

        if (!$token) return _rh_domain_error('Could not authenticate with parent panel.');

        $myDomains = _rh_domain_call('getMyDomains', [], $token);
        $parentDomainId = null;
        foreach ($myDomains['domains'] ?? [] as $d) {
            if (strtolower($d['domainname'] ?? '') === strtolower($domain)) {
                $parentDomainId = $d['id'];
                break;
            }
        }

        if (!$parentDomainId) return _rh_domain_error('Domain not found on parent panel.');

        $resp = _rh_domain_call('renewDomain', ['domainid' => $parentDomainId, 'years' => $years], $token);

        if (($resp['status'] ?? '') === 'success') return ['success' => true];
        return _rh_domain_error($resp['message'] ?? 'Renewal failed');
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_GetNameservers(array $params): array
{
    try {
        $domain = $params['sld'] . '.' . $params['tld'];
        $token  = _rh_domain_getToken();

        if (!$token) {
            return _rh_domain_error('Could not authenticate.');
        }

        // Call parent API specifically for this domain's nameservers
        $resp = _rh_domain_call('getNameservers', [
            'domain' => $domain
        ], $token);

        if (($resp['status'] ?? '') !== 'success') {
            return _rh_domain_error($resp['message'] ?? 'Could not fetch nameservers.');
        }

        return [
            'ns1' => $resp['ns1'] ?? '',
            'ns2' => $resp['ns2'] ?? '',
            'ns3' => $resp['ns3'] ?? '',
            'ns4' => $resp['ns4'] ?? '',
            'ns5' => $resp['ns5'] ?? '',
        ];
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_SaveNameservers(array $params): array
{
    try {
         
         
        $domain   = $params['sld'] . '.' . $params['tld'];
        $token    = _rh_domain_getToken();

        if (!$token) return _rh_domain_error('Could not authenticate.');

        $myDomains = _rh_domain_call('getMyDomains', [], $token);
        $parentDomainId = null;
        foreach ($myDomains['domains'] ?? [] as $d) {
            if (strtolower($d['domainname'] ?? '') === strtolower($domain)) {
                $parentDomainId = $d['id'];
                break;
            }
        }
        if (!$parentDomainId) return _rh_domain_error('Domain not found on parent.');

        $callParams = ['domainid' => $parentDomainId];
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($params['ns' . $i])) $callParams['ns' . $i] = $params['ns' . $i];
        }

        $resp = _rh_domain_call('changeNSDomain', $callParams, $token);

        if (($resp['status'] ?? '') === 'success') return ['success' => true];
        return _rh_domain_error($resp['message'] ?? 'Nameserver update failed');
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_GetRegistrarLock(array $params): string
{
    try {
         
         
        $domain   = $params['sld'] . '.' . $params['tld'];
        $token    = _rh_domain_getToken();

        if (!$token) return 'unlocked';

        $myDomains = _rh_domain_call('getMyDomains', [], $token);
        foreach ($myDomains['domains'] ?? [] as $d) {
            if (strtolower($d['domainname'] ?? '') === strtolower($domain)) {
                return ($d['reglock'] ?? 0) ? 'locked' : 'unlocked';
            }
        }
        return 'unlocked';
    } catch (\Exception $e) {
        return 'unlocked';
    }
}

function rh_domain_reseller_SaveRegistrarLock(array $params): array
{
    try {
         
         
        $domain   = $params['sld'] . '.' . $params['tld'];
        $token    = _rh_domain_getToken();
        $lock     = $params['lockenabled'] ? 'on' : 'off';

        if (!$token) return _rh_domain_error('Could not authenticate.');

        $myDomains = _rh_domain_call('getMyDomains', [], $token);
        $parentDomainId = null;
        foreach ($myDomains['domains'] ?? [] as $d) {
            if (strtolower($d['domainname'] ?? '') === strtolower($domain)) {
                $parentDomainId = $d['id'];
                break;
            }
        }
        if (!$parentDomainId) return _rh_domain_error('Domain not found on parent.');

        $resp = _rh_domain_call('toggleDomainLock', ['domainid' => $parentDomainId, 'lock' => $lock], $token);

        if (($resp['status'] ?? '') === 'success') return ['success' => true];
        return _rh_domain_error($resp['message'] ?? 'Lock update failed');
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_GetDNS(array $params): array
{
    try {
         
         
        $domain   = $params['sld'] . '.' . $params['tld'];
        $token    = _rh_domain_getToken();

        if (!$token) return _rh_domain_error('Could not authenticate.');

        $myDomains = _rh_domain_call('getMyDomains', [], $token);
        $parentDomainId = null;
        foreach ($myDomains['domains'] ?? [] as $d) {
            if (strtolower($d['domainname'] ?? '') === strtolower($domain)) {
                $parentDomainId = $d['id'];
                break;
            }
        }
        if (!$parentDomainId) return _rh_domain_error('Domain not found on parent.');

        $resp    = _rh_domain_call('getDNSRecords', ['domainid' => $parentDomainId], $token);
        $records = $resp['records'] ?? [];

        $dnsRecords = [];
        foreach ($records as $rec) {
            $dnsRecords[] = [
                'hostname' => $rec['hostname'] ?? '',
                'type'     => $rec['type']     ?? 'A',
                'address'  => $rec['value']    ?? '',
                'priority' => $rec['priority'] ?? '',
            ];
        }
        return $dnsRecords;
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_SaveDNS(array $params): array
{
    try {
         
         
        $domain   = $params['sld'] . '.' . $params['tld'];
        $token    = _rh_domain_getToken();

        if (!$token) return _rh_domain_error('Could not authenticate.');

        $myDomains = _rh_domain_call('getMyDomains', [], $token);
        $parentDomainId = null;
        foreach ($myDomains['domains'] ?? [] as $d) {
            if (strtolower($d['domainname'] ?? '') === strtolower($domain)) {
                $parentDomainId = $d['id'];
                break;
            }
        }
        if (!$parentDomainId) return _rh_domain_error('Domain not found on parent.');

        $records = [];
        foreach ($params['dnsrecords'] ?? [] as $rec) {
            $records[] = [
                'hostname' => $rec['hostname'] ?? '',
                'type'     => $rec['type']     ?? 'A',
                'value'    => $rec['address']  ?? '',
                'priority' => $rec['priority'] ?? '',
            ];
        }

        $resp = _rh_domain_call('saveDNSRecords', ['domainid' => $parentDomainId, 'records' => $records], $token);

        if (($resp['status'] ?? '') === 'success') return ['success' => true];
        return _rh_domain_error($resp['message'] ?? 'DNS save failed');
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_GetEPPCode(array $params): array
{
    try {
         
         
        $domain   = $params['sld'] . '.' . $params['tld'];
        $token    = _rh_domain_getToken();

        if (!$token) return _rh_domain_error('Could not authenticate.');

        $resp = _rh_domain_call('getDomainEPP', ['domain' => $domain], $token);

        if (($resp['status'] ?? '') === 'success') {
            return ['eppcode' => $resp['eppcode'] ?? ''];
        }
        return _rh_domain_error($resp['message'] ?? 'EPP fetch failed');
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

// ── NEW FEATURE: Domain Synchronization (`Sync`) ───────────────
function rh_domain_reseller_Sync(array $params): array
{
    try {
        $domain   = $params['domain'];
         
         
        
        // Fallback admin token connection if user context is missing during background cron
        $token =  _rh_domain_getToken();
        
        $myDomains = _rh_domain_call('getMyDomains', [], $token);
        foreach ($myDomains['domains'] ?? [] as $d) {
            if (strtolower($d['domainname'] ?? '') === strtolower($domain)) {
                $status = strtolower($d['status'] ?? '');
                $isActive = ($status === 'active');
                $isCancelled = ($status === 'cancelled' || $status === 'terminated');
                $isTransferredAway = ($status === 'transferred away');

                return [
                    'active'          => $isActive,
                    'cancelled'       => $isCancelled,
                    'transferredAway' => $isTransferredAway,
                    'expirydate'      => $d['expirydate'] ?? null,
                ];
            }
        }
        return ['error' => 'Domain not found on parent panel'];
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// ── NEW FEATURE: Transfer Synchronization (`TransferSync`) ─────
function rh_domain_reseller_TransferSync(array $params): array
{
    try {
        $domain   = $params['domain'];
         $token =  _rh_domain_getToken();

        $myDomains = _rh_domain_call('getMyDomains', [], $token);
        foreach ($myDomains['domains'] ?? [] as $d) {
            if (strtolower($d['domainname'] ?? '') === strtolower($domain)) {
                $status = strtolower($d['status'] ?? '');
                if ($status === 'active') {
                    return [
                        'completed'  => true,
                        'expirydate' => $d['expirydate'] ?? null,
                    ];
                }
            }
        }
        return ['completed' => false];
    } catch (\Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

// ── NEW FEATURE: WHOIS Contact Details Management ─────────────
function rh_domain_reseller_GetContactDetails(array $params): array
{
    try {
         
         
        $domain   = $params['sld'] . '.' . $params['tld'];
        $token    = _rh_domain_getToken();

        if (!empty($params['customadminpassword'])) {
            // Fallback or handle custom parameters if required
        }

        $resp = _rh_domain_call('getClientSummary', [], $token);
        $c = $resp['client'] ?? [];

        // Return standard WHMCS registrant contact mapping structure
        return [
            'Registrant' => [
                'First Name'   => $c['firstname'] ?? '',
                'Last Name'    => $c['lastname'] ?? '',
                'Company Name' => $c['companyname'] ?? '',
                'Email Address'=> $c['email'] ?? '',
                'Address 1'    => $c['address1'] ?? '',
                'Address 2'    => $c['address2'] ?? '',
                'City'         => $c['city'] ?? '',
                'State'        => $c['state'] ?? '',
                'Postcode'     => $c['postcode'] ?? '',
                'Country'      => $c['country'] ?? '',
                'Phone Number' => $c['phonenumber'] ?? '',
            ],
        ];
    } catch (\Exception $e) {
        return [];
    }
}

function rh_domain_reseller_SaveContactDetails(array $params): array
{
    try {
         
         
        $token    = _rh_domain_getToken();

        $contact  = $params['contactdetails']['Registrant'] ?? [];
        $updatePayload = [
            'firstname'   => $contact['First Name'] ?? '',
            'lastname'    => $contact['Last Name'] ?? '',
            'companyname' => $contact['Company Name'] ?? '',
            'address1'    => $contact['Address 1'] ?? '',
            'address2'    => $contact['Address 2'] ?? '',
            'city'        => $contact['City'] ?? '',
            'state'       => $contact['State'] ?? '',
            'postcode'    => $contact['Postcode'] ?? '',
            'country'     => $contact['Country'] ?? '',
            'phonenumber' => $contact['Phone Number'] ?? '',
        ];

        $resp = _rh_domain_call('updateContactDetails', $updatePayload, $token);
        if (($resp['status'] ?? '') === 'success') return ['success' => true];

        return _rh_domain_error($resp['message'] ?? 'Failed to update contact details');
    } catch (\Exception $e) {
        return _rh_domain_error($e->getMessage());
    }
}

function rh_domain_reseller_CheckAvailability(array $params): ResultsList
{
    $results = new ResultsList();

    try {
        $token    = '';
        $token =  _rh_domain_getToken();

        foreach ($params['domains'] ?? [] as $domainObj) {
            $sld    = $domainObj->getSld();
            $tld    = $domainObj->getTld();
            $domain = $sld . '.' . $tld;

            $resp = _rh_domain_call('searchDomain', ['domain' => $domain], $token);

            $result = new \WHMCS\Domain\Registrar\SearchResult($sld, $tld);
            $result->setStatus(
                ($resp['available'] ?? false)
                    ? \WHMCS\Domain\Registrar\SearchResult::STATUS_NOT_REGISTERED
                    : \WHMCS\Domain\Registrar\SearchResult::STATUS_REGISTERED
            );
            $results->append($result);
        }
    } catch (\Exception $e) {
        // Return empty on exception
    }

    return $results;
}

function rh_domain_reseller_GetTLDPricing(array $params): ResultsList
{
    $results = new ResultsList();

    try {
        $currencyId = $params['currencyId'] ?? 1;
        
        // Fetch token using the admin/reseller credentials
        $token = _rh_domain_getToken();
        
        $resp    = _rh_domain_call('domainPricing', ['currencyid' => $currencyId], $token);
        $pricing = $resp['pricing'] ?? $resp['tlds'] ?? [];

        if (empty($pricing)) {
            return $results;
        }

        // Get currency code returned by parent API or default to USD
        $currencyCode = $resp['currency']['code'] ?? 'USD';

        foreach ($pricing as $tld => $tldData) {
            $extension = '.' . ltrim($tld, '.');

            // Extract 1-year baseline prices
            $registerPrice = (float) ($tldData['register']['1'] ?? $tldData['register'][1] ?? 0);
            $renewPrice    = (float) ($tldData['renew']['1']    ?? $tldData['renew'][1]    ?? 0);
            $transferPrice = (float) ($tldData['transfer']['1'] ?? $tldData['transfer'][1] ?? 0);

            // Construct the WHMCS ImportItem
            $item = (new ImportItem())
                ->setExtension($extension)
                ->setMinYears(1)
                ->setMaxYears(10)
                ->setRegisterPrice($registerPrice)
                ->setRenewPrice($renewPrice)
                ->setTransferPrice($transferPrice)
                ->setCurrency($currencyCode);

            $results->append($item);
        }
    } catch (\Exception $e) {
        logModuleCall('rh_domain_reseller', 'GetTLDPricing_Error', $params, $e->getMessage());
    }

    return $results;
}