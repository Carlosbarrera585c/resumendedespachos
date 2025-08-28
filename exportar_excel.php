<?php
// Requerir el autoloader de Composer para usar PhpSpreadsheet
require 'vendor/autoload.php';

// Reutilizar la lógica de obtención de datos desde el nuevo archivo de funciones
require_once 'funciones.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Obtener los filtros de la URL
$transportadoras_seleccionadas = $_GET['transportadora'] ?? [];
$datos_para_exportar = obtener_datos($transportadoras_seleccionadas);

// Crear una nueva hoja de cálculo
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Resumen Despachos');

// Definir las cabeceras
$columnas = [
    'A' => 'Planilla',
    'B' => 'Transportadora',
    'C' => 'Pedido',
    'D' => 'Guía',
    'E' => 'Destinatario',
    'F' => 'Origen',
    'G' => 'Destino',
    'H' => 'Cant. Ordenada',
    'I' => 'Cant. Despachada',
    'J' => 'Cant. Pendiente',
    'K' => 'Cant. Cancelada',
    'L' => 'Estado',
    'M' => 'Fecha Despacho',
    'N' => 'Fecha Entrega',
    'O' => 'Días Transcurridos',
    'P' => 'Días Promesa',
    'Q' => 'Cumplimiento'
];

// Escribir las cabeceras en la primera fila
foreach ($columnas as $col => $titulo) {
    $sheet->setCellValue($col . '1', $titulo);
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
}

// Llenar la hoja con los datos
$fila_num = 2;
if (!empty($datos_para_exportar)) {
    foreach ($datos_para_exportar as $fila_dato) {
        $sheet->setCellValue('A' . $fila_num, $fila_dato['TRPPLANILLA']);
        $sheet->setCellValue('B' . $fila_num, $fila_dato['CARRIER']);
        $sheet->setCellValue('C' . $fila_num, $fila_dato['PEDIDOCLIENTE']);
        $sheet->setCellValue('D' . $fila_num, $fila_dato['GUIA']);
        $sheet->setCellValue('E' . $fila_num, $fila_dato['DCLIENTESHP']);
        $sheet->setCellValue('F' . $fila_num, $fila_dato['ORIGEN']);
        $sheet->setCellValue('G' . $fila_num, $fila_dato['CIUDAD']);
        $sheet->setCellValue('H' . $fila_num, $fila_dato['TOT_ORDENADA']);
        $sheet->setCellValue('I' . $fila_num, $fila_dato['CANT_DESPACHADA']);
        $sheet->setCellValue('J' . $fila_num, $fila_dato['TOT_PENDIENTE']);
        $sheet->setCellValue('K' . $fila_num, $fila_dato['TOT_CANCELADA']);
        $sheet->setCellValue('L' . $fila_num, $fila_dato['ESTADO']);
        $sheet->setCellValue('M' . $fila_num, !empty($fila_dato['DOCFECHA']) ? (new DateTime($fila_dato['DOCFECHA']))->format('Y-m-d') : '');
        $sheet->setCellValue('N' . $fila_num, !empty($fila_dato['FECHA_ENTREGA']) ? (new DateTime($fila_dato['FECHA_ENTREGA']))->format('Y-m-d') : '');
        $sheet->setCellValue('O' . $fila_num, $fila_dato['DIAS_TRANSCURRIDOS']);
        $sheet->setCellValue('P' . $fila_num, $fila_dato['DIAS_PROMESA']);
        $sheet->setCellValue('Q' . $fila_num, $fila_dato['CUMPLIMIENTO_TE']);
        $fila_num++;
    }
}

// Ajustar el ancho de las columnas automáticamente
foreach (range('A', 'Q') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Configurar las cabeceras HTTP para forzar la descarga del archivo
$filename = "Resumen_Despachos_" . date('Y-m-d') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Crear el escritor y enviar el archivo al navegador
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
