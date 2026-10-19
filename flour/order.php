<?php
/**
 * ==========================================================================
 * flour/order.php  -  ENDPOINT:  POST /flour/order
 * ==========================================================================
 * Places a flour order for a supplier.
 *
 *   - quantity_kg     DECIMAL(12,3)
 *   - unit_price_kobo integer (money in kobo, 1 Naira = 100 kobo)
 *   - total_cost_kobo = quantity x unit price, also stored in kobo
 *   - delivered_at    NULL while the flour is still on the way
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    flash('This endpoint only accepts POST requests.', 'warning');
    redirect('flour/');
}

$errors = [];

$supplierId    = input_int('supplier_id', $errors, 1, 100000000, 'Supplier');
$quantityKg    = input_decimal('quantity_kg', $errors, 0.001, 1000000, 'Quantity');
$unitPriceKobo = input_int('unit_price_kobo', $errors, 0, 100000000, 'Price per kg');
$orderedAt     = input_datetime_local('ordered_at', $errors, true, 'Ordered at');
$deliveredAt   = input_datetime_local('delivered_at', $errors, false, 'Delivered at');

// ---- business rule: the supplier must exist -------------------------
if ($supplierId !== null) {
    $exists = (int) scalar('SELECT COUNT(*) FROM suppliers WHERE id = ?', [$supplierId]);
    if ($exists === 0) {
        $errors['supplier_id'] = 'That supplier does not exist.';
    }
}

// ---- business rule: you cannot deliver before ordering --------------
if ($orderedAt !== null && $deliveredAt !== null && strtotime($deliveredAt) < strtotime($orderedAt)) {
    $errors['delivered_at'] = 'The delivery date cannot be before the order date.';
}

if ($errors) {
    set_form_errors($errors);
    remember_old($_POST);
    flash('Flour order was NOT saved - please check the form.', 'danger');
    redirect('flour/');
}

// ---- money calculation (integer kobo, never a float in the DB) ------
$totalCostKobo = (int) round($quantityKg * $unitPriceKobo);

stream_query(
    'INSERT INTO flour_orders
        (supplier_id, quantity_kg, unit_price_kobo, total_cost_kobo, ordered_at, delivered_at, status)
     VALUES (?, ?, ?, ?, ?, ?, ?)',
    [
        $supplierId,
        $quantityKg,
        $unitPriceKobo,
        $totalCostKobo,
        $orderedAt,
        $deliveredAt,
        $deliveredAt === null ? 'ordered' : 'received',
    ]
);

forget_old();
flash(sprintf(
    'Flour order placed: %s kg from supplier #%s for %s (total %s).',
    number_format($quantityKg, 3),
    $supplierId,
    naira($unitPriceKobo) . '/kg',
    naira($totalCostKobo)
));

redirect('flour/');
