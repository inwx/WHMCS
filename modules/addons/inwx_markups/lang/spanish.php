<?php

if (!defined('WHMCS')) {
    exit('This file cannot be accessed directly');
}

$_ADMINLANG['inwx_markups']['heading.title'] = 'INWX Sincronización de Precios y Márgenes';
$_ADMINLANG['inwx_markups']['heading.description'] = 'Aquí puedes configurar, por TLD y moneda:';
$_ADMINLANG['inwx_markups']['heading.modeDisable'] = 'Desactivar sincronización';
$_ADMINLANG['inwx_markups']['heading.modeDisableDesc'] = 'Los precios antiguos permanecen sin cambios';
$_ADMINLANG['inwx_markups']['heading.modeFixed'] = 'Precio de venta fijo';
$_ADMINLANG['inwx_markups']['heading.modeFixedDesc'] = 'Siempre se aplicará este precio, independientemente de la sincronización';
$_ADMINLANG['inwx_markups']['heading.modeMarkup'] = 'Margen';
$_ADMINLANG['inwx_markups']['heading.modeMarkupDesc'] = 'Cantidad fija o porcentaje sobre el precio de compra al registrador (solo para TLDs con registrador "inwx")';

$_ADMINLANG['inwx_markups']['bulk.title'] = 'Configuración masiva para todos los TLDs de INWX';

$_ADMINLANG['inwx_markups']['field.currency'] = 'Moneda';
$_ADMINLANG['inwx_markups']['field.mode'] = 'Modo';
$_ADMINLANG['inwx_markups']['field.register'] = 'Registro (1 año)';
$_ADMINLANG['inwx_markups']['field.renew'] = 'Renovación (1 año)';
$_ADMINLANG['inwx_markups']['field.transfer'] = 'Transferencia (1 año)';
$_ADMINLANG['inwx_markups']['field.registerMarkup'] = 'Margen en registro';
$_ADMINLANG['inwx_markups']['field.renewMarkup'] = 'Margen en renovación';
$_ADMINLANG['inwx_markups']['field.transferMarkup'] = 'Margen en transferencia';
$_ADMINLANG['inwx_markups']['field.rounding'] = 'Redondeo';
$_ADMINLANG['inwx_markups']['field.roundingDescription'] = 'Se aplica después del margen (ej. 5,75 → 5,99).';
$_ADMINLANG['inwx_markups']['field.roundingDescriptionFull'] = 'Se aplica después del margen / precio fijo (ej. 5,75 → 5,99).';
$_ADMINLANG['inwx_markups']['field.tldExample'] = 'ej. .es';
$_ADMINLANG['inwx_markups']['field.fixedPriceOnly'] = 'solo para modo "Precio de venta fijo"';
$_ADMINLANG['inwx_markups']['field.markupExample'] = 'ej. 10 para 10% o 0,50 para 0,50€ de margen';

$_ADMINLANG['inwx_markups']['option.pleaseSelect'] = '-- por favor selecciona --';
$_ADMINLANG['inwx_markups']['option.none'] = '-- ninguna --';
$_ADMINLANG['inwx_markups']['option.percent'] = 'Porcentaje (%)';
$_ADMINLANG['inwx_markups']['option.fixedAmount'] = 'Cantidad fija';
$_ADMINLANG['inwx_markups']['option.likeRegister'] = 'Como Registro (por defecto)';
$_ADMINLANG['inwx_markups']['option.noRounding'] = 'Sin redondeo';

$_ADMINLANG['inwx_markups']['mode.none'] = 'Normal (sin sobreescribir)';
$_ADMINLANG['inwx_markups']['mode.disable'] = 'Desactivar sincronización (mantener precios antiguos)';
$_ADMINLANG['inwx_markups']['mode.disableShort'] = 'Desactivar sincronización';
$_ADMINLANG['inwx_markups']['mode.fixed'] = 'Precio de venta fijo';
$_ADMINLANG['inwx_markups']['mode.markup'] = 'Margen (cantidad fija o %)';
$_ADMINLANG['inwx_markups']['mode.markupShort'] = 'Margen';

$_ADMINLANG['inwx_markups']['button.bulkApply'] = 'Aplicar masivamente a todos los TLDs de INWX';
$_ADMINLANG['inwx_markups']['button.search'] = 'Buscar';
$_ADMINLANG['inwx_markups']['button.reset'] = 'Restablecer';
$_ADMINLANG['inwx_markups']['button.save'] = 'Guardar';

$_ADMINLANG['inwx_markups']['rules.title'] = 'Reglas existentes';
$_ADMINLANG['inwx_markups']['rules.noRulesFound'] = 'No se encontraron reglas.';
$_ADMINLANG['inwx_markups']['rules.noRulesDefined'] = 'Aún no se han definido reglas.';

$_ADMINLANG['inwx_markups']['search.placeholder'] = 'Buscar por TLD, moneda o modo...';

$_ADMINLANG['inwx_markups']['pagination.perPage'] = 'Entradas por página:';
$_ADMINLANG['inwx_markups']['pagination.rulesFound'] = 'regla(s) encontrada(s)';
$_ADMINLANG['inwx_markups']['pagination.page'] = 'Página';
$_ADMINLANG['inwx_markups']['pagination.of'] = 'de';
$_ADMINLANG['inwx_markups']['pagination.previous'] = 'Atrás';
$_ADMINLANG['inwx_markups']['pagination.next'] = 'Siguiente';

$_ADMINLANG['inwx_markups']['table.currency'] = 'Moneda';
$_ADMINLANG['inwx_markups']['table.mode'] = 'Modo';
$_ADMINLANG['inwx_markups']['table.register'] = 'Registro';
$_ADMINLANG['inwx_markups']['table.renew'] = 'Renovación';
$_ADMINLANG['inwx_markups']['table.transfer'] = 'Transferencia';
$_ADMINLANG['inwx_markups']['table.actions'] = 'Acciones';

$_ADMINLANG['inwx_markups']['action.edit'] = 'Editar';
$_ADMINLANG['inwx_markups']['action.delete'] = 'Eliminar';
$_ADMINLANG['inwx_markups']['action.confirmDelete'] = '¿Realmente deseas eliminarlo?';

$_ADMINLANG['inwx_markups']['form.editRule'] = 'Editar regla';
$_ADMINLANG['inwx_markups']['form.addRule'] = 'Añadir nueva regla';

$_ADMINLANG['inwx_markups']['message.saved'] = 'Guardado.';
$_ADMINLANG['inwx_markups']['message.savedDescription'] = 'La configuración de TLD ha sido aplicada.';
$_ADMINLANG['inwx_markups']['message.deleted'] = 'Eliminado.';
$_ADMINLANG['inwx_markups']['message.deletedDescription'] = 'La entrada ha sido eliminada.';

$_ADMINLANG['inwx_markups']['error.title'] = 'Error.';
$_ADMINLANG['inwx_markups']['error.checkInput'] = 'Por favor verifica los datos introducidos.';
$_ADMINLANG['inwx_markups']['error.loadData'] = 'Error al cargar datos: ';
$_ADMINLANG['inwx_markups']['error.toolNotFound'] = 'Herramienta no encontrada.';
