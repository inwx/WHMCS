<?php

namespace INWX\Markups\Controllers;

use INWX\Markups\Models\TldDefault;
use INWX\Markups\Models\TldOverride;
use INWX\Markups\Services\NotificationService;
use INWX\Markups\Services\ValidationService;
use INWX\Markups\Views\MarkupsView;
use WHMCS\Database\Capsule;

/**
 * MarkupsController.
 *
 * Handles request routing and coordinates between models and views.
 */
class MarkupsController
{
    private const DEFAULT_RESULTS_PER_PAGE = 10;

    /** @var MarkupsView */
    private $view;

    public function __construct()
    {
        $this->view = new MarkupsView();
    }

    /**
     * Render the admin area sidebar.
     */
    public function sidebar(array $vars): string
    {
        return $this->view->renderSidebar($vars);
    }

    /**
     * Entry point for admin output.
     */
    public function output(array $vars): void
    {
        $moduleLink = $vars['modulelink'] ?? 'addonmodules.php?module=inwx_markups';
        $currentTool = $_GET['tool'] ?? 'markups';

        if ($currentTool === 'markups') {
            $this->outputMarkups($moduleLink);
        } else {
            echo $this->view->renderError(inwx_markups_lang('error.toolNotFound', 'Tool not found.'));
        }
    }

    /**
     * Output handler for TLD Markups tool.
     */
    private function outputMarkups(string $moduleLink): void
    {
        inwx_markups_ensureSchema();

        $action = $_REQUEST['action'] ?? '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($action, $moduleLink);
            return;
        }

        if ($action === 'delete') {
            $this->handleDelete($moduleLink);
        }

        $this->renderMarkupsPage($moduleLink);
    }

    /**
     * Render the markups management page.
     */
    private function renderMarkupsPage(string $moduleLink): void
    {
        [$search, $page, $perPage, $perPageOptions] = $this->getListParams();

        try {
            // Use transaction to ensure count and data are from same snapshot
            Capsule::connection()->beginTransaction();

            try {
                $currencies = Capsule::table('tblcurrencies')->orderBy('code')->get();
                $tlds = Capsule::table('tbldomainpricing')
                    ->where('autoreg', 'inwx')
                    ->orderBy('extension')
                    ->get();

                $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
                $editRow = $editId > 0 ? TldOverride::findById($editId) : null;

                $defaults = TldDefault::getAll();

                // Calculate pagination before queries to avoid race condition
                $perPage = max(1, $perPage);
                $offset = ($page - 1) * $perPage;

                $searchTerm = $search !== '' ? $search : null;
                $currenciesArray = $currencies->toArray();

                $totalCount = TldOverride::countActiveOverrides($searchTerm, $currenciesArray);
                $overrides = TldOverride::getActiveOverrides($searchTerm, $currenciesArray, $perPage, $offset);
                Capsule::connection()->commit();
            } catch (\Throwable $txException) {
                Capsule::connection()->rollBack();
                throw $txException;
            }

            $totalPages = max(1, (int) ceil($totalCount / $perPage));
        } catch (\Throwable $e) {
            echo $this->view->renderError(
                inwx_markups_lang('error.loadData', 'Error loading data: ') . htmlspecialchars($e->getMessage())
            );
            return;
        }

        $this->view->renderMarkupsPage([
            'moduleLink' => $moduleLink,
            'currencies' => $currencies,
            'tlds' => $tlds,
            'defaults' => $defaults,
            'overrides' => $overrides,
            'editRow' => $editRow,
            'search' => $search,
            'page' => $page,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * Handle POST actions.
     */
    private function handlePost(string $action, string $moduleLink): void
    {
        if ($action === 'save') {
            $this->handleSave($moduleLink);
        } elseif ($action === 'bulk') {
            $this->handleBulk($moduleLink);
        }
    }

    /**
     * Handle delete action.
     */
    private function handleDelete(string $moduleLink): void
    {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            return;
        }

        $success = TldOverride::deleteById($id);

        $redirectParams = $success ? ['deleted' => '1'] : ['error' => '1'];
        $this->preserveListParams($redirectParams);

        header('Location: ' . inwx_markups_buildUrl($moduleLink, 'markups', $redirectParams));
        exit;
    }

    /**
     * Handle single-rule save.
     */
    private function handleSave(string $moduleLink): void
    {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $tld = trim((string) ($_POST['tld'] ?? ''));
        $currencyId = (int) ($_POST['currency_id'] ?? 0);
        $mode = (string) ($_POST['mode'] ?? 'none');

        $error = ValidationService::validateSaveParams($tld, $currencyId, $mode);
        if ($error !== null) {
            $this->redirect($moduleLink, ['error' => '1', 'msg' => $error]);
            return;
        }

        if ($mode === 'none') {
            $success = $id > 0
                ? TldOverride::deleteById($id)
                : TldOverride::deleteByTldAndCurrency($tld, $currencyId);

            $this->redirect($moduleLink, $success ? ['saved' => '1'] : ['error' => '1']);
            return;
        }

        // Validate rounding ending before building data
        $roundingEndingRaw = trim((string) ($_POST['rounding_ending'] ?? ''));
        if ($roundingEndingRaw !== '') {
            $roundingEnding = (float) str_replace(',', '.', $roundingEndingRaw);
            $error = ValidationService::validateRoundingEnding($roundingEnding);
            if ($error !== null) {
                $this->redirect($moduleLink, ['error' => '1', 'msg' => $error]);
                return;
            }
        }

        $data = $this->buildSaveData($tld, $currencyId, $mode);

        if ($mode === 'markup') {
            $error = ValidationService::validateMarkupFields([
                ['type' => $data['markup_type_register'], 'value' => $_POST['markup_value_register'] ?? '', 'label' => 'Register'],
                ['type' => $data['markup_type_renew'], 'value' => $_POST['markup_value_renew'] ?? '', 'label' => 'Renew'],
                ['type' => $data['markup_type_transfer'], 'value' => $_POST['markup_value_transfer'] ?? '', 'label' => 'Transfer'],
                ['type' => $data['markup_type_restore'], 'value' => $_POST['markup_value_restore'] ?? '', 'label' => 'Restore'],
            ]);

            if ($error !== null) {
                $this->redirect($moduleLink, ['error' => '1', 'msg' => $error]);
                return;
            }
        }

        $success = TldOverride::save($data, $id);

        if ($success) {
            logActivity('INWX Markups: About to send markup notification');
            $this->sendMarkupNotification($data, $id > 0 ? 'updated' : 'created');
            logActivity('INWX Markups: Markup notification method completed');
        } else {
            logActivity('INWX Markups: Save failed, no notification sent');
        }

        $this->redirect($moduleLink, $success ? ['saved' => '1'] : ['error' => '1']);
    }

    /**
     * Handle bulk configuration save.
     */
    private function handleBulk(string $moduleLink): void
    {
        $currencyId = (int) ($_POST['bulk_currency_id'] ?? 0);
        $mode = (string) ($_POST['bulk_mode'] ?? 'none');

        $error = ValidationService::validateBulkParams($currencyId, $mode);
        if ($error !== null) {
            $this->redirect($moduleLink, ['error' => '1', 'msg' => $error]);
            return;
        }

        // Validate rounding ending before building data
        $bulkRoundingRaw = trim((string) ($_POST['bulk_rounding_ending'] ?? ''));
        if ($bulkRoundingRaw !== '') {
            $bulkRoundingEnding = (float) str_replace(',', '.', $bulkRoundingRaw);
            $error = ValidationService::validateRoundingEnding($bulkRoundingEnding);
            if ($error !== null) {
                $this->redirect($moduleLink, ['error' => '1', 'msg' => $error]);
                return;
            }
        }

        if ($mode === 'markup') {
            $error = ValidationService::validateMarkupFields([
                ['type' => $_POST['bulk_markup_type_register'] ?? '', 'value' => $_POST['bulk_markup_value_register'] ?? '', 'label' => 'Register'],
                ['type' => $_POST['bulk_markup_type_renew'] ?? '', 'value' => $_POST['bulk_markup_value_renew'] ?? '', 'label' => 'Renew'],
                ['type' => $_POST['bulk_markup_type_transfer'] ?? '', 'value' => $_POST['bulk_markup_value_transfer'] ?? '', 'label' => 'Transfer'],
                ['type' => $_POST['bulk_markup_type_restore'] ?? '', 'value' => $_POST['bulk_markup_value_restore'] ?? '', 'label' => 'Restore'],
            ], true);

            if ($error !== null) {
                $this->redirect($moduleLink, ['error' => '1', 'msg' => $error]);
                return;
            }
        }

        $data = $this->buildBulkData($currencyId, $mode);
        $success = TldDefault::saveForCurrency($currencyId, $data);

        if ($success) {
            logActivity('INWX Markups: About to send bulk markup notification');
            $this->sendBulkMarkupNotification($data);
            logActivity('INWX Markups: Bulk markup notification method completed');
        } else {
            logActivity('INWX Markups: Bulk save failed, no notification sent');
        }

        $this->redirect($moduleLink, $success ? ['saved' => '1'] : ['error' => '1']);
    }

    /**
     * Build save data array from POST.
     */
    private function buildSaveData(string $tld, int $currencyId, string $mode): array
    {
        $fixedRegister = $_POST['fixed_register'] ?? '';
        $fixedRenew = $_POST['fixed_renew'] ?? '';
        $fixedTransfer = $_POST['fixed_transfer'] ?? '';
        $fixedRestore = $_POST['fixed_restore'] ?? '';

        if ($mode === 'fixed' && $fixedRegister !== '') {
            $fixedRenew = $fixedRenew !== '' ? $fixedRenew : $fixedRegister;
            $fixedTransfer = $fixedTransfer !== '' ? $fixedTransfer : $fixedRegister;
            $fixedRestore = $fixedRestore !== '' ? $fixedRestore : $fixedRegister;
        }

        $roundingEndingRaw = trim((string) ($_POST['rounding_ending'] ?? ''));
        $roundingEnding = $roundingEndingRaw !== '' ? (float) str_replace(',', '.', $roundingEndingRaw) : null;

        $markupTypeRegister = trim((string) ($_POST['markup_type_register'] ?? ''));
        $markupValueRegister = trim((string) ($_POST['markup_value_register'] ?? ''));
        $markupTypeRenew = trim((string) ($_POST['markup_type_renew'] ?? ''));
        $markupValueRenew = trim((string) ($_POST['markup_value_renew'] ?? ''));
        $markupTypeTransfer = trim((string) ($_POST['markup_type_transfer'] ?? ''));
        $markupValueTransfer = trim((string) ($_POST['markup_value_transfer'] ?? ''));
        $markupTypeRestore = trim((string) ($_POST['markup_type_restore'] ?? ''));
        $markupValueRestore = trim((string) ($_POST['markup_value_restore'] ?? ''));

        return [
            'tld' => $tld,
            'currency_id' => $currencyId,
            'mode' => $mode,
            'fixed_register' => $mode === 'fixed' && $fixedRegister !== '' ? (float) str_replace(',', '.', $fixedRegister) : null,
            'fixed_renew' => $mode === 'fixed' && $fixedRenew !== '' ? (float) str_replace(',', '.', $fixedRenew) : null,
            'fixed_transfer' => $mode === 'fixed' && $fixedTransfer !== '' ? (float) str_replace(',', '.', $fixedTransfer) : null,
            'fixed_restore' => $mode === 'fixed' && $fixedRestore !== '' ? (float) str_replace(',', '.', $fixedRestore) : null,
            'markup_type_register' => $mode === 'markup' && in_array($markupTypeRegister, ['percent', 'fixed'], true) ? $markupTypeRegister : null,
            'markup_value_register' => $mode === 'markup' && $markupTypeRegister !== '' && $markupValueRegister !== '' ? (float) str_replace(',', '.', $markupValueRegister) : null,
            'markup_type_renew' => $mode === 'markup' && in_array($markupTypeRenew, ['percent', 'fixed'], true) ? $markupTypeRenew : null,
            'markup_value_renew' => $mode === 'markup' && $markupTypeRenew !== '' && $markupValueRenew !== '' ? (float) str_replace(',', '.', $markupValueRenew) : null,
            'markup_type_transfer' => $mode === 'markup' && in_array($markupTypeTransfer, ['percent', 'fixed'], true) ? $markupTypeTransfer : null,
            'markup_value_transfer' => $mode === 'markup' && $markupTypeTransfer !== '' && $markupValueTransfer !== '' ? (float) str_replace(',', '.', $markupValueTransfer) : null,
            'markup_type_restore' => $mode === 'markup' && in_array($markupTypeRestore, ['percent', 'fixed'], true) ? $markupTypeRestore : null,
            'markup_value_restore' => $mode === 'markup' && $markupTypeRestore !== '' && $markupValueRestore !== '' ? (float) str_replace(',', '.', $markupValueRestore) : null,
            'rounding_ending' => $roundingEnding,
        ];
    }

    /**
     * Build bulk data array from POST.
     */
    private function buildBulkData(int $currencyId, string $mode): array
    {
        $fixedRegister = $_POST['bulk_fixed_register'] ?? '';
        $fixedRenew = $_POST['bulk_fixed_renew'] ?? '';
        $fixedTransfer = $_POST['bulk_fixed_transfer'] ?? '';
        $fixedRestore = $_POST['bulk_fixed_restore'] ?? '';

        if ($mode === 'fixed' && $fixedRegister !== '') {
            $fixedRenew = $fixedRenew !== '' ? $fixedRenew : $fixedRegister;
            $fixedTransfer = $fixedTransfer !== '' ? $fixedTransfer : $fixedRegister;
            $fixedRestore = $fixedRestore !== '' ? $fixedRestore : $fixedRegister;
        }

        $bulkRoundingRaw = trim((string) ($_POST['bulk_rounding_ending'] ?? ''));
        $bulkRoundingEnding = $bulkRoundingRaw !== '' ? (float) str_replace(',', '.', $bulkRoundingRaw) : null;

        $markupTypeRegister = trim((string) ($_POST['bulk_markup_type_register'] ?? ''));
        $markupValueRegister = trim((string) ($_POST['bulk_markup_value_register'] ?? ''));
        $markupTypeRenew = trim((string) ($_POST['bulk_markup_type_renew'] ?? ''));
        $markupValueRenew = trim((string) ($_POST['bulk_markup_value_renew'] ?? ''));
        $markupTypeTransfer = trim((string) ($_POST['bulk_markup_type_transfer'] ?? ''));
        $markupValueTransfer = trim((string) ($_POST['bulk_markup_value_transfer'] ?? ''));
        $markupTypeRestore = trim((string) ($_POST['bulk_markup_type_restore'] ?? ''));
        $markupValueRestore = trim((string) ($_POST['bulk_markup_value_restore'] ?? ''));

        return [
            'currency_id' => $currencyId,
            'mode' => $mode,
            'fixed_register' => $mode === 'fixed' && $fixedRegister !== '' ? (float) str_replace(',', '.', $fixedRegister) : null,
            'fixed_renew' => $mode === 'fixed' && $fixedRenew !== '' ? (float) str_replace(',', '.', $fixedRenew) : null,
            'fixed_transfer' => $mode === 'fixed' && $fixedTransfer !== '' ? (float) str_replace(',', '.', $fixedTransfer) : null,
            'fixed_restore' => $mode === 'fixed' && $fixedRestore !== '' ? (float) str_replace(',', '.', $fixedRestore) : null,
            'markup_type_register' => $mode === 'markup' && in_array($markupTypeRegister, ['percent', 'fixed'], true) ? $markupTypeRegister : null,
            'markup_value_register' => $mode === 'markup' && $markupTypeRegister !== '' && $markupValueRegister !== '' ? (float) str_replace(',', '.', $markupValueRegister) : null,
            'markup_type_renew' => $mode === 'markup' && in_array($markupTypeRenew, ['percent', 'fixed'], true) ? $markupTypeRenew : null,
            'markup_value_renew' => $mode === 'markup' && $markupTypeRenew !== '' && $markupValueRenew !== '' ? (float) str_replace(',', '.', $markupValueRenew) : null,
            'markup_type_transfer' => $mode === 'markup' && in_array($markupTypeTransfer, ['percent', 'fixed'], true) ? $markupTypeTransfer : null,
            'markup_value_transfer' => $mode === 'markup' && $markupTypeTransfer !== '' && $markupValueTransfer !== '' ? (float) str_replace(',', '.', $markupValueTransfer) : null,
            'markup_type_restore' => $mode === 'markup' && in_array($markupTypeRestore, ['percent', 'fixed'], true) ? $markupTypeRestore : null,
            'markup_value_restore' => $mode === 'markup' && $markupTypeRestore !== '' && $markupValueRestore !== '' ? (float) str_replace(',', '.', $markupValueRestore) : null,
            'rounding_ending' => $bulkRoundingEnding,
        ];
    }

    /**
     * Get search and pagination parameters.
     *
     * @return array{0:string,1:int,2:int,3:array<int>}
     */
    private function getListParams(): array
    {
        $search = trim((string) ($_GET['search'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $perPageOptions = [5, 10, 25, 50, 100];
        $perPageRaw = (int) ($_GET['per_page'] ?? self::DEFAULT_RESULTS_PER_PAGE);
        $perPage = in_array($perPageRaw, $perPageOptions, true) && $perPageRaw > 0
            ? $perPageRaw
            : self::DEFAULT_RESULTS_PER_PAGE;

        return [$search, $page, $perPage, $perPageOptions];
    }

    /**
     * Preserve list params across redirects.
     */
    private function preserveListParams(array &$params): void
    {
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $params['search'] = $_GET['search'];
        }
        if (isset($_GET['page']) && $_GET['page'] > 1) {
            $params['page'] = (string) ((int) $_GET['page']);
        }
        if (isset($_GET['per_page']) && in_array((int) $_GET['per_page'], [5, 10, 25, 50, 100], true) && (int) $_GET['per_page'] !== self::DEFAULT_RESULTS_PER_PAGE) {
            $params['per_page'] = (string) ((int) $_GET['per_page']);
        }
    }

    /**
     * Redirect helper.
     */
    private function redirect(string $moduleLink, array $params): void
    {
        header('Location: ' . inwx_markups_buildUrl($moduleLink, 'markups', $params));
        exit;
    }

    /**
     * Send notification when markup is created or updated.
     */
    private function sendMarkupNotification(array $data, string $action): void
    {
        try {
            $currency = Capsule::table('tblcurrencies')
                ->where('id', $data['currency_id'])
                ->first();
            $currencyCode = $currency ? $currency->code : $data['currency_id'];

            $lines = [];
            $lines[] = "TLD: {$data['tld']}";
            $lines[] = "Currency: {$currencyCode}";
            $lines[] = "Mode: {$data['mode']}";

            if ($data['mode'] === 'fixed') {
                if ($data['fixed_register'] !== null) {
                    $lines[] = "Fixed Register: {$data['fixed_register']}";
                }
                if ($data['fixed_renew'] !== null) {
                    $lines[] = "Fixed Renew: {$data['fixed_renew']}";
                }
                if ($data['fixed_transfer'] !== null) {
                    $lines[] = "Fixed Transfer: {$data['fixed_transfer']}";
                }
                if ($data['fixed_restore'] !== null) {
                    $lines[] = "Fixed Restore: {$data['fixed_restore']}";
                }
            } elseif ($data['mode'] === 'markup') {
                if ($data['markup_type_register'] !== null && $data['markup_value_register'] !== null) {
                    $lines[] = "Register Markup: {$data['markup_value_register']} ({$data['markup_type_register']})";
                }
                if ($data['markup_type_renew'] !== null && $data['markup_value_renew'] !== null) {
                    $lines[] = "Renew Markup: {$data['markup_value_renew']} ({$data['markup_type_renew']})";
                }
                if ($data['markup_type_transfer'] !== null && $data['markup_value_transfer'] !== null) {
                    $lines[] = "Transfer Markup: {$data['markup_value_transfer']} ({$data['markup_type_transfer']})";
                }
                if ($data['markup_type_restore'] !== null && $data['markup_value_restore'] !== null) {
                    $lines[] = "Restore Markup: {$data['markup_value_restore']} ({$data['markup_type_restore']})";
                }
            }

            if ($data['rounding_ending'] !== null) {
                $lines[] = "Rounding Ending: {$data['rounding_ending']}";
            }

            $actionText = $action === 'created' ? 'created' : 'updated';
            $subject = "INWX Markup {$actionText}: {$data['tld']} [{$currencyCode}]";
            $body = "A markup configuration was {$actionText}:\n\n" . implode("\n", $lines) . "\n\nTime: " . date('c');

            NotificationService::sendAdminEmail($subject, $body);
        } catch (\Throwable $e) {
            logActivity('INWX Markups: Failed to send markup notification: ' . $e->getMessage());
        }
    }

    /**
     * Send notification when bulk default markup is updated.
     */
    private function sendBulkMarkupNotification(array $data): void
    {
        try {
            $currency = Capsule::table('tblcurrencies')
                ->where('id', $data['currency_id'])
                ->first();
            $currencyCode = $currency ? $currency->code : $data['currency_id'];

            $lines = [];
            $lines[] = "Currency: {$currencyCode}";
            $lines[] = "Mode: {$data['mode']}";
            $lines[] = 'Scope: Default configuration for all TLDs';

            if ($data['mode'] === 'fixed') {
                if ($data['fixed_register'] !== null) {
                    $lines[] = "Fixed Register: {$data['fixed_register']}";
                }
                if ($data['fixed_renew'] !== null) {
                    $lines[] = "Fixed Renew: {$data['fixed_renew']}";
                }
                if ($data['fixed_transfer'] !== null) {
                    $lines[] = "Fixed Transfer: {$data['fixed_transfer']}";
                }
                if ($data['fixed_restore'] !== null) {
                    $lines[] = "Fixed Restore: {$data['fixed_restore']}";
                }
            } elseif ($data['mode'] === 'markup') {
                if ($data['markup_type_register'] !== null && $data['markup_value_register'] !== null) {
                    $lines[] = "Register Markup: {$data['markup_value_register']} ({$data['markup_type_register']})";
                }
                if ($data['markup_type_renew'] !== null && $data['markup_value_renew'] !== null) {
                    $lines[] = "Renew Markup: {$data['markup_value_renew']} ({$data['markup_type_renew']})";
                }
                if ($data['markup_type_transfer'] !== null && $data['markup_value_transfer'] !== null) {
                    $lines[] = "Transfer Markup: {$data['markup_value_transfer']} ({$data['markup_type_transfer']})";
                }
                if ($data['markup_type_restore'] !== null && $data['markup_value_restore'] !== null) {
                    $lines[] = "Restore Markup: {$data['markup_value_restore']} ({$data['markup_type_restore']})";
                }
            }

            if ($data['rounding_ending'] !== null) {
                $lines[] = "Rounding Ending: {$data['rounding_ending']}";
            }

            $subject = "INWX Bulk Markup updated: Default configuration [{$currencyCode}]";
            $body = "Default markup configuration was updated:\n\n" . implode("\n", $lines) . "\n\nTime: " . date('c');

            NotificationService::sendAdminEmail($subject, $body);
        } catch (\Throwable $e) {
            logActivity('INWX Markups: Failed to send bulk markup notification: ' . $e->getMessage());
        }
    }
}
