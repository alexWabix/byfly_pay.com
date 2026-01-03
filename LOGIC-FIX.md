# ПРАВИЛЬНАЯ ЛОГИКА ОБРАБОТКИ ПЛАТЕЖЕЙ

## Простой алгоритм:

```php
// 1. Извлекаем данные
$originalAmount = trans['original_amount'];  // Базовая сумма
$previousNet = trans['actual_amount_received']; // Уже получено чистыми
$currentGross = actualAmount from terminal; // Текущая оплата
$productType = from terminal response;

// 2. Вычисляем чистую сумму от текущей оплаты
if (ProductType == Loan/Credit/Installment) {
    $bankCommission = currentGross * 0.14;
    $currentNet = currentGross - bankCommission;
} else {
    $currentNet = currentGross;
}

// 3. Суммируем
$totalNet = previousNet + currentNet;

// 4. Определяем статус
if (totalNet >= originalAmount) {
    status = 'paid';
} else {
    status = 'partially_paid';
    remaining = originalAmount - totalNet;
}

// 5. Записываем
UPDATE transactions SET
    actual_amount_received = totalNet,  // ЧИСТЫМИ
    paid_amount = (предыдущий paid_amount + currentGross), // Всего оплачено клиентом
    commission_amount = bankCommission,
    remaining_amount = max(0, remaining),
    needs_additional_payment = (remaining > 10 ? 1 : 0),
    status = status
```

Это простая и понятная логика!




