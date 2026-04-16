<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

$_ADMINLANG['inwx_markups']['heading.title'] = 'INWX TLD Sync-Overrides & Aufpreise';
$_ADMINLANG['inwx_markups']['heading.description'] = 'Hier kannst du pro TLD und Währung festlegen:';
$_ADMINLANG['inwx_markups']['heading.modeDisable'] = 'Sync deaktivieren';
$_ADMINLANG['inwx_markups']['heading.modeDisableDesc'] = 'Alte Preise bleiben erhalten';
$_ADMINLANG['inwx_markups']['heading.modeFixed'] = 'Fester Verkaufspreis';
$_ADMINLANG['inwx_markups']['heading.modeFixedDesc'] = 'Immer dieser Preis, unabhängig vom Sync';
$_ADMINLANG['inwx_markups']['heading.modeMarkup'] = 'Aufpreis';
$_ADMINLANG['inwx_markups']['heading.modeMarkupDesc'] = 'Fester Betrag oder Prozentsatz auf den Registrar-Kostenpreis (nur für TLDs mit Registrar "inwx")';

$_ADMINLANG['inwx_markups']['bulk.title'] = 'Bulk-Konfiguration für alle INWX-TLDs';

$_ADMINLANG['inwx_markups']['field.currency'] = 'Währung';
$_ADMINLANG['inwx_markups']['field.mode'] = 'Modus';
$_ADMINLANG['inwx_markups']['field.register'] = 'Register (1 Jahr)';
$_ADMINLANG['inwx_markups']['field.renew'] = 'Renew (1 Jahr)';
$_ADMINLANG['inwx_markups']['field.transfer'] = 'Transfer (1 Jahr)';
$_ADMINLANG['inwx_markups']['field.registerMarkup'] = 'Register Aufpreis';
$_ADMINLANG['inwx_markups']['field.renewMarkup'] = 'Renew Aufpreis';
$_ADMINLANG['inwx_markups']['field.transferMarkup'] = 'Transfer Aufpreis';
$_ADMINLANG['inwx_markups']['field.rounding'] = 'Rundung';
$_ADMINLANG['inwx_markups']['field.roundingDescription'] = 'Wird nach dem Aufpreis angewendet (z. B. 5,75 → 5,99).';
$_ADMINLANG['inwx_markups']['field.roundingDescriptionFull'] = 'Wird nach Aufpreis / Fixpreis angewendet (z. B. 5,75 → 5,99).';
$_ADMINLANG['inwx_markups']['field.tldExample'] = 'z. B. .es';
$_ADMINLANG['inwx_markups']['field.fixedPriceOnly'] = 'nur bei Modus "Fester Verkaufspreis"';
$_ADMINLANG['inwx_markups']['field.markupExample'] = 'z.B. 10 für 10% oder 0.50 für 0,50€ Aufpreis';

$_ADMINLANG['inwx_markups']['option.pleaseSelect'] = '-- bitte wählen --';
$_ADMINLANG['inwx_markups']['option.none'] = '-- keine --';
$_ADMINLANG['inwx_markups']['option.percent'] = 'Prozent (%)';
$_ADMINLANG['inwx_markups']['option.fixedAmount'] = 'Fester Betrag';
$_ADMINLANG['inwx_markups']['option.likeRegister'] = 'Wie Register (Standard)';
$_ADMINLANG['inwx_markups']['option.noRounding'] = 'Keine Rundung';

$_ADMINLANG['inwx_markups']['mode.none'] = 'Normal (kein Override)';
$_ADMINLANG['inwx_markups']['mode.disable'] = 'Sync deaktivieren (alte Preise beibehalten)';
$_ADMINLANG['inwx_markups']['mode.disableShort'] = 'Sync deaktivieren';
$_ADMINLANG['inwx_markups']['mode.fixed'] = 'Fester Verkaufspreis';
$_ADMINLANG['inwx_markups']['mode.markup'] = 'Aufpreis (fester Betrag oder %)';
$_ADMINLANG['inwx_markups']['mode.markupShort'] = 'Aufpreis';

$_ADMINLANG['inwx_markups']['button.bulkApply'] = 'Bulk auf alle INWX-TLDs anwenden';
$_ADMINLANG['inwx_markups']['button.search'] = 'Suchen';
$_ADMINLANG['inwx_markups']['button.reset'] = 'Zurücksetzen';
$_ADMINLANG['inwx_markups']['button.save'] = 'Speichern';

$_ADMINLANG['inwx_markups']['rules.title'] = 'Bestehende Regeln';
$_ADMINLANG['inwx_markups']['rules.noRulesFound'] = 'Keine Regeln gefunden.';
$_ADMINLANG['inwx_markups']['rules.noRulesDefined'] = 'Noch keine Regeln definiert.';

$_ADMINLANG['inwx_markups']['search.placeholder'] = 'Suche nach TLD, Währung oder Modus...';

$_ADMINLANG['inwx_markups']['pagination.perPage'] = 'Einträge pro Seite:';
$_ADMINLANG['inwx_markups']['pagination.rulesFound'] = 'Regel(n) gefunden';
$_ADMINLANG['inwx_markups']['pagination.page'] = 'Seite';
$_ADMINLANG['inwx_markups']['pagination.of'] = 'von';
$_ADMINLANG['inwx_markups']['pagination.previous'] = 'Zurück';
$_ADMINLANG['inwx_markups']['pagination.next'] = 'Weiter';

$_ADMINLANG['inwx_markups']['table.currency'] = 'Währung';
$_ADMINLANG['inwx_markups']['table.mode'] = 'Modus';
$_ADMINLANG['inwx_markups']['table.register'] = 'Register';
$_ADMINLANG['inwx_markups']['table.renew'] = 'Renew';
$_ADMINLANG['inwx_markups']['table.transfer'] = 'Transfer';
$_ADMINLANG['inwx_markups']['table.actions'] = 'Aktionen';

$_ADMINLANG['inwx_markups']['action.edit'] = 'Bearbeiten';
$_ADMINLANG['inwx_markups']['action.delete'] = 'Löschen';
$_ADMINLANG['inwx_markups']['action.confirmDelete'] = 'Wirklich löschen?';

$_ADMINLANG['inwx_markups']['form.editRule'] = 'Regel bearbeiten';
$_ADMINLANG['inwx_markups']['form.addRule'] = 'Neue Regel hinzufügen';

$_ADMINLANG['inwx_markups']['message.saved'] = 'Gespeichert.';
$_ADMINLANG['inwx_markups']['message.savedDescription'] = 'Die TLD-Einstellung wurde übernommen.';
$_ADMINLANG['inwx_markups']['message.deleted'] = 'Gelöscht.';
$_ADMINLANG['inwx_markups']['message.deletedDescription'] = 'Der Eintrag wurde entfernt.';

$_ADMINLANG['inwx_markups']['error.title'] = 'Fehler.';
$_ADMINLANG['inwx_markups']['error.checkInput'] = 'Bitte Eingaben prüfen.';
$_ADMINLANG['inwx_markups']['error.loadData'] = 'Fehler beim Laden der Daten: ';
$_ADMINLANG['inwx_markups']['error.toolNotFound'] = 'Tool nicht gefunden.';
