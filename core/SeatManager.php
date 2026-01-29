<?php
require_once __DIR__ . '/../config/Database.php';

class SeatManager
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // Fetch all Shift definitions
    public function getShifts()
    {
        $stmt = $this->pdo->query("SELECT * FROM shifts ORDER BY start_time ASC");
        return $stmt->fetchAll();
    }

    public function getShiftById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM shifts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getAllSeats()
    {
        $stmt = $this->pdo->query("SELECT * FROM seats ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * ---------------------------------------------------------
     * THE CORE ALGORITHM: Visual Seat Map
     * ---------------------------------------------------------
     * 1. Fetches all seats.
     * 2. Fetches all bookings for the specific date.
     * 3. Checks if the Target Shift overlaps with any Booking.
     */
    public function getSeatMap($shiftId, $date)
    {
        $shift = $this->getShiftById($shiftId);
        if (!$shift) return $this->getAllSeats();

        $seats = $this->getAllSeats();
        $sqlDate = date('Y-m-d', strtotime($date));

        // 1. Fetch Bookings
        $sql = "
            SELECT s.seat_id, s.payment_status, u.name 
            FROM subscriptions s
            JOIN shifts sh ON s.shift_id = sh.id
            JOIN users u ON s.user_id = u.id
            WHERE s.payment_status IN ('paid', 'pending') 
            AND ? BETWEEN s.start_date AND s.end_date
            AND (sh.start_time < ? AND sh.end_time > ?)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$sqlDate, $shift['end_time'], $shift['start_time']]);
        $bookings = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

        // 2. Map Status (Use &$seat to modify the actual array)
        foreach ($seats as &$seat) {
            $seatId = $seat['id'];
            $originalDbStatus = $seat['status']; // Capture DB status (0 or 1) before overwriting

            // Default State
            $seat['status'] = 'available';
            $seat['occupant_name'] = null;

            // Priority 1: Check Maintenance using the captured DB Value
            if ($originalDbStatus == 0) {
                $seat['status'] = 'maintenance';
            }
            // Priority 2: Check Bookings
            elseif (isset($bookings[$seatId])) {
                $booking = $bookings[$seatId];
                if ($booking['payment_status'] === 'pending') {
                    $seat['status'] = 'pending';
                    $seat['occupant_name'] = "Reserved";
                } else {
                    $seat['status'] = 'occupied';
                    $seat['occupant_name'] = $booking['name'];
                }
            }
        }
        unset($seat); // Clean up reference

        return $seats;
    }

    /**
     * Helper: Time Overlap Logic
     * Returns TRUE if ranges overlap.
     * Formula: (StartA < EndB) AND (EndA > StartB)
     */
    private function checkOverlap($startA, $endA, $startB, $endB)
    {
        return ($startA < $endB) && ($endA > $startB);
    }

    /**
     * ---------------------------------------------------------
     * TRANSACTIONAL: Create a Booking with Date Range Logic with Payment Method & Notes
     * ---------------------------------------------------------
     */
    public function createBooking($userId, $seatId, $shiftId, $startDate, $duration, $method = 'online', $notes = null, $adminId = null, $amountReceived = 0, $couponId = null, $manualDiscount = 0)
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Get Shift & Date Details
            $shift = $this->getShiftById($shiftId);
            if (!$shift) throw new Exception("Invalid Shift");

            $start = date('Y-m-d', strtotime($startDate));
            $end = date('Y-m-d', strtotime("$startDate +$duration months"));

            // ---------------------------------------------------------
            // 2. CRITICAL: OVERLAP CHECK (Prevent Double Booking)
            // ---------------------------------------------------------
            $checkSql = "
                SELECT COUNT(*) FROM subscriptions s
                JOIN shifts sh ON s.shift_id = sh.id
                WHERE s.seat_id = ? 
                AND s.payment_status IN ('paid', 'pending') -- Ignore rejected/expired
                
                -- Check 1: Date Range Overlap
                -- (NewStart <= ExistingEnd) AND (NewEnd >= ExistingStart)
                AND (? <= s.end_date AND ? >= s.start_date)
                
                -- Check 2: Time Overlap (The Golden Rule)
                -- (NewShiftStart < ExistingShiftEnd) AND (NewShiftEnd > ExistingShiftStart)
                AND (? < sh.end_time AND ? > sh.start_time)
            ";

            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([
                $seatId,
                $start,
                $end,                 // Date Params
                $shift['start_time'],
                $shift['end_time'] // Time Params
            ]);

            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Seat is already booked for this time slot.");
            }
            // ---------------------------------------------------------


            // 3. Calculate Financials
            $baseAmount = $shift['monthly_price'] * $duration;
            $discountAmount = 0;
            $couponCode = null;

            // Apply Coupon
            if ($couponId) {
                $couponStmt = $this->pdo->prepare("SELECT * FROM coupons WHERE id = ? AND status = 1");
                $couponStmt->execute([$couponId]);
                $coupon = $couponStmt->fetch();

                if ($coupon) {
                    $couponCode = $coupon['code'];
                    if ($coupon['type'] == 'percent') {
                        $discountAmount = $baseAmount * ($coupon['value'] / 100);
                    } else {
                        $discountAmount = $coupon['value'];
                    }
                }
            }

            // Apply Manual Discount
            $totalDiscount = $discountAmount + floatval($manualDiscount);
            if ($totalDiscount > $baseAmount) $totalDiscount = $baseAmount;

            $finalAmount = $baseAmount - $totalDiscount;

            // Determine Status
            $paidAmount = ($method === 'cash') ? floatval($amountReceived) : 0;
            // If Cash: Mark 'paid' immediately. If Online: Mark 'pending'.
            $status = ($method === 'cash') ? 'paid' : 'pending';

            // 4. Insert Booking
            $sql = "INSERT INTO subscriptions 
                    (user_id, seat_id, shift_id, start_date, end_date, amount, manual_discount, final_amount, paid_amount, payment_status, payment_method, coupon_applied, collected_by, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $seatId,
                $shiftId,
                $start,
                $end,
                $baseAmount,
                $manualDiscount,
                $finalAmount,
                $paidAmount,
                $status,
                $method,
                $couponCode,
                $adminId,
                $notes
            ]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Booking confirmed'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
