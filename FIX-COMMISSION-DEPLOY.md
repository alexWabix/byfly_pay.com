# Исправление расчета комиссий - Инструкция по деплою

## Проблема

При попытке вручную выставить статус "полностью оплачена" возникала ошибка:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'event_type' in 'field list'
```

Также были расхождения в расчете комиссий между созданием платежа и обработкой платежей.

## Решение

### 1. Исправлена ошибка с логированием

**Файл:** `api/endpoints/admin.php`

**Что исправлено:**
- Заменено `event_type` на `action` в логировании транзакций
- Заменено `event_data` на `request_data`
- Добавлены правильные поля согласно схеме `transaction_logs`

### 2. Унифицирован расчет комиссий

**Файл:** `api/includes/Transaction.php`

**Что добавлено:**
- Новый метод `calculateCommission()` - единый для всей системы
- Гарантирует идентичный расчет при создании платежа и при обработке
- Округление до 2 знаков после запятой для точности

**Изменения:**
- Метод `create()` теперь использует `calculateCommission()`
- Метод `processKaspiPayment()` теперь использует `calculateCommission()`

### 3. Исправлены CRON скрипты

**Файлы:**
- `api/cron/check-active-payments.php`
- `api/cron/recalculate-loan-commissions.php`

**Что исправлено:**
- Убраны жестко закодированные проценты (14%, 1%)
- Проценты теперь берутся из базы данных (`payment_methods`)
- Гарантирована консистентность с основной логикой

### 4. Добавлена миграция базы данных

**Файл:** `database/fix-commission-calculation.sql`

Миграция проверяет и добавляет все необходимые поля:
- Поля для операций терминалов
- Поля для деталей транзакций
- Поля для информации о клиентах

## Инструкция по деплою

### Шаг 1: Бэкап базы данных

```bash
mysqldump -u payments -p payments > backup_before_commission_fix_$(date +%Y%m%d_%H%M%S).sql
```

### Шаг 2: Применить миграцию базы данных

```bash
mysql -u payments -p payments < database/fix-commission-calculation.sql
```

Миграция автоматически:
- Проверит наличие всех полей
- Добавит недостающие поля (если их нет)
- Покажет текущие значения комиссий

### Шаг 3: Обновить код на сервере

```bash
# Скопировать измененные файлы:
# - api/endpoints/admin.php
# - api/includes/Transaction.php
# - api/cron/check-active-payments.php
# - api/cron/recalculate-loan-commissions.php
# - database/migration-terminal-operations.sql
```

Или используйте скрипт сборки:

```bash
./build.sh
# Распакуйте созданный deploy.zip на сервер
```

### Шаг 4: Проверить настройки комиссий

Убедитесь, что в таблице `payment_methods` установлены правильные проценты:

```sql
SELECT code, name, 
       commission_percent, 
       credit_commission_percent, 
       installment_commission_percent,
       add_commission_to_amount
FROM payment_methods
ORDER BY sort_order;
```

**Ожидаемые значения:**
- `kaspi_gold`: commission_percent = 1.00
- `kaspi_red`: commission_percent = 1.00
- `kaspi_credit`: credit_commission_percent = 14.00
- `kaspi_installment_12`: installment_commission_percent = 14.00
- `kaspi_installment_24`: installment_commission_percent = 14.00

Если нужно изменить, используйте:

```sql
UPDATE payment_methods SET commission_percent = 1.00 WHERE code = 'kaspi_gold';
UPDATE payment_methods SET commission_percent = 1.00 WHERE code = 'kaspi_red';
UPDATE payment_methods SET credit_commission_percent = 14.00 WHERE code = 'kaspi_credit';
UPDATE payment_methods SET installment_commission_percent = 14.00 WHERE code = 'kaspi_installment_12';
UPDATE payment_methods SET installment_commission_percent = 14.00 WHERE code = 'kaspi_installment_24';
```

### Шаг 5: Тестирование

1. **Проверить вручную установку статуса "оплачено":**
   - Зайти в админку
   - Найти транзакцию со статусом `partially_paid`
   - Нажать кнопку "Отметить как оплачено"
   - Убедиться, что ошибки нет и статус изменился

2. **Проверить создание нового платежа:**
   - Создать тестовый платеж через API
   - Проверить, что комиссия рассчитана правильно
   - Сумма должна совпадать с ожидаемой

3. **Проверить обработку платежа через Kaspi:**
   - Инициировать платеж через терминал
   - Проверить, что комиссия та же, что при создании
   - Убедиться, что нет расхождений

4. **Проверить CRON скрипт:**
   ```bash
   php api/cron/check-active-payments.php
   ```
   - Убедиться, что комиссии берутся из БД
   - Проверить, что расчеты корректны

## Что изменилось в логике

### До исправления

1. **При создании платежа:**
   - Комиссия рассчитывалась на основе payment_method_id
   - Использовалась своя логика

2. **При обработке платежа:**
   - Комиссия рассчитывалась заново
   - Могла отличаться от созданной

3. **В CRON скриптах:**
   - Жестко закодированные проценты (14%, 1%)
   - Могли не соответствовать настройкам в БД

### После исправления

1. **Единый метод `calculateCommission()`:**
   - Один и тот же расчет везде
   - Гарантия консистентности
   - Округление до 2 знаков

2. **Проценты из базы данных:**
   - Все проценты берутся из `payment_methods`
   - Легко изменить в одном месте
   - Автоматически применяется везде

3. **Правильная логика:**
   - Для кредита/рассрочки: ВСЕГДА добавляем комиссию
   - Для обычных: только если `add_commission_to_amount = 1`
   - Одинаково работает при создании и обработке

## Формула расчета

```
Базовая логика:
1. Определяем процент комиссии из payment_methods
2. Проверяем, нужно ли добавлять комиссию:
   - Если has_credit = 1 → всегда добавляем
   - Если has_installment = 1 → всегда добавляем
   - Если add_commission_to_amount = 1 → добавляем
3. Расчет:
   commission = ROUND(amount * (percent / 100), 2)
   total = ROUND(amount + commission, 2)
```

## Примеры расчета

### Пример 1: Kaspi Gold (обычная оплата)
```
Базовая сумма: 10,000 ₸
Процент: 1%
add_commission_to_amount = 1

Комиссия: 10,000 * 0.01 = 100 ₸
Итого к оплате: 10,100 ₸
```

### Пример 2: Kaspi Credit (кредит)
```
Базовая сумма: 10,000 ₸
Процент: 14%
has_credit = 1 (ВСЕГДА добавляем комиссию)

Комиссия: 10,000 * 0.14 = 1,400 ₸
Итого к оплате: 11,400 ₸
```

### Пример 3: Kaspi Installment 12 (рассрочка)
```
Базовая сумма: 10,000 ₸
Процент: 14%
has_installment = 1 (ВСЕГДА добавляем комиссию)

Комиссия: 10,000 * 0.14 = 1,400 ₸
Итого к оплате: 11,400 ₸
```

## Проверка корректности

После деплоя проверьте логи:

```bash
# Проверить логи PHP
tail -f /var/log/php-fpm.log

# Проверить логи MySQL
tail -f /var/log/mysql/error.log

# Проверить логи приложения
tail -f /path/to/your/app/logs/
```

## Откат (если что-то пошло не так)

```bash
# Восстановить базу данных из бэкапа
mysql -u payments -p payments < backup_before_commission_fix_YYYYMMDD_HHMMSS.sql

# Вернуть старые файлы (если делали резервную копию)
# ...
```

## Контакты

При возникновении проблем обращаться к администратору ByFly Travel.

---
**Дата:** 27.12.2025  
**Версия:** 2.1  
**Автор:** Cursor AI Assistant




