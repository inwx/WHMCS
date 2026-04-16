<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

$_ADDONLANG['inwx_markups']['heading.title'] = 'INWX TLD Sync-Overrides & Markups';
$_ADDONLANG['inwx_markups']['heading.description'] = 'Here you can configure per TLD and currency:';
$_ADDONLANG['inwx_markups']['heading.modeDisable'] = 'Disable sync';
$_ADDONLANG['inwx_markups']['heading.modeDisableDesc'] = 'Old prices remain unchanged';
$_ADDONLANG['inwx_markups']['heading.modeFixed'] = 'Fixed selling price';
$_ADDONLANG['inwx_markups']['heading.modeFixedDesc'] = 'Always this price, regardless of sync';
$_ADDONLANG['inwx_markups']['heading.modeMarkup'] = 'Markup';
$_ADDONLANG['inwx_markups']['heading.modeMarkupDesc'] = 'Fixed amount or percentage on registrar cost price (only for TLDs with registrar "inwx")';

$_ADDONLANG['inwx_markups']['bulk.title'] = 'Bulk configuration for all INWX TLDs';

$_ADDONLANG['inwx_markups']['field.currency'] = 'Currency';
$_ADDONLANG['inwx_markups']['field.mode'] = 'Mode';
$_ADDONLANG['inwx_markups']['field.register'] = 'Register (1 year)';
$_ADDONLANG['inwx_markups']['field.renew'] = 'Renew (1 year)';
$_ADDONLANG['inwx_markups']['field.transfer'] = 'Transfer (1 year)';
$_ADDONLANG['inwx_markups']['field.registerMarkup'] = 'Register markup';
$_ADDONLANG['inwx_markups']['field.renewMarkup'] = 'Renew markup';
$_ADDONLANG['inwx_markups']['field.transferMarkup'] = 'Transfer markup';
$_ADDONLANG['inwx_markups']['field.rounding'] = 'Rounding';
$_ADDONLANG['inwx_markups']['field.roundingDescription'] = 'Applied after markup (e.g. 5.75 → 5.99).';
$_ADDONLANG['inwx_markups']['field.roundingDescriptionFull'] = 'Applied after markup / fixed price (e.g. 5.75 → 5.99).';
$_ADDONLANG['inwx_markups']['field.tldExample'] = 'e.g. .es';
$_ADDONLANG['inwx_markups']['field.fixedPriceOnly'] = 'only for mode "Fixed selling price"';
$_ADDONLANG['inwx_markups']['field.markupExample'] = 'e.g. 10 for 10% or 0.50 for 0.50€ markup';

$_ADDONLANG['inwx_markups']['option.pleaseSelect'] = '-- please select --';
$_ADDONLANG['inwx_markups']['option.none'] = '-- none --';
$_ADDONLANG['inwx_markups']['option.percent'] = 'Percent (%)';
$_ADDONLANG['inwx_markups']['option.fixedAmount'] = 'Fixed amount';
$_ADDONLANG['inwx_markups']['option.likeRegister'] = 'Like Register (default)';
$_ADDONLANG['inwx_markups']['option.noRounding'] = 'No rounding';

$_ADDONLANG['inwx_markups']['mode.none'] = 'Normal (no override)';
$_ADDONLANG['inwx_markups']['mode.disable'] = 'Disable sync (keep old prices)';
$_ADDONLANG['inwx_markups']['mode.disableShort'] = 'Disable sync';
$_ADDONLANG['inwx_markups']['mode.fixed'] = 'Fixed selling price';
$_ADDONLANG['inwx_markups']['mode.markup'] = 'Markup (fixed amount or %)';
$_ADDONLANG['inwx_markups']['mode.markupShort'] = 'Markup';

$_ADDONLANG['inwx_markups']['button.bulkApply'] = 'Apply bulk to all INWX TLDs';
$_ADDONLANG['inwx_markups']['button.search'] = 'Search';
$_ADDONLANG['inwx_markups']['button.reset'] = 'Reset';
$_ADDONLANG['inwx_markups']['button.save'] = 'Save';

$_ADDONLANG['inwx_markups']['rules.title'] = 'Existing rules';
$_ADDONLANG['inwx_markups']['rules.noRulesFound'] = 'No rules found.';
$_ADDONLANG['inwx_markups']['rules.noRulesDefined'] = 'No rules defined yet.';

$_ADDONLANG['inwx_markups']['search.placeholder'] = 'Search for TLD, currency or mode...';

$_ADDONLANG['inwx_markups']['pagination.perPage'] = 'Entries per page:';
$_ADDONLANG['inwx_markups']['pagination.rulesFound'] = 'rule(s) found';
$_ADDONLANG['inwx_markups']['pagination.page'] = 'Page';
$_ADDONLANG['inwx_markups']['pagination.of'] = 'of';
$_ADDONLANG['inwx_markups']['pagination.previous'] = 'Back';
$_ADDONLANG['inwx_markups']['pagination.next'] = 'Next';

$_ADDONLANG['inwx_markups']['table.currency'] = 'Currency';
$_ADDONLANG['inwx_markups']['table.mode'] = 'Mode';
$_ADDONLANG['inwx_markups']['table.register'] = 'Register';
$_ADDONLANG['inwx_markups']['table.renew'] = 'Renew';
$_ADDONLANG['inwx_markups']['table.transfer'] = 'Transfer';
$_ADDONLANG['inwx_markups']['table.rounding'] = 'Rounding';
$_ADDONLANG['inwx_markups']['table.actions'] = 'Actions';

$_ADDONLANG['inwx_markups']['action.edit'] = 'Edit';
$_ADDONLANG['inwx_markups']['action.delete'] = 'Delete';
$_ADDONLANG['inwx_markups']['action.confirmDelete'] = 'Really delete?';

$_ADDONLANG['inwx_markups']['form.editRule'] = 'Edit rule';
$_ADDONLANG['inwx_markups']['form.addRule'] = 'Add new rule';

$_ADDONLANG['inwx_markups']['message.saved'] = 'Saved.';
$_ADDONLANG['inwx_markups']['message.savedDescription'] = 'The TLD setting has been applied.';
$_ADDONLANG['inwx_markups']['message.deleted'] = 'Deleted.';
$_ADDONLANG['inwx_markups']['message.deletedDescription'] = 'The entry has been removed.';

$_ADDONLANG['inwx_markups']['error.title'] = 'Error.';
$_ADDONLANG['inwx_markups']['error.checkInput'] = 'Please check your input.';
$_ADDONLANG['inwx_markups']['error.loadData'] = 'Error loading data: ';
$_ADDONLANG['inwx_markups']['error.toolNotFound'] = 'Tool not found.';
