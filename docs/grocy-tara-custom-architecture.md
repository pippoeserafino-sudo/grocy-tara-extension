# Architettura custom per tara in Grocy

## Obiettivo

Questo documento descrive una estensione custom per il caso in cui Grocy ha una tara base, ma la tua esigenza richiede una logica più rigorosa, simile al comportamento di Paprika:

- acquisto normale
- stock normale
- consumo normale
- consumo pesato con tara della stock entry selezionata
- conversione base invariata

## Requisito di business

### Regole principali

1. Il prodotto mantiene la sua conversione standard.
2. L'acquisto non deve essere trasformato in una quantità fittizia con tara.
3. La tara non sostituisce la conversione del prodotto.
4. La tara si applica solo al consumo pesato.
5. La tara ha effetto sulla stock row selezionata, non sull'intero prodotto.
6. Le stock entry con amount = 0 non sono selezionabili.
7. Se la riga contiene più contenitori, la tara totale è `quantity * tara_per_unit`.
8. Il consumo pesato usa `gross - tara_total`.

## Modello dati proposto

### 1) Product metadata

La soluzione più semplice da implementare in Grocy è aggiungere un custom field sul prodotto:

- `tare_grams` (float / decimal)
- opzionale, default 0
- usato come fallback se la stock row non ha override specifico

### 2) Stock entry metadata

Se vuoi rispettare il caso con contenitori diversi per riga, la soluzione migliore è aggiungere un custom field sulla stock row:

- `tare_grams_override` (float / decimal)
- default null

### 3) Logica di applicazione

`effective_tare = stock_entry.tare_grams_override ?? product.tare_grams`

## Formula di calcolo

### Caso base

`net_remaining = gross_weight_grams - total_tare`

`total_tare = row_quantity * effective_tare`

`consumed = previous_stock_grams - net_remaining`

### Caso a più contenitori

Se la stock row tiene 2 bottiglie da 40 g ciascuna e tara 133 g per bottiglia:

- `row_quantity = 2`
- `effective_tare = 133`
- `total_tare = 2 * 133 = 266`
- `total_net = 2 * 40 = 80`
- `gross_total = 266 + 80 = 346`

## Regole di validazione

Prima di applicare il consumo pesato:

- la stock entry deve esistere
- la quantity della stock entry deve essere > 0
- il peso lordo deve essere >= tara_totale
- `net_remaining` deve essere >= 0
- `consumed` deve essere >= 0
- la stock entry deve essere nel punto stock corretto
- eventuale `location_id` deve essere verificato

## Endpoint custom consigliato

### Route

`POST /api/stock/entries/{stockEntryId}/consume-by-weight`

### Payload

```json
{
  "gross_weight_grams": 153,
  "location_id": 1,
  "spoiled": false,
  "note": "consumo pesato"
}
```

### Risposta attesa

```json
{
  "stock_entry_id": 42,
  "previous_stock_grams": 40,
  "gross_weight_grams": 153,
  "total_tare_grams": 133,
  "net_remaining_grams": 20,
  "consumed_grams": 20,
  "new_stock_grams": 20,
  "valid": true,
  "mode": "weighted_consume"
}
```

## Esempio di pseudocodice

```php
$stockEntry = getStockEntry($stockEntryId);
$product = getProduct($stockEntry['product_id']);

$rowQuantity = getRowQuantityInStockUnit($stockEntry);
$effectiveTare = $stockEntry['tare_grams_override'] ?? $product['tare_grams'] ?? 0;
$totalTare = $rowQuantity * $effectiveTare;

$grossWeight = (float) $payload['gross_weight_grams'];
$netRemaining = $grossWeight - $totalTare;

if ($stockEntry['amount'] <= 0) {
    throw new Exception('Stock entry with quantity 0 is not selectable');
}

if ($netRemaining < 0) {
    throw new Exception('Gross weight is lower than the total tare');
}

$consumed = $stockEntry['amount'] - $netRemaining;

if ($consumed < 0) {
    throw new Exception('Consumed amount cannot be negative');
}

consumeStockEntry($stockEntryId, $consumed);
```

## UI workflow consigliato

Nel modulo di consumo stock:

- seleziona la stock row da consumare
- mostra la quantity disponibile in stock unit
- mostra la tara applicata alla riga
- chiede il peso lordo
- mostra il netto risultante
- applica il consumo solo se la validazione passa

## Eventi da evitare

Non fare mai quanto segue:

- non trasformare la conversione prodotto da `1 bottiglia = 40 g` a `1 bottiglia = 173 g`
- non fare la tara sul prodotto senza distinguere la stock row
- non usare la tara per il normale consumo in grammi
- non selezionare stock entry a quantità zero
- non usare la tara globale senza verificare la quantità della riga

## Casi di test

1. stock 40g, tara 133g, gross 153g -> risultato 20g consumati, 20g residui
2. stock 80g, row_quantity 2, tara 133g, gross 346g -> risultato 0 consumati, 80g residui
3. stock 40g, gross 100g, tara 133g -> invalid, gross too low
4. stock 0g -> non selezionabile
5. acquisto normale -> invariato
6. consumo diretto normale -> invariato

## Conclusione

La tua esigenza non è la tara generica di Grocy, ma una estensione custom della logica di consumo pesato che usa la tara della stock row selezionata, lasciando invariata la conversione base del prodotto.

Questo è il modello consigliato per rispettare il comportamento del caso Paprika e tutte le regole che hai descritto.
