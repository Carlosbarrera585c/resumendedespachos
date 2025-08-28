<?php
// Habilitar la visualización de errores para facilitar la depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- LÓGICA DE CÁLCULO DE DÍAS FESTIVOS EN COLOMBIA ---
function get_colombian_holidays($year)
{
    $holidays = [];
    $easter_date = new DateTime(date('Y-m-d', easter_date($year)));

    // Fijos
    $holidays[] = (new DateTime("$year-01-01"))->format('Y-m-d'); // Año Nuevo
    $holidays[] = (new DateTime("$year-05-01"))->format('Y-m-d'); // Día del Trabajo
    $holidays[] = (new DateTime("$year-07-20"))->format('Y-m-d'); // Día de la Independencia
    $holidays[] = (new DateTime("$year-08-07"))->format('Y-m-d'); // Batalla de Boyacá
    $holidays[] = (new DateTime("$year-12-08"))->format('Y-m-d'); // Inmaculada Concepción
    $holidays[] = (new DateTime("$year-12-25"))->format('Y-m-d'); // Navidad

    $next_monday = function (DateTime $date) {
        if ($date->format('N') != 1) {
            $date->modify('next monday');
        }
        return $date;
    };

    // Ley de Puche
    $holidays[] = $next_monday(new DateTime("$year-01-06"))->format('Y-m-d'); // Reyes Magos
    $holidays[] = $next_monday(new DateTime("$year-03-19"))->format('Y-m-d'); // San José
    $holidays[] = $next_monday(new DateTime("$year-06-29"))->format('Y-m-d'); // San Pedro y San Pablo
    $holidays[] = $next_monday(new DateTime("$year-08-15"))->format('Y-m-d'); // Asunción de la Virgen
    $holidays[] = $next_monday(new DateTime("$year-10-12"))->format('Y-m-d'); // Día de la Raza
    $holidays[] = $next_monday(new DateTime("$year-11-01"))->format('Y-m-d'); // Todos los Santos
    $holidays[] = $next_monday(new DateTime("$year-11-11"))->format('Y-m-d'); // Independencia de Cartagena

    // Basados en Pascua
    $holidays[] = (clone $easter_date)->modify('-3 days')->format('Y-m-d'); // Jueves Santo
    $holidays[] = (clone $easter_date)->modify('-2 days')->format('Y-m-d'); // Viernes Santo
    $holidays[] = $next_monday((clone $easter_date)->modify('+40 days'))->format('Y-m-d'); // Ascensión de Jesús
    $holidays[] = $next_monday((clone $easter_date)->modify('+61 days'))->format('Y-m-d'); // Corpus Christi
    $holidays[] = $next_monday((clone $easter_date)->modify('+68 days'))->format('Y-m-d'); // Sagrado Corazón

    return $holidays;
}

function contar_dias_habiles($fecha_inicio_str, $fecha_fin_str)
{
    try {
        $fecha_inicio = new DateTime($fecha_inicio_str);
        $fecha_fin = new DateTime($fecha_fin_str);
    } catch (Exception $e) {
        return null; // Retorna null si las fechas son inválidas
    }

    $holidays_year_start = get_colombian_holidays((int)$fecha_inicio->format('Y'));
    $holidays_year_end = get_colombian_holidays((int)$fecha_fin->format('Y'));
    $holidays = array_unique(array_merge($holidays_year_start, $holidays_year_end));

    $dias_habiles = 0;
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($fecha_inicio, $interval, $fecha_fin->modify('+1 day'));

    foreach ($period as $date) {
        if ($date->format('N') < 6 && !in_array($date->format('Y-m-d'), $holidays)) {
            $dias_habiles++;
        }
    }
    return $dias_habiles;
}


// --- FUNCIÓN PRINCIPAL PARA OBTENER Y PROCESAR DATOS ---
function obtener_datos($transportadoras_filtro = [])
{
    $hoy = new DateTime();
    $hace_60_dias = (new DateTime())->sub(new DateInterval('P60D'));
    $fecha_limite = $hace_60_dias->format('Y-m-d');
    $fecha_fin = $hoy->format('Y-m-d');

    $dsn_wms = '';
    $user_wms = '';
    $pass_wms = '';
    $conn_wms = odbc_connect($dsn_wms, $user_wms, $pass_wms) or die("Error al conectar a WMS: " . odbc_errormsg());

    $sql_wms = "
        WITH TABLA AS (
            SELECT m.trpplanilla, m.docfecha, m.docnumero, m.clienteshp, m.qty, m.carrier, 
                   m.pedidocliente, m.codtra, p.vehiculoid, p.horafincargue, r.lineaproducto, cliente,
                   m.whscod AS ORIGEN
            FROM inmov m 
            LEFT OUTER JOIN trpplanillas p ON p.trpplanilla = m.trpplanilla
            LEFT OUTER JOIN resmst r ON r.recurso = m.recurso
            WHERE m.codtra = 'SALEPCKLST' 
              AND m.whscod IN ('ZFP', 'MED', 'MOSQ') 
              AND m.docfecha >= ? AND m.docfecha <= ?
    ";

    $params_wms = [$fecha_limite, $fecha_fin];
    if (!empty($transportadoras_filtro)) {
        $placeholders = implode(', ', array_fill(0, count($transportadoras_filtro), '?'));
        $sql_wms .= " AND m.carrier IN ($placeholders)";
        $params_wms = array_merge($params_wms, $transportadoras_filtro);
    }

    $sql_wms .= "
        ) 
        SELECT trpplanilla, docfecha, carrier, pedidocliente, clienteshp, ORIGEN,
               (SELECT FIRST 1 dclienteshp FROM clientesshp WHERE cliente = t.cliente AND clienteshp = t.clienteshp) AS dclienteshp,
               CAST((SELECT SUM(qordenada) FROM peddet WHERE cliente = t.cliente AND pedidocliente = t.pedidocliente) AS INTEGER) AS tot_ordenada,
               CAST(SUM(qty) AS INTEGER) AS cant_despachada, 
               (SELECT SUM(qordenada - qdespachada - qcancelada) FROM peddet WHERE cliente = t.cliente AND pedidocliente = t.pedidocliente) AS tot_pendiente,
               (SELECT SUM(qcancelada) FROM peddet WHERE cliente = t.cliente AND pedidocliente = t.pedidocliente) AS tot_cancelada
        FROM TABLA t
        GROUP BY trpplanilla, docfecha, carrier, pedidocliente, codtra, vehiculoid, horafincargue, cliente, clienteshp, ORIGEN
    ";

    $stmt_wms = odbc_prepare($conn_wms, $sql_wms);
    odbc_execute($stmt_wms, $params_wms);

    $datos_wms = [];
    while ($fila = odbc_fetch_array($stmt_wms)) {
        $datos_wms[] = $fila;
    }
    odbc_close($conn_wms);

    if (empty($datos_wms)) return [];

    $transportadoras_unicas = [];
    foreach ($datos_wms as $fila) {
        if (!empty($fila['CARRIER'])) {
            $transportadoras_unicas[] = trim($fila['CARRIER']);
        }
    }
    $transportadoras_unicas = array_unique($transportadoras_unicas);

    $tiempos_promesa_map = [];
    $dsn_estado = '';
    $user_estado = '';
    $pass_estado = '';
    $conn_estado = odbc_connect($dsn_estado, $user_estado, $pass_estado);
    if ($conn_estado) {
        $sql_tiempos = "SELECT ciudad, dias_promesa FROM tiempos_entrega";
        $stmt_tiempos = odbc_prepare($conn_estado, $sql_tiempos);
        if ($stmt_tiempos && odbc_execute($stmt_tiempos)) {
            while ($row = odbc_fetch_array($stmt_tiempos)) {
                $tiempos_promesa_map[strtoupper($row['ciudad'])] = (int)$row['dias_promesa'];
            }
        }
    }

    $datos_estado_map = [];
    if (!empty($transportadoras_unicas) && $conn_estado) {
        $tabla_aliases = [
            "al dia" => "aldia",
            "coordinadora" => "coordinadora",
            "boterosoto" => "boterosoto",
            "ditransa" => "ditransa",
            "reclama" => "reclama",
            "interllantas" => "interllantas",
            "timon" => "timon",
            "tmq" => "tmq",
            "particular" => "particular"

        ];

        foreach ($transportadoras_unicas as $transportadora) {
            try {
                $nombre_tabla = str_replace(' ', '', strtolower($transportadora));
                $nombre_tabla = $tabla_aliases[$nombre_tabla] ?? $nombre_tabla;

                // Consulta que trae todas las guías de los últimos 60 días.
                // ¡Asegúrate que la columna 'fecha_despacho' exista en todas las tablas de transportadoras!
                $query_por_transportadora = "SELECT guia, documento, estado, ciudad, fecha_entrega FROM `$nombre_tabla` WHERE fecha_despacho >= ?";

                $stmt_estado = odbc_prepare($conn_estado, $query_por_transportadora);

                if ($stmt_estado) {
                    odbc_execute($stmt_estado, [$fecha_limite]);

                    while ($row = odbc_fetch_array($stmt_estado)) {
                        $documento = trim($row['documento']);
                        if ($documento) {
                            $clave_numerica = preg_replace('/[^0-9]/', '', $documento);
                            if ($clave_numerica) {
                                $datos_estado_map[$clave_numerica] = $row;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        odbc_close($conn_estado);
    }

    $datos_finales = [];
    foreach ($datos_wms as $fila) {
        $pedido_cliente = trim($fila['PEDIDOCLIENTE']);
        $clave_numerica_busqueda = preg_replace('/[^0-9]/', '', $pedido_cliente);
        $info_estado = $datos_estado_map[$clave_numerica_busqueda] ?? null;

        $fila['GUIA'] = $info_estado['guia'] ?? null;
        $fila['ESTADO'] = $info_estado['estado'] ?? null;
        $fila['CIUDAD'] = $info_estado['ciudad'] ?? 'No encontrada';
        $fila['FECHA_ENTREGA'] = $info_estado['fecha_entrega'] ?? null;

        if ($fila['DOCFECHA']) {
            $fecha_inicio = $fila['DOCFECHA'];
            $fecha_fin = $fila['FECHA_ENTREGA'] ?? date('Y-m-d');
            $fila['DIAS_TRANSCURRIDOS'] = contar_dias_habiles($fecha_inicio, $fecha_fin);
        } else {
            $fila['DIAS_TRANSCURRIDOS'] = null;
        }

        $ciudad_normalizada = strtoupper($fila['CIUDAD']);
        $dias_promesa = $tiempos_promesa_map[$ciudad_normalizada] ?? null;
        $fila['DIAS_PROMESA'] = $dias_promesa;

        $fila['CUMPLIMIENTO_TE'] = 'N/A';
        if (strtolower($fila['ESTADO'] ?? '') == 'entregado') {
            if ($dias_promesa !== null && $fila['DIAS_TRANSCURRIDOS'] !== null) {
                $fila['CUMPLIMIENTO_TE'] = ($fila['DIAS_TRANSCURRIDOS'] <= $dias_promesa) ? 'CUMPLE' : 'NO CUMPLE';
            }
        } else if (!empty($fila['ESTADO'])) {
            $fila['CUMPLIMIENTO_TE'] = 'PENDIENTE';
        }

        $datos_finales[] = $fila;
    }

    return $datos_finales;
}

function obtener_lista_transportadoras()
{
    $dsn_wms = 'SERVINETWMS';
    $user_wms = 'sysdba';
    $pass_wms = 'masterkey';
    $conn_wms = odbc_connect($dsn_wms, $user_wms, $pass_wms) or die("Error al conectar a WMS: " . odbc_errormsg());

    $hoy = new DateTime();
    $hace_60_dias = (new DateTime())->sub(new DateInterval('P60D'));
    $fecha_limite = $hace_60_dias->format('Y-m-d');
    $fecha_fin = $hoy->format('Y-m-d');

    $sql = "SELECT DISTINCT carrier FROM inmov 
            WHERE codtra = 'SALEPCKLST' AND whscod IN ('ZFP', 'MED', 'MOSQ')
            AND docfecha >= ? AND docfecha <= ?
            AND carrier IS NOT NULL AND carrier <> '' ORDER BY carrier";

    $stmt = odbc_prepare($conn_wms, $sql);
    odbc_execute($stmt, [$fecha_limite, $fecha_fin]);

    $transportadoras = [];
    while ($row = odbc_fetch_array($stmt)) {
        if (!empty($row['CARRIER'])) {
            $transportadoras[] = trim($row['CARRIER']);
        }
    }
    odbc_close($conn_wms);
    return $transportadoras;
}

