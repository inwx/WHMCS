<?php

namespace INWX\Markups\Views;

/**
 * MarkupsView - Handles all HTML rendering for the INWX Markups addon.
 */
class MarkupsView
{
    /**
     * Default number of results per page.
     */
    private const DEFAULT_RESULTS_PER_PAGE = 10;

    /**
     * Render the admin area sidebar with navigation menu.
     *
     * @param array $vars Module variables from WHMCS
     *
     * @return string HTML output for sidebar
     */
    public function renderSidebar(array $vars): string
    {
        $moduleLink = $vars['modulelink'] ?? 'addonmodules.php?module=inwx_markups';
        $currentTool = $_GET['tool'] ?? 'markups';

        $tools = [
            'markups' => [
                'name' => inwx_markups_lang('sidebar.tldMarkups', 'TLD Markups'),
                'icon' => 'fa-tags',
                'url' => $moduleLink . '&tool=markups',
            ],
            // Future tools can be added here (ssl, updates, info, whoisproxy, ...)
        ];

        $html = '<div class="inwx-manager-sidebar">';
        $html .= '<h3>' . inwx_markups_lang('sidebar.title', 'INWX Manager') . '</h3>';
        $html .= '<ul class="inwx-manager-menu">';

        foreach ($tools as $toolKey => $tool) {
            $active = $currentTool === $toolKey ? ' active' : '';
            $html .= '<li class="inwx-menu-item' . $active . '">';
            $html .= '<a href="' . htmlspecialchars($tool['url']) . '">';
            if (!empty($tool['icon'])) {
                $html .= '<i class="fa ' . htmlspecialchars($tool['icon']) . '"></i> ';
            }
            $html .= htmlspecialchars($tool['name']);
            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        // Basic CSS for the sidebar
        $html .= $this->renderSidebarStyles();

        return $html;
    }

    /**
     * Render an error box with a message.
     *
     * @param string $message Error message to display
     *
     * @return string HTML output for error box
     */
    public function renderError(string $message): string
    {
        return '<div class="errorbox">' . htmlspecialchars($message) . '</div>';
    }

    /**
     * Main rendering method that orchestrates all sub-methods for the markups page.
     *
     * @param array $data Data array containing all necessary information for rendering:
     *                    - string $moduleLink
     *                    - array $currencies
     *                    - array $defaults
     *                    - array $overrides
     *                    - array $tlds
     *                    - object|null $editRow
     *                    - string $search
     *                    - int $page
     *                    - int $perPage
     *                    - int $totalCount
     *                    - int $totalPages
     *                    - array $perPageOptions
     */
    public function renderMarkupsPage(array $data): void
    {
        $this->renderFlashMessages();
        $this->renderIntro();
        $this->renderBulkForm($data['moduleLink'], $data['currencies'], $data['defaults']);
        $this->renderRulesTable(
            $data['moduleLink'],
            $data['currencies'],
            $data['defaults'],
            $data['overrides'],
            $data['search'],
            $data['page'],
            $data['perPage'],
            $data['totalCount'],
            $data['totalPages'],
            $data['perPageOptions']
        );
        $this->renderForm($data['moduleLink'], $data['currencies'], $data['tlds'], $data['editRow']);
        $this->renderJavascript($data['moduleLink']);
    }

    /**
     * Render flash messages for success, deleted, or error states.
     */
    public function renderFlashMessages(): void
    {
        if (isset($_GET['saved'])) {
            echo '<div class="successbox"><strong>' . inwx_markups_lang('message.saved', 'Gespeichert.') . '</strong> ' . inwx_markups_lang('message.savedDescription', 'Die TLD-Einstellung wurde übernommen.') . '</div>';
        } elseif (isset($_GET['deleted'])) {
            echo '<div class="successbox"><strong>' . inwx_markups_lang('message.deleted', 'Gelöscht.') . '</strong> ' . inwx_markups_lang('message.deletedDescription', 'Der Eintrag wurde entfernt.') . '</div>';
        } elseif (isset($_GET['error'])) {
            $errorMsg = isset($_GET['msg']) ? htmlspecialchars(urldecode($_GET['msg'])) : inwx_markups_lang('error.checkInput', 'Bitte Eingaben prüfen.');
            echo '<div class="errorbox"><strong>' . inwx_markups_lang('error.title', 'Fehler.') . '</strong> ' . $errorMsg . '</div>';
        }
    }

    /**
     * Render the introduction text/heading.
     */
    public function renderIntro(): void
    {
        echo '<h2>' . inwx_markups_lang('heading.title', 'INWX TLD Sync-Overrides & Aufpreise') . '</h2>';
        echo '<p>' . inwx_markups_lang('heading.description', 'Hier kannst du pro TLD und Währung festlegen:') . '<br>';
        echo '- <strong>' . inwx_markups_lang('heading.modeDisable', 'Sync deaktivieren') . '</strong>: ' . inwx_markups_lang('heading.modeDisableDesc', 'Alte Preise bleiben erhalten') . '<br>';
        echo '- <strong>' . inwx_markups_lang('heading.modeFixed', 'Fester Verkaufspreis') . '</strong>: ' . inwx_markups_lang('heading.modeFixedDesc', 'Immer dieser Preis, unabhängig vom Sync') . '<br>';
        echo '- <strong>' . inwx_markups_lang('heading.modeMarkup', 'Aufpreis') . '</strong>: ' . inwx_markups_lang('heading.modeMarkupDesc', 'Fester Betrag oder Prozentsatz auf den Registrar-Kostenpreis (nur für TLDs mit Registrar "inwx")') . '</p>';
    }

    /**
     * Render the bulk configuration form for all INWX TLDs.
     *
     * @param string $moduleLink Module link URL
     * @param mixed  $currencies Array/Collection of currency objects
     */
    public function renderBulkForm(string $moduleLink, $currencies, $defaults = []): void
    {
        // Embed defaults as JSON for JS pre-fill when currency changes
        $defaultsJson = [];
        foreach ($defaults as $defaultRow) {
            $defaultsJson[(int) $defaultRow->currency_id] = [
                'mode' => $defaultRow->mode ?? 'none',
                'fixed_register' => $defaultRow->fixed_register,
                'fixed_renew' => $defaultRow->fixed_renew,
                'fixed_transfer' => $defaultRow->fixed_transfer,
                'fixed_restore' => $defaultRow->fixed_restore,
                'markup_type_register' => $defaultRow->markup_type_register ?? '',
                'markup_value_register' => $defaultRow->markup_value_register ?? '',
                'markup_type_renew' => $defaultRow->markup_type_renew ?? '',
                'markup_value_renew' => $defaultRow->markup_value_renew ?? '',
                'markup_type_transfer' => $defaultRow->markup_type_transfer ?? '',
                'markup_value_transfer' => $defaultRow->markup_value_transfer ?? '',
                'markup_type_restore' => $defaultRow->markup_type_restore ?? '',
                'markup_value_restore' => $defaultRow->markup_value_restore ?? '',
                'rounding_ending' => $defaultRow->rounding_ending ?? '',
            ];
        }
        echo '<script>var inwxBulkDefaults = ' . json_encode($defaultsJson) . ';</script>';

        echo '<h3>' . inwx_markups_lang('bulk.title', 'Bulk-Konfiguration für alle INWX-TLDs') . '</h3>';
        echo '<form method="post" action="' . inwx_markups_buildUrl($moduleLink, 'markups', ['action' => 'bulk']) . '">';
        echo '<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">';

        // Bulk Currency
        $this->renderBulkCurrencyField($currencies);

        // Bulk Mode
        $this->renderBulkModeField();

        // Bulk fixed prices
        $this->renderBulkFixedPriceFields();

        // Bulk markup
        $this->renderBulkMarkupFields();

        // Bulk rounding
        $this->renderBulkRoundingField();

        echo '</table>';
        echo '<p><input type="submit" class="btn btn-default" value="' . inwx_markups_lang('button.bulkApply', 'Bulk auf alle INWX-TLDs anwenden') . '"></p>';
        echo '</form>';
    }

    /**
     * Render the rules table with search, pagination, and existing rules.
     *
     * @param string $moduleLink     Module link URL
     * @param mixed  $currencies     Array/Collection of currency objects
     * @param mixed  $defaults       Array/Collection of default rows
     * @param mixed  $overrides      Array/Collection of override rows
     * @param string $search         Current search term
     * @param int    $page           Current page number
     * @param int    $perPage        Results per page
     * @param int    $totalCount     Total count of results
     * @param int    $totalPages     Total number of pages
     * @param array  $perPageOptions Available per-page options
     */
    public function renderRulesTable(
        string $moduleLink,
        $currencies,
        $defaults,
        $overrides,
        string $search,
        int $page,
        int $perPage,
        int $totalCount,
        int $totalPages,
        array $perPageOptions,
    ): void {
        echo '<h3>' . inwx_markups_lang('rules.title', 'Bestehende Regeln') . '</h3>';

        // Search form and per-page selector
        $this->renderSearchAndPagination($moduleLink, $search, $perPage, $perPageOptions);

        // Show total count (only overrides are paginated)
        if ($search !== '') {
            echo '<p><strong>' . $totalCount . '</strong> ' . inwx_markups_lang('pagination.rulesFound', 'Regel(n) gefunden');
            if ($totalCount > 0) {
                echo ' (' . inwx_markups_lang('pagination.page', 'Seite') . ' ' . $page . ' ' . inwx_markups_lang('pagination.of', 'von') . ' ' . $totalPages . ')';
            }
            echo '</p>';
        }

        $hasDefaults = isset($defaults) && count($defaults) > 0;

        if ($totalCount === 0 && !$hasDefaults) {
            echo '<p>' . ($search !== '' ? inwx_markups_lang('rules.noRulesFound', 'Keine Regeln gefunden.') : inwx_markups_lang('rules.noRulesDefined', 'Noch keine Regeln definiert.')) . '</p>';
            return;
        }

        $this->renderRulesTableContent($moduleLink, $currencies, $defaults, $overrides, $search, $page, $perPage);

        // Pagination
        if ($totalPages > 1) {
            $this->renderPaginationLinks($moduleLink, $search, $page, $perPage, $totalPages);
        }
    }

    /**
     * Render the form for adding/editing a single TLD rule.
     *
     * @param string      $moduleLink Module link URL
     * @param mixed       $currencies Array/Collection of currency objects
     * @param mixed       $tlds       Array/Collection of TLD objects
     * @param object|null $editRow    Row being edited, or null for new entry
     */
    public function renderForm(string $moduleLink, $currencies, $tlds, $editRow): void
    {
        $formTitle = $editRow ? inwx_markups_lang('form.editRule', 'Regel bearbeiten') : inwx_markups_lang('form.addRule', 'Neue Regel hinzufügen');
        echo '<h3>' . $formTitle . '</h3>';

        $currentTld = $editRow ? (string) $editRow->tld : '';
        $currentCurrencyId = $editRow ? (int) $editRow->currency_id : 0;
        $currentMode = $editRow ? (string) $editRow->mode : 'none';

        echo '<form method="post" action="' . inwx_markups_buildUrl($moduleLink, 'markups', ['action' => 'save']) . '">';
        if ($editRow) {
            echo '<input type="hidden" name="id" value="' . (int) $editRow->id . '">';
        }
        echo '<table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">';

        // TLD
        $this->renderFormTldField($tlds, $currentTld);

        // Currency
        $this->renderFormCurrencyField($currencies, $currentCurrencyId);

        // Mode
        $this->renderFormModeField($currentMode);

        // Fixed Prices
        $this->renderFormFixedPriceFields($editRow);

        // Markup Configuration
        $this->renderFormMarkupFields($editRow);

        // Rounding per TLD
        $this->renderFormRoundingField($editRow);

        echo '</table>';

        echo '<p><input type="submit" class="btn btn-primary" value="' . inwx_markups_lang('button.save', 'Speichern') . '"></p>';
        echo '</form>';
    }

    /**
     * Render JavaScript for search, pagination, and mode/bulk toggles.
     *
     * @param string $moduleLink Module link URL
     */
    public function renderJavascript(string $moduleLink): void
    {
        echo '<script>
    (function() {
        var moduleLink = ' . json_encode($moduleLink) . ';

        // Helper function to build URL with current params
        function buildUrl(search, perPage) {
            var url = moduleLink;
            var params = [];
            params.push("tool=markups");
            if (search) {
                params.push("search=" + encodeURIComponent(search));
            }
            if (perPage && perPage !== ' . self::DEFAULT_RESULTS_PER_PAGE . ') {
                params.push("per_page=" + perPage);
            }
            if (params.length > 0) {
                url += (url.indexOf("?") >= 0 ? "&" : "?") + params.join("&");
            }
            return url;
        }

        // Search functionality
        var searchInput = document.getElementById("inwx-search-input");
        var searchBtn = document.getElementById("inwx-search-btn");
        var perPageSelect = document.getElementById("inwx-per-page");
        if (searchInput && searchBtn) {
            function performSearch() {
                var search = searchInput.value.trim();
                var perPage = perPageSelect ? perPageSelect.value : ' . self::DEFAULT_RESULTS_PER_PAGE . ';
                window.location.href = buildUrl(search, perPage);
            }
            searchBtn.addEventListener("click", performSearch);
            searchInput.addEventListener("keypress", function(e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    performSearch();
                }
            });
        }

        // Per-page selector
        if (perPageSelect) {
            perPageSelect.addEventListener("change", function() {
                var search = searchInput ? searchInput.value.trim() : "";
                var perPage = this.value;
                window.location.href = buildUrl(search, perPage);
            });
        }

        function toggleRows() {
            var mode = document.getElementById("inwx-mode-select").value;
            var fixedRows = document.querySelectorAll(".inwx-fixed-row");
            var markupRows = document.querySelectorAll(".inwx-markup-row");
            for (var i = 0; i < fixedRows.length; i++) {
                fixedRows[i].style.display = (mode === "fixed") ? "" : "none";
            }
            for (var i = 0; i < markupRows.length; i++) {
                markupRows[i].style.display = (mode === "markup") ? "" : "none";
            }
        }
        var select = document.getElementById("inwx-mode-select");
        if (select) {
            select.addEventListener("change", toggleRows);
            toggleRows();
        }

        function toggleBulkRows() {
            var mode = document.getElementById("inwx-bulk-mode-select").value;
            var fixedRows = document.querySelectorAll(".inwx-bulk-fixed-row");
            var markupRows = document.querySelectorAll(".inwx-bulk-markup-row");
            for (var i = 0; i < fixedRows.length; i++) {
                fixedRows[i].style.display = (mode === "fixed") ? "" : "none";
            }
            for (var i = 0; i < markupRows.length; i++) {
                markupRows[i].style.display = (mode === "markup") ? "" : "none";
            }
        }
        var bulkSelect = document.getElementById("inwx-bulk-mode-select");
        if (bulkSelect) {
            bulkSelect.addEventListener("change", toggleBulkRows);
            toggleBulkRows();
        }

        // Pre-fill bulk form from saved defaults when currency changes
        function prefillBulkDefaults() {
            var currencySelect = document.querySelector("select[name=bulk_currency_id]");
            if (!currencySelect || typeof inwxBulkDefaults === "undefined") return;
            var d = inwxBulkDefaults[currencySelect.value];
            var modeSelect = document.getElementById("inwx-bulk-mode-select");

            if (!d) {
                // No defaults for this currency: reset form
                if (modeSelect) { modeSelect.value = "none"; toggleBulkRows(); }
                var inputs = document.querySelectorAll("input[name^=bulk_fixed_], input[name^=bulk_markup_value_]");
                for (var i = 0; i < inputs.length; i++) inputs[i].value = "";
                var selects = document.querySelectorAll("select[name^=bulk_markup_type_]");
                for (var i = 0; i < selects.length; i++) selects[i].value = "";
                var roundingSelect = document.querySelector("select[name=bulk_rounding_ending]");
                if (roundingSelect) roundingSelect.value = "";
                return;
            }

            if (modeSelect) { modeSelect.value = d.mode || "none"; toggleBulkRows(); }

            var fields = ["register", "renew", "transfer", "restore"];
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                var fixedInput = document.querySelector("input[name=bulk_fixed_" + f + "]");
                if (fixedInput) fixedInput.value = d["fixed_" + f] || "";
                var typeSelect = document.querySelector("select[name=bulk_markup_type_" + f + "]");
                if (typeSelect) typeSelect.value = d["markup_type_" + f] || "";
                var valueInput = document.querySelector("input[name=bulk_markup_value_" + f + "]");
                if (valueInput) valueInput.value = d["markup_value_" + f] || "";
            }
            var roundingSelect = document.querySelector("select[name=bulk_rounding_ending]");
            if (roundingSelect) roundingSelect.value = d.rounding_ending || "";
        }

        var bulkCurrencySelect = document.querySelector("select[name=bulk_currency_id]");
        if (bulkCurrencySelect) {
            bulkCurrencySelect.addEventListener("change", prefillBulkDefaults);
            // Auto-fill on load if single currency (already selected)
            if (bulkCurrencySelect.value) prefillBulkDefaults();
        }
    })();
    </script>';
    }

    // ========================================================================
    // Private helper methods for rendering specific sections
    // ========================================================================

    /**
     * Render sidebar CSS styles.
     *
     * @return string CSS styles
     */
    private function renderSidebarStyles(): string
    {
        return '<style>
        .inwx-manager-sidebar {
            margin: 0;
            padding: 0;
        }
        .inwx-manager-sidebar h3 {
            margin: 0 0 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
            font-weight: 600;
        }
        .inwx-manager-menu {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .inwx-menu-item {
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #e9ecef;
        }
        .inwx-menu-item a {
            display: block;
            padding: 10px 15px;
            color: #495057;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .inwx-menu-item a:hover {
            background-color: #f8f9fa;
            color: #007bff;
        }
        .inwx-menu-item.active a {
            background-color: #007bff;
            color: #fff;
            font-weight: 600;
        }
        .inwx-menu-item a i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }
    </style>';
    }

    /**
     * Render bulk currency field.
     *
     * @param mixed $currencies Array/Collection of currency objects
     */
    private function renderBulkCurrencyField($currencies): void
    {
        echo '<tr><td class="fieldlabel">' . inwx_markups_lang('field.currency', 'Währung') . '</td><td class="fieldarea">';
        echo '<select name="bulk_currency_id">';
        $currencyCount = count($currencies);
        $hasSingleCurrency = $currencyCount === 1;
        if (!$hasSingleCurrency) {
            echo '<option value="" disabled selected>' . inwx_markups_lang('option.pleaseSelect', '-- bitte wählen --') . '</option>';
        }
        foreach ($currencies as $currency) {
            $cid = (int) $currency->id;
            $selected = $hasSingleCurrency ? ' selected' : '';
            echo '<option value="' . $cid . '"' . $selected . '>' . htmlspecialchars($currency->code) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';
    }

    /**
     * Render bulk mode field.
     */
    private function renderBulkModeField(): void
    {
        echo '<tr><td class="fieldlabel">' . inwx_markups_lang('field.mode', 'Modus') . '</td><td class="fieldarea">';
        echo '<select name="bulk_mode" id="inwx-bulk-mode-select">';
        $bulkModes = [
            'none' => inwx_markups_lang('mode.none', 'Normal (kein Override)'),
            'disable' => inwx_markups_lang('mode.disable', 'Sync deaktivieren (alte Preise beibehalten)'),
            'fixed' => inwx_markups_lang('mode.fixed', 'Fester Verkaufspreis'),
            'markup' => inwx_markups_lang('mode.markup', 'Aufpreis (fester Betrag oder %)'),
        ];
        foreach ($bulkModes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';
    }

    /**
     * Render bulk fixed price fields.
     */
    private function renderBulkFixedPriceFields(): void
    {
        echo '<tr class="inwx-bulk-fixed-row"><td class="fieldlabel">' . inwx_markups_lang('field.register', 'Register (1 Jahr)') . '</td><td class="fieldarea">';
        echo '<input type="text" name="bulk_fixed_register" size="8" value="">';
        echo '</td></tr>';

        echo '<tr class="inwx-bulk-fixed-row"><td class="fieldlabel">' . inwx_markups_lang('field.renew', 'Renew (1 Jahr)') . '</td><td class="fieldarea">';
        echo '<input type="text" name="bulk_fixed_renew" size="8" value="">';
        echo '</td></tr>';

        echo '<tr class="inwx-bulk-fixed-row"><td class="fieldlabel">' . inwx_markups_lang('field.transfer', 'Transfer (1 Jahr)') . '</td><td class="fieldarea">';
        echo '<input type="text" name="bulk_fixed_transfer" size="8" value="">';
        echo '</td></tr>';

        echo '<tr class="inwx-bulk-fixed-row"><td class="fieldlabel">' . inwx_markups_lang('field.restore', 'Restore (1 Jahr)') . '</td><td class="fieldarea">';
        echo '<input type="text" name="bulk_fixed_restore" size="8" value="">';
        echo '</td></tr>';
    }

    /**
     * Render bulk markup fields.
     */
    private function renderBulkMarkupFields(): void
    {
        echo '<tr class="inwx-bulk-markup-row"><td class="fieldlabel">' . inwx_markups_lang('field.registerMarkup', 'Register Aufpreis') . '</td><td class="fieldarea">';
        echo '<select name="bulk_markup_type_register">';
        echo '<option value="">' . inwx_markups_lang('option.none', '-- keine --') . '</option>';
        echo '<option value="percent">' . inwx_markups_lang('option.percent', 'Prozent (%)') . '</option>';
        echo '<option value="fixed">' . inwx_markups_lang('option.fixedAmount', 'Fester Betrag') . '</option>';
        echo '</select> ';
        echo '<input type="text" name="bulk_markup_value_register" size="8" value="">';
        echo '</td></tr>';

        echo '<tr class="inwx-bulk-markup-row"><td class="fieldlabel">' . inwx_markups_lang('field.renewMarkup', 'Renew Aufpreis') . '</td><td class="fieldarea">';
        echo '<select name="bulk_markup_type_renew">';
        echo '<option value="">' . inwx_markups_lang('option.likeRegister', 'Wie Register (Standard)') . '</option>';
        echo '<option value="percent">' . inwx_markups_lang('option.percent', 'Prozent (%)') . '</option>';
        echo '<option value="fixed">' . inwx_markups_lang('option.fixedAmount', 'Fester Betrag') . '</option>';
        echo '</select> ';
        echo '<input type="text" name="bulk_markup_value_renew" size="8" value="">';
        echo '</td></tr>';

        echo '<tr class="inwx-bulk-markup-row"><td class="fieldlabel">' . inwx_markups_lang('field.transferMarkup', 'Transfer Aufpreis') . '</td><td class="fieldarea">';
        echo '<select name="bulk_markup_type_transfer">';
        echo '<option value="">' . inwx_markups_lang('option.likeRegister', 'Wie Register (Standard)') . '</option>';
        echo '<option value="percent">' . inwx_markups_lang('option.percent', 'Prozent (%)') . '</option>';
        echo '<option value="fixed">' . inwx_markups_lang('option.fixedAmount', 'Fester Betrag') . '</option>';
        echo '</select> ';
        echo '<input type="text" name="bulk_markup_value_transfer" size="8" value="">';
        echo '</td></tr>';

        echo '<tr class="inwx-bulk-markup-row"><td class="fieldlabel">' . inwx_markups_lang('field.restoreMarkup', 'Restore Aufpreis') . '</td><td class="fieldarea">';
        echo '<select name="bulk_markup_type_restore">';
        echo '<option value="">' . inwx_markups_lang('option.likeRegister', 'Wie Register (Standard)') . '</option>';
        echo '<option value="percent">' . inwx_markups_lang('option.percent', 'Prozent (%)') . '</option>';
        echo '<option value="fixed">' . inwx_markups_lang('option.fixedAmount', 'Fester Betrag') . '</option>';
        echo '</select> ';
        echo '<input type="text" name="bulk_markup_value_restore" size="8" value="">';
        echo '</td></tr>';
    }

    /**
     * Render bulk rounding field.
     */
    private function renderBulkRoundingField(): void
    {
        echo '<tr><td class="fieldlabel">' . inwx_markups_lang('field.rounding', 'Rundung') . '</td><td class="fieldarea">';
        echo '<select name="bulk_rounding_ending">';
        echo '<option value="" selected>' . inwx_markups_lang('option.noRounding', 'Keine Rundung') . '</option>';

        $bulkRoundingOptions = [
            '0.00' => 'x.00',
            '0.50' => 'x.50',
            '0.95' => 'x.95',
        ];
        for ($i = 0; $i <= 9; ++$i) {
            $value = '0.' . $i . '9';
            $label = 'x.' . $i . '9';
            $bulkRoundingOptions[$value] = $label;
        }
        sort($bulkRoundingOptions);
        foreach ($bulkRoundingOptions as $value => $label) {
            echo '<option value="' . $value . '">' . $label . '</option>';
        }

        echo '</select> ';
        echo '<small>' . inwx_markups_lang('field.roundingDescription', 'Wird nach dem Aufpreis angewendet (z. B. 5,75 → 5,99).') . '</small>';
        echo '</td></tr>';
    }

    /**
     * Render search form and pagination controls.
     *
     * @param string $moduleLink     Module link URL
     * @param string $search         Current search term
     * @param int    $perPage        Results per page
     * @param array  $perPageOptions Available per-page options
     */
    private function renderSearchAndPagination(string $moduleLink, string $search, int $perPage, array $perPageOptions): void
    {
        echo '<div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">';
        echo '<div>';
        echo '<input type="text" id="inwx-search-input" value="' . htmlspecialchars($search) . '" placeholder="' . inwx_markups_lang('search.placeholder', 'Suche nach TLD, Währung oder Modus...') . '" style="width: 300px; padding: 5px;">';
        echo '<button type="button" class="btn btn-default" id="inwx-search-btn">' . inwx_markups_lang('button.search', 'Suchen') . '</button>';
        if ($search !== '') {
            $resetParams = [];
            if ($perPage !== self::DEFAULT_RESULTS_PER_PAGE) {
                $resetParams['per_page'] = (string) $perPage;
            }
            $resetUrl = inwx_markups_buildUrl($moduleLink, 'markups', $resetParams);
            echo ' <a href="' . htmlspecialchars($resetUrl) . '" class="btn btn-default" style="margin-left: 10px;">' . inwx_markups_lang('button.reset', 'Zurücksetzen') . '</a>';
        }
        echo '</div>';
        echo '<div style="text-align: right;">';
        echo '<label for="inwx-per-page">' . inwx_markups_lang('pagination.perPage', 'Einträge pro Seite:') . '</label>';
        echo '<select id="inwx-per-page" style="margin-left: 5px; padding: 5px;">';
        foreach ($perPageOptions as $option) {
            $selected = $perPage === $option ? ' selected' : '';
            echo '<option value="' . $option . '"' . $selected . '>' . $option . '</option>';
        }
        echo '</select>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render the rules table content (defaults and overrides).
     *
     * @param string $moduleLink Module link URL
     * @param mixed  $currencies Array/Collection of currency objects
     * @param mixed  $defaults   Array/Collection of default rows
     * @param mixed  $overrides  Array/Collection of override rows
     * @param string $search     Current search term
     * @param int    $page       Current page number
     * @param int    $perPage    Results per page
     */
    private function renderRulesTableContent(
        string $moduleLink,
        $currencies,
        $defaults,
        $overrides,
        string $search,
        int $page,
        int $perPage,
    ): void {
        echo '<table class="datatable" width="100%" border="0" cellspacing="1" cellpadding="3">';
        echo '<tr><th>ID</th><th>TLD</th><th>' . inwx_markups_lang('table.currency', 'Währung') . '</th><th>' . inwx_markups_lang('table.mode', 'Modus') . '</th><th>' . inwx_markups_lang('table.register', 'Register') . '</th><th>' . inwx_markups_lang('table.renew', 'Renew') . '</th><th>' . inwx_markups_lang('table.transfer', 'Transfer') . '</th><th>' . inwx_markups_lang('table.restore', 'Restore') . '</th><th>' . inwx_markups_lang('table.rounding', 'Rundung') . '</th><th>' . inwx_markups_lang('table.actions', 'Aktionen') . '</th></tr>';

        // First: per-currency defaults (set via bulk)
        $hasDefaults = isset($defaults) && count($defaults) > 0;
        if ($hasDefaults) {
            $this->renderDefaultRows($currencies, $defaults);
        }

        // Then: per-TLD overrides (already filtered to mode != "none")
        $this->renderOverrideRows($moduleLink, $currencies, $overrides, $search, $page, $perPage);

        echo '</table>';
    }

    /**
     * Render default rows (bulk settings).
     *
     * @param mixed $currencies Array/Collection of currency objects
     * @param mixed $defaults   Array/Collection of default rows
     */
    private function renderDefaultRows($currencies, $defaults): void
    {
        foreach ($defaults as $defaultRow) {
            $currency = null;
            foreach ($currencies as $c) {
                if ((int) $c->id === (int) $defaultRow->currency_id) {
                    $currency = $c;
                    break;
                }
            }

            $modeLabel = $this->getModeLabel($defaultRow->mode);
            $tldLabel = inwx_markups_lang('table.defaultTldLabel', 'Default (alle INWX-TLDs)');

            echo '<tr>';
            echo '<td>-</td>';
            echo '<td><em>' . htmlspecialchars($tldLabel) . '</em></td>';
            echo '<td>' . ($currency ? htmlspecialchars($currency->code) : (int) $defaultRow->currency_id) . '</td>';
            echo '<td>' . htmlspecialchars($modeLabel) . '</td>';

            if ($defaultRow->mode === 'markup') {
                $this->renderMarkupColumns($defaultRow);
            } else {
                $this->renderFixedColumns($defaultRow);
            }

            $roundingDisplay = $defaultRow->rounding_ending !== null
                ? number_format((float) $defaultRow->rounding_ending, 2)
                : '-';

            echo '<td>' . htmlspecialchars($roundingDisplay) . '</td>';
            echo '<td><em>' . inwx_markups_lang('table.editViaBulk', 'Über Bulk-Einstellungen bearbeiten') . '</em></td>';
            echo '</tr>';
        }
    }

    /**
     * Render override rows (per-TLD rules).
     *
     * @param string $moduleLink Module link URL
     * @param mixed  $currencies Array/Collection of currency objects
     * @param mixed  $overrides  Array/Collection of override rows
     * @param string $search     Current search term
     * @param int    $page       Current page number
     * @param int    $perPage    Results per page
     */
    private function renderOverrideRows(
        string $moduleLink,
        $currencies,
        $overrides,
        string $search,
        int $page,
        int $perPage,
    ): void {
        foreach ($overrides as $row) {
            $currency = null;
            foreach ($currencies as $c) {
                if ((int) $c->id === (int) $row->currency_id) {
                    $currency = $c;
                    break;
                }
            }
            $modeLabel = $this->getModeLabel($row->mode);

            // Build link with search params preserved
            $linkParams = [];
            if ($search !== '') {
                $linkParams['search'] = $search;
            }
            if ($page > 1) {
                $linkParams['page'] = (string) $page;
            }
            if ($perPage !== self::DEFAULT_RESULTS_PER_PAGE) {
                $linkParams['per_page'] = (string) $perPage;
            }

            echo '<tr>';
            echo '<td>' . (int) $row->id . '</td>';
            echo '<td>' . htmlspecialchars($row->tld) . '</td>';
            echo '<td>' . ($currency ? htmlspecialchars($currency->code) : (int) $row->currency_id) . '</td>';
            echo '<td>' . htmlspecialchars($modeLabel) . '</td>';

            if ($row->mode === 'markup') {
                $this->renderMarkupColumns($row);
            } else {
                $this->renderFixedColumns($row);
            }

            $roundingDisplay = $row->rounding_ending !== null
                ? number_format((float) $row->rounding_ending, 2)
                : '-';
            echo '<td>' . htmlspecialchars($roundingDisplay) . '</td>';
            echo '<td>';
            $editParams = array_merge($linkParams, ['edit' => (string) ((int) $row->id)]);
            echo '<a href="' . inwx_markups_buildUrl($moduleLink, 'markups', $editParams) . '">' . inwx_markups_lang('action.edit', 'Bearbeiten') . '</a> | ';
            $deleteParams = array_merge($linkParams, ['action' => 'delete', 'id' => (string) ((int) $row->id)]);
            echo '<a href="' . inwx_markups_buildUrl($moduleLink, 'markups', $deleteParams) . '" onclick="return confirm(\'' . inwx_markups_lang('action.confirmDelete', 'Wirklich löschen?') . '\');">' . inwx_markups_lang('action.delete', 'Löschen') . '</a>';
            echo '</td>';
            echo '</tr>';
        }
    }

    /**
     * Render markup columns for a row.
     *
     * @param object $row Row object with markup data
     */
    private function renderMarkupColumns($row): void
    {
        $markupReg = ($row->markup_type_register && $row->markup_value_register !== null)
            ? htmlspecialchars($row->markup_type_register === 'percent' ? $row->markup_value_register . '%' : number_format((float) $row->markup_value_register, 2))
            : '-';
        $markupRen = ($row->markup_type_renew && $row->markup_value_renew !== null)
            ? htmlspecialchars($row->markup_type_renew === 'percent' ? $row->markup_value_renew . '%' : number_format((float) $row->markup_value_renew, 2))
            : '-';
        $markupTrans = ($row->markup_type_transfer && $row->markup_value_transfer !== null)
            ? htmlspecialchars($row->markup_type_transfer === 'percent' ? $row->markup_value_transfer . '%' : number_format((float) $row->markup_value_transfer, 2))
            : '-';
        $markupRestore = ($row->markup_type_restore && $row->markup_value_restore !== null)
            ? htmlspecialchars($row->markup_type_restore === 'percent' ? $row->markup_value_restore . '%' : number_format((float) $row->markup_value_restore, 2))
            : '-';
        echo '<td>' . $markupReg . '</td>';
        echo '<td>' . $markupRen . '</td>';
        echo '<td>' . $markupTrans . '</td>';
        echo '<td>' . $markupRestore . '</td>';
    }

    /**
     * Render fixed price columns for a row.
     *
     * @param object $row Row object with fixed price data
     */
    private function renderFixedColumns($row): void
    {
        echo '<td>' . ($row->fixed_register !== null ? htmlspecialchars(number_format((float) $row->fixed_register, 2)) : '-') . '</td>';
        echo '<td>' . ($row->fixed_renew !== null ? htmlspecialchars(number_format((float) $row->fixed_renew, 2)) : '-') . '</td>';
        echo '<td>' . ($row->fixed_transfer !== null ? htmlspecialchars(number_format((float) $row->fixed_transfer, 2)) : '-') . '</td>';
        echo '<td>' . ($row->fixed_restore !== null ? htmlspecialchars(number_format((float) $row->fixed_restore, 2)) : '-') . '</td>';
    }

    /**
     * Get mode label translation.
     *
     * @param string $mode Mode value
     *
     * @return string Translated label
     */
    private function getModeLabel(string $mode): string
    {
        $modeLabels = [
            'none' => inwx_markups_lang('mode.none', 'Normal (kein Override)'),
            'disable' => inwx_markups_lang('mode.disableShort', 'Sync deaktivieren'),
            'fixed' => inwx_markups_lang('mode.fixed', 'Fester Verkaufspreis'),
            'markup' => inwx_markups_lang('mode.markupShort', 'Aufpreis'),
        ];

        return $modeLabels[$mode] ?? $mode;
    }

    /**
     * Render pagination links.
     *
     * @param string $moduleLink Module link URL
     * @param string $search     Current search term
     * @param int    $page       Current page number
     * @param int    $perPage    Results per page
     * @param int    $totalPages Total number of pages
     */
    private function renderPaginationLinks(string $moduleLink, string $search, int $page, int $perPage, int $totalPages): void
    {
        echo '<div style="margin-top: 15px; text-align: center;">';
        $baseParams = [];
        if ($search !== '') {
            $baseParams['search'] = $search;
        }
        if ($perPage !== self::DEFAULT_RESULTS_PER_PAGE) {
            $baseParams['per_page'] = (string) $perPage;
        }

        // Previous link
        if ($page > 1) {
            $prevParams = array_merge($baseParams, ['page' => (string) ($page - 1)]);
            echo '<a href="' . inwx_markups_buildUrl($moduleLink, 'markups', $prevParams) . '" class="btn btn-default" style="margin-right: 5px;">&laquo; ' . inwx_markups_lang('pagination.previous', 'Zurück') . '</a>';
        }

        // Page numbers
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        if ($startPage > 1) {
            $firstParams = array_merge($baseParams, ['page' => '1']);
            echo '<a href="' . inwx_markups_buildUrl($moduleLink, 'markups', $firstParams) . '" class="btn btn-default" style="margin-right: 5px;">1</a>';
            if ($startPage > 2) {
                echo '<span style="margin-right: 5px;">...</span>';
            }
        }
        for ($i = $startPage; $i <= $endPage; ++$i) {
            if ($i === $page) {
                echo '<span class="btn btn-primary" style="margin-right: 5px; cursor: default;">' . $i . '</span>';
            } else {
                $pageParams = array_merge($baseParams, ['page' => (string) $i]);
                echo '<a href="' . inwx_markups_buildUrl($moduleLink, 'markups', $pageParams) . '" class="btn btn-default" style="margin-right: 5px;">' . $i . '</a>';
            }
        }
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                echo '<span style="margin-right: 5px;">...</span>';
            }
            $lastParams = array_merge($baseParams, ['page' => (string) $totalPages]);
            echo '<a href="' . inwx_markups_buildUrl($moduleLink, 'markups', $lastParams) . '" class="btn btn-default" style="margin-right: 5px;">' . $totalPages . '</a>';
        }

        // Next link
        if ($page < $totalPages) {
            $nextParams = array_merge($baseParams, ['page' => (string) ($page + 1)]);
            echo '<a href="' . inwx_markups_buildUrl($moduleLink, 'markups', $nextParams) . '" class="btn btn-default" style="margin-left: 5px;">' . inwx_markups_lang('pagination.next', 'Weiter') . ' &raquo;</a>';
        }

        echo '</div>';
    }

    /**
     * Render TLD field for the form.
     *
     * @param mixed  $tlds       Array/Collection of TLD objects
     * @param string $currentTld Currently selected TLD
     */
    private function renderFormTldField($tlds, string $currentTld): void
    {
        echo '<tr><td class="fieldlabel">TLD</td><td class="fieldarea">';
        echo '<select name="tld">';
        $tldCount = count($tlds);
        $hasSingleTld = $tldCount === 1;
        if (!$hasSingleTld) {
            $placeholderSelected = $currentTld === '' ? ' selected' : '';
            echo '<option value="" disabled' . $placeholderSelected . '>' . inwx_markups_lang('option.pleaseSelect', '-- bitte wählen --') . '</option>';
        }
        foreach ($tlds as $tldRow) {
            $ext = (string) $tldRow->extension;
            if ($currentTld !== '') {
                $selected = $ext === $currentTld ? ' selected' : '';
            } elseif ($hasSingleTld) {
                $selected = ' selected';
            } else {
                $selected = '';
            }
            echo '<option value="' . htmlspecialchars($ext) . '"' . $selected . '>' . htmlspecialchars($ext) . '</option>';
        }
        echo '</select>';
        echo ' &nbsp; <small>' . inwx_markups_lang('field.tldExample', 'z. B. .es') . '</small>';
        echo '</td></tr>';
    }

    /**
     * Render currency field for the form.
     *
     * @param mixed $currencies        Array/Collection of currency objects
     * @param int   $currentCurrencyId Currently selected currency ID
     */
    private function renderFormCurrencyField($currencies, int $currentCurrencyId): void
    {
        echo '<tr><td class="fieldlabel">' . inwx_markups_lang('field.currency', 'Währung') . '</td><td class="fieldarea">';
        echo '<select name="currency_id">';
        $currencyCount = count($currencies);
        $hasSingleCurrency = $currencyCount === 1;
        if (!$hasSingleCurrency) {
            $placeholderSelected = $currentCurrencyId <= 0 ? ' selected' : '';
            echo '<option value="" disabled' . $placeholderSelected . '>' . inwx_markups_lang('option.pleaseSelect', '-- bitte wählen --') . '</option>';
        }
        foreach ($currencies as $currency) {
            $cid = (int) $currency->id;
            if ($currentCurrencyId > 0) {
                $selected = $cid === $currentCurrencyId ? ' selected' : '';
            } elseif ($hasSingleCurrency) {
                $selected = ' selected';
            } else {
                $selected = '';
            }
            echo '<option value="' . $cid . '"' . $selected . '>' . htmlspecialchars($currency->code) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';
    }

    /**
     * Render mode field for the form.
     *
     * @param string $currentMode Currently selected mode
     */
    private function renderFormModeField(string $currentMode): void
    {
        echo '<tr><td class="fieldlabel">' . inwx_markups_lang('field.mode', 'Modus') . '</td><td class="fieldarea">';
        echo '<select name="mode" id="inwx-mode-select">';
        $modes = [
            'none' => inwx_markups_lang('mode.none', 'Normal (kein Override)'),
            'disable' => inwx_markups_lang('mode.disable', 'Sync deaktivieren (alte Preise beibehalten)'),
            'fixed' => inwx_markups_lang('mode.fixed', 'Fester Verkaufspreis'),
            'markup' => inwx_markups_lang('mode.markup', 'Aufpreis (fester Betrag oder %)'),
        ];
        foreach ($modes as $value => $label) {
            $selected = $value === $currentMode ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';
    }

    /**
     * Render fixed price fields for the form.
     *
     * @param object|null $editRow Row being edited, or null for new entry
     */
    private function renderFormFixedPriceFields($editRow): void
    {
        $fixedRegisterVal = $editRow && $editRow->fixed_register !== null ? (string) $editRow->fixed_register : '';
        $fixedRenewVal = $editRow && $editRow->fixed_renew !== null ? (string) $editRow->fixed_renew : '';
        $fixedTransferVal = $editRow && $editRow->fixed_transfer !== null ? (string) $editRow->fixed_transfer : '';
        $fixedRestoreVal = $editRow && $editRow->fixed_restore !== null ? (string) $editRow->fixed_restore : '';

        echo '<tr class="inwx-fixed-row"><td class="fieldlabel">' . inwx_markups_lang('field.register', 'Register (1 Jahr)') . '</td><td class="fieldarea">';
        echo '<input type="text" name="fixed_register" size="8" value="' . htmlspecialchars($fixedRegisterVal) . '"> <small>' . inwx_markups_lang('field.fixedPriceOnly', 'nur bei Modus "Fester Verkaufspreis"') . '</small>';
        echo '</td></tr>';

        echo '<tr class="inwx-fixed-row"><td class="fieldlabel">' . inwx_markups_lang('field.renew', 'Renew (1 Jahr)') . '</td><td class="fieldarea">';
        echo '<input type="text" name="fixed_renew" size="8" value="' . htmlspecialchars($fixedRenewVal) . '">';
        echo '</td></tr>';

        echo '<tr class="inwx-fixed-row"><td class="fieldlabel">' . inwx_markups_lang('field.transfer', 'Transfer (1 Jahr)') . '</td><td class="fieldarea">';
        echo '<input type="text" name="fixed_transfer" size="8" value="' . htmlspecialchars($fixedTransferVal) . '">';
        echo '</td></tr>';

        echo '<tr class="inwx-fixed-row"><td class="fieldlabel">' . inwx_markups_lang('field.restore', 'Restore (1 Jahr)') . '</td><td class="fieldarea">';
        echo '<input type="text" name="fixed_restore" size="8" value="' . htmlspecialchars($fixedRestoreVal) . '">';
        echo '</td></tr>';
    }

    /**
     * Render markup fields for the form.
     *
     * @param object|null $editRow Row being edited, or null for new entry
     */
    private function renderFormMarkupFields($editRow): void
    {
        $markupTypeReg = $editRow && $editRow->markup_type_register ? (string) $editRow->markup_type_register : '';
        $markupValueReg = $editRow && $editRow->markup_value_register !== null ? (string) $editRow->markup_value_register : '';
        $markupTypeRen = $editRow && $editRow->markup_type_renew ? (string) $editRow->markup_type_renew : '';
        $markupValueRen = $editRow && $editRow->markup_value_renew !== null ? (string) $editRow->markup_value_renew : '';
        $markupTypeTrans = $editRow && $editRow->markup_type_transfer ? (string) $editRow->markup_type_transfer : '';
        $markupValueTrans = $editRow && $editRow->markup_value_transfer !== null ? (string) $editRow->markup_value_transfer : '';
        $markupTypeRestore = $editRow && $editRow->markup_type_restore ? (string) $editRow->markup_type_restore : '';
        $markupValueRestore = $editRow && $editRow->markup_value_restore !== null ? (string) $editRow->markup_value_restore : '';

        echo '<tr class="inwx-markup-row"><td class="fieldlabel">' . inwx_markups_lang('field.registerMarkup', 'Register Aufpreis') . '</td><td class="fieldarea">';
        echo '<select name="markup_type_register">';
        echo '<option value="">' . inwx_markups_lang('option.none', '-- keine --') . '</option>';
        echo '<option value="percent"' . ($markupTypeReg === 'percent' ? ' selected' : '') . '>' . inwx_markups_lang('option.percent', 'Prozent (%)') . '</option>';
        echo '<option value="fixed"' . ($markupTypeReg === 'fixed' ? ' selected' : '') . '>' . inwx_markups_lang('option.fixedAmount', 'Fester Betrag') . '</option>';
        echo '</select> ';
        echo '<input type="text" name="markup_value_register" size="8" value="' . htmlspecialchars($markupValueReg) . '">';
        echo ' <small>' . inwx_markups_lang('field.markupExample', 'z.B. 10 für 10% oder 0.50 für 0,50€ Aufpreis') . '</small>';
        echo '</td></tr>';

        echo '<tr class="inwx-markup-row"><td class="fieldlabel">' . inwx_markups_lang('field.renewMarkup', 'Renew Aufpreis') . '</td><td class="fieldarea">';
        echo '<select name="markup_type_renew">';
        echo '<option value="">' . inwx_markups_lang('option.likeRegister', 'Wie Register (Standard)') . '</option>';
        echo '<option value="percent"' . ($markupTypeRen === 'percent' ? ' selected' : '') . '>' . inwx_markups_lang('option.percent', 'Prozent (%)') . '</option>';
        echo '<option value="fixed"' . ($markupTypeRen === 'fixed' ? ' selected' : '') . '>' . inwx_markups_lang('option.fixedAmount', 'Fester Betrag') . '</option>';
        echo '</select> ';
        echo '<input type="text" name="markup_value_renew" size="8" value="' . htmlspecialchars($markupValueRen) . '">';
        echo '</td></tr>';

        echo '<tr class="inwx-markup-row"><td class="fieldlabel">' . inwx_markups_lang('field.transferMarkup', 'Transfer Aufpreis') . '</td><td class="fieldarea">';
        echo '<select name="markup_type_transfer">';
        echo '<option value="">' . inwx_markups_lang('option.likeRegister', 'Wie Register (Standard)') . '</option>';
        echo '<option value="percent"' . ($markupTypeTrans === 'percent' ? ' selected' : '') . '>' . inwx_markups_lang('option.percent', 'Prozent (%)') . '</option>';
        echo '<option value="fixed"' . ($markupTypeTrans === 'fixed' ? ' selected' : '') . '>' . inwx_markups_lang('option.fixedAmount', 'Fester Betrag') . '</option>';
        echo '</select> ';
        echo '<input type="text" name="markup_value_transfer" size="8" value="' . htmlspecialchars($markupValueTrans) . '">';
        echo '</td></tr>';

        echo '<tr class="inwx-markup-row"><td class="fieldlabel">' . inwx_markups_lang('field.restoreMarkup', 'Restore Aufpreis') . '</td><td class="fieldarea">';
        echo '<select name="markup_type_restore">';
        echo '<option value="">' . inwx_markups_lang('option.likeRegister', 'Wie Register (Standard)') . '</option>';
        echo '<option value="percent"' . ($markupTypeRestore === 'percent' ? ' selected' : '') . '>' . inwx_markups_lang('option.percent', 'Prozent (%)') . '</option>';
        echo '<option value="fixed"' . ($markupTypeRestore === 'fixed' ? ' selected' : '') . '>' . inwx_markups_lang('option.fixedAmount', 'Fester Betrag') . '</option>';
        echo '</select> ';
        echo '<input type="text" name="markup_value_restore" size="8" value="' . htmlspecialchars($markupValueRestore) . '">';
        echo '</td></tr>';
    }

    /**
     * Render rounding field for the form.
     *
     * @param object|null $editRow Row being edited, or null for new entry
     */
    private function renderFormRoundingField($editRow): void
    {
        $roundingEndingVal = $editRow && $editRow->rounding_ending !== null ? (string) $editRow->rounding_ending : '';
        echo '<tr><td class="fieldlabel">' . inwx_markups_lang('field.rounding', 'Rundung') . '</td><td class="fieldarea">';
        echo '<select name="rounding_ending">';
        $selected = $roundingEndingVal === '' ? ' selected' : '';
        echo '<option value=""' . $selected . '>' . inwx_markups_lang('option.noRounding', 'Keine Rundung') . '</option>';
        $options = [
            '0.00' => 'x.00',
            '0.50' => 'x.50',
            '0.95' => 'x.95',
        ];
        for ($i = 0; $i <= 9; ++$i) {
            $value = '0.' . $i . '9';
            $label = 'x.' . $i . '9';
            $options[$value] = $label;
        }
        sort($options);
        foreach ($options as $value => $label) {
            $selected = ((string) $roundingEndingVal === (string) $value) ? ' selected' : '';
            echo '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }
        echo '</select> ';
        echo '<small>' . inwx_markups_lang('field.roundingDescriptionFull', 'Wird nach Aufpreis / Fixpreis angewendet (z. B. 5,75 → 5,99).') . '</small>';
        echo '</td></tr>';
    }
}
