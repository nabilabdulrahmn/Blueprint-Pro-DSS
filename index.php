<?php
require_once 'db.php';
require_once 'auth.php';

// -------------------------------------------------------
// CALCULATION ENGINE — runs when form is submitted
// -------------------------------------------------------
$results = null;
$formError = '';
$saved = false;
$suggestions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Collect event-level inputs ---
    $eventName = isset($_POST['event_name']) ? trim($_POST['event_name']) : '';
    $eventDays = isset($_POST['event_days']) ? intval($_POST['event_days']) : 1;
    $boothRental = isset($_POST['booth_rental']) ? floatval($_POST['booth_rental']) : 0;
    $transportCost = isset($_POST['transport_cost']) ? floatval($_POST['transport_cost']) : 0;
    $marketingCost = isset($_POST['marketing_cost']) ? floatval($_POST['marketing_cost']) : 0;
    $numStaff = isset($_POST['num_staff']) ? intval($_POST['num_staff']) : 1;
    $hourlyWage = isset($_POST['hourly_wage']) ? floatval($_POST['hourly_wage']) : 0;
    $hoursPerDay = isset($_POST['hours_per_day']) ? floatval($_POST['hours_per_day']) : 8;
    $totalTraffic = isset($_POST['total_traffic']) ? intval($_POST['total_traffic']) : 0;
    $captureRate = isset($_POST['capture_rate']) ? floatval($_POST['capture_rate']) : 3.0;
    $conversionRate = isset($_POST['conversion_rate']) ? floatval($_POST['conversion_rate']) : 5.0;

    // --- New feature variables ---
    $expectedWastagePct = isset($_POST['expected_wastage_pct']) ? floatval($_POST['expected_wastage_pct']) : 0.0;
    $taxRate = isset($_POST['tax_rate']) ? floatval($_POST['tax_rate']) : 0.0;
    $isTaxInclusive = isset($_POST['is_tax_inclusive']) ? intval($_POST['is_tax_inclusive']) : 1;

    // --- Collect product arrays ---
    $pNames = isset($_POST['product_name']) ? $_POST['product_name'] : [];
    $pPrices = isset($_POST['product_price']) ? $_POST['product_price'] : [];
    $pCogs = isset($_POST['product_cogs']) ? $_POST['product_cogs'] : [];
    $pUnits = isset($_POST['product_units']) ? $_POST['product_units'] : [];

    // --- Validation ---
    if ($eventName === '') {
        $formError = 'Please enter an event name.';
    } else if ($eventDays < 1) {
        $formError = 'Event must be at least 1 day.';
    } else if (!is_array($pNames) || count($pNames) < 1) {
        $formError = 'Please add at least one product.';
    } else if ($totalTraffic < 1) {
        $formError = 'Total expected traffic must be at least 1.';
    } else {

        // --- Build product list and validate ---
        $products = [];
        $productCount = count($pNames);
        $hasValidProduct = false;

        for ($pi = 0; $pi < $productCount; $pi++) {
            $pn = isset($pNames[$pi]) ? trim($pNames[$pi]) : '';
            $pp = isset($pPrices[$pi]) ? floatval($pPrices[$pi]) : 0;
            $pc = isset($pCogs[$pi]) ? floatval($pCogs[$pi]) : 0;
            $pu = isset($pUnits[$pi]) ? intval($pUnits[$pi]) : 0;

            if ($pn === '')
                continue;
            if ($pp <= 0) {
                $formError = 'Product "' . htmlspecialchars($pn) . '" needs a selling price > 0.';
                break;
            }
            if ($pc < 0) {
                $formError = 'Product "' . htmlspecialchars($pn) . '" COGS cannot be negative.';
                break;
            }

            // Apply Tax and Wastage mathematically
            $effectiveSellingPrice = $pp;
            if ($taxRate > 0 && $isTaxInclusive == 1) {
                // E.g. RM 10.00 inclusive of 6% SST = 10 / 1.06 = RM 9.43 effective revenue per unit
                $effectiveSellingPrice = $pp / (1 + ($taxRate / 100));
            }

            $effectiveCOGS = $pc;
            if ($expectedWastagePct > 0) {
                $effectiveCOGS = $pc * (1 + ($expectedWastagePct / 100));
            }

            if ($effectiveSellingPrice <= $effectiveCOGS) {
                $formError = 'Product "' . htmlspecialchars($pn) . '": Effective selling price (RM ' . number_format($effectiveSellingPrice, 2) . ') must exceed Effective COGS (RM ' . number_format($effectiveCOGS, 2) . '). Check wastage and tax settings.';
                break;
            }
            if ($pu < 1) {
                $formError = 'Product "' . htmlspecialchars($pn) . '" needs at least 1 estimated unit.';
                break;
            }

            $margin = $effectiveSellingPrice - $effectiveCOGS;
            $rev = $effectiveSellingPrice * $pu;

            $products[] = [
                'name' => $pn,
                'selling_price' => $pp,
                'cogs_per_unit' => $pc,
                'effective_price' => $effectiveSellingPrice,
                'effective_cogs' => $effectiveCOGS,
                'gross_margin' => $margin,
                'estimated_units' => $pu,
                'estimated_revenue' => $rev,
            ];
            $hasValidProduct = true;
        }

        if ($formError === '' && !$hasValidProduct) {
            $formError = 'Please add at least one valid product.';
        }
    }

    if ($formError === '' && count($products) > 0) {

        // --- CALCULATION LOGIC ---

        // 1. Total Fixed Costs
        $laborCost = $numStaff * $hourlyWage * $hoursPerDay * $eventDays;
        $totalFixedCosts = $boothRental + $transportCost + $marketingCost + $laborCost;

        // 2. Aggregate product metrics
        $totalEstUnits = 0;
        $totalEstRevenue = 0;
        $totalCOGS = 0;
        $weightedMarginSum = 0;

        $pLen = count($products);
        for ($pi = 0; $pi < $pLen; $pi++) {
            $totalEstUnits += $products[$pi]['estimated_units'];
            $totalEstRevenue += $products[$pi]['estimated_revenue'];
            $totalCOGS += $products[$pi]['effective_cogs'] * $products[$pi]['estimated_units'];
            $weightedMarginSum += $products[$pi]['gross_margin'] * $products[$pi]['estimated_units'];
        }

        // 3. Weighted-average gross margin
        $weightedMargin = 0;
        if ($totalEstUnits > 0) {
            $weightedMargin = $weightedMarginSum / $totalEstUnits;
        }

        // 4. Break-Even Point (Units)
        $breakEvenUnits = 0;
        if ($weightedMargin > 0) {
            $breakEvenUnits = ceil($totalFixedCosts / $weightedMargin);
        }

        // 5. Projected Profit/Loss
        $projectedProfit = $totalEstRevenue - $totalFixedCosts - $totalCOGS;

        // 6. Required Conversion Rate to Break Even
        $captureDecimal = $captureRate / 100;
        $requiredConvRate = 0;
        if ($totalTraffic > 0 && $captureDecimal > 0 && $weightedMargin > 0) {
            $unitsNeeded = ceil($totalFixedCosts / $weightedMargin);
            $requiredConvRate = ($unitsNeeded / ($totalTraffic * $captureDecimal)) * 100;
        }

        // 7. Risk Assessment
        $riskLevel = 'LOW';
        $verdict = 'VIABLE — Go for it!';

        if ($requiredConvRate > 10) {
            $riskLevel = 'HIGH';
            $verdict = 'HIGH RISK — DO NOT JOIN';
        } else if ($requiredConvRate > 6) {
            $riskLevel = 'MEDIUM';
            $verdict = 'MODERATE RISK — Proceed with caution';
        }

        if ($projectedProfit < 0) {
            if ($riskLevel === 'LOW') {
                $riskLevel = 'MEDIUM';
                $verdict = 'PROJECTED LOSS — Review costs';
            }
        }

        // =============================================
        // 8. SMART SUGGESTIONS ENGINE
        // =============================================
        if ($riskLevel !== 'LOW') {

            // HOW MUCH PROFIT IS NEEDED to reach break-even (from current loss)
            $profitGap = abs($projectedProfit);

            // --- Suggestion A: Reduce Booth Rental ---
            if ($boothRental > 0) {
                $maxBoothCut = $boothRental;
                $neededCut = min($profitGap, $maxBoothCut);
                $newBooth = $boothRental - $neededCut;
                $newProfit = $projectedProfit + $neededCut;
                $suggestions[] = [
                    'icon' => 'bx-store',
                    'color' => 'blue',
                    'title' => 'Negotiate Booth Rental',
                    'detail' => 'Reduce booth rental from RM ' . number_format($boothRental, 2) . ' → RM ' . number_format($newBooth, 2),
                    'impact' => 'Saves RM ' . number_format($neededCut, 2) . ' → New profit: RM ' . number_format($newProfit, 2),
                ];
            }

            // --- Suggestion B: Reduce Staff ---
            if ($numStaff > 1) {
                $savingsPerStaff = $hourlyWage * $hoursPerDay * $eventDays;
                $newStaffProfit = $projectedProfit + $savingsPerStaff;
                $suggestions[] = [
                    'icon' => 'bx-group',
                    'color' => 'purple',
                    'title' => 'Reduce Staff by 1',
                    'detail' => 'Cut from ' . $numStaff . ' → ' . ($numStaff - 1) . ' staff members',
                    'impact' => 'Saves RM ' . number_format($savingsPerStaff, 2) . ' → New profit: RM ' . number_format($newStaffProfit, 2),
                ];
            }

            // --- Suggestion C: Increase prices by X% ---
            if ($weightedMargin > 0) {
                // How much extra margin per unit is needed?
                $extraNeeded = 0;
                if ($totalEstUnits > 0) {
                    $extraNeeded = $profitGap / $totalEstUnits;
                }
                // Calculate the average price increase across products
                $avgPrice = $totalEstRevenue / max($totalEstUnits, 1);
                $pctIncrease = 0;
                if ($avgPrice > 0) {
                    $pctIncrease = ($extraNeeded / $avgPrice) * 100;
                }
                if ($pctIncrease > 0 && $pctIncrease < 50) {
                    $suggestions[] = [
                        'icon' => 'bx-trending-up',
                        'color' => 'green',
                        'title' => 'Increase Prices by ~' . number_format($pctIncrease, 1) . '%',
                        'detail' => 'Raise each product\'s price by approximately RM ' . number_format($extraNeeded, 2) . ' per unit',
                        'impact' => 'Covers the RM ' . number_format($profitGap, 2) . ' gap to reach break-even',
                    ];
                }
            }

            // --- Suggestion D: Sell more units ---
            if ($weightedMargin > 0) {
                $extraUnitsNeeded = ceil($profitGap / $weightedMargin);
                $newTotalUnits = $totalEstUnits + $extraUnitsNeeded;
                $suggestions[] = [
                    'icon' => 'bx-package',
                    'color' => 'yellow',
                    'title' => 'Sell ' . number_format($extraUnitsNeeded) . ' More Units',
                    'detail' => 'Increase total estimated sales from ' . number_format($totalEstUnits) . ' → ' . number_format($newTotalUnits) . ' units',
                    'impact' => 'At weighted margin RM ' . number_format($weightedMargin, 2) . '/unit, this covers the gap',
                ];
            }

            // --- Suggestion E: Improve Conversion Rate ---
            if ($requiredConvRate > $conversionRate) {
                $suggestions[] = [
                    'icon' => 'bx-target-lock',
                    'color' => 'red',
                    'title' => 'Target ' . number_format($requiredConvRate, 2) . '% Conversion Rate',
                    'detail' => 'Current estimate: ' . number_format($conversionRate, 1) . '% → Needed: ' . number_format($requiredConvRate, 2) . '%',
                    'impact' => 'Use sampling, demos, or promos to boost conversion at the booth',
                ];
            }

            // --- Suggestion F: Cut total costs ---
            $totalCostsAll = $totalFixedCosts + $totalCOGS;
            if ($totalCostsAll > 0 && $projectedProfit < 0) {
                $pctCostCut = ($profitGap / $totalCostsAll) * 100;
                $suggestions[] = [
                    'icon' => 'bx-cut',
                    'color' => 'orange',
                    'title' => 'Reduce Overall Costs by ' . number_format($pctCostCut, 1) . '%',
                    'detail' => 'Total costs: RM ' . number_format($totalCostsAll, 2) . ' → Target: RM ' . number_format($totalCostsAll - $profitGap, 2),
                    'impact' => 'Combine booth negotiation, staff reduction, and cheaper materials',
                ];
            }
        }

        // --- Pack results ---
        $results = [
            'event_name'           => $eventName,
            'event_days'           => $eventDays,
            'booth_rental'         => $boothRental,
            'transport_cost'       => $transportCost,
            'marketing_cost'       => $marketingCost,
            'labor_cost'           => $laborCost,
            'total_fixed_costs'    => $totalFixedCosts,
            'weighted_margin'      => $weightedMargin,
            'total_estimated_units'=> $totalEstUnits,
            'estimated_revenue'    => $totalEstRevenue,
            'total_cogs'           => $totalCOGS,
            'break_even_units'     => $breakEvenUnits,
            'projected_profit'     => $projectedProfit,
            'required_conv_rate'   => $requiredConvRate,
            'risk_level'           => $riskLevel,
            'verdict'              => $verdict,
            'products'             => $products,
            // Store raw inputs for Recalculate pre-fill
            'raw' => [
                'event_name'      => $eventName,
                'event_days'      => $eventDays,
                'booth_rental'    => $boothRental,
                'transport_cost'  => $transportCost,
                'marketing_cost'  => $marketingCost,
                'num_staff'       => $numStaff,
                'hourly_wage'     => $hourlyWage,
                'hours_per_day'   => $hoursPerDay,
                'total_traffic'   => $totalTraffic,
                'capture_rate'    => $captureRate,
                'conversion_rate' => $conversionRate,
                'expected_wastage_pct' => $expectedWastagePct,
                'tax_rate'        => $taxRate,
                'is_tax_inclusive'=> $isTaxInclusive,
            ],
        ];

        // Store in session so the Save action can access it
        $_SESSION['dekukis_pending_result']   = $results;
        $_SESSION['dekukis_pending_products'] = $products;
        $_SESSION['dekukis_saved_id']         = null; // reset
    }
}

// -------------------------------------------------------
// SAVE ACTION — only when user explicitly clicks Save
// -------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'save_analysis') {
    $saved = false;
    if (!$db_error && isset($_SESSION['dekukis_pending_result'])) {
        $r     = $_SESSION['dekukis_pending_result'];
        $prods = $_SESSION['dekukis_pending_products'];

        $sName    = mysqli_real_escape_string($conn, $r['event_name']);
        $sVerdict = mysqli_real_escape_string($conn, $r['verdict']);
        $sRisk    = mysqli_real_escape_string($conn, $r['risk_level']);

        $sql = "INSERT INTO event_analyses (
                    event_name, event_days, booth_rental, transport_cost, marketing_cost,
                    num_staff, hourly_wage, hours_per_day, expected_wastage_pct, tax_rate, is_tax_inclusive,
                    total_traffic, capture_rate, conversion_rate, total_fixed_costs,
                    weighted_margin, total_estimated_units, estimated_revenue, total_cogs,
                    break_even_units, projected_profit, required_conv_rate,
                    risk_level, verdict, status
                ) VALUES (
                    '$sName', {$r['event_days']}, {$r['booth_rental']}, {$r['transport_cost']}, {$r['marketing_cost']},
                    {$r['raw']['num_staff']}, {$r['raw']['hourly_wage']}, {$r['raw']['hours_per_day']}, {$r['raw']['expected_wastage_pct']}, {$r['raw']['tax_rate']}, {$r['raw']['is_tax_inclusive']},
                    {$r['raw']['total_traffic']}, {$r['raw']['capture_rate']}, {$r['raw']['conversion_rate']}, {$r['total_fixed_costs']},
                    {$r['weighted_margin']}, {$r['total_estimated_units']}, {$r['estimated_revenue']}, {$r['total_cogs']},
                    {$r['break_even_units']}, {$r['projected_profit']}, {$r['required_conv_rate']},
                    '$sRisk', '$sVerdict', 'forecast'
                )";

        $insertResult = mysqli_query($conn, $sql);
        if ($insertResult) {
            $eventId = mysqli_insert_id($conn);
            $saved   = true;
            $_SESSION['dekukis_saved_id'] = $eventId;

            $pLen = count($prods);
            for ($pi = 0; $pi < $pLen; $pi++) {
                $spn = mysqli_real_escape_string($conn, $prods[$pi]['name']);
                $psql = "INSERT INTO event_products (event_id, product_name, selling_price, cogs_per_unit, gross_margin, estimated_units, estimated_revenue)
                         VALUES ($eventId, '$spn', {$prods[$pi]['selling_price']}, {$prods[$pi]['cogs_per_unit']}, {$prods[$pi]['gross_margin']}, {$prods[$pi]['estimated_units']}, {$prods[$pi]['estimated_revenue']})";
                mysqli_query($conn, $psql);
            }
        }
    }
    // Re-load results from session and redirect back to show result state
    if (isset($_SESSION['dekukis_pending_result'])) {
        $results     = $_SESSION['dekukis_pending_result'];
        $suggestions = []; // suggestions already shown; rebuild if needed
    }
}

// Restore session results if returning after Save (no new POST)
if ($results === null && isset($_SESSION['dekukis_pending_result']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Don't restore on GET — fresh page always shows idle state
    // (User can only see results after submitting the form)
}

// -------------------------------------------------------
// HISTORY — Fetch past 5 analyses for the sidebar
// -------------------------------------------------------
$history = [];
if (!$db_error) {
    $historyResult = mysqli_query($conn, "SELECT id, event_name, verdict, risk_level, projected_profit, status, created_at FROM event_analyses ORDER BY id DESC LIMIT 5");
    if ($historyResult && mysqli_num_rows($historyResult) > 0) {
        while ($hRow = mysqli_fetch_array($historyResult)) {
            $history[] = $hRow;
        }
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
    <link rel="stylesheet" href="assets/css/style.css?v=5">
</head>

<body>

    <div class="app-shell">

        <!-- ====== SIDEBAR ====== -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon"><i class="bx bx-store-alt"></i></div>
                <div>
                    <span class="brand-name">BLUEPRINT <span class="brand-pro">PRO</span></span>
                    <span class="brand-tag">Projected Intelligence</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item active"><i class="bx bx-calculator"></i> Analyzer</a>
                <a href="history.php" class="nav-item"><i class="bx bx-history"></i> Past Analyses</a>
                <a href="guide.php" class="nav-item"><i class="bx bx-book-open"></i> User Guide</a>
            </nav>

            <?php if (count($history) > 0): ?>
                <div class="sidebar-history">
                    <h4><i class="bx bx-time-five"></i> Recent</h4>
                    <?php
                    $hLen = count($history);
                    for ($i = 0; $i < $hLen; $i++) {
                        $h = $history[$i];
                        $hName = ucwords(strtolower(htmlspecialchars($h['event_name'])));
                        $hRisk = htmlspecialchars($h['risk_level']);
                        $hProfit = floatval($h['projected_profit']);
                        $hStatus = isset($h['status']) ? $h['status'] : 'forecast';

                        $dotClass = 'dot-green';
                        if ($hRisk === 'HIGH')
                            $dotClass = 'dot-red';
                        if ($hRisk === 'MEDIUM')
                            $dotClass = 'dot-yellow';

                        $profitClass = $hProfit >= 0 ? 'profit-pos' : 'profit-neg';
                        $profitSign = $hProfit >= 0 ? '+' : '';

                        echo "<div class='history-item'>";
                        echo "<span class='history-dot $dotClass'></span>";
                        echo "<div class='history-text'>";
                        echo "<span class='history-name'>$hName</span>";
                        echo "</div>";
                        echo "<span class='history-profit $profitClass'>{$profitSign}RM " . number_format(abs($hProfit), 2) . "</span>";
                        echo "</div>";
                    }
                    ?>
                </div>
            <?php endif; ?>

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

        <!-- ====== MAIN CONTENT ====== -->
        <main class="main">

            <!-- Header -->
            <header class="header">
                <div>
                    <h1>BLUEPRINT <span class="brand-pro">PRO</span></h1>
                    <p class="header-sub">Precision Financial Architecture for the Modern Vendor</p>
                </div>
            </header>

            <div class="content-grid">

                <!-- ====== INPUT FORM ====== -->
                <section class="card form-card">
                    <span class="card-corner-bl"></span><span class="card-corner-br"></span>
                    <div class="card-title">
                        <i class="bx bx-edit-alt"></i> Event Data Input
                    </div>

                    <?php if ($formError !== ''): ?>
                        <div class="alert alert-error">
                            <i class="bx bx-error-circle"></i> <?php echo htmlspecialchars($formError); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($db_error): ?>
                        <div class="alert alert-error">
                            <i class="bx bx-error-circle"></i> <?php echo $db_error; ?>
                            <br><small>Run <strong>setup.sql</strong> or <strong>migrate.sql</strong> in phpMyAdmin
                                first.</small>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="index.php" id="analyzer-form">

                        <!-- Event Info -->
                        <div class="form-section-title"><i class="bx bx-calendar-event"></i> Event Info</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="event_name">Event Name</label>
                                <input type="text" id="event_name" name="event_name"
                                    placeholder="e.g. Bazaar Ramadan KL"
                                    value="<?php echo isset($_POST['event_name']) ? htmlspecialchars($_POST['event_name']) : ''; ?>"
                                    required>
                            </div>
                            <div class="form-group small">
                                <label for="event_days">Duration (Days)</label>
                                <input type="number" id="event_days" name="event_days" min="1" placeholder="3"
                                    value="<?php echo isset($_POST['event_days']) ? htmlspecialchars($_POST['event_days']) : '1'; ?>"
                                    required>
                            </div>
                        </div>

                        <!-- Fixed Costs -->
                        <div class="form-section-title"><i class="bx bx-money"></i> Fixed Costs (RM)</div>
                        <div class="form-row three">
                            <div class="form-group">
                                <label for="booth_rental">Booth Rental <i class="bx bx-info-circle tooltip"
                                        data-tooltip="Include the hidden fees. Add your electricity surcharge, table rental, and deposit loss. Rent isn't just the floor space."></i></label>
                                <input type="number" id="booth_rental" name="booth_rental" step="0.01" min="0"
                                    placeholder="500.00"
                                    value="<?php echo isset($_POST['booth_rental']) ? htmlspecialchars($_POST['booth_rental']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="transport_cost">Transport / Gas <i class="bx bx-info-circle tooltip"
                                        data-tooltip="Don't forget tolls, parking fees, and multiple supply runs. Fuel isn't free, and your time driving isn't either."></i></label>
                                <input type="number" id="transport_cost" name="transport_cost" step="0.01" min="0"
                                    placeholder="150.00"
                                    value="<?php echo isset($_POST['transport_cost']) ? htmlspecialchars($_POST['transport_cost']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="marketing_cost">Marketing Materials <i class="bx bx-info-circle tooltip"
                                        data-tooltip="Event-specific flyers, banners, and targeted social ads only. Don't dump your yearly branding budget here."></i></label>
                                <input type="number" id="marketing_cost" name="marketing_cost" step="0.01" min="0"
                                    placeholder="100.00"
                                    value="<?php echo isset($_POST['marketing_cost']) ? htmlspecialchars($_POST['marketing_cost']) : ''; ?>">
                            </div>
                        </div>

                        <!-- Labor -->
                        <div class="form-section-title"><i class="bx bx-group"></i> Labor</div>
                        <div class="form-row three">
                            <div class="form-group">
                                <label for="num_staff">Number of Staff <i class="bx bx-info-circle tooltip"
                                        data-tooltip="Count yourself. If you are working for free, you do not have a business; you have a badly paying hobby."></i></label>
                                <input type="number" id="num_staff" name="num_staff" min="1" placeholder="2"
                                    value="<?php echo isset($_POST['num_staff']) ? htmlspecialchars($_POST['num_staff']) : '1'; ?>">
                            </div>
                            <div class="form-group">
                                <label for="hourly_wage">Hourly Wage (RM)</label>
                                <input type="number" id="hourly_wage" name="hourly_wage" step="0.01" min="0"
                                    placeholder="10.00"
                                    value="<?php echo isset($_POST['hourly_wage']) ? htmlspecialchars($_POST['hourly_wage']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="hours_per_day">Hours / Day</label>
                                <input type="number" id="hours_per_day" name="hours_per_day" step="0.5" min="1" max="24"
                                    placeholder="8"
                                    value="<?php echo isset($_POST['hours_per_day']) ? htmlspecialchars($_POST['hours_per_day']) : '8'; ?>">
                            </div>
                        </div>

                        <!-- Products (Dynamic) -->
                        <div class="form-section-title"><i class="bx bx-package"></i> Products</div>
                        <div id="products-container">
                            <?php
                            // Re-populate products on form re-submit
                            $postedNames = isset($_POST['product_name']) ? $_POST['product_name'] : [];
                            $postedPrices = isset($_POST['product_price']) ? $_POST['product_price'] : [];
                            $postedCogs = isset($_POST['product_cogs']) ? $_POST['product_cogs'] : [];
                            $postedUnits = isset($_POST['product_units']) ? $_POST['product_units'] : [];
                            $postedCount = count($postedNames);

                            if ($postedCount > 0) {
                                for ($pi = 0; $pi < $postedCount; $pi++) {
                                    $pnVal = htmlspecialchars($postedNames[$pi]);
                                    $ppVal = htmlspecialchars($postedPrices[$pi]);
                                    $pcVal = htmlspecialchars($postedCogs[$pi]);
                                    $puVal = htmlspecialchars($postedUnits[$pi]);
                                    echo '<div class="product-row">';
                                    echo '  <div class="product-row-header">';
                                    echo '    <span class="product-row-num">Product ' . ($pi + 1) . '</span>';
                                    if ($pi > 0) {
                                        echo '    <button type="button" class="remove-product-btn" onclick="removeProduct(this)" title="Remove Product"><i class="bx bx-trash"></i></button>';
                                    }
                                    echo '  </div>';
                                    echo '  <div class="form-row four">';
                                    echo '    <div class="form-group"><label>Product Name</label><input type="text" name="product_name[]" placeholder="e.g. Choc Cookies" value="' . $pnVal . '" required></div>';
                                    echo '    <div class="form-group"><label>Selling Price (RM)</label><input type="number" name="product_price[]" step="0.01" min="0.01" placeholder="25.00" value="' . $ppVal . '" required></div>';
                                    echo '    <div class="form-group"><label>COGS / Unit (RM) <i class="bx bx-info-circle tooltip" data-tooltip="Wait, what about the box? Cost of Goods Sold MUST include ingredients AND packaging."></i></label><input type="number" name="product_cogs[]" step="0.01" min="0" placeholder="8.00" value="' . $pcVal . '" required></div>';
                                    echo '    <div class="form-group"><label>Est. Units to Sell</label><input type="number" name="product_units[]" min="1" placeholder="50" value="' . $puVal . '" required></div>';
                                    echo '  </div>';
                                    echo '</div>';
                                }
                            } else {
                                ?>
                                <div class="product-row">
                                    <div class="product-row-header">
                                        <span class="product-row-num">Product 1</span>
                                    </div>
                                    <div class="form-row four">
                                        <div class="form-group"><label>Product Name</label><input type="text"
                                                name="product_name[]" placeholder="e.g. Choc Cookies" required></div>
                                        <div class="form-group"><label>Selling Price (RM)</label><input type="number"
                                                name="product_price[]" step="0.01" min="0.01" placeholder="25.00" required>
                                        </div>
                                        <div class="form-group"><label>COGS / Unit (RM) <i class="bx bx-info-circle tooltip"
                                                    data-tooltip="Wait, what about the box? Cost of Goods Sold MUST include ingredients AND packaging."></i></label><input
                                                type="number" name="product_cogs[]" step="0.01" min="0" placeholder="8.00"
                                                required></div>
                                        <div class="form-group"><label>Est. Units to Sell</label><input type="number"
                                                name="product_units[]" min="1" placeholder="50" required></div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <button type="button" class="add-product-btn" id="add-product-btn" onclick="addProduct()">
                            <i class="bx bx-plus"></i> Add Another Product
                        </button>

                        <!-- Traffic Estimates -->
                        <div class="form-section-title"><i class="bx bx-walk"></i> Traffic Estimates</div>
                        <div class="form-row three">
                            <div class="form-group">
                                <label for="total_traffic">Total Event Attendance</label>
                                <input type="number" id="total_traffic" name="total_traffic" min="1" placeholder="5000"
                                    value="<?php echo isset($_POST['total_traffic']) ? htmlspecialchars($_POST['total_traffic']) : ''; ?>"
                                    required>
                            </div>
                            <div class="form-group">
                                <label for="capture_rate">Capture Rate (%) <i class="bx bx-info-circle tooltip"
                                        data-tooltip="The percentage of foot traffic that actually stops at your booth. Most people will ignore you. Be realistic."></i></label>
                                <input type="number" id="capture_rate" name="capture_rate" step="0.1" min="0.1"
                                    max="100" placeholder="20.0"
                                    value="<?php echo isset($_POST['capture_rate']) ? htmlspecialchars($_POST['capture_rate']) : '20.0'; ?>">
                            </div>
                            <div class="form-group">
                                <label for="conversion_rate">Conversion Rate (%) <i class="bx bx-info-circle tooltip"
                                        data-tooltip="The percentage of people who stopped and actually opened their wallets. Looking isn't buying."></i></label>
                                <input type="number" id="conversion_rate" name="conversion_rate" step="0.1" min="0.1"
                                    max="100" placeholder="5.0"
                                    value="<?php echo isset($_POST['conversion_rate']) ? htmlspecialchars($_POST['conversion_rate']) : '5.0'; ?>">
                            </div>
                        </div>

                        <!-- Risk & Tax Configuration -->
                        <div class="form-section-title" style="margin-top:24px;"><i class="bx bx-shield-quarter"></i> Risk & Tax Configuration</div>
                        <div class="form-row three">
                            <div class="form-group">
                                <label for="expected_wastage_pct">Expected Wastage (%) <i class="bx bx-info-circle tooltip" data-tooltip="Percentage of stock that will go unsold, expire, or be given as samples. Inflates COGS."></i></label>
                                <input type="number" id="expected_wastage_pct" name="expected_wastage_pct" step="0.1" min="0" max="100" placeholder="5.0"
                                    value="<?php echo isset($_POST['expected_wastage_pct']) ? htmlspecialchars($_POST['expected_wastage_pct']) : '0.0'; ?>">
                            </div>
                            <div class="form-group">
                                <label for="tax_rate">Tax Rate (%) <i class="bx bx-info-circle tooltip" data-tooltip="e.g. 6% SST. Set to 0 if not registered."></i></label>
                                <input type="number" id="tax_rate" name="tax_rate" step="0.1" min="0" max="100" placeholder="6.0"
                                    value="<?php echo isset($_POST['tax_rate']) ? htmlspecialchars($_POST['tax_rate']) : '0.0'; ?>">
                            </div>
                            <div class="form-group">
                                <label for="is_tax_inclusive">Tax Strategy <i class="bx bx-info-circle tooltip" data-tooltip="Inclusive slashes your margin (business eats tax). Exclusive passes tax to customer above retail price."></i></label>
                                <select id="is_tax_inclusive" name="is_tax_inclusive" style="width: 100%; padding: 12px; background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: var(--radius-sm); color: #fff; font-family: inherit; font-size: 14px;">
                                    <option value="1" style="background:#0a0e1a;" <?php echo (!isset($_POST['is_tax_inclusive']) || $_POST['is_tax_inclusive'] == '1') ? 'selected' : ''; ?>>Inclusive (Eat the tax)</option>
                                    <option value="0" style="background:#0a0e1a;" <?php echo (isset($_POST['is_tax_inclusive']) && $_POST['is_tax_inclusive'] == '0') ? 'selected' : ''; ?>>Exclusive (Pass to customer)</option>
                                </select>
                            </div>
                        </div>

                    </form>
                </section>

                <!-- ====== RESULTS PANEL ====== -->
                <section class="card results-card" id="results-panel">
                    <span class="card-corner-bl"></span><span class="card-corner-br"></span>
                    <?php if ($results): ?>

                        <!-- Verdict Banner -->
                        <?php
                        $verdictClass = 'verdict-green';
                        $verdictIcon = 'bx-check-circle';
                        if ($results['risk_level'] === 'HIGH') {
                            $verdictClass = 'verdict-red';
                            $verdictIcon = 'bx-x-circle';
                        } else if ($results['risk_level'] === 'MEDIUM') {
                            $verdictClass = 'verdict-yellow';
                            $verdictIcon = 'bx-error';
                        }
                        ?>
                        <div class="verdict-banner <?php echo $verdictClass; ?> anim-reveal" style="--anim-delay:0.05s">
                            <i class="bx <?php echo $verdictIcon; ?>"></i>
                            <div>
                                <h2><?php echo htmlspecialchars($results['verdict']); ?></h2>
                                <p><?php echo htmlspecialchars($results['event_name']); ?> —
                                    <?php echo $results['event_days']; ?>
                                    Day<?php echo $results['event_days'] > 1 ? 's' : ''; ?>
                                </p>
                            </div>
                        </div>

                        <!-- KPI Cards -->
                        <div class="kpi-row anim-reveal" style="--anim-delay:0.2s">
                            <div class="kpi-card">
                                <span class="kpi-label">Break-Even Point</span>
                                <span class="kpi-value counter"
                                    data-target="<?php echo $results['break_even_units']; ?>"
                                    data-prefix=""
                                    data-suffix=""
                                    data-decimals="0"><?php echo number_format($results['break_even_units']); ?></span>
                                <span class="kpi-unit">units to sell</span>
                            </div>
                            <div class="kpi-card <?php echo $results['projected_profit'] >= 0 ? 'kpi-positive' : 'kpi-negative'; ?>">
                                <span class="kpi-label">Projected Profit/Loss</span>
                                <span class="kpi-value counter"
                                    data-target="<?php echo abs($results['projected_profit']); ?>"
                                    data-prefix="<?php echo ($results['projected_profit'] >= 0 ? '+' : '-') . 'RM '; ?>"
                                    data-suffix=""
                                    data-decimals="2"><?php echo ($results['projected_profit'] >= 0 ? '+' : '') . 'RM ' . number_format($results['projected_profit'], 2); ?></span>
                                <span class="kpi-unit"><?php echo $results['projected_profit'] >= 0 ? 'estimated profit' : 'estimated loss'; ?></span>
                            </div>
                            <div class="kpi-card">
                                <span class="kpi-label">Total Est. Units</span>
                                <span class="kpi-value counter"
                                    data-target="<?php echo $results['total_estimated_units']; ?>"
                                    data-prefix=""
                                    data-suffix=""
                                    data-decimals="0"><?php echo number_format($results['total_estimated_units']); ?></span>
                                <span class="kpi-unit">across <?php echo count($results['products']); ?>
                                    product<?php echo count($results['products']) > 1 ? 's' : ''; ?></span>
                            </div>
                        </div>

                        <!-- Per-Product Breakdown -->
                        <div class="breakdown anim-reveal" style="--anim-delay:0.35s">
                            <h3><i class="bx bx-package"></i> Product Breakdown</h3>
                            <div class="product-table-wrap">
                                <table class="breakdown-table product-breakdown-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>COGS</th>
                                            <th>Margin</th>
                                            <th>Est. Units</th>
                                            <th>Est. Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $rpLen = count($results['products']);
                                        for ($rpi = 0; $rpi < $rpLen; $rpi++) {
                                            $rp = $results['products'][$rpi];
                                            echo '<tr>';
                                            echo '<td><strong>' . htmlspecialchars($rp['name']) . '</strong></td>';
                                            echo '<td>RM ' . number_format($rp['selling_price'], 2) . '</td>';
                                            echo '<td>RM ' . number_format($rp['cogs_per_unit'], 2) . '</td>';
                                            echo '<td class="text-safe">RM ' . number_format($rp['gross_margin'], 2) . '</td>';
                                            echo '<td>' . number_format($rp['estimated_units']) . '</td>';
                                            echo '<td>RM ' . number_format($rp['estimated_revenue'], 2) . '</td>';
                                            echo '</tr>';
                                        }
                                        ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="row-highlight">
                                            <td><strong>Totals</strong></td>
                                            <td>—</td>
                                            <td>—</td>
                                            <td><strong>RM
                                                    <?php echo number_format($results['weighted_margin'], 2); ?></strong>
                                                <small>avg</small>
                                            </td>
                                            <td><strong><?php echo number_format($results['total_estimated_units']); ?></strong>
                                            </td>
                                            <td><strong>RM
                                                    <?php echo number_format($results['estimated_revenue'], 2); ?></strong>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Break-Even Point Chart -->
                        <div class="bep-chart-card anim-reveal" style="--anim-delay:0.5s">
                            <h3><i class="bx bx-line-chart"></i> Break-Even Analysis</h3>
                            <div class="bep-chart-wrap">
                                <canvas id="bepChart"></canvas>
                            </div>
                            <div class="bep-chart-legend">
                                <span class="legend-item"><span class="legend-dot" style="background:#3b82f6;"></span> Total
                                    Revenue</span>
                                <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span> Total
                                    Cost</span>
                                <span class="legend-item"><span class="legend-dot" style="background:#f59e0b;"></span> BEP
                                    (<?php echo number_format($results['break_even_units']); ?> units)</span>
                            </div>
                        </div>

                        <!-- Financial Breakdown -->
                        <div class="breakdown anim-reveal" style="--anim-delay:0.65s">
                            <h3><i class="bx bx-bar-chart-alt-2"></i> Financial Breakdown</h3>
                            <table class="breakdown-table">
                                <tr>
                                    <td class="label-cell">Booth Rental</td>
                                    <td class="value-cell">RM <?php echo number_format($results['booth_rental'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Transport / Gas</td>
                                    <td class="value-cell">RM <?php echo number_format($results['transport_cost'], 2); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Marketing Materials</td>
                                    <td class="value-cell">RM <?php echo number_format($results['marketing_cost'], 2); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Labor Cost</td>
                                    <td class="value-cell">RM <?php echo number_format($results['labor_cost'], 2); ?></td>
                                </tr>
                                <tr class="row-highlight">
                                    <td class="label-cell"><strong>Total Fixed Costs</strong></td>
                                    <td class="value-cell"><strong>RM
                                            <?php echo number_format($results['total_fixed_costs'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="table-divider"></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Total COGS</td>
                                    <td class="value-cell">RM <?php echo number_format($results['total_cogs'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="label-cell">Total Est. Revenue</td>
                                    <td class="value-cell">RM <?php echo number_format($results['estimated_revenue'], 2); ?>
                                    </td>
                                </tr>
                                <tr class="row-highlight">
                                    <td class="label-cell"><strong>Projected Profit/Loss</strong></td>
                                    <td
                                        class="value-cell <?php echo $results['projected_profit'] >= 0 ? 'text-safe' : 'text-danger'; ?>">
                                        <strong><?php echo ($results['projected_profit'] >= 0 ? '+' : '') . 'RM ' . number_format($results['projected_profit'], 2); ?></strong>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Reality Check -->
                        <div class="reality-check anim-reveal" style="--anim-delay:0.8s">
                            <div class="reality-row">
                                <span>Required Conversion Rate to Break Even</span>
                                <span
                                    class="<?php echo $results['required_conv_rate'] > 10 ? 'text-danger' : ($results['required_conv_rate'] > 6 ? 'text-warning' : 'text-safe'); ?>">
                                    <?php echo number_format($results['required_conv_rate'], 2); ?>%
                                </span>
                            </div>
                            <div class="reality-row">
                                <span>Your Set Conversion Rate</span>
                                <span><?php echo number_format($conversionRate, 1); ?>%</span>
                            </div>
                            <div class="reality-row">
                                <span>Revenue vs Costs</span>
                                <span
                                    class="<?php echo $results['estimated_revenue'] >= $results['total_fixed_costs'] ? 'text-safe' : 'text-danger'; ?>">
                                    <?php
                                    if ($results['total_fixed_costs'] > 0) {
                                        echo number_format(($results['estimated_revenue'] / $results['total_fixed_costs']) * 100, 1) . '% coverage';
                                    } else {
                                        echo '∞ (no fixed costs)';
                                    }
                                    ?>
                                </span>
                            </div>

                            <?php if ($results['risk_level'] === 'HIGH'): ?>
                                <div class="alert alert-danger">
                                    <i class="bx bx-shield-x"></i>
                                    <strong>HIGH RISK — DO NOT JOIN.</strong>
                                    The required conversion rate
                                    (<?php echo number_format($results['required_conv_rate'], 2); ?>%) exceeds the 10% safety
                                    threshold.
                                    You would need to sell <strong><?php echo number_format($results['break_even_units']); ?>
                                        units</strong> just to break even.
                                </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sensitivity Analysis -->
                        <div class="sensitivity-panel anim-reveal" style="--anim-delay:0.9s; margin-top:24px; padding: 24px; background: rgba(0,0,0,0.25); border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.05); box-shadow: inset 0 2px 10px rgba(0,0,0,0.2);">
                            <h3 style="margin-bottom: 8px; font-size: 15px; display: flex; align-items: center; gap: 8px;"><i class="bx bx-slider-alt" style="color: var(--orange);"></i> Stress Test: Conversion Drop</h3>
                            <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 20px;">What if your booth is less popular than you think? Drag the slider to simulate lower conversion rates and see if you still break even.</p>
                            
                            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; background: rgba(0,0,0,0.2); padding: 12px 16px; border-radius: var(--radius-sm);">
                                <span style="font-weight: 700; font-variant-numeric: tabular-nums; font-size: 18px; color: var(--orange); min-width: 55px;" id="sens-rate-display"><?php echo number_format($conversionRate, 1); ?>%</span>
                                <input type="range" id="sensitivity-slider" min="0.1" max="<?php echo max(0.1, $conversionRate); ?>" step="0.1" value="<?php echo max(0.1, $conversionRate); ?>" style="flex:1; accent-color: var(--orange); height: 6px; cursor: pointer;">
                            </div>

                            <div class="reality-row" style="padding-bottom: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 12px;">
                                <span style="color: var(--text-secondary);">Simulated Revenue</span>
                                <span id="sens-revenue" style="font-weight: 700; font-variant-numeric: tabular-nums; font-size: 15px;">RM <?php echo number_format($results['estimated_revenue'], 2); ?></span>
                            </div>
                            <div class="reality-row">
                                <span style="color: var(--text-secondary);">Simulated Profit/Loss</span>
                                <span id="sens-profit" style="font-weight: 800; font-variant-numeric: tabular-nums; font-size: 16px;" class="<?php echo $results['projected_profit'] >= 0 ? 'text-safe' : 'text-danger'; ?>">
                                    <?php echo ($results['projected_profit'] >= 0 ? '+' : '') . 'RM ' . number_format($results['projected_profit'], 2); ?>
                                </span>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const slider = document.getElementById('sensitivity-slider');
                                if(!slider) return;
                                
                                const baseTraffic = <?php echo $totalTraffic; ?>;
                                const baseCapture = <?php echo $captureRate; ?> / 100;
                                const totalFixedCosts = <?php echo $results['total_fixed_costs']; ?>;
                                
                                // Weighted averages based on user's manual product estimates
                                const avgPrice = <?php echo $results['total_estimated_units'] > 0 ? $results['estimated_revenue'] / $results['total_estimated_units'] : 0; ?>;
                                const avgCogs = <?php echo $results['total_estimated_units'] > 0 ? $results['total_cogs'] / $results['total_estimated_units'] : 0; ?>;

                                slider.addEventListener('input', function(e) {
                                    const simConv = parseFloat(e.target.value);
                                    document.getElementById('sens-rate-display').textContent = simConv.toFixed(1) + '%';
                                    
                                    // Recalculate financial metrics based on new conversion rate
                                    const simUnits = baseTraffic * baseCapture * (simConv / 100);
                                    const simRevenue = simUnits * avgPrice;
                                    const simCogs = simUnits * avgCogs;
                                    const simProfit = simRevenue - totalFixedCosts - simCogs;
                                    
                                    // Update DOM Elements
                                    document.getElementById('sens-revenue').textContent = 'RM ' + simRevenue.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    
                                    const profitEl = document.getElementById('sens-profit');
                                    profitEl.textContent = (simProfit >= 0 ? '+' : '') + 'RM ' + simProfit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    profitEl.className = simProfit >= 0 ? 'text-safe' : 'text-danger';
                                });
                            });
                        </script>

                        <!-- ====== SMART SUGGESTIONS ====== -->
                        <?php if (count($suggestions) > 0): ?>
                            <div class="suggestions-section">
                                <h3><i class="bx bx-bulb"></i> Smart Suggestions — How to Make It Work</h3>
                                <p class="suggestions-subtitle">Here are actionable changes to improve viability:</p>
                                <div class="suggestions-grid">
                                    <?php
                                    $sLen = count($suggestions);
                                    for ($si = 0; $si < $sLen; $si++) {
                                        $sg = $suggestions[$si];
                                        $sgIcon = htmlspecialchars($sg['icon']);
                                        $sgColor = htmlspecialchars($sg['color']);
                                        $sgTitle = htmlspecialchars($sg['title']);
                                        $sgDetail = htmlspecialchars($sg['detail']);
                                        $sgImpact = htmlspecialchars($sg['impact']);
                                        echo '<div class="suggestion-card suggestion-' . $sgColor . '">';
                                        echo '  <div class="suggestion-header">';
                                        echo '    <i class="bx ' . $sgIcon . '"></i>';
                                        echo '    <strong>' . $sgTitle . '</strong>';
                                        echo '  </div>';
                                        echo '  <p class="suggestion-detail">' . $sgDetail . '</p>';
                                        echo '  <p class="suggestion-impact"><i class="bx bx-right-arrow-alt"></i> ' . $sgImpact . '</p>';
                                        echo '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($saved): ?>
                            <!-- Already saved confirmation -->
                            <div class="save-confirmation analysis-action-bar anim-reveal" style="--anim-delay:1s">
                                <div>
                                    <i class="bx bx-check-circle"></i>
                                    Analysis saved to <a href="history.php" style="color:var(--bp-cyan);text-decoration:underline;">Past Analyses</a>
                                    — <span class="status-badge status-forecast">FORECAST</span>
                                </div>
                                <div style="display: flex; gap: 12px; margin-top: 16px;">
                                    <a href="export_pdf.php?id=<?php echo $_SESSION['dekukis_saved_id']; ?>" target="_blank" class="action-btn action-btn-save" style="background: rgba(59, 130, 246, 0.1); border: 1px solid var(--blue); color: var(--blue);">
                                        <i class="bx bxs-file-pdf"></i> Export PDF Report
                                    </a>
                                    <a href="index.php" class="action-btn action-btn-recalc" style="flex: 1; text-align: center;">
                                        <i class="bx bx-refresh"></i> Run Another
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Save + Recalculate Action Bar -->
                            <div class="analysis-action-bar anim-reveal" style="--anim-delay:1s">
                                <form method="POST" action="" style="display:contents;">
                                    <input type="hidden" name="action" value="save_analysis">
                                    <button type="submit" class="action-btn action-btn-save">
                                        <i class="bx bx-save"></i> Save to Past Analyses
                                    </button>
                                </form>
                                <a href="index.php" class="action-btn action-btn-recalc">
                                    <i class="bx bx-refresh"></i> Recalculate
                                </a>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>

                        <!-- ======= DECISION SUPPORT IDLE STATE ======= -->
                        <div class="card-title">
                            <i class="bx bx-brain"></i> Bazaar Decision Support
                        </div>

                        <div class="decision-support-panel">
                            <div class="support-headline">
                                <h2 class="mascot-title">Your Data, Decided.</h2>
                                <p class="mascot-sub">Fill in event details on the left. The numbers will tell you if it's worth showing up.</p>
                            </div>
                            <div class="decision-tips">
                                <div class="tip"><i class="bx bx-package"></i> Add multiple products for accurate margin forecasting</div>
                                <div class="tip"><i class="bx bx-trending-up"></i> Conversion rates above 10% are exceptionally rare</div>
                                <div class="tip"><i class="bx bx-exit"></i> If BEP exceeds estimated sales volume, walk away</div>
                            </div>
                            <div class="industry-benchmarks">
                                <p class="benchmarks-label">Industry Benchmarks</p>
                                <div class="benchmark-grid">
                                    <div class="benchmark-card">
                                        <div class="benchmark-icon benchmark-green"><i class="bx bx-trending-up"></i></div>
                                        <div class="benchmark-info"><span>Target Margin</span><strong>15%</strong></div>
                                    </div>
                                    <div class="benchmark-card">
                                        <div class="benchmark-icon benchmark-red"><i class="bx bx-target-lock"></i></div>
                                        <div class="benchmark-info"><span>Target BEP Units</span><strong>75</strong></div>
                                    </div>
                                    <div class="benchmark-card">
                                        <div class="benchmark-icon benchmark-yellow"><i class="bx bx-user-check"></i></div>
                                        <div class="benchmark-info"><span>Conversion Aim</span><strong>5%</strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endif; ?>

                    <!-- Analyze Bazaar — only shown on idle state -->
                    <?php if (!$results): ?>
                    <button type="submit" form="analyzer-form" id="analyze-btn" class="submit-btn analyze-master-btn"
                        onclick="dekukisBeforeSubmit(this)">
                        <span class="btn-idle"><i class="bx bx-analyse"></i> New Execution</span>
                        <span class="btn-loading" style="display:none;"><i class="bx bx-loader-alt bx-spin"></i> Calculating…</span>
                    </button>
                    <?php endif; ?>

                </section>

            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // -------------------------------------------------------
        // DYNAMIC PRODUCT ROWS
        // -------------------------------------------------------
        var productIndex = <?php echo max($postedCount, 1); ?>;

        function addProduct() {
            productIndex++;
            var container = document.getElementById('products-container');
            var row = document.createElement('div');
            row.className = 'product-row product-row-enter';
            row.innerHTML = '' +
                '<div class="product-row-header">' +
                '  <span class="product-row-num">Product ' + productIndex + '</span>' +
                '  <button type="button" class="remove-product-btn" onclick="removeProduct(this)" title="Remove Product"><i class="bx bx-trash"></i></button>' +
                '</div>' +
                '<div class="form-row four">' +
                '  <div class="form-group"><label>Product Name</label><input type="text" name="product_name[]" placeholder="e.g. Brownies" required></div>' +
                '  <div class="form-group"><label>Selling Price (RM)</label><input type="number" name="product_price[]" step="0.01" min="0.01" placeholder="25.00" required></div>' +
                '  <div class="form-group"><label>COGS / Unit (RM)</label><input type="number" name="product_cogs[]" step="0.01" min="0" placeholder="8.00" required></div>' +
                '  <div class="form-group"><label>Est. Units to Sell</label><input type="number" name="product_units[]" min="1" placeholder="50" required></div>' +
                '</div>';
            container.appendChild(row);

            // Trigger animation
            setTimeout(function () { row.classList.remove('product-row-enter'); }, 10);

            renumberProducts();
        }

        function removeProduct(btn) {
            var row = btn.closest('.product-row');
            row.classList.add('product-row-exit');
            setTimeout(function () {
                row.remove();
                renumberProducts();
            }, 250);
        }

        function renumberProducts() {
            var rows = document.querySelectorAll('#products-container .product-row');
            for (var i = 0; i < rows.length; i++) {
                var numSpan = rows[i].querySelector('.product-row-num');
                if (numSpan) numSpan.textContent = 'Product ' + (i + 1);
            }
        }

        // -------------------------------------------------------
        // BREAK-EVEN CHART
        // -------------------------------------------------------
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($results): ?>
                // Scroll to results
                var panel = document.getElementById('results-panel');
                if (panel) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                // --- Break-Even Chart ---
                var bepCanvas = document.getElementById('bepChart');
                if (bepCanvas) {
                    var fixedCosts = <?php echo $results['total_fixed_costs']; ?>;
                    var weightedPrice = <?php echo ($results['total_estimated_units'] > 0 ? $results['estimated_revenue'] / $results['total_estimated_units'] : 0); ?>;
                    var weightedCogs = <?php echo ($results['total_estimated_units'] > 0 ? $results['total_cogs'] / $results['total_estimated_units'] : 0); ?>;
                    var bepUnits = <?php echo $results['break_even_units']; ?>;
                    var estUnits = <?php echo $results['total_estimated_units']; ?>;

                    // X-axis: 0 to max(bepUnits*1.8, estUnits*1.5, 10)
                    var maxUnits = Math.max(Math.ceil(bepUnits * 1.8), Math.ceil(estUnits * 1.5), 10);
                    var step = Math.max(1, Math.ceil(maxUnits / 10));
                    var labels = [];
                    var revenueData = [];
                    var costData = [];

                    for (var u = 0; u <= maxUnits; u += step) {
                        labels.push(u);
                        revenueData.push(u * weightedPrice);
                        costData.push(fixedCosts + (u * weightedCogs));
                    }

                    var ctx = bepCanvas.getContext('2d');

                    // Revenue gradient
                    var revGrad = ctx.createLinearGradient(0, 0, 0, 350);
                    revGrad.addColorStop(0, 'rgba(59, 130, 246, 0.15)');
                    revGrad.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Total Revenue',
                                    data: revenueData,
                                    borderColor: '#3b82f6',
                                    borderWidth: 3,
                                    backgroundColor: revGrad,
                                    fill: true,
                                    tension: 0.1,
                                    pointRadius: 0,
                                    pointHoverRadius: 5,
                                    pointHoverBackgroundColor: '#3b82f6'
                                },
                                {
                                    label: 'Total Cost',
                                    data: costData,
                                    borderColor: '#ef4444',
                                    borderWidth: 3,
                                    borderDash: [6, 4],
                                    backgroundColor: 'transparent',
                                    fill: false,
                                    tension: 0.1,
                                    pointRadius: 0,
                                    pointHoverRadius: 5,
                                    pointHoverBackgroundColor: '#ef4444'
                                }
                            ]
                        },
                        options: {
                            animation: {
                                duration: 2000,
                                easing: 'easeOutQuart'
                            },
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(22, 25, 37, 0.95)',
                                    titleColor: '#fff',
                                    bodyColor: '#94a3b8',
                                    borderColor: 'rgba(255,255,255,0.05)',
                                    borderWidth: 1,
                                    padding: 14,
                                    callbacks: {
                                        title: function (items) { return items[0].label + ' units'; },
                                        label: function (item) { return ' ' + item.dataset.label + ': RM ' + item.parsed.y.toFixed(2); }
                                    }
                                },
                                annotation: undefined
                            },
                            scales: {
                                x: {
                                    title: { display: true, text: 'Units Sold', color: '#7c879a', font: { size: 12 } },
                                    grid: { color: 'rgba(255,255,255,0.03)' },
                                    ticks: { color: '#7c879a', font: { size: 11 } }
                                },
                                y: {
                                    title: { display: true, text: 'Amount (RM)', color: '#7c879a', font: { size: 12 } },
                                    grid: { color: 'rgba(255,255,255,0.04)', borderDash: [4, 4] },
                                    ticks: {
                                        color: '#7c879a',
                                        font: { size: 11 },
                                        callback: function (val) { return 'RM ' + val.toLocaleString(); }
                                    },
                                    beginAtZero: true
                                }
                            },
                            interaction: { intersect: false, mode: 'index' }
                        },
                        plugins: [{
                            id: 'bepLine',
                            afterDraw: function (chart) {
                                if (bepUnits <= 0) return;
                                var xScale = chart.scales.x;
                                var yScale = chart.scales.y;
                                var ctx2 = chart.ctx;

                                var bepRevenue = bepUnits * weightedPrice;
                                var ratio = bepUnits / maxUnits;
                                var xPixel = xScale.left + (ratio * (xScale.right - xScale.left));
                                var yPixel = yScale.getPixelForValue(bepRevenue);

                                // Vertical dashed line
                                ctx2.save();
                                ctx2.setLineDash([5, 5]);
                                ctx2.strokeStyle = '#f59e0b';
                                ctx2.lineWidth = 2;
                                ctx2.beginPath();
                                ctx2.moveTo(xPixel, yScale.top);
                                ctx2.lineTo(xPixel, yScale.bottom);
                                ctx2.stroke();
                                ctx2.restore();

                                // BEP dot
                                ctx2.save();
                                ctx2.beginPath();
                                ctx2.arc(xPixel, yPixel, 7, 0, Math.PI * 2);
                                ctx2.fillStyle = '#f59e0b';
                                ctx2.fill();
                                ctx2.strokeStyle = '#0c0e16';
                                ctx2.lineWidth = 3;
                                ctx2.stroke();
                                ctx2.restore();

                                // BEP label
                                ctx2.save();
                                ctx2.font = '600 11px Inter, sans-serif';
                                ctx2.fillStyle = '#f59e0b';
                                ctx2.textAlign = 'center';
                                ctx2.fillText('BEP: ' + bepUnits + ' units', xPixel, yScale.top - 8);
                                ctx2.restore();
                            }
                        }]
                    });
                }
            <?php endif; ?>
        });

        // ================================================================
        // SUBMIT BUTTON — Loading State
        // ================================================================
        function dekukisBeforeSubmit(btn) {
            btn.querySelector('.btn-idle').style.display = 'none';
            btn.querySelector('.btn-loading').style.display = 'inline-flex';
            btn.style.opacity = '0.8';
            btn.style.pointerEvents = 'none';
        }

        // ================================================================
        // STAGGERED REVEAL — .anim-reveal elements fade up on load
        // ================================================================
        document.addEventListener('DOMContentLoaded', function () {
            var reveals = document.querySelectorAll('.anim-reveal');
            for (var i = 0; i < reveals.length; i++) {
                (function (el) {
                    var delay = parseFloat(el.style.getPropertyValue('--anim-delay') || '0') * 1000;
                    setTimeout(function () {
                        el.classList.add('anim-visible');
                    }, delay);
                })(reveals[i]);
            }

            // ============================================================
            // TABLE ROWS — sequential fade-in inside breakdowns
            // ============================================================
            var tableRows = document.querySelectorAll('.breakdown-table tr, .breakdown-table td');
            for (var r = 0; r < tableRows.length; r++) {
                tableRows[r].style.opacity = '0';
                tableRows[r].style.transform = 'translateX(-8px)';
                tableRows[r].style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                (function (row, idx) {
                    setTimeout(function () {
                        row.style.opacity = '1';
                        row.style.transform = 'translateX(0)';
                    }, 700 + idx * 60);
                })(tableRows[r], r);
            }

            // ============================================================
            // REALITY ROW — sequential pulse-in
            // ============================================================
            var realityRows = document.querySelectorAll('.reality-row');
            for (var rr = 0; rr < realityRows.length; rr++) {
                realityRows[rr].style.opacity = '0';
                realityRows[rr].style.transform = 'translateY(6px)';
                realityRows[rr].style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                (function (row, idx) {
                    setTimeout(function () {
                        row.style.opacity = '1';
                        row.style.transform = 'translateY(0)';
                    }, 900 + idx * 120);
                })(realityRows[rr], rr);
            }

            // ============================================================
            // KPI COUNTER — animated number roll-up
            // ============================================================
            var counters = document.querySelectorAll('.counter');
            if (counters.length > 0) {
                setTimeout(function () {
                    for (var ci = 0; ci < counters.length; ci++) {
                        animateCounter(counters[ci]);
                    }
                }, 350); // start after KPI row fades in
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