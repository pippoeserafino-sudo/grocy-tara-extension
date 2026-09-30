<?php
/**
 * Minimal weighted-consume logic for the Paprika-style case.
 *
 * Business rules:
 * - purchase flow stays unchanged
 * - stock conversion stays unchanged
 * - only the weighted consumption path uses the tare rule
 * - tara applies to the selected stock row
 */

define('TARA_CASE_OK', 'ok');

function paprikaWeightedConsumeCase(float $previousStock, float $tarePerUnit, float $grossWeight, float $rowQuantity = 1.0): array
{
    if ($previousStock <= 0) {
        throw new InvalidArgumentException('Stock entry with quantity 0 is not selectable.');
    }

    $totalTare = $tarePerUnit * $rowQuantity;
    $netRemaining = $grossWeight - $totalTare;

    if ($netRemaining < 0) {
        throw new InvalidArgumentException('Gross weight is lower than the total tare.');
    }

    $consumed = $previousStock - $netRemaining;

    if ($consumed < 0) {
        throw new InvalidArgumentException('Consumed amount cannot be negative.');
    }

    return [
        'previous_stock' => $previousStock,
        'row_quantity' => $rowQuantity,
        'tare_per_unit' => $tarePerUnit,
        'total_tare' => $totalTare,
        'gross_weight' => $grossWeight,
        'net_remaining' => $netRemaining,
        'consumed' => $consumed,
        'new_stock' => $previousStock - $consumed,
        'status' => TARA_CASE_OK,
    ];
}

// Example: Paprika scenario
$case = paprikaWeightedConsumeCase(40.0, 133.0, 153.0, 1.0);
print_r($case);
