<?php
/**
 * TubigKo — data access layer.
 *
 * Loads everything the page templates need from MySQL and shapes it into
 * the same $GALLONS / $CUSTOMERS / $DELIVERIES / $PAYMENTS / $NOTIFICATIONS
 * / $PAYMENT_METHODS arrays the original frontend-only demo used, so the
 * page markup barely has to change. Also exposes small CRUD helper
 * functions used by the form handlers at the top of each page.
 */

require_once __DIR__ . '/db.php';

$APP_NAME = "TubigKo Water Refilling Station System";

// ---------------------------------------------------------------------
// Display helpers
// ---------------------------------------------------------------------

function peso($n) { return "&#8369;" . number_format((float)$n, 2); }

function badge_class($status)
{
    $s = strtolower($status);
    if (in_array($s, ["paid", "delivered", "active", "enabled", "received by customer"])) return "badge badge--ok";
    if (in_array($s, ["pending", "ongoing", "confirmed", "preparing", "out for delivery"])) return "badge badge--warn";
    if (in_array($s, ["failed", "inactive", "disabled", "cancelled", "refunded"])) return "badge badge--bad";
    return "badge";
}

function delivery_stage_label(string $status): string
{
    return match ($status) {
        'pending'          => 'Preparing',
        'confirmed'        => 'Confirmed',
        'out_for_delivery' => 'Out for delivery',
        'delivered'        => 'Received by customer',
        'cancelled'        => 'Cancelled',
        default            => ucfirst($status),
    };
}

function delivery_group_label(string $status): string
{
    return match ($status) {
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        default     => 'Ongoing',
    };
}

// ---------------------------------------------------------------------
// Loaders — build the arrays the templates iterate over
// ---------------------------------------------------------------------

function load_gallons(): array
{
    $rows = db_query('SELECT * FROM gallons ORDER BY name ASC');
    return array_map(function ($g) {
        return [
            'db_id' => (int)$g['id'],
            'id'    => $g['code'],
            'name'  => $g['name'],
            'type'  => $g['water_type'],
            'size'  => $g['gallon_size'],
            'price' => (float)$g['price_per_gallon'],
            'stock' => (int)$g['stock'],
            'desc'  => $g['description'],
            'status' => $g['status'],
        ];
    }, $rows);
}

function load_customers(): array
{
    $rows = db_query(
        "SELECT u.*, COALESCE(o.order_count, 0) AS order_count
         FROM users u
         LEFT JOIN (SELECT customer_id, COUNT(*) AS order_count FROM deliveries GROUP BY customer_id) o
           ON o.customer_id = u.id
         WHERE u.role = 'customer'
         ORDER BY u.created_at DESC"
    );
    return array_map(function ($c) {
        return [
            'db_id'   => (int)$c['id'],
            'id'      => sprintf('CUST-%04d', $c['id']),
            'name'    => $c['full_name'],
            'phone'   => $c['phone'],
            'email'   => $c['email'],
            'address' => $c['address'],
            'joined'  => substr($c['created_at'], 0, 10),
            'orders'  => (int)$c['order_count'],
            'status'  => ucfirst($c['status']),
        ];
    }, $rows);
}

function load_deliveries(?int $customerId = null): array
{
    $sql = "SELECT d.*, u.full_name AS customer_name
            FROM deliveries d
            JOIN users u ON u.id = d.customer_id";
    $params = [];
    if ($customerId !== null) {
        $sql .= " WHERE d.customer_id = ?";
        $params[] = $customerId;
    }
    $sql .= " ORDER BY d.created_at DESC";
    $rows = db_query($sql, $params);

    $out = [];
    foreach ($rows as $d) {
        $items = db_query('SELECT gallon_name, quantity FROM delivery_items WHERE delivery_id = ?', [$d['id']]);
        $itemsLabel = implode(', ', array_map(fn($i) => $i['quantity'] . ' x ' . $i['gallon_name'], $items));

        $out[] = [
            'db_id'      => (int)$d['id'],
            'id'         => $d['order_code'],
            'customer'   => $d['customer_name'],
            'customer_id'=> (int)$d['customer_id'],
            'address'    => $d['delivery_address'],
            'items'      => $itemsLabel !== '' ? $itemsLabel : '—',
            'rider'      => $d['rider'] ?: 'Not yet assigned',
            'scheduled'  => date('Y-m-d', strtotime($d['delivery_date'])) . ' ' . $d['delivery_time'],
            'notes'      => $d['notes'],
            'total'      => (float)$d['total_amount'],
            'raw_status' => $d['status'],
            'status'     => delivery_group_label($d['status']),
            'stage'      => delivery_stage_label($d['status']),
        ];
    }
    return $out;
}

function load_payment_methods(bool $enabledOnly = false): array
{
    $sql = 'SELECT * FROM payment_methods';
    if ($enabledOnly) {
        $sql .= " WHERE status = 'enabled'";
    }
    $sql .= ' ORDER BY id ASC';
    $rows = db_query($sql);
    return array_map(function ($m) {
        return [
            'db_id'    => (int)$m['id'],
            'id'       => $m['code'],
            'method'   => $m['method'],
            'provider' => $m['provider'],
            'fee'      => (float)$m['fee'],
            'status'   => ucfirst($m['status']),
        ];
    }, $rows);
}

function update_payment_method_status(int $id, string $status, ?string &$error = null): bool
{
    if (!in_array($status, ['enabled', 'disabled'], true)) {
        $error = 'Invalid payment method status.';
        return false;
    }

    return db_exec('UPDATE payment_methods SET status = ? WHERE id = ?', [$status, $id], $error);
}

function load_payments(?int $customerId = null): array
{
    $sql = "SELECT p.*, u.full_name AS customer_name, d.order_code
            FROM payments p
            JOIN users u ON u.id = p.customer_id
            JOIN deliveries d ON d.id = p.delivery_id";
    $params = [];
    if ($customerId !== null) {
        $sql .= " WHERE p.customer_id = ?";
        $params[] = $customerId;
    }
    $sql .= " ORDER BY p.created_at DESC";
    $rows = db_query($sql, $params);

    return array_map(function ($p) {
        return [
            'db_id'      => (int)$p['id'],
            'id'         => $p['payment_code'],
            'customer'   => $p['customer_name'],
            'order'      => $p['order_code'],
            'delivery_id'=> (int)$p['delivery_id'],
            'method'     => $p['payment_method'],
            'reference'  => $p['reference_number'],
            'amount'     => (float)$p['amount'],
            'date'       => substr($p['payment_date'] ?? $p['created_at'], 0, 10),
            'raw_status' => $p['status'],
            'status'     => ucfirst($p['status']),
        ];
    }, $rows);
}

function load_notifications(?int $userId = null, bool $includeBroadcast = true): array
{
    if ($userId !== null) {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        if ($includeBroadcast) {
            $sql = "SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL";
        }
    } else {
        $sql = "SELECT * FROM notifications";
        $params = [];
    }
    $sql .= " ORDER BY created_at DESC";
    $rows = db_query($sql, $params);

    return array_map(function ($n) {
        return [
            'db_id'   => (int)$n['id'],
            'id'      => $n['notif_code'],
            'user_id' => $n['user_id'] !== null ? (int)$n['user_id'] : null,
            'title'   => $n['title'],
            'message' => $n['message'],
            'audience'=> $n['audience'],
            'date'    => $n['created_at'],
            'type'    => $n['type'],
            'read'    => (bool)$n['is_read'],
        ];
    }, $rows);
}

// ---------------------------------------------------------------------
// Code generators (human-friendly IDs shown in the UI)
// ---------------------------------------------------------------------

function next_code(string $prefix, string $table, string $column): string
{
    $row = db_one("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '-%']);
    if ($row) {
        $parts = explode('-', $row[$column]);
        $numPart = end($parts);
        $next = (int)$numPart + 1;
        $width = strlen($numPart);
        return $prefix . '-' . str_pad((string)$next, $width, '0', STR_PAD_LEFT);
    }
    // No existing rows with this prefix yet — start a fresh 3-digit sequence.
    return $prefix . '-' . str_pad('1', 3, '0', STR_PAD_LEFT);
}

// ---------------------------------------------------------------------
// Mutations — used by the POST handlers at the top of each page
// ---------------------------------------------------------------------

function create_gallon(array $in, ?string &$error = null): bool
{
    $code = next_code('G', 'gallons', 'code');
    return db_exec(
        'INSERT INTO gallons (code, name, water_type, gallon_size, price_per_gallon, stock, description) VALUES (?,?,?,?,?,?,?)',
        [$code, $in['name'], $in['type'], $in['size'], $in['price'], $in['stock'], $in['description']],
        $error
    );
}

function update_customer_status(int $id, string $status, ?string &$error = null): bool
{
    return db_exec('UPDATE users SET status = ? WHERE id = ? AND role = "customer"', [$status, $id], $error);
}

function delete_customer(int $id, ?string &$error = null): bool
{
    $active = db_one("SELECT COUNT(*) AS c FROM deliveries WHERE customer_id = ? AND status NOT IN ('delivered','cancelled')", [$id]);
    if ($active && (int)$active['c'] > 0) {
        $error = 'This customer has active deliveries and cannot be deleted. Deactivate the account instead.';
        return false;
    }
    return db_exec('DELETE FROM users WHERE id = ? AND role = "customer"', [$id], $error);
}

function update_delivery_status(int $id, string $status, ?string $rider = null, ?string &$error = null): bool
{
    if ($rider !== null && $rider !== '') {
        return db_exec('UPDATE deliveries SET status = ?, rider = ? WHERE id = ?', [$status, $rider, $id], $error);
    }
    return db_exec('UPDATE deliveries SET status = ? WHERE id = ?', [$status, $id], $error);
}

function update_payment_status(int $id, string $status, ?string &$error = null): bool
{
    $paymentDate = $status === 'paid' ? date('Y-m-d H:i:s') : null;
    return db_exec('UPDATE payments SET status = ?, payment_date = COALESCE(?, payment_date) WHERE id = ?', [$status, $paymentDate, $id], $error);
}

function create_notification(?int $userId, string $audience, string $title, string $message, string $type, ?string &$error = null): bool
{
    $code = next_code('N', 'notifications', 'notif_code');
    return db_exec(
        'INSERT INTO notifications (notif_code, user_id, audience, title, message, type) VALUES (?,?,?,?,?,?)',
        [$code, $userId, $audience, $title, $message, $type],
        $error
    );
}

function mark_notifications_read(int $userId, ?int $singleId = null): void
{
    if ($singleId !== null) {
        db_exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)', [$singleId, $userId], $err);
    } else {
        db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL', [$userId], $err);
    }
}

/**
 * Create a delivery request (order) with its line items and an initial
 * payment record, from the customer checkout form. Recalculates all
 * money values on the server — never trusts totals posted by the browser.
 */
function create_order(int $customerId, string $address, string $date, string $time, ?string $notes, array $cartItems, string $paymentMethod, ?string $reference, ?string &$error = null): ?string
{
    if (empty($cartItems)) {
        $error = 'Your cart is empty. Please select at least one gallon.';
        return null;
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Recalculate every line against the authoritative gallons table.
        $lines = [];
        $total = 0.0;
        foreach ($cartItems as $item) {
            $gallonId = (int)($item['id'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            $gallon = db_one('SELECT * FROM gallons WHERE id = ?', [$gallonId]);
            if (!$gallon) {
                continue;
            }
            $lineTotal = $qty * (float)$gallon['price_per_gallon'];
            $total += $lineTotal;
            $lines[] = [
                'gallon_id'   => $gallon['id'],
                'gallon_name' => $gallon['name'],
                'qty'         => $qty,
                'price'       => (float)$gallon['price_per_gallon'],
                'line_total'  => $lineTotal,
            ];
        }

        if (empty($lines)) {
            $pdo->rollBack();
            $error = 'None of the selected gallons could be found.';
            return null;
        }

        $orderCode = next_code('ORD', 'deliveries', 'order_code');
        $stmt = $pdo->prepare(
            'INSERT INTO deliveries (order_code, customer_id, delivery_address, delivery_date, delivery_time, notes, total_amount, status) VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$orderCode, $customerId, $address, $date, $time, $notes, $total, 'pending']);
        $deliveryId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO delivery_items (delivery_id, gallon_id, gallon_name, quantity, price_per_gallon, total_amount) VALUES (?,?,?,?,?,?)'
        );
        foreach ($lines as $l) {
            $itemStmt->execute([$deliveryId, $l['gallon_id'], $l['gallon_name'], $l['qty'], $l['price'], $l['line_total']]);
        }

        $paymentCode = next_code('PAY', 'payments', 'payment_code');
        $payStmt = $pdo->prepare(
            'INSERT INTO payments (payment_code, customer_id, delivery_id, amount, payment_method, reference_number, status) VALUES (?,?,?,?,?,?,?)'
        );
        $payStmt->execute([$paymentCode, $customerId, $deliveryId, $total, $paymentMethod, $reference, 'pending']);

        $notifCode = next_code('N', 'notifications', 'notif_code');
        $notifStmt = $pdo->prepare(
            'INSERT INTO notifications (notif_code, user_id, audience, title, message, type) VALUES (?,?,?,?,?,?)'
        );
        $notifStmt->execute([
            $notifCode,
            $customerId,
            'Customer',
            'Delivery request submitted',
            "Your order $orderCode was submitted and is being prepared.",
            'Delivery',
        ]);

        $pdo->commit();
        return $orderCode;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('TubigKo create_order failed: ' . $e->getMessage());
        $error = 'Could not submit your order. Please try again.';
        return null;
    }
}
