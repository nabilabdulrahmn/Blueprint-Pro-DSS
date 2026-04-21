<?php
require_once 'db.php';
require_once 'auth.php';
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
        .guide-content h2 { margin-top: 32px; margin-bottom: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px; border-bottom: 1px solid var(--border); padding-bottom: 12px; }
        .guide-content h3 { margin-top: 24px; margin-bottom: 12px; color: var(--text-primary); font-size: 16px; }
        .guide-content p { color: var(--text-secondary); line-height: 1.7; margin-bottom: 16px; font-size: 14px; }
        .guide-content ul { color: var(--text-secondary); line-height: 1.7; margin-bottom: 16px; padding-left: 20px; font-size: 14px; }
        .guide-content li { margin-bottom: 8px; }
        .guide-content strong { color: var(--text-primary); }
        .guide-card { padding: 24px; }
        .guide-alert { background: rgba(59, 130, 246, 0.05); border-left: 4px solid var(--blue); padding: 16px; margin: 20px 0; border-radius: 0 var(--radius-sm) var(--radius-sm) 0; }
        .guide-alert p { margin: 0; color: var(--blue); font-weight: 500; }
        .guide-icon { font-size: 24px; color: var(--blue); }
    </style>
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
            <a href="index.php" class="nav-item"><i class="bx bx-calculator"></i> Analyzer</a>
            <a href="history.php" class="nav-item"><i class="bx bx-history"></i> Past Analyses</a>
            <a href="guide.php" class="nav-item active"><i class="bx bx-book-open"></i> User Guide</a>
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

    <!-- ====== MAIN CONTENT ====== -->
    <main class="main">
        <header class="header">
            <div>
                <h1>Dashboard <span class="brand-pro">User Manual</span></h1>
                <p class="header-sub">Precision Financial Architecture for the Modern Vendor</p>
            </div>
        </header>

        <div class="content-grid" style="grid-template-columns: 1fr;">
            <section class="card guide-card">
                <div class="guide-content">
                    <p>Welcome to the Blueprint Pro Business Dashboard! Your goal isn't just to track cookies; it's to build a profitable and sustainable business. This tool is designed to act as your digital business consultant, helping you make data-driven decisions before, during, and after your events.</p>

                    <h2><i class="bx bx-compass guide-icon"></i> System Overview</h2>
                    <p>The Blueprint Pro dashboard works in three distinct phases:</p>
                    <ul>
                        <li><strong>Phase 1: Validation (Before the Event):</strong> You plan your event. You tell the system what products you want to sell, how much they cost to make, your expected operational expenses (rent, transport), and your target sales. The system then analyzes your plan and determines if it is financially viable.</li>
                        <li><strong>Phase 2: Recording Actuals (After the Event):</strong> The event is over. You return to the system and record exactly what happened. What was the <em>actual</em> amount of rent you paid? How many cookies did you <em>actually</em> sell? How many did you bring back?</li>
                        <li><strong>Phase 3: Financial Analysis (The Result):</strong> The system takes your forecast and compares it to your actuals, generating a comprehensive, professional breakdown of your performance, including your Profit & Loss statement, and benchmarking data against previous events.</li>
                    </ul>

                    <h2><i class="bx bx-list-ol guide-icon"></i> Step-by-Step Workflow</h2>
                    
                    <h3>Step 1: Creating a New Forecast</h3>
                    <p>When considering a new bazaar or expo, start by creating a new forecast on the <strong>Analyzer</strong> page.</p>
                    <ul>
                        <li><strong>Fill in Event Details:</strong> Input the event name, date, and expected customer foot traffic.</li>
                        <li><strong>Enter Fixed Costs:</strong> Booth Rental, Transport, Marketing, and Labor Setup.</li>
                        <li><strong>Add Your Products:</strong> Enter every product you plan to sell. <strong>Crucial:</strong> Ensure COGS/Unit accurately reflects the cost of ingredients and packaging per unit.</li>
                    </ul>
                    <div class="guide-alert">
                        <p><strong>The Smart Validator Output:</strong> After clicking analyze, pay attention to the Break-Even Point (BEP) and Risk Level. If it's HIGH, follow the Smart Suggestions to adjust your pricing or cost structure before committing.</p>
                    </div>

                    <h3>Step 2: Recording Post-Event Actuals</h3>
                    <p>Once the event finishes, go to <strong>Past Analyses</strong>, find your event, and click <strong>Assess Actuals</strong>. This is where you record reality.</p>
                    <ul>
                        <li><strong>Update Fixed Costs:</strong> Did the rent change? Did your staff work overtime? Enter the exact amounts paid.</li>
                        <li><strong>Inventory Tracking:</strong> Input Starting Inventory (brought) and Remaining Stock (taken home). The system will automatically calculate any unaccounted wastage/shrinkage.</li>
                    </ul>

                    <h3>Step 3: Analyzing Your Performance</h3>
                    <p>After saving, the system unlocks the Professional Post-Event Dashboard.</p>
                    <ul>
                        <li><strong>The Expo P&L:</strong> A full income statement showing Revenue, Gross Profit, Net Operating Profit, and Final Net Profit. See exactly where your ringgit went.</li>
                        <li><strong>Performance Scorecard:</strong> Check your Margin Erosion (did you lose profit margin?), Labor ROI (were you overstaffed?), and CAC Light (was your marketing effective?).</li>
                        <li><strong>Wastage Tracker:</strong> Identifies missing stock from theft, unrecorded sample giveaways, or miscounting.</li>
                        <li><strong>Benchmarking:</strong> Auto-compares this event's performance and specific product metrics against your last completed event.</li>
                    </ul>

                    <h2><i class="bx bx-bulb guide-icon" style="color: var(--yellow);"></i> Best Practices for Business Owners</h2>
                    <ul>
                        <li><strong>Don't Cheat the COGS:</strong> The entire system relies on accurate Cost of Goods Sold. Re-calculate your ingredient costs frequently. If butter prices jump 20%, update your COGS immediately.</li>
                        <li><strong>Count Diligently:</strong> Your Wastage Tracker is only as good as your physical count. Instruct your staff to perform a rigorous inventory count before loading the van and after the event closes.</li>
                        <li><strong>Read the Scorecard First:</strong> Before diving into the numbers, check the Margin Erosion and Labor ROI. They are the fastest indicators of structural problems in your business model.</li>
                        <li><strong>Use the BEP as a Target:</strong> Your Break-Even Point isn't just a number; it's your daily goal. Communicate this number to your staff: "Team, we need to hit RM 800 in sales today just to pay the rent. Let's hustle!"</li>
                    </ul>

                </div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
