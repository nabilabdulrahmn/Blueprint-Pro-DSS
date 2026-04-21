<?php
require_once 'db.php';
require_once 'auth.php';

// Handle DELETE request
$deleteMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = intval($_POST['delete_id']);
    if ($deleteId > 0 && !$db_error) {
        $delResult = mysqli_query($conn, "DELETE FROM event_analyses WHERE id = $deleteId");
        if ($delResult && mysqli_affected_rows($conn) > 0) {
            $deleteMsg = 'Analysis #' . $deleteId . ' deleted successfully.';
        } else {
            $deleteMsg = 'Failed to delete analysis #' . $deleteId . '.';
        }
    }
}

// Pagination
$perPage     = 15;
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $perPage;

$totalRows  = 0;
$totalPages = 1;
$rows       = [];

if (!$db_error) {
    $countR = mysqli_query($conn, "SELECT COUNT(*) as c FROM event_analyses");
    if ($countR && $cRow = mysqli_fetch_array($countR)) {
        $totalRows  = intval($cRow['c']);
        $totalPages = max(1, ceil($totalRows / $perPage));
    }

    $result = mysqli_query($conn, "SELECT * FROM event_analyses ORDER BY id DESC LIMIT $perPage OFFSET $offset");
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_array($result)) {
            $rows[] = $row;
        }
    }
}

// Count stats
$forecastCount  = 0;
$completedCount = 0;
if (!$db_error) {
    $statsR = mysqli_query($conn, "SELECT status, COUNT(*) as c FROM event_analyses GROUP BY status");
    if ($statsR) {
        while ($sRow = mysqli_fetch_array($statsR)) {
            if ($sRow['status'] === 'forecast') $forecastCount = intval($sRow['c']);
            if ($sRow['status'] === 'completed') $completedCount = intval($sRow['c']);
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
    <link rel="stylesheet" href="assets/css/style.css?v=2">
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
            <a href="history.php" class="nav-item active"><i class="bx bx-history"></i> Past Analyses</a>
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
                <h1>Past <span class="brand-pro">Executions</span></h1>
                <p class="header-sub">Precision Financial Architecture for the Modern Vendor</p>
            </div>
        </header>

        <div class="content-single">

            <!-- Stats Strip -->
            <div class="history-stats">
                <div class="hist-stat">
                    <i class="bx bx-bar-chart-alt-2"></i>
                    <span><strong><?php echo $totalRows; ?></strong> Total</span>
                </div>
                <div class="hist-stat">
                    <i class="bx bx-line-chart" style="color: var(--blue);"></i>
                    <span><strong><?php echo $forecastCount; ?></strong> Forecast</span>
                </div>
                <div class="hist-stat">
                    <i class="bx bx-check-circle" style="color: var(--green);"></i>
                    <span><strong><?php echo $completedCount; ?></strong> Completed</span>
                </div>
            </div>

            <div class="card">
                <?php if ($db_error): ?>
                    <div class="alert alert-error">
                        <i class="bx bx-error-circle"></i> <?php echo $db_error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($deleteMsg !== ''): ?>
                    <div class="alert <?php echo strpos($deleteMsg, 'successfully') !== false ? 'alert-success' : 'alert-error'; ?>">
                        <i class="bx <?php echo strpos($deleteMsg, 'successfully') !== false ? 'bx-check-circle' : 'bx-error-circle'; ?>"></i>
                        <?php echo htmlspecialchars($deleteMsg); ?>
                    </div>
                <?php endif; ?>

                <?php if (count($rows) > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Event Name</th>
                                <th>Days</th>
                                <th>Fixed Costs</th>
                                <th>BEP (Units)</th>
                                <th>Est. Revenue</th>
                                <th>Profit/Loss</th>
                                <th>Risk</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rLen = count($rows);
                            for ($i = 0; $i < $rLen; $i++) {
                                $r = $rows[$i];
                                $rId      = htmlspecialchars($r['id']);
                                $rName    = htmlspecialchars($r['event_name']);
                                $rDays    = intval($r['event_days']);
                                $rFixed   = floatval($r['total_fixed_costs']);
                                $rBep     = intval($r['break_even_units']);
                                $rRev     = floatval($r['estimated_revenue']);
                                $rProfit  = floatval($r['projected_profit']);
                                $rRisk    = htmlspecialchars($r['risk_level']);
                                $rDate    = htmlspecialchars($r['created_at']);
                                $rStatus  = isset($r['status']) ? $r['status'] : 'forecast';

                                $riskClass = 'risk-low';
                                if ($rRisk === 'HIGH') $riskClass = 'risk-high';
                                if ($rRisk === 'MEDIUM') $riskClass = 'risk-medium';

                                $profitClass = $rProfit >= 0 ? 'text-safe' : 'text-danger';

                                $statusCls = $rStatus === 'completed' ? 'status-completed' : 'status-forecast';
                                $statusLabel = $rStatus === 'completed' ? 'COMPLETED' : 'FORECAST';

                                echo "<tr>";
                                echo "<td>$rId</td>";
                                echo "<td><strong>$rName</strong></td>";
                                echo "<td>$rDays</td>";
                                echo "<td>RM " . number_format($rFixed, 2) . "</td>";
                                echo "<td>$rBep</td>";
                                echo "<td>RM " . number_format($rRev, 2) . "</td>";
                                echo "<td class='$profitClass'><strong>" . ($rProfit >= 0 ? '+' : '') . "RM " . number_format($rProfit, 2) . "</strong></td>";
                                echo "<td><span class='risk-badge $riskClass'>$rRisk</span></td>";
                                echo "<td><span class='status-badge $statusCls'>$statusLabel</span></td>";
                                echo "<td class='date-cell'>$rDate</td>";
                                echo "<td class='actions-cell'>";

                                // Action buttons
                                if ($rStatus === 'completed') {
                                    echo "<a href='post_event.php?id=$rId' class='action-btn action-view' title='View Comparison'><i class='bx bx-bar-chart'></i></a>";
                                } else {
                                    echo "<a href='post_event.php?id=$rId' class='action-btn action-record' title='Record Actuals'><i class='bx bx-edit'></i></a>";
                                }

                                echo "<form method='POST' action='history.php?page=$currentPage' style='display:inline;' onsubmit=\"return confirm('Delete analysis for " . addslashes($rName) . "?');\" >";
                                echo "<input type='hidden' name='delete_id' value='$rId'>";
                                echo "<button type='submit' class='delete-btn' title='Delete'><i class='bx bx-trash'></i></button>";
                                echo "</form>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?php echo ($currentPage - 1); ?>" class="page-btn"><i class="bx bx-chevron-left"></i></a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $currentPage - 2);
                    $end   = min($totalPages, $currentPage + 2);
                    for ($p = $start; $p <= $end; $p++) {
                        $active = ($p === $currentPage) ? 'active' : '';
                        echo "<a href='?page=$p' class='page-btn $active'>$p</a>";
                    }
                    ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo ($currentPage + 1); ?>" class="page-btn"><i class="bx bx-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="bx bx-folder-open"></i>
                        <h3>No Analyses Found</h3>
                        <p>Go to the <a href="index.php">Analyzer</a> to run your first event analysis.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include 'helper.php'; ?>
</body>
</html>
