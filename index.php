<?php
require_once 'funciones.php';

// --- LÓGICA PRINCIPAL DE LA PÁGINA ---
$transportadoras_seleccionadas = $_GET['transportadora'] ?? [];
$transportadoras_disponibles = obtener_lista_transportadoras();
$datos_filtrados = obtener_datos($transportadoras_seleccionadas);

?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Resumen de Despachos - Últimos 2 Meses</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Estilo para jQuery UI Resizable -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <link rel="stylesheet" href="styles.css">

    <style>
        #tablaDatos_wrapper .dataTables_scrollHead,
        #tablaDatos_wrapper .dataTables_scrollBody {
            width: 100% !important;
        }

        #tablaDatos {
            width: 100% !important;
        }

        /* --- ESTILOS PARA LOS TÍTULOS Y AJUSTE DE COLUMNAS --- */
        #tablaDatos thead th {
            white-space: nowrap;
            /* Evita que el texto del encabezado se divida en varias líneas */
            vertical-align: middle;
            position: relative;
            /* Necesario para el manejador de tamaño */
        }

        /* Estilo para el manejador de arrastre de las columnas */
        #tablaDatos thead th .ui-resizable-handle {
            position: absolute;
            right: -5px;
            top: 0;
            bottom: 0;
            width: 10px;
            cursor: col-resize;
        }

        /* --- NUEVO: AJUSTE DE TAMAÑO DE FUENTE DEL CONTENIDO DE LA TABLA --- */
        #tablaDatos tbody td {
            font-size: 0.8rem;
            /* Reduce el tamaño de la letra en las celdas */
        }
    </style>
</head>

<body>
    <div class="container-fluid px-4 mt-4">
        <div class="sticky-top bg-light py-2 shadow-sm" style="z-index: 1030;">
            <h2 class="titulo-principal mb-3">Resumen de Despachos - Últimos 2 Meses</h2>
            <form method="get" id="filtroForm" class="row g-3 align-items-center mb-3 mt-2 px-3">
                <div class="col-md-5">
                    <label for="transportadora" class="form-label">Transportadoras</label>
                    <select name="transportadora[]" id="transportadora" class="form-select select2" multiple>
                        <?php foreach ($transportadoras_disponibles as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo in_array($t, $transportadoras_seleccionadas) ? 'selected' : ''; ?>>
                                <?php echo strtoupper(htmlspecialchars($t)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-filtrar w-100">Filtrar</button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="index.php" class="btn btn-limpiar w-100">Limpiar</a>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a id="exportarExcelBtn" href="#" class="btn btn-exportar w-100">Exportar a Excel</a>
                </div>
            </form>
        </div>

        <div class="table-responsive-sm px-3">
            <p class="mb-3">Cantidad total de registros encontrados: <strong><?php echo count($datos_filtrados); ?></strong></p>
            <table id="tablaDatos" class="table table-bordered table-hover table-sm table-striped align-middle w-100">
                <thead class="table-dark">
                    <tr>
                        <th>Planilla</th>
                        <th>Transp.</th>
                        <th>Pedido</th>
                        <th>Guía</th>
                        <th>Destinatario</th>
                        <th>Origen</th>
                        <th>Destino</th>
                        <th>Ordenada</th>
                        <th>Despachada</th>
                        <th>Pendte</th>
                        <th>Cancel</th>
                        <th>Estado</th>
                        <th>Despacho</th>
                        <th>Entrega</th>
                        <th>Días Trans.</th>
                        <th>Días Promesa</th>
                        <th>Cumplimiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($datos_filtrados as $fila): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fila['TRPPLANILLA'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['CARRIER'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['PEDIDOCLIENTE'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['GUIA'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['DCLIENTESHP'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['ORIGEN'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['CIUDAD'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['TOT_ORDENADA'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['CANT_DESPACHADA'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['TOT_PENDIENTE'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($fila['TOT_CANCELADA'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($fila['ESTADO'])):
                                    $estado = strtolower($fila['ESTADO']);
                                    $bg_class = 'bg-warning text-dark'; // Default
                                    if (strpos($estado, 'entregado') !== false) $bg_class = 'bg-success';
                                    elseif (strpos($estado, 'anulado') !== false) $bg_class = 'bg-danger';
                                    elseif (strpos($estado, 'pendiente') !== false) $bg_class = 'bg-secondary';
                                    elseif (strpos($estado, 'en ruta') !== false || strpos($estado, 'en transito') !== false) $bg_class = 'bg-primary';
                                ?>
                                    <span class="badge <?php echo $bg_class; ?>"><?php echo htmlspecialchars($fila['ESTADO']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($fila['DOCFECHA']) ? (new DateTime($fila['DOCFECHA']))->format('Y-m-d') : ''; ?></td>
                            <td><?php echo !empty($fila['FECHA_ENTREGA']) ? (new DateTime($fila['FECHA_ENTREGA']))->format('Y-m-d') : ''; ?></td>
                            <td>
                                <?php
                                $dias = $fila['DIAS_TRANSCURRIDOS'];
                                $dias_class = 'bg-secondary';
                                if ($dias !== null) {
                                    if ($dias <= 5) $dias_class = 'bg-success';
                                    elseif ($dias <= 10) $dias_class = 'bg-warning text-dark';
                                    else $dias_class = 'bg-danger';
                                }
                                ?>
                                <span class="badge <?php echo $dias_class; ?>"><?php echo $dias ?? '-'; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($fila['DIAS_PROMESA'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $cumplimiento = $fila['CUMPLIMIENTO_TE'];
                                $cumplimiento_class = 'bg-light text-dark';
                                if ($cumplimiento == 'CUMPLE') $cumplimiento_class = 'bg-success';
                                elseif ($cumplimiento == 'NO CUMPLE') $cumplimiento_class = 'bg-danger';
                                elseif ($cumplimiento == 'PENDIENTE') $cumplimiento_class = 'bg-info text-dark';
                                ?>
                                <span class="badge <?php echo $cumplimiento_class; ?>"><?php echo htmlspecialchars($cumplimiento); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script> <!-- jQuery UI para Resizable -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#tablaDatos').DataTable({
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                },
                pageLength: 50,
                lengthMenu: [10, 25, 50, 100, 200, 500],
                ordering: true,
                responsive: true,
                deferRender: true,
                scrollY: '65vh',
                scrollCollapse: true,
                scrollX: true,
                processing: true,
                createdRow: function(row, data, dataIndex) {
                    // El índice de la columna 'Pendiente' ahora es 9
                    let valorPendiente = parseFloat(data[9].replace(',', '').trim());
                    if (!isNaN(valorPendiente) && valorPendiente > 0) {
                        $(row).addClass('resaltado-pendiente');
                    }
                }
            });

            // --- NUEVO: HABILITAR AJUSTE DE ANCHO DE COLUMNAS ---
            $('#tablaDatos thead th').resizable({
                handles: 'e', // Habilitar el manejador en el borde derecho (east)
                stop: function(event, ui) {
                    // Cuando se termina de arrastrar, se ajusta el ancho de la columna
                    $(this).css({
                        width: ui.size.width + 'px'
                    });
                    table.columns.adjust(); // Se le dice a DataTables que recalcule el layout
                }
            });

            $('.select2').select2({
                placeholder: "Selecciona una o varias transportadoras",
                allowClear: true,
                width: '100%'
            });

            function actualizarEnlaceExportacion() {
                var transportadoras = $('#transportadora').val();
                var queryString = $.param({
                    'transportadora': transportadoras
                });
                $('#exportarExcelBtn').attr('href', 'exportar_excel.php?' + queryString);
            }

            actualizarEnlaceExportacion();
            $('#transportadora').on('change', actualizarEnlaceExportacion);
        });
    </script>
</body>

</html>
