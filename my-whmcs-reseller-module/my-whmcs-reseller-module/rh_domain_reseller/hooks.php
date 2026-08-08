<?php
/**
 * RH Domain Reseller — Admin Homepage Balance Widget Hook
 * Path: /modules/registrars/rh_domain_reseller/hooks.php
 */

if (!defined('WHMCS')) {
    die('Direct access not permitted');
}

use WHMCS\Module\AbstractWidget;

class RHResellerBalanceWidget extends AbstractWidget
{
    protected $title = 'RH Reseller Account Balance';
    protected $description = 'Displays current live parent reseller balance and account status.';
    protected $weight = 150;
    protected $columns = 1;
    protected $cache = false;

    public function getData()
    {
        $helperPath = ROOTDIR . '/modules/registrars/rh_domain_reseller/helpers.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
        }

        if (!function_exists('_rh_domain_getToken') || !function_exists('_rh_domain_call')) {
            return [];
        }

        try {
            $token = _rh_domain_getToken();
            if (!$token) {
                return [];
            }

            $resp = _rh_domain_call('getClientSummary', [], $token);
            $client = $resp['client'] ?? [];

            return [
                'balance'      => (float) ($client['credit'] ?? 0.00),
                'currencyCode' => $client['currency_code'] ?? 'USD',
                'clientName'   => trim(($client['firstname'] ?? '') . ' ' . ($client['lastname'] ?? 'Reseller Account')),
                'clientStatus' => ucfirst($client['status'] ?? 'Active'),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function generateOutput($vars)
    {
        $balance      = $vars['balance'] ?? 0.00;
        $currencyCode = $vars['currencyCode'] ?? 'USD';
        $clientName   = $vars['clientName'] ?? 'Reseller Account';
        $clientStatus = $vars['clientStatus'] ?? 'Active';

        $badgeClass = $balance > 10 ? 'success' : ($balance > 0 ? 'warning' : 'danger');

        return '
        <div class="widget-panel" style="background:#fff; border:1px solid #e0e0e0; border-radius:4px; padding:15px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
            <div style="font-size:12px; font-weight:600; text-transform:uppercase; color:#666; margin-bottom:5px;">
                Parent Reseller Balance
            </div>
            <div style="font-size:22px; font-weight:bold; color:#333; margin-bottom:5px;">
                ' . htmlspecialchars($currencyCode) . ' ' . number_format($balance, 2) . '
            </div>
            <div style="font-size:11px; color:#888;">
                <span class="label label-' . $badgeClass . '">' . $clientStatus . '</span> 
                &bull; ' . htmlspecialchars($clientName) . '
            </div>
        </div>';
    }
}

add_hook('AdminHomeWidgets', 1, function () {
    return new RHResellerBalanceWidget();
});