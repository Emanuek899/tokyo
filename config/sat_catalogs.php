<?php
// Minimal SAT catalogs for selects and validation

const SAT_FORMA_PAGO = [
    '01' => 'Efectivo',
    '02' => 'Cheque nominativo',
    '03' => 'Transferencia electrónica de fondos',
    '04' => 'Tarjeta de crédito',
    '28' => 'Tarjeta de débito',
    '99' => 'Por definir',
];

const SAT_METODO_PAGO = [
    'PUE' => 'Pago en una sola exhibición',
    'PPD' => 'Pago en parcialidades o diferido',
];

const SAT_USO_CFDI = [
    'G01' => 'Adquisición de mercancías',
    'G03' => 'Gastos en general',
    'P01' => 'Por definir (no vigente, evitar)',
];

const SAT_REGIMENES = [
    '601' => 'General de Ley Personas Morales',
    '603' => 'Personas Morales con fines no lucrativos',
    '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
    '606' => 'Arrendamiento',
    '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
    '616' => 'Sin obligaciones fiscales',
];

function validar_rfc_estricto(string $rfc): bool {
    $rfc = strtoupper(trim($rfc));
    if (strlen($rfc) < 12 || strlen($rfc) > 13) return false;
    return (bool)preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc);
}

function normaliza_rfc(string $rfc): string { return strtoupper(trim($rfc)); }

