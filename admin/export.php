<?php
require_once 'admin_check.php';
require_once '../config/Database.php';

$pdo = Database::getInstance()->getConnection();
$type = $_GET['type'] ?? '';

// Helper function to output CSV
function outputCSV($data, $filename)
{
    // Clean buffer to prevent corruption
    if (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $output = fopen('php://output', 'w');

    // Add Byte Order Mark (BOM) for Excel UTF-8 compatibility
    fputs($output, "\xEF\xBB\xBF");

    // Auto-generate headers from array keys of the first row
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]), ",", "\"", "\\");
    }

    foreach ($data as $row) {
        fputcsv($output, $row, ",", "\"", "\\");
    }
    fclose($output);
    exit;
}

// 1. EXPORT BOOKINGS (Detailed Financials)
if ($type === 'bookings') {
    $sql = "
        SELECT 
            s.id as Booking_ID, 
            u.name as Student_Name, 
            u.phone as Phone, 
            st.label as Seat_Number, 
            sh.name as Shift, 
            s.start_date, 
            s.end_date, 
            
            -- Financial Breakdown
            s.amount as Base_Price, 
            s.manual_discount as Manual_Discount,
            COALESCE(s.coupon_applied, '-') as Coupon_Code,
            s.final_amount as Final_Bill, 
            s.paid_amount as Paid_Amount, 
            (s.final_amount - s.paid_amount) as Balance_Due, 
            
            -- Status & Meta
            s.payment_status as Status, 
            s.payment_method as Method, 
            COALESCE(admin.name, 'Online/System') as Collected_By,
            s.notes as Admin_Notes, 
            s.created_at as Booking_Date
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN seats st ON s.seat_id = st.id
        JOIN shifts sh ON s.shift_id = sh.id
        LEFT JOIN users admin ON s.collected_by = admin.id
        ORDER BY s.created_at DESC
    ";
    $data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    outputCSV($data, 'Booking_Report_' . date('Y-m-d'));
}

// 2. EXPORT ATTENDANCE (Daily Log)
if ($type === 'attendance') {
    $sql = "
        SELECT 
            a.date as Date, 
            u.name as Student_Name, 
            st.label as Seat_Assigned, 
            TIME_FORMAT(a.check_in_time, '%h:%i %p') as Check_In, 
            a.status as Status
        FROM attendance a
        JOIN users u ON a.user_id = u.id
        -- Link to subscription to find which seat they had on that specific date
        LEFT JOIN subscriptions s ON u.id = s.user_id 
             AND a.date BETWEEN s.start_date AND s.end_date
             AND s.payment_status = 'paid'
        LEFT JOIN seats st ON s.seat_id = st.id
        ORDER BY a.date DESC, a.check_in_time ASC
    ";
    $data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    outputCSV($data, 'Attendance_Log_' . date('Y-m-d'));
}

// 3. EXPORT OUTSTANDING DUES (Debt Report)
if ($type === 'dues') {
    $sql = "
        SELECT 
            u.name as Student_Name, 
            u.phone as Phone, 
            st.label as Seat,
            s.end_date as Plan_Expiry,
            s.final_amount as Total_Bill,
            s.paid_amount as Paid_So_Far,
            (s.final_amount - s.paid_amount) as PENDING_DUE_AMOUNT
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN seats st ON s.seat_id = st.id
        WHERE (s.final_amount - s.paid_amount) > 0
        AND s.payment_status != 'rejected'
        ORDER BY PENDING_DUE_AMOUNT DESC
    ";
    $data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    outputCSV($data, 'Outstanding_Dues_' . date('Y-m-d'));
}

// Fallback
header("Location: reports.php");
exit;
