<?php
require_once 'db.php';
require_once 'auth.php';

$eventId   = isset($_GET['id']) ? intval($_GET['id']) : 0;
$event     = null;
$products  = [];
$actuals   = null;
$actualProducts = [];
$saveMsg   = '';
$saveOk    = false;
$mode      = 'form'; // 'form' or 'comparison'

// -------------------------------------------------------
// Load the forecast event data
// -------------------------------------------------------
if ($eventId > 0 && !$db_error) {
    $evResult = mysqli_query($conn, "SELECT * FROM event_analyses WHERE id = $eventId");
    if ($evResult && mysqli_num_rows($evResult) > 0) {
        $event = mysqli_fetch_array($evResult);
    }

    // Load forecast products
    $prResult = mysqli_query($conn, "SELECT * FROM event_products WHERE event_id = $eventId ORDER BY id ASC");
    if ($prResult && mysqli_num_rows($prResult) > 0) {
        while ($pRow = mysqli_fetch_array($prResult)) {
            $products[] = $pRow;
        }
    }

    // Check if actuals already exist
    $acResult = mysqli_query($conn, "SELECT * FROM event_actuals WHERE event_id = $eventId LIMIT 1");
    if ($acResult && mysqli_num_rows($acResult) > 0) {
        $actuals = mysqli_fetch_array($acResult);
        $mode = 'comparison';

        // Load actual products
        $apResult = mysqli_query($conn, "SELECT * FROM event_actual_products WHERE event_id = $eventId ORDER BY id ASC");
        if ($apResult && mysqli_num_rows($apResult) > 0) {
            while ($apRow = mysqli_fetch_array($apResult)) {
                $actualProducts[] = $apRow;
            }
        }
    }

    // -------------------------------------------------------
    // Load previous completed event for benchmarking
    // -------------------------------------------------------
    $prevEvent = null;
    $prevActuals = null;
    $prevActualProducts = [];
    if ($mode === 'comparison' && !$db_error) {
        $prevSQL = "SELECT id FROM event_analyses WHERE id < $eventId AND status = 'completed' ORDER BY id DESC LIMIT 1";
        $prevResult = mysqli_query($conn, $prevSQL);
        if ($prevResult && mysqli_num_rows($prevResult) > 0) {
            $prevRow = mysqli_fetch_array($prevResult);
            $prevId = intval($prevRow['id']);

            // Load full previous event
            $peResult = mysqli_query($conn, "SELECT * FROM event_analyses WHERE id = $prevId");
            if ($peResult && mysqli_num_rows($peResult) > 0) {
                $prevEvent = mysqli_fetch_array($peResult);
            }

            // Load previous actuals
            $paResult = mysqli_query($conn, "SELECT * FROM event_actuals WHERE event_id = $prevId LIMIT 1");
            if ($paResult && mysqli_num_rows($paResult) > 0) {
                $prevActuals = mysqli_fetch_array($paResult);
            }

            // Load previous actual products
            $papResult = mysqli_query($conn, "SELECT * FROM event_actual_products WHERE event_id = $prevId ORDER BY id ASC");
            if ($papResult && mysqli_num_rows($papResult) > 0) {
                while ($papRow = mysqli_fetch_array($papResult)) {
                    $prevActualProducts[] = $papRow;
                }
            }
        }
    }
}

// -------------------------------------------------------
// Handle POST — Save actuals
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $event && $mode === 'form' && !$db_error) {

    $aBooth     = isset($_POST['actual_booth_rental'])     ? floatval($_POST['actual_booth_rental']) : 0;
    $aTransport = isset($_POST['actual_transport_cost'])   ? floatval($_POST['actual_transport_cost']) : 0;
    $aMarketing = isset($_POST['actual_marketing_cost'])   ? floatval($_POST['actual_marketing_cost']) : 0;
    $aLabor     = isset($_POST['actual_labor_cost'])       ? floatval($_POST['actual_labor_cost']) : 0;
    $aNotes     = isset($_POST['actual_notes'])            ? trim($_POST['actual_notes']) : '';

    $apNames    = isset($_POST['ap_name'])      ? $_POST['ap_name']      : [];
    $apPrices   = isset($_POST['ap_price'])     ? $_POST['ap_price']     : [];
    $apCogs     = isset($_POST['ap_cogs'])      ? $_POST['ap_cogs']      : [];
    $apUnits    = isset($_POST['ap_units'])     ? $_POST['ap_units']     : [];
    $apStartInv = isset($_POST['ap_start_inv']) ? $_POST['ap_start_inv'] : [];
    $apRemaining= isset($_POST['ap_remaining']) ? $_POST['ap_remaining'] : [];

    $aTotalFixed   = $aBooth + $aTransport + $aMarketing + $aLabor;
    $aTotalRevenue = 0;
    $aTotalCOGS    = 0;

    // Build actual products
    $actualProdData = [];
    $apLen = count($apNames);
    for ($api = 0; $api < $apLen; $api++) {
        $apn = isset($apNames[$api])    ? trim($apNames[$api])          : '';
        $app = isset($apPrices[$api])   ? floatval($apPrices[$api])     : 0;
        $apc = isset($apCogs[$api])     ? floatval($apCogs[$api])       : 0;
        $apu = isset($apUnits[$api])    ? intval($apUnits[$api])        : 0;
        $apsi = isset($apStartInv[$api]) ? intval($apStartInv[$api])   : 0;
        $aprs = isset($apRemaining[$api])? intval($apRemaining[$api])   : 0;
        $apr = $app * $apu;

        $aTotalRevenue += $apr;
        $aTotalCOGS    += $apc * $apu;

        $actualProdData[] = [
            'name'      => $apn,
            'price'     => $app,
            'cogs'      => $apc,
            'units'     => $apu,
            'revenue'   => $apr,
            'start_inv' => $apsi,
            'remaining' => $aprs,
        ];
    }

    $aProfit = $aTotalRevenue - $aTotalFixed - $aTotalCOGS;

    // Save event_actuals
    $sNotes = mysqli_real_escape_string($conn, $aNotes);
    $sqlActual = "INSERT INTO event_actuals (
                    event_id, actual_booth_rental, actual_transport_cost, actual_marketing_cost,
                    actual_labor_cost, actual_total_fixed, actual_total_revenue, actual_total_cogs,
                    actual_profit, notes
                  ) VALUES (
                    $eventId, $aBooth, $aTransport, $aMarketing,
                    $aLabor, $aTotalFixed, $aTotalRevenue, $aTotalCOGS,
                    $aProfit, '$sNotes'
                  )";

    $insActual = mysqli_query($conn, $sqlActual);
    if ($insActual) {
        // Save actual products
        $apdLen = count($actualProdData);
        for ($api = 0; $api < $apdLen; $api++) {
            $apd = $actualProdData[$api];
            $sapn = mysqli_real_escape_string($conn, $apd['name']);
            $sapSQL = "INSERT INTO event_actual_products (event_id, product_name, actual_selling_price, actual_cogs_per_unit, actual_units_sold, actual_revenue, starting_inventory, remaining_stock)
                       VALUES ($eventId, '$sapn', " . $apd['price'] . ", " . $apd['cogs'] . ", " . $apd['units'] . ", " . $apd['revenue'] . ", " . $apd['start_inv'] . ", " . $apd['remaining'] . ")";
            mysqli_query($conn, $sapSQL);
        }

        // Update event status
        mysqli_query($conn, "UPDATE event_analyses SET status = 'completed' WHERE id = $eventId");

        $saveOk  = true;
        $saveMsg = 'Actual results saved successfully!';

        // Reload data for comparison view
        $actuals = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM event_actuals WHERE event_id = $eventId LIMIT 1"));
        $apReload = mysqli_query($conn, "SELECT * FROM event_actual_products WHERE event_id = $eventId ORDER BY id ASC");
        $actualProducts = [];
        if ($apReload && mysqli_num_rows($apReload) > 0) {
            while ($apRow = mysqli_fetch_array($apReload)) {
                $actualProducts[] = $apRow;
            }
        }
        $mode = 'comparison';
    } else {
        $saveMsg = 'Failed to save actuals. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blueprint Pro | Decision Support System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <style>
        /* Print Styles */
        @media print {
            @page { margin: 1cm; size: auto; }
            html, body { background: white !important; color: #000 !important; height: auto !important; min-height: 0 !important; margin: 0 !important; padding: 0 !important; }
            .sidebar, .back-link, .print-btn, .ai-helper-widget { display: none !important; }
            .main { padding: 0 !important; margin: 0 !important; width: 100% !important; border: none !important; overflow: visible !important; height: auto !important; min-height: 0 !important; }
            .app-shell { display: block !important; overflow: visible !important; height: auto !important; min-height: 0 !important; }
            .content-single { max-width: 100% !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; }
            .card, .pnl-section { box-shadow: none !important; border: 1px solid #ccc !important; margin-bottom: 20px !important; break-inside: avoid; background: white !important; page-break-inside: avoid; }
            .verdict-banner { color: #000 !important; background: transparent !important; border: 2px solid #aaa !important; page-break-after: avoid; }
            h1, h2, h3, h4, p, span, td, th, strong { color: #000 !important; }
            .comp-val strong, .pnl-amount, .pnl-total .pnl-amount { color: #000 !important; }
            .var-positive, .var-negative, .b-good, .b-bad, .b-neutral { color: #000 !important; text-shadow: none !important; }
            canvas { max-width: 100% !important; page-break-inside: avoid; }
            /* Keep grid columns on print */
            .comparison-kpis, .scorecard-grid, .benchmark-grid { display: grid !important; grid-template-columns: repeat(3, 1fr) !important; gap: 15px !important; page-break-inside: avoid; }
            .comparison-kpis .comp-kpi { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="app-shell">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="bx bx-store-alt"></i></div>
            <div>
                <span class="brand-name">BLUEPRINT <span class="brand-pro">PRO</span></span>
                <span class="brand-tag">Projected Intelligence</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class="bx bx-calculator"></i> Analyzer</a>
            <a href="history.php" class="nav-item"><i class="bx bx-history"></i> Past Analyses</a>
            <a href="guide.php" class="nav-item"><i class="bx bx-book-open"></i> User Guide</a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar"><?php echo strtoupper(substr($loggedInUserName, 0, 1)); ?></div>
                <div class="sidebar-user-info">
                    <span class="sidebar-user-name"><?php echo htmlspecialchars($loggedInFullName); ?></span>
                    <span class="sidebar-user-role">@<?php echo htmlspecialchars($loggedInUserName); ?></span>
                </div>
            </div>
            <a href="logout.php" class="sidebar-logout-btn"><i class="bx bx-log-out"></i> Sign Out</a>
            <span style="display:block;margin-top:12px;">Blueprint Pro &copy; 2024</span>
        </div>
    </aside>

    <!-- Main -->
    <main class="main">
        <header class="header">
            <div>
                <h1><?php echo $mode === 'comparison' ? 'Forecast vs Actual' : 'Record Post-Event Results'; ?></h1>
                <p class="header-sub">
                    <?php if ($event): ?>
                        <?php echo htmlspecialchars($event['event_name']); ?> — <?php echo intval($event['event_days']); ?> Day<?php echo intval($event['event_days']) > 1 ? 's' : ''; ?>
                    <?php else: ?>
                        Event not found
                    <?php endif; ?>
                </p>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <?php if ($mode === 'comparison'): ?>
                    <a href="export_pdf.php?id=<?php echo $eventId; ?>" target="_blank" class="submit-btn print-btn" style="padding: 10px 16px; background: rgba(59, 130, 246, 0.1); color: var(--blue); border: 1px solid var(--blue); border-radius: var(--radius-sm); font-family: var(--font-primary); font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none;">
                        <i class="bx bxs-file-pdf"></i> Export PDF Report
                    </a>
                <?php endif; ?>
                <a href="history.php" class="back-link"><i class="bx bx-arrow-back"></i> Back to History</a>
            </div>
        </header>

        <div class="content-single">

            <?php if (!$event): ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="bx bx-error-circle"></i>
                        <h3>Event Not Found</h3>
                        <p>Go to <a href="history.php">Past Analyses</a> and select an event to record actuals.</p>
                    </div>
                </div>

            <?php elseif ($mode === 'comparison'): ?>
                <!-- ====== COMPARISON VIEW ====== -->

                <?php if ($saveMsg !== ''): ?>
                    <div class="alert <?php echo $saveOk ? 'alert-success' : 'alert-error'; ?>">
                        <i class="bx <?php echo $saveOk ? 'bx-check-circle' : 'bx-error-circle'; ?>"></i>
                        <?php echo htmlspecialchars($saveMsg); ?>
                    </div>
                <?php endif; ?>

                <?php
                // Calculate forecast totals
                $fFixed   = floatval($event['total_fixed_costs']);
                $fRevenue = floatval($event['estimated_revenue']);
                $fCogs    = floatval($event['total_cogs']);
                $fProfit  = floatval($event['projected_profit']);
                $fUnits   = intval($event['total_estimated_units']);

                $aFixed   = floatval($actuals['actual_total_fixed']);
                $aRevenue = floatval($actuals['actual_total_revenue']);
                $aCogs    = floatval($actuals['actual_total_cogs']);
                $aProfit  = floatval($actuals['actual_profit']);
                $aUnits   = 0;
                $apLen = count($actualProducts);
                for ($api = 0; $api < $apLen; $api++) {
                    $aUnits += intval($actualProducts[$api]['actual_units_sold']);
                }
                ?>

                <!-- Outcome Banner -->
                <?php
                $outcomeClass = 'verdict-green';
                $outcomeIcon  = 'bx-check-circle';
                $outcomeText  = 'EVENT PROFITABLE';
                if ($aProfit < 0) {
                    $outcomeClass = 'verdict-red';
                    $outcomeIcon  = 'bx-x-circle';
                    $outcomeText  = 'EVENT AT A LOSS';
                } else if ($aProfit == 0) {
                    $outcomeClass = 'verdict-yellow';
                    $outcomeIcon  = 'bx-minus-circle';
                    $outcomeText  = 'BREAK EVEN';
                }
                ?>
                <div class="card">
                    <div class="verdict-banner <?php echo $outcomeClass; ?>">
                        <i class="bx <?php echo $outcomeIcon; ?>"></i>
                        <div>
                            <h2><?php echo $outcomeText; ?></h2>
                            <p>Actual Profit/Loss: <strong><?php echo ($aProfit >= 0 ? '+' : '') . 'RM ' . number_format($aProfit, 2); ?></strong></p>
                        </div>
                    </div>

                    <!-- Summary KPIs: Forecast vs Actual -->
                    <div class="comparison-kpis">
                        <div class="comp-kpi">
                            <span class="comp-kpi-label">Total Revenue</span>
                            <div class="comp-kpi-values">
                                <div class="comp-val forecast">
                                    <small>Forecast</small>
                                    <strong class="counter" data-target="<?php echo $fRevenue; ?>" data-prefix="RM " data-decimals="2">RM <?php echo number_format($fRevenue, 2); ?></strong>
                                </div>
                                <div class="comp-val actual">
                                    <small>Actual</small>
                                    <strong class="counter" data-target="<?php echo $aRevenue; ?>" data-prefix="RM " data-decimals="2">RM <?php echo number_format($aRevenue, 2); ?></strong>
                                </div>
                            </div>
                            <?php
                            $revVar = $aRevenue - $fRevenue;
                            $revPct = ($fRevenue > 0) ? (($revVar / $fRevenue) * 100) : 0;
                            ?>
                            <span class="comp-variance <?php echo $revVar >= 0 ? 'var-positive' : 'var-negative'; ?>">
                                <?php echo ($revVar >= 0 ? '+' : '') . number_format($revPct, 1); ?>% (<?php echo ($revVar >= 0 ? '+' : '') . 'RM ' . number_format($revVar, 2); ?>)
                            </span>
                        </div>

                        <div class="comp-kpi">
                            <span class="comp-kpi-label">Total Costs</span>
                            <div class="comp-kpi-values">
                                <div class="comp-val forecast">
                                    <small>Forecast</small>
                                    <strong class="counter" data-target="<?php echo $fFixed + $fCogs; ?>" data-prefix="RM " data-decimals="2">RM <?php echo number_format($fFixed + $fCogs, 2); ?></strong>
                                </div>
                                <div class="comp-val actual">
                                    <small>Actual</small>
                                    <strong class="counter" data-target="<?php echo $aFixed + $aCogs; ?>" data-prefix="RM " data-decimals="2">RM <?php echo number_format($aFixed + $aCogs, 2); ?></strong>
                                </div>
                            </div>
                            <?php
                            $costVar = ($aFixed + $aCogs) - ($fFixed + $fCogs);
                            $costPct = (($fFixed + $fCogs) > 0) ? (($costVar / ($fFixed + $fCogs)) * 100) : 0;
                            // For costs, NEGATIVE is good (spent less)
                            ?>
                            <span class="comp-variance <?php echo $costVar <= 0 ? 'var-positive' : 'var-negative'; ?>">
                                <?php echo ($costVar >= 0 ? '+' : '') . number_format($costPct, 1); ?>% (<?php echo ($costVar >= 0 ? '+' : '') . 'RM ' . number_format($costVar, 2); ?>)
                            </span>
                        </div>

                        <div class="comp-kpi">
                            <span class="comp-kpi-label">Profit/Loss</span>
                            <div class="comp-kpi-values">
                                <div class="comp-val forecast">
                                    <small>Forecast</small>
                                    <strong class="counter" data-target="<?php echo abs($fProfit); ?>" data-prefix="<?php echo $fProfit >= 0 ? '+RM ' : '-RM '; ?>" data-decimals="2"><?php echo ($fProfit >= 0 ? '+' : '') . 'RM ' . number_format($fProfit, 2); ?></strong>
                                </div>
                                <div class="comp-val actual">
                                    <small>Actual</small>
                                    <strong class="counter" data-target="<?php echo abs($aProfit); ?>" data-prefix="<?php echo $aProfit >= 0 ? '+RM ' : '-RM '; ?>" data-decimals="2"><?php echo ($aProfit >= 0 ? '+' : '') . 'RM ' . number_format($aProfit, 2); ?></strong>
                                </div>
                            </div>
                            <?php
                            $profitVar = $aProfit - $fProfit;
                            ?>
                            <span class="comp-variance <?php echo $profitVar >= 0 ? 'var-positive' : 'var-negative'; ?>">
                                <?php echo ($profitVar >= 0 ? '+' : '') . 'RM ' . number_format($profitVar, 2); ?> vs forecast
                            </span>
                        </div>
                    </div>

                    <!-- Cost Comparison Table -->
                    <div class="comparison-section">
                        <h3><i class="bx bx-money"></i> Fixed Cost Comparison</h3>
                        <table class="comparison-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Forecast</th>
                                    <th>Actual</th>
                                    <th>Variance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $costItems = [
                                    ['Booth Rental',   floatval($event['booth_rental']),   floatval($actuals['actual_booth_rental'])],
                                    ['Transport / Gas', floatval($event['transport_cost']), floatval($actuals['actual_transport_cost'])],
                                    ['Marketing',      floatval($event['marketing_cost']),  floatval($actuals['actual_marketing_cost'])],
                                    ['Labor Cost',     floatval($event['total_fixed_costs']) - floatval($event['booth_rental']) - floatval($event['transport_cost']) - floatval($event['marketing_cost']), floatval($actuals['actual_labor_cost'])],
                                ];
                                $ciLen = count($costItems);
                                for ($ci = 0; $ci < $ciLen; $ci++) {
                                    $ciName = $costItems[$ci][0];
                                    $ciF    = $costItems[$ci][1];
                                    $ciA    = $costItems[$ci][2];
                                    $ciV    = $ciA - $ciF;
                                    $ciVcls = $ciV <= 0 ? 'var-positive' : 'var-negative';
                                    echo '<tr>';
                                    echo '<td>' . $ciName . '</td>';
                                    echo '<td>RM ' . number_format($ciF, 2) . '</td>';
                                    echo '<td>RM ' . number_format($ciA, 2) . '</td>';
                                    echo '<td class="' . $ciVcls . '">' . ($ciV >= 0 ? '+' : '') . 'RM ' . number_format($ciV, 2) . '</td>';
                                    echo '</tr>';
                                }
                                ?>
                                <tr class="row-highlight">
                                    <td><strong>Total Fixed</strong></td>
                                    <td><strong>RM <?php echo number_format($fFixed, 2); ?></strong></td>
                                    <td><strong>RM <?php echo number_format($aFixed, 2); ?></strong></td>
                                    <?php $fixedVar = $aFixed - $fFixed; ?>
                                    <td class="<?php echo $fixedVar <= 0 ? 'var-positive' : 'var-negative'; ?>">
                                        <strong><?php echo ($fixedVar >= 0 ? '+' : '') . 'RM ' . number_format($fixedVar, 2); ?></strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Product Comparison Table -->
                    <div class="comparison-section">
                        <h3><i class="bx bx-package"></i> Product Sales Comparison</h3>
                        <table class="comparison-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Forecast Units</th>
                                    <th>Actual Units</th>
                                    <th>Forecast Revenue</th>
                                    <th>Actual Revenue</th>
                                    <th>Variance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $fpLen = count($products);
                                for ($fpi = 0; $fpi < $fpLen; $fpi++) {
                                    $fpName  = htmlspecialchars($products[$fpi]['product_name']);
                                    $fpUnits = intval($products[$fpi]['estimated_units']);
                                    $fpRev   = floatval($products[$fpi]['estimated_revenue']);

                                    // Find matching actual product
                                    $matchedUnits = 0;
                                    $matchedRev   = 0;
                                    $apLen2 = count($actualProducts);
                                    for ($api2 = 0; $api2 < $apLen2; $api2++) {
                                        if ($actualProducts[$api2]['product_name'] === $products[$fpi]['product_name']) {
                                            $matchedUnits = intval($actualProducts[$api2]['actual_units_sold']);
                                            $matchedRev   = floatval($actualProducts[$api2]['actual_revenue']);
                                            break;
                                        }
                                    }

                                    $revDiff = $matchedRev - $fpRev;
                                    $unitDiff = $matchedUnits - $fpUnits;

                                    echo '<tr>';
                                    echo '<td><strong>' . $fpName . '</strong></td>';
                                    echo '<td>' . number_format($fpUnits) . '</td>';
                                    echo '<td>' . number_format($matchedUnits) . '</td>';
                                    echo '<td>RM ' . number_format($fpRev, 2) . '</td>';
                                    echo '<td>RM ' . number_format($matchedRev, 2) . '</td>';
                                    echo '<td class="' . ($revDiff >= 0 ? 'var-positive' : 'var-negative') . '">' . ($revDiff >= 0 ? '+' : '') . 'RM ' . number_format($revDiff, 2) . '</td>';
                                    echo '</tr>';
                                }
                                ?>
                                <tr class="row-highlight">
                                    <td><strong>Totals</strong></td>
                                    <td><strong><?php echo number_format($fUnits); ?></strong></td>
                                    <td><strong><?php echo number_format($aUnits); ?></strong></td>
                                    <td><strong>RM <?php echo number_format($fRevenue, 2); ?></strong></td>
                                    <td><strong>RM <?php echo number_format($aRevenue, 2); ?></strong></td>
                                    <?php $totalRevVar = $aRevenue - $fRevenue; ?>
                                    <td class="<?php echo $totalRevVar >= 0 ? 'var-positive' : 'var-negative'; ?>">
                                        <strong><?php echo ($totalRevVar >= 0 ? '+' : '') . 'RM ' . number_format($totalRevVar, 2); ?></strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Product COGS Comparison Table -->
                    <div class="comparison-section">
                        <h3><i class="bx bx-receipt"></i> Product COGS Comparison</h3>
                        <table class="comparison-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Forecast COGS/Unit</th>
                                    <th>Actual COGS/Unit</th>
                                    <th>Forecast Total COGS</th>
                                    <th>Actual Total COGS</th>
                                    <th>Variance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalFcstCogs = 0;
                                $totalActCogs = 0;
                                $fpLen = count($products);
                                for ($fpi = 0; $fpi < $fpLen; $fpi++) {
                                    $fpName  = htmlspecialchars($products[$fpi]['product_name']);
                                    $fpUnitCogs = floatval($products[$fpi]['cogs_per_unit']);
                                    $fpUnits = intval($products[$fpi]['estimated_units']);
                                    $fpTotalCogs = $fpUnitCogs * $fpUnits;
                                    $totalFcstCogs += $fpTotalCogs;

                                    // Find matching actual product
                                    $matchedUnitCogs = 0;
                                    $matchedTotalCogs = 0;
                                    $apLen2 = count($actualProducts);
                                    for ($api2 = 0; $api2 < $apLen2; $api2++) {
                                        if ($actualProducts[$api2]['product_name'] === $products[$fpi]['product_name']) {
                                            $matchedUnitCogs = floatval($actualProducts[$api2]['actual_cogs_per_unit']);
                                            $matchedTotalCogs = $matchedUnitCogs * intval($actualProducts[$api2]['actual_units_sold']);
                                            break;
                                        }
                                    }
                                    $totalActCogs += $matchedTotalCogs;

                                    $cogsDiff = $matchedTotalCogs - $fpTotalCogs;

                                    echo '<tr>';
                                    echo '<td><strong>' . $fpName . '</strong></td>';
                                    echo '<td>RM ' . number_format($fpUnitCogs, 2) . '</td>';
                                    echo '<td>RM ' . number_format($matchedUnitCogs, 2) . '</td>';
                                    echo '<td>RM ' . number_format($fpTotalCogs, 2) . '</td>';
                                    echo '<td>RM ' . number_format($matchedTotalCogs, 2) . '</td>';
                                    
                                    // For COGS, positive variance (spent more) is bad (var-negative class), negative variance is good (var-positive class)
                                    echo '<td class="' . ($cogsDiff <= 0 ? 'var-positive' : 'var-negative') . '">' . ($cogsDiff >= 0 ? '+' : '') . 'RM ' . number_format($cogsDiff, 2) . '</td>';
                                    echo '</tr>';
                                }
                                ?>
                                <tr class="row-highlight">
                                    <td><strong>Totals</strong></td>
                                    <td></td>
                                    <td></td>
                                    <td><strong>RM <?php echo number_format($totalFcstCogs, 2); ?></strong></td>
                                    <td><strong>RM <?php echo number_format($totalActCogs, 2); ?></strong></td>
                                    <?php 
                                    $totalCogsVar = $totalActCogs - $totalFcstCogs; 
                                    ?>
                                    <td class="<?php echo $totalCogsVar <= 0 ? 'var-positive' : 'var-negative'; ?>">
                                        <strong><?php echo ($totalCogsVar >= 0 ? '+' : '') . 'RM ' . number_format($totalCogsVar, 2); ?></strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Forecast vs Actual Bar Chart -->
                    <div class="bep-chart-card">
                        <h3><i class="bx bx-bar-chart"></i> Forecast vs Actual</h3>
                        <div class="bep-chart-wrap">
                            <canvas id="comparisonChart"></canvas>
                        </div>
                        <div class="bep-chart-legend">
                            <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span> Forecast</span>
                            <span class="legend-item"><span class="legend-dot" style="background:#10b981;"></span> Actual</span>
                        </div>
                    </div>

                    <!-- ====== 1. EXPO P&L INCOME STATEMENT ====== -->
                    <?php
                    // P&L breakdown — Marketing is separated from fixed costs
                    $pnlRevenue    = $aRevenue;
                    $pnlCogs       = $aCogs;
                    $pnlGross      = $pnlRevenue - $pnlCogs;
                    $pnlBooth      = floatval($actuals['actual_booth_rental']);
                    $pnlLabor      = floatval($actuals['actual_labor_cost']);
                    $pnlTransport  = floatval($actuals['actual_transport_cost']);
                    $pnlFixedOps   = $pnlBooth + $pnlLabor + $pnlTransport;
                    $pnlNetOp      = $pnlGross - $pnlFixedOps;
                    $pnlMarketing  = floatval($actuals['actual_marketing_cost']);
                    $pnlFinalNet   = $pnlNetOp - $pnlMarketing;
                    $pnlRevSafe    = ($pnlRevenue > 0) ? $pnlRevenue : 1; // avoid div-by-zero
                    ?>
                    <div class="comparison-section">
                        <h3><i class="bx bx-file"></i> Expo P&L — Income Statement</h3>
                        <table class="pnl-statement">
                            <thead>
                                <tr>
                                    <th>Line Item</th>
                                    <th class="pnl-amount-col">Amount (RM)</th>
                                    <th class="pnl-pct-col">% of Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="pnl-label">Total Revenue</td>
                                    <td class="pnl-amount"><?php echo number_format($pnlRevenue, 2); ?></td>
                                    <td class="pnl-pct">100.0%</td>
                                </tr>
                                <tr class="pnl-deduction">
                                    <td class="pnl-label pnl-indent">Less: Total COGS (Variable Costs)</td>
                                    <td class="pnl-amount">(<?php echo number_format($pnlCogs, 2); ?>)</td>
                                    <td class="pnl-pct"><?php echo number_format(($pnlCogs / $pnlRevSafe) * 100, 1); ?>%</td>
                                </tr>
                                <tr class="pnl-subtotal">
                                    <td class="pnl-label"><strong>= Gross Profit</strong></td>
                                    <td class="pnl-amount"><strong class="<?php echo $pnlGross >= 0 ? 'text-safe' : 'text-danger'; ?>"><?php echo number_format($pnlGross, 2); ?></strong></td>
                                    <td class="pnl-pct"><strong><?php echo number_format(($pnlGross / $pnlRevSafe) * 100, 1); ?>%</strong></td>
                                </tr>
                                <tr class="pnl-deduction">
                                    <td class="pnl-label pnl-indent">Less: Booth Rental</td>
                                    <td class="pnl-amount">(<?php echo number_format($pnlBooth, 2); ?>)</td>
                                    <td class="pnl-pct"><?php echo number_format(($pnlBooth / $pnlRevSafe) * 100, 1); ?>%</td>
                                </tr>
                                <tr class="pnl-deduction">
                                    <td class="pnl-label pnl-indent">Less: Labor Cost</td>
                                    <td class="pnl-amount">(<?php echo number_format($pnlLabor, 2); ?>)</td>
                                    <td class="pnl-pct"><?php echo number_format(($pnlLabor / $pnlRevSafe) * 100, 1); ?>%</td>
                                </tr>
                                <tr class="pnl-deduction">
                                    <td class="pnl-label pnl-indent">Less: Transport / Gas</td>
                                    <td class="pnl-amount">(<?php echo number_format($pnlTransport, 2); ?>)</td>
                                    <td class="pnl-pct"><?php echo number_format(($pnlTransport / $pnlRevSafe) * 100, 1); ?>%</td>
                                </tr>
                                <tr class="pnl-subtotal">
                                    <td class="pnl-label"><strong>= Net Operating Profit</strong></td>
                                    <td class="pnl-amount"><strong class="<?php echo $pnlNetOp >= 0 ? 'text-safe' : 'text-danger'; ?>"><?php echo number_format($pnlNetOp, 2); ?></strong></td>
                                    <td class="pnl-pct"><strong><?php echo number_format(($pnlNetOp / $pnlRevSafe) * 100, 1); ?>%</strong></td>
                                </tr>
                                <tr class="pnl-deduction">
                                    <td class="pnl-label pnl-indent">Less: Marketing / Misc</td>
                                    <td class="pnl-amount">(<?php echo number_format($pnlMarketing, 2); ?>)</td>
                                    <td class="pnl-pct"><?php echo number_format(($pnlMarketing / $pnlRevSafe) * 100, 1); ?>%</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="pnl-final <?php echo $pnlFinalNet >= 0 ? 'pnl-final-positive' : 'pnl-final-negative'; ?>">
                                    <td class="pnl-label"><strong>= Final Net Profit</strong></td>
                                    <td class="pnl-amount"><strong><?php echo ($pnlFinalNet >= 0 ? '' : '-') . 'RM ' . number_format(abs($pnlFinalNet), 2); ?></strong></td>
                                    <td class="pnl-pct"><strong><?php echo number_format(($pnlFinalNet / $pnlRevSafe) * 100, 1); ?>%</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- ====== 2. PERFORMANCE SCORECARD ====== -->
                    <?php
                    // Margin Erosion
                    $fMarginPct = ($fRevenue > 0) ? (($fRevenue - $fCogs - $fFixed) / $fRevenue) * 100 : 0;
                    $aMarginPct = ($aRevenue > 0) ? (($aRevenue - $aCogs - $aFixed) / $aRevenue) * 100 : 0;
                    $marginDelta = $aMarginPct - $fMarginPct;
                    $marginEroded = $marginDelta < 0;

                    // Labor ROI
                    $laborCost = floatval($actuals['actual_labor_cost']);
                    $laborROI = ($laborCost > 0) ? $aRevenue / $laborCost : 0;
                    $laborStatus = 'healthy';
                    $laborLabel = 'Strong';
                    if ($laborROI > 0 && $laborROI < 2) { $laborStatus = 'critical'; $laborLabel = 'Overstaffed'; }
                    else if ($laborROI >= 2 && $laborROI < 4) { $laborStatus = 'warning'; $laborLabel = 'Acceptable'; }
                    else if ($laborROI >= 4) { $laborStatus = 'healthy'; $laborLabel = 'Efficient'; }

                    // CAC Light
                    $marketingCost = floatval($actuals['actual_marketing_cost']);
                    $cacLight = ($aUnits > 0) ? $marketingCost / $aUnits : 0;
                    ?>
                    <div class="comparison-section">
                        <h3><i class="bx bx-tachometer"></i> Performance Scorecard</h3>
                        <div class="scorecard-grid">
                            <!-- Margin Erosion -->
                            <div class="scorecard-metric <?php echo $marginEroded ? 'metric-status-critical' : 'metric-status-healthy'; ?>">
                                <div class="metric-icon">
                                    <i class="bx <?php echo $marginEroded ? 'bx-trending-down' : 'bx-trending-up'; ?>"></i>
                                </div>
                                <div class="metric-body">
                                    <span class="metric-label">Margin Erosion</span>
                                    <span class="metric-value"><?php echo ($marginDelta >= 0 ? '+' : '') . number_format($marginDelta, 1); ?>pp</span>
                                    <div class="metric-detail">
                                        <span>Forecast: <?php echo number_format($fMarginPct, 1); ?>%</span>
                                        <span>Actual: <?php echo number_format($aMarginPct, 1); ?>%</span>
                                    </div>
                                    <span class="metric-verdict <?php echo $marginEroded ? 'verdict-bad' : 'verdict-good'; ?>">
                                        <?php echo $marginEroded ? 'Margin dropped — sloppy costs' : 'Margin held or improved'; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Labor ROI -->
                            <div class="scorecard-metric metric-status-<?php echo $laborStatus; ?>">
                                <div class="metric-icon">
                                    <i class="bx bx-group"></i>
                                </div>
                                <div class="metric-body">
                                    <span class="metric-label">Labor ROI</span>
                                    <span class="metric-value"><?php echo ($laborCost > 0) ? number_format($laborROI, 1) . 'x' : 'N/A'; ?></span>
                                    <div class="metric-detail">
                                        <span>Revenue: RM <?php echo number_format($aRevenue, 2); ?></span>
                                        <span>Labor: RM <?php echo number_format($laborCost, 2); ?></span>
                                    </div>
                                    <span class="metric-verdict <?php echo ($laborStatus === 'critical') ? 'verdict-bad' : (($laborStatus === 'warning') ? 'verdict-warn' : 'verdict-good'); ?>">
                                        <?php echo ($laborCost > 0) ? $laborLabel . ' — RM 1 labor = RM ' . number_format($laborROI, 2) . ' revenue' : 'No labor cost recorded'; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- CAC Light -->
                            <div class="scorecard-metric <?php echo ($cacLight > 5) ? 'metric-status-warning' : 'metric-status-healthy'; ?>">
                                <div class="metric-icon">
                                    <i class="bx bx-bullseye"></i>
                                </div>
                                <div class="metric-body">
                                    <span class="metric-label">CAC Light</span>
                                    <span class="metric-value"><?php echo ($marketingCost > 0 && $aUnits > 0) ? 'RM ' . number_format($cacLight, 2) : ($marketingCost == 0 ? 'RM 0.00' : 'N/A'); ?></span>
                                    <div class="metric-detail">
                                        <span>Marketing: RM <?php echo number_format($marketingCost, 2); ?></span>
                                        <span>Units Sold: <?php echo number_format($aUnits); ?></span>
                                    </div>
                                    <span class="metric-verdict <?php echo ($marketingCost == 0) ? 'verdict-good' : (($cacLight > 5) ? 'verdict-warn' : 'verdict-good'); ?>">
                                        <?php
                                        if ($marketingCost == 0) {
                                            echo 'No marketing spend — organic sales';
                                        } else if ($aUnits == 0) {
                                            echo 'No units sold — marketing wasted';
                                        } else {
                                            echo 'Cost per sale: RM ' . number_format($cacLight, 2) . '/unit';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ====== 3. WASTAGE & SHRINKAGE TRACKER ====== -->
                    <?php
                    // Check if any product has inventory data
                    $hasInventoryData = false;
                    $apLen3 = count($actualProducts);
                    for ($api3 = 0; $api3 < $apLen3; $api3++) {
                        if (isset($actualProducts[$api3]['starting_inventory']) && intval($actualProducts[$api3]['starting_inventory']) > 0) {
                            $hasInventoryData = true;
                            break;
                        }
                    }
                    ?>
                    <div class="comparison-section">
                        <h3><i class="bx bx-error-alt"></i> Wastage & Shrinkage Tracker</h3>
                        <?php if ($hasInventoryData): ?>
                        <table class="comparison-table wastage-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Brought</th>
                                    <th>Sold</th>
                                    <th>Remaining</th>
                                    <th>Unaccounted</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalBrought = 0;
                                $totalSold = 0;
                                $totalRemaining = 0;
                                $totalUnaccounted = 0;
                                for ($wi = 0; $wi < $apLen3; $wi++) {
                                    $wName = htmlspecialchars($actualProducts[$wi]['product_name']);
                                    $wBrought = intval(isset($actualProducts[$wi]['starting_inventory']) ? $actualProducts[$wi]['starting_inventory'] : 0);
                                    $wSold = intval($actualProducts[$wi]['actual_units_sold']);
                                    $wRemain = intval(isset($actualProducts[$wi]['remaining_stock']) ? $actualProducts[$wi]['remaining_stock'] : 0);
                                    $wLoss = $wBrought - $wSold - $wRemain;

                                    $totalBrought += $wBrought;
                                    $totalSold += $wSold;
                                    $totalRemaining += $wRemain;
                                    $totalUnaccounted += $wLoss;

                                    $wStatusClass = 'wastage-ok';
                                    $wStatusIcon = 'bx-check-circle';
                                    $wStatusText = 'Clean';
                                    if ($wLoss > 0) {
                                        $wStatusClass = 'wastage-flag';
                                        $wStatusIcon = 'bx-error';
                                        $wStatusText = $wLoss . ' lost';
                                    } else if ($wLoss < 0) {
                                        $wStatusClass = 'wastage-warn';
                                        $wStatusIcon = 'bx-help-circle';
                                        $wStatusText = 'Check count';
                                    }

                                    echo '<tr>';
                                    echo '<td><strong>' . $wName . '</strong></td>';
                                    echo '<td>' . number_format($wBrought) . '</td>';
                                    echo '<td>' . number_format($wSold) . '</td>';
                                    echo '<td>' . number_format($wRemain) . '</td>';
                                    echo '<td class="' . ($wLoss > 0 ? 'var-negative' : ($wLoss < 0 ? 'text-warning' : '')) . '"><strong>' . $wLoss . '</strong></td>';
                                    echo '<td><span class="' . $wStatusClass . '"><i class="bx ' . $wStatusIcon . '"></i> ' . $wStatusText . '</span></td>';
                                    echo '</tr>';
                                }
                                ?>
                                <tr class="row-highlight">
                                    <td><strong>Totals</strong></td>
                                    <td><strong><?php echo number_format($totalBrought); ?></strong></td>
                                    <td><strong><?php echo number_format($totalSold); ?></strong></td>
                                    <td><strong><?php echo number_format($totalRemaining); ?></strong></td>
                                    <td class="<?php echo $totalUnaccounted > 0 ? 'var-negative' : ''; ?>"><strong><?php echo $totalUnaccounted; ?></strong></td>
                                    <td>
                                        <?php if ($totalUnaccounted > 0): ?>
                                            <span class="wastage-flag"><i class="bx bx-error"></i> <?php echo $totalUnaccounted; ?> total unaccounted</span>
                                        <?php else: ?>
                                            <span class="wastage-ok"><i class="bx bx-check-circle"></i> All accounted</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <?php if ($totalUnaccounted > 0): ?>
                        <div class="wastage-alert">
                            <i class="bx bx-shield-x"></i>
                            <div>
                                <strong><?php echo $totalUnaccounted; ?> unit<?php echo $totalUnaccounted > 1 ? 's' : ''; ?> unaccounted for.</strong>
                                <p>Possible causes: free samples given out, measurement errors, damaged goods, or shrinkage. Review your process for next event.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="wastage-empty">
                            <i class="bx bx-info-circle"></i>
                            <span>No inventory data recorded for this event. Future events will track Starting Inventory and Remaining Stock per product.</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ====== 4. VS. LAST EVENT BENCHMARK ====== -->
                    <div class="comparison-section">
                        <h3><i class="bx bx-git-compare"></i> vs. Last Event Benchmark</h3>

                        <?php if ($prevEvent && $prevActuals): ?>
                        <?php
                        $prevName = htmlspecialchars($prevEvent['event_name']);
                        $prevRevenue = floatval($prevActuals['actual_total_revenue']);
                        $prevCogsTot = floatval($prevActuals['actual_total_cogs']);
                        $prevProfit  = floatval($prevActuals['actual_profit']);
                        $prevFixed   = floatval($prevActuals['actual_total_fixed']);
                        $prevMargin  = ($prevRevenue > 0) ? (($prevRevenue - $prevCogsTot - $prevFixed) / $prevRevenue) * 100 : 0;
                        $prevUnits   = 0;
                        $ppLen = count($prevActualProducts);
                        for ($ppi = 0; $ppi < $ppLen; $ppi++) {
                            $prevUnits += intval($prevActualProducts[$ppi]['actual_units_sold']);
                        }

                        // Deltas
                        $dRevenue  = ($prevRevenue > 0) ? (($aRevenue - $prevRevenue) / $prevRevenue) * 100 : 0;
                        $dCogs     = ($prevCogsTot > 0) ? (($aCogs - $prevCogsTot) / $prevCogsTot) * 100 : 0;
                        $dProfit   = ($prevProfit != 0) ? (($aProfit - $prevProfit) / abs($prevProfit)) * 100 : 0;
                        $dMargin   = $aMarginPct - $prevMargin;
                        $dUnits    = ($prevUnits > 0) ? (($aUnits - $prevUnits) / $prevUnits) * 100 : 0;
                        ?>

                        <!-- Tier 1: Event-Level Aggregates -->
                        <div class="benchmark-tier-label">Event-Level — <?php echo htmlspecialchars($event['event_name']); ?> vs <?php echo $prevName; ?></div>
                        <div class="benchmark-grid">
                            <?php
                            $benchItems = [
                                ['Revenue', $prevRevenue, $aRevenue, $dRevenue, true],
                                ['COGS', $prevCogsTot, $aCogs, $dCogs, false],
                                ['Profit', $prevProfit, $aProfit, $dProfit, true],
                                ['Margin', $prevMargin, $aMarginPct, $dMargin, true],
                                ['Units Sold', $prevUnits, $aUnits, $dUnits, true],
                            ];
                            $biLen = count($benchItems);
                            for ($bi = 0; $bi < $biLen; $bi++) {
                                $bLabel = $benchItems[$bi][0];
                                $bPrev  = $benchItems[$bi][1];
                                $bCurr  = $benchItems[$bi][2];
                                $bDelta = $benchItems[$bi][3];
                                $bHigherIsGood = $benchItems[$bi][4];

                                // For COGS, lower is better
                                $bIsGood = $bHigherIsGood ? ($bDelta >= 0) : ($bDelta <= 0);
                                $bArrow  = ($bDelta >= 0) ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt';
                                $bClass  = $bIsGood ? 'bench-good' : 'bench-bad';

                                $bIsRM = ($bLabel !== 'Margin' && $bLabel !== 'Units Sold');
                                $bIsPct = ($bLabel === 'Margin');

                                echo '<div class="benchmark-row ' . $bClass . '">';
                                echo '<span class="bench-label">' . $bLabel . '</span>';
                                echo '<div class="bench-values">';
                                if ($bIsRM) {
                                    echo '<span class="bench-prev">RM ' . number_format($bPrev, 2) . '</span>';
                                    echo '<span class="bench-arrow"><i class="bx bx-right-arrow-alt"></i></span>';
                                    echo '<span class="bench-curr">RM ' . number_format($bCurr, 2) . '</span>';
                                } else if ($bIsPct) {
                                    echo '<span class="bench-prev">' . number_format($bPrev, 1) . '%</span>';
                                    echo '<span class="bench-arrow"><i class="bx bx-right-arrow-alt"></i></span>';
                                    echo '<span class="bench-curr">' . number_format($bCurr, 1) . '%</span>';
                                } else {
                                    echo '<span class="bench-prev">' . number_format($bPrev) . '</span>';
                                    echo '<span class="bench-arrow"><i class="bx bx-right-arrow-alt"></i></span>';
                                    echo '<span class="bench-curr">' . number_format($bCurr) . '</span>';
                                }
                                echo '</div>';
                                echo '<span class="bench-delta ' . $bClass . '"><i class="bx ' . $bArrow . '"></i> ';
                                if ($bIsPct) {
                                    echo ($bDelta >= 0 ? '+' : '') . number_format($bDelta, 1) . 'pp';
                                } else {
                                    echo ($bDelta >= 0 ? '+' : '') . number_format($bDelta, 1) . '%';
                                }
                                echo '</span>';
                                echo '</div>';
                            }
                            ?>
                        </div>

                        <!-- Natural Language Summary -->
                        <div class="benchmark-summary">
                            <i class="bx bx-bot"></i>
                            <p>
                                <?php
                                // Build natural language summary
                                $summaryParts = [];

                                if ($dProfit >= 0) {
                                    $summaryParts[] = 'You made <strong>' . number_format(abs($dProfit), 1) . '% more profit</strong> than <strong>' . $prevName . '</strong>';
                                } else {
                                    $summaryParts[] = 'Your profit <strong>dropped ' . number_format(abs($dProfit), 1) . '%</strong> compared to <strong>' . $prevName . '</strong>';
                                }

                                if ($dCogs > 0) {
                                    $summaryParts[] = 'but your COGS increased by ' . number_format(abs($dCogs), 1) . '%';
                                } else if ($dCogs < 0) {
                                    $summaryParts[] = 'and your COGS decreased by ' . number_format(abs($dCogs), 1) . '%';
                                }

                                if ($dMargin >= 0.5) {
                                    $summaryParts[] = 'Your margin improved by ' . number_format(abs($dMargin), 1) . ' percentage points — you\'re getting more efficient.';
                                } else if ($dMargin <= -0.5) {
                                    $summaryParts[] = 'Your margin dropped by ' . number_format(abs($dMargin), 1) . ' percentage points — review your cost control.';
                                } else {
                                    $summaryParts[] = 'Your margin held steady.';
                                }

                                $spLen = count($summaryParts);
                                for ($spi = 0; $spi < $spLen; $spi++) {
                                    echo $summaryParts[$spi];
                                    if ($spi < $spLen - 1) echo ', ';
                                }
                                ?>
                            </p>
                        </div>

                        <!-- Tier 2: Smart Product Matching -->
                        <div class="benchmark-tier-label" style="margin-top: 24px;">Product-Level Comparison</div>
                        <?php
                        // Build product name sets
                        $currProdNames = [];
                        $apLen4 = count($actualProducts);
                        for ($api4 = 0; $api4 < $apLen4; $api4++) {
                            $currProdNames[] = $actualProducts[$api4]['product_name'];
                        }
                        $prevProdNames = [];
                        $ppLen2 = count($prevActualProducts);
                        for ($ppi2 = 0; $ppi2 < $ppLen2; $ppi2++) {
                            $prevProdNames[] = $prevActualProducts[$ppi2]['product_name'];
                        }

                        // Categorize: returning, new, dropped
                        $returning = [];
                        $newProds = [];
                        $dropped = [];

                        for ($api4 = 0; $api4 < $apLen4; $api4++) {
                            $cName = $actualProducts[$api4]['product_name'];
                            $found = false;
                            for ($ppi2 = 0; $ppi2 < $ppLen2; $ppi2++) {
                                if ($prevActualProducts[$ppi2]['product_name'] === $cName) {
                                    $returning[] = ['curr' => $actualProducts[$api4], 'prev' => $prevActualProducts[$ppi2]];
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $newProds[] = $actualProducts[$api4];
                            }
                        }
                        for ($ppi2 = 0; $ppi2 < $ppLen2; $ppi2++) {
                            $pName = $prevActualProducts[$ppi2]['product_name'];
                            $foundInCurr = false;
                            for ($api4 = 0; $api4 < $apLen4; $api4++) {
                                if ($actualProducts[$api4]['product_name'] === $pName) {
                                    $foundInCurr = true;
                                    break;
                                }
                            }
                            if (!$foundInCurr) {
                                $dropped[] = $prevActualProducts[$ppi2];
                            }
                        }
                        ?>

                        <?php if (count($returning) > 0): ?>
                        <table class="comparison-table" style="margin-bottom: 16px;">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Prev Units</th>
                                    <th>Now Units</th>
                                    <th>Prev Revenue</th>
                                    <th>Now Revenue</th>
                                    <th>COGS/Unit Change</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rLen = count($returning);
                                for ($ri = 0; $ri < $rLen; $ri++) {
                                    $rCurr = $returning[$ri]['curr'];
                                    $rPrev = $returning[$ri]['prev'];
                                    $rName = htmlspecialchars($rCurr['product_name']);
                                    $rPrevU = intval($rPrev['actual_units_sold']);
                                    $rCurrU = intval($rCurr['actual_units_sold']);
                                    $rPrevR = floatval($rPrev['actual_revenue']);
                                    $rCurrR = floatval($rCurr['actual_revenue']);
                                    $rPrevC = floatval($rPrev['actual_cogs_per_unit']);
                                    $rCurrC = floatval($rCurr['actual_cogs_per_unit']);
                                    $rCogsDelta = $rCurrC - $rPrevC;

                                    echo '<tr>';
                                    echo '<td><strong>' . $rName . '</strong></td>';
                                    echo '<td><span class="product-badge badge-returning">RETURNING</span></td>';
                                    echo '<td>' . number_format($rPrevU) . '</td>';
                                    echo '<td>' . number_format($rCurrU) . '</td>';
                                    echo '<td>RM ' . number_format($rPrevR, 2) . '</td>';
                                    echo '<td>RM ' . number_format($rCurrR, 2) . '</td>';
                                    echo '<td class="' . ($rCogsDelta <= 0 ? 'var-positive' : 'var-negative') . '">' . ($rCogsDelta >= 0 ? '+' : '') . 'RM ' . number_format($rCogsDelta, 2) . '</td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <?php if (count($newProds) > 0): ?>
                        <table class="comparison-table" style="margin-bottom: 16px;">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Units Sold</th>
                                    <th>Revenue</th>
                                    <th>COGS/Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $nLen = count($newProds);
                                for ($ni = 0; $ni < $nLen; $ni++) {
                                    echo '<tr>';
                                    echo '<td><strong>' . htmlspecialchars($newProds[$ni]['product_name']) . '</strong></td>';
                                    echo '<td><span class="product-badge badge-new">NEW</span></td>';
                                    echo '<td>' . number_format(intval($newProds[$ni]['actual_units_sold'])) . '</td>';
                                    echo '<td>RM ' . number_format(floatval($newProds[$ni]['actual_revenue']), 2) . '</td>';
                                    echo '<td>RM ' . number_format(floatval($newProds[$ni]['actual_cogs_per_unit']), 2) . '</td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <?php if (count($dropped) > 0): ?>
                        <table class="comparison-table" style="margin-bottom: 16px;">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Status</th>
                                    <th>Last Units</th>
                                    <th>Last Revenue</th>
                                    <th>Last COGS/Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $drLen = count($dropped);
                                for ($dri = 0; $dri < $drLen; $dri++) {
                                    echo '<tr class="dropped-row">';
                                    echo '<td><strong>' . htmlspecialchars($dropped[$dri]['product_name']) . '</strong></td>';
                                    echo '<td><span class="product-badge badge-dropped">DROPPED</span></td>';
                                    echo '<td>' . number_format(intval($dropped[$dri]['actual_units_sold'])) . '</td>';
                                    echo '<td>RM ' . number_format(floatval($dropped[$dri]['actual_revenue']), 2) . '</td>';
                                    echo '<td>RM ' . number_format(floatval($dropped[$dri]['actual_cogs_per_unit']), 2) . '</td>';
                                    echo '</tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                        <?php if (count($returning) === 0 && count($newProds) === 0 && count($dropped) === 0): ?>
                            <p style="font-size: 13px; color: var(--text-secondary);">No product data available for comparison.</p>
                        <?php endif; ?>

                        <?php else: ?>
                        <!-- No previous event -->
                        <div class="benchmark-first-event">
                            <i class="bx bx-trophy"></i>
                            <div>
                                <strong>This is your first completed event!</strong>
                                <p>Future events will benchmark against this one. Keep recording actuals to build your performance history.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($actuals['notes']) && trim($actuals['notes']) !== ''): ?>
                    <div class="reality-check">
                        <h3><i class="bx bx-note"></i> Notes</h3>
                        <p style="font-size: 14px; color: var(--text-secondary); line-height: 1.7;">
                            <?php echo nl2br(htmlspecialchars($actuals['notes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- ====== ACTUALS INPUT FORM ====== -->
                <div class="card">
                    <div class="card-title">
                        <i class="bx bx-edit-alt"></i> Record Actual Results
                    </div>

                    <!-- Forecast Summary (read-only) -->
                    <div class="forecast-reference">
                        <h4><i class="bx bx-bar-chart-alt-2"></i> Forecast Reference</h4>
                        <div class="forecast-ref-row">
                            <span>Projected Profit/Loss:</span>
                            <strong class="<?php echo floatval($event['projected_profit']) >= 0 ? 'text-safe' : 'text-danger'; ?>">
                                <?php echo (floatval($event['projected_profit']) >= 0 ? '+' : '') . 'RM ' . number_format(floatval($event['projected_profit']), 2); ?>
                            </strong>
                        </div>
                        <div class="forecast-ref-row">
                            <span>Total Fixed Costs:</span>
                            <strong>RM <?php echo number_format(floatval($event['total_fixed_costs']), 2); ?></strong>
                        </div>
                        <div class="forecast-ref-row">
                            <span>Est. Revenue:</span>
                            <strong>RM <?php echo number_format(floatval($event['estimated_revenue']), 2); ?></strong>
                        </div>
                        <div class="forecast-ref-row">
                            <span>Risk Level:</span>
                            <?php
                            $refRisk = htmlspecialchars($event['risk_level']);
                            $refRiskCls = 'risk-low';
                            if ($refRisk === 'HIGH') $refRiskCls = 'risk-high';
                            if ($refRisk === 'MEDIUM') $refRiskCls = 'risk-medium';
                            ?>
                            <span class="risk-badge <?php echo $refRiskCls; ?>"><?php echo $refRisk; ?></span>
                        </div>
                    </div>

                    <form method="POST" action="post_event.php?id=<?php echo $eventId; ?>" id="actuals-form">

                        <!-- Actual Fixed Costs -->
                        <div class="form-section-title"><i class="bx bx-money"></i> Actual Fixed Costs (RM)</div>
                        <div class="form-row four">
                            <div class="form-group">
                                <label>Booth Rental</label>
                                <input type="number" name="actual_booth_rental" step="0.01" min="0"
                                    placeholder="<?php echo number_format(floatval($event['booth_rental']), 2); ?>"
                                    value="<?php echo isset($_POST['actual_booth_rental']) ? htmlspecialchars($_POST['actual_booth_rental']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Transport / Gas</label>
                                <input type="number" name="actual_transport_cost" step="0.01" min="0"
                                    placeholder="<?php echo number_format(floatval($event['transport_cost']), 2); ?>"
                                    value="<?php echo isset($_POST['actual_transport_cost']) ? htmlspecialchars($_POST['actual_transport_cost']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Marketing</label>
                                <input type="number" name="actual_marketing_cost" step="0.01" min="0"
                                    placeholder="<?php echo number_format(floatval($event['marketing_cost']), 2); ?>"
                                    value="<?php echo isset($_POST['actual_marketing_cost']) ? htmlspecialchars($_POST['actual_marketing_cost']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Total Labor</label>
                                <input type="number" name="actual_labor_cost" step="0.01" min="0"
                                    placeholder="<?php echo number_format(floatval($event['total_fixed_costs']) - floatval($event['booth_rental']) - floatval($event['transport_cost']) - floatval($event['marketing_cost']), 2); ?>"
                                    value="<?php echo isset($_POST['actual_labor_cost']) ? htmlspecialchars($_POST['actual_labor_cost']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Actual Product Sales -->
                        <div class="form-section-title"><i class="bx bx-package"></i> Actual Product Sales</div>
                        <?php
                        $pLen = count($products);
                        for ($pi = 0; $pi < $pLen; $pi++) {
                            $fpName  = htmlspecialchars($products[$pi]['product_name']);
                            $fpPrice = number_format(floatval($products[$pi]['selling_price']), 2);
                            $fpCogs  = number_format(floatval($products[$pi]['cogs_per_unit']), 2);
                            $fpUnits = intval($products[$pi]['estimated_units']);
                        ?>
                        <div class="product-row">
                            <div class="product-row-header">
                                <span class="product-row-num"><?php echo $fpName; ?> <small class="text-muted">(Forecast: <?php echo $fpUnits; ?> units @ RM <?php echo $fpPrice; ?>)</small></span>
                            </div>
                            <input type="hidden" name="ap_name[]" value="<?php echo $fpName; ?>">
                            <div class="form-row five">
                                <div class="form-group">
                                    <label>Actual Price (RM)</label>
                                    <input type="number" name="ap_price[]" step="0.01" min="0" placeholder="<?php echo $fpPrice; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Actual COGS (RM)</label>
                                    <input type="number" name="ap_cogs[]" step="0.01" min="0" placeholder="<?php echo $fpCogs; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Starting Inventory</label>
                                    <input type="number" name="ap_start_inv[]" min="0" placeholder="e.g. <?php echo $fpUnits + 10; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Units Sold</label>
                                    <input type="number" name="ap_units[]" min="0" placeholder="<?php echo $fpUnits; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Remaining Stock</label>
                                    <input type="number" name="ap_remaining[]" min="0" placeholder="0" required>
                                </div>
                            </div>
                        </div>
                        <?php } ?>

                        <!-- Notes -->
                        <div class="form-section-title"><i class="bx bx-note"></i> Notes (optional)</div>
                        <div class="form-group">
                            <textarea name="actual_notes" rows="3" class="form-textarea" placeholder="Any observations, lessons learned, weather conditions, etc."><?php echo isset($_POST['actual_notes']) ? htmlspecialchars($_POST['actual_notes']) : ''; ?></textarea>
                        </div>

                        <button type="submit" class="submit-btn" id="save-actuals-btn">
                            <i class="bx bx-check-circle"></i> Save Actual Results & Compare
                        </button>

                    </form>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($mode === 'comparison' && $actuals): ?>
    var compCanvas = document.getElementById('comparisonChart');
    if (compCanvas) {
        var ctx = compCanvas.getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Revenue', 'Fixed Costs', 'COGS', 'Profit/Loss'],
                datasets: [
                    {
                        label: 'Forecast',
                        data: [
                            <?php echo number_format($fRevenue, 2, '.', ''); ?>,
                            <?php echo number_format($fFixed, 2, '.', ''); ?>,
                            <?php echo number_format($fCogs, 2, '.', ''); ?>,
                            <?php echo number_format($fProfit, 2, '.', ''); ?>
                        ],
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        borderRadius: 6
                    },
                    {
                        label: 'Actual',
                        data: [
                            <?php echo number_format($aRevenue, 2, '.', ''); ?>,
                            <?php echo number_format($aFixed, 2, '.', ''); ?>,
                            <?php echo number_format($aCogs, 2, '.', ''); ?>,
                            <?php echo number_format($aProfit, 2, '.', ''); ?>
                        ],
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                animation: { duration: 2000, easing: 'easeOutQuart' },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(22, 25, 37, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#94a3b8',
                        padding: 14,
                        callbacks: {
                            label: function(item) { return ' ' + item.dataset.label + ': RM ' + item.parsed.y.toFixed(2); }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.03)' },
                        ticks: { color: '#7c879a', font: { size: 12, weight: '600' } }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.04)', borderDash: [4, 4] },
                        ticks: {
                            color: '#7c879a',
                            font: { size: 11 },
                            callback: function(val) { return 'RM ' + val.toLocaleString(); }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // Start animated number roll-up
    var counters = document.querySelectorAll('.counter');
    if (counters.length > 0) {
        setTimeout(function () {
            for (var ci = 0; ci < counters.length; ci++) {
                animateCounter(counters[ci]);
            }
        }, 100);
    }
});

// Easing: easeOutExpo
function easeOutExpo(t) {
    return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
}

function animateCounter(el) {
    var target   = parseFloat(el.getAttribute('data-target')) || 0;
    var prefix   = el.getAttribute('data-prefix') || '';
    var suffix   = el.getAttribute('data-suffix') || '';
    var decimals = parseInt(el.getAttribute('data-decimals')) || 0;
    var duration = 1800; // ms
    var startTime = null;

    function formatNum(n) {
        return n.toLocaleString('en-MY', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var elapsed  = timestamp - startTime;
        var progress = Math.min(elapsed / duration, 1);
        var eased    = easeOutExpo(progress);
        var current  = eased * target;

        el.textContent = prefix + formatNum(current) + suffix;

        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            el.textContent = prefix + formatNum(target) + suffix;
        }
    }

    el.textContent = prefix + formatNum(0) + suffix;
    requestAnimationFrame(step);
}
</script>

<?php include 'helper.php'; ?>
</body>
</html>
