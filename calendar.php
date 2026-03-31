<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireRole(['admin', 'receptionist', 'stylist']); // Staff only

// Calculate Month/Year
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('m');
$year = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');

// Adjust bounds
if ($month > 12) { $month = 1; $year++; }
if ($month < 1) { $month = 12; $year--; }

$firstDayOfMonth = sprintf("%04d-%02d-01", $year, $month);
$lastDayOfMonth = date("Y-m-t", strtotime($firstDayOfMonth));

// Fetch all appointments for the month
$stmt = $pdo->prepare("
    SELECT a.id, a.appointment_date, a.appointment_time, 
           c.name as client_name, s.name as service_name, u.name as stylist_name,
           a.status
    FROM appointments a
    JOIN clients c ON a.client_id = c.id
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.stylist_id = u.id
    WHERE a.appointment_date BETWEEN ? AND ?
    AND a.status != 'cancelled'
    ORDER BY a.appointment_time ASC
");
$stmt->execute([$firstDayOfMonth, $lastDayOfMonth]);
$appointments = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC); 
// Fetch group will group by the first column if we did that, but we need to group by date in PHP
$grouped_appointments = [];
foreach($appointments as $app) {
    // We didn't fetch_group properly for date, so manual group:
    $dateKey = $app['appointment_date'];
    if(!isset($grouped_appointments[$dateKey])) {
        $grouped_appointments[$dateKey] = [];
    }
    $grouped_appointments[$dateKey][] = $app;
}

$monthName = date('F', mktime(0, 0, 0, $month, 10));
$daysInMonth = date('t', mktime(0, 0, 0, $month, 1));
$dayOfWeek = date('w', mktime(0, 0, 0, $month, 1)); // 0=Sun, 6=Sat

include 'includes/header.php';
?>

<style>
.calendar { width: 100%; border-collapse: collapse; background: var(--color-white); box-shadow: var(--shadow-sm); }
.calendar th { background: var(--color-black); color: var(--color-white); padding: 10px; text-align: center; border: 1px solid #333; width: 14.28%; }
.calendar td { border: 1px solid #ddd; height: 120px; vertical-align: top; padding: 5px; position: relative; }
.calendar .day-number { font-weight: bold; margin-bottom: 5px; display: block; }
.calendar .today { background-color: rgba(212, 175, 55, 0.1); }
.calendar .today .day-number { color: var(--color-primary); font-size: 1.2rem; }
.app-badge { display: block; font-size: 0.75rem; background: var(--color-light-grey); border-left: 3px solid var(--color-primary); padding: 3px 5px; margin-bottom: 3px; border-radius: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; transition: background 0.2s; }
.app-badge:hover { background: #eee; }
.app-completed { border-left-color: #28a745; text-decoration: line-through; color: #888; }
</style>

<div class="container py-2">
    <div class="flex flex-between mb-2">
        <h2>Schedule Calendar</h2>
        <div>
            <a href="?m=<?php echo $month-1; ?>&y=<?php echo $year; ?>" class="btn btn-outline-gold">&larr; Prev</a>
            <span style="font-size: 1.2rem; font-weight: bold; margin: 0 15px;"><?php echo $monthName . ' ' . $year; ?></span>
            <a href="?m=<?php echo $month+1; ?>&y=<?php echo $year; ?>" class="btn btn-outline-gold">Next &rarr;</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="calendar">
            <thead>
                <tr>
                    <th>Sun</th>
                    <th>Mon</th>
                    <th>Tue</th>
                    <th>Wed</th>
                    <th>Thu</th>
                    <th>Fri</th>
                    <th>Sat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                <?php
                // Pad empty cells before the first day
                for ($i = 0; $i < $dayOfWeek; $i++) {
                    echo "<td></td>";
                }

                $currentDay = 1;
                $col = $dayOfWeek;

                while ($currentDay <= $daysInMonth) {
                    if ($col == 7) {
                        echo "</tr><tr>";
                        $col = 0;
                    }
                    
                    $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $currentDay);
                    $isToday = ($dateStr === date('Y-m-d')) ? 'today' : '';
                    
                    echo "<td class='$isToday'><span class='day-number'>$currentDay</span>";
                    
                    if (isset($grouped_appointments[$dateStr])) {
                        // Limit to 4 to prevent massive cells, show +X more
                        $count = 0;
                        foreach($grouped_appointments[$dateStr] as $app) {
                            if($count >= 4) {
                                $more = count($grouped_appointments[$dateStr]) - 4;
                                echo "<div class='app-badge' style='text-align:center; font-weight:bold;'>+$more more</div>";
                                break;
                            }
                            $timeStr = date('g:ia', strtotime($app['appointment_time']));
                            $client = htmlspecialchars($app['client_name']);
                            $stylist = htmlspecialchars($app['stylist_name']);
                            $compClass = ($app['status'] == 'completed') ? 'app-completed' : '';
                            
                            echo "<div class='app-badge $compClass' title='$client with $stylist - {$app['status']}'>";
                            echo "<strong>$timeStr</strong> $client ($stylist)";
                            echo "</div>";
                            $count++;
                        }
                    }
                    
                    echo "</td>";
                    
                    $currentDay++;
                    $col++;
                }

                // Pad remaining cells
                while ($col < 7 && $col > 0) {
                    echo "<td></td>";
                    $col++;
                }
                ?>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
