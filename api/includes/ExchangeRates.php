<?php
/**
 * Exchange Rates Management Class
 * Работа с курсами валют от Национального банка РК
 */

class ExchangeRates {
    private $db;
    private $apiUrl = 'https://nationalbank.kz/rss/get_rates.cfm?fdate=';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Получение курса для конвертации
     */
    public function getRate($fromCurrency, $toCurrency) {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $rate = $this->db->fetch(
            "SELECT rate FROM exchange_rates 
             WHERE from_currency = ? AND to_currency = ? 
             ORDER BY fetched_at DESC LIMIT 1",
            [$fromCurrency, $toCurrency]
        );

        return $rate ? floatval($rate['rate']) : null;
    }

    /**
     * Конвертация суммы из одной валюты в другую
     */
    public function convert($amount, $fromCurrency, $toCurrency) {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        // Если конвертируем ИЗ KZT в другую валюту
        if ($fromCurrency === 'KZT') {
            $rate = $this->getRate('KZT', $toCurrency);
            if ($rate) {
                return $amount * $rate;
            }
        }

        // Если конвертируем В KZT из другой валюты
        if ($toCurrency === 'KZT') {
            $rate = $this->getRate('KZT', $fromCurrency);
            if ($rate) {
                return $amount / $rate;
            }
        }

        // Конвертация через KZT (базовая валюта)
        $rateFrom = $this->getRate('KZT', $fromCurrency);
        $rateTo = $this->getRate('KZT', $toCurrency);
        
        if ($rateFrom && $rateTo) {
            $amountInKZT = $amount / $rateFrom;
            return $amountInKZT * $rateTo;
        }

        return $amount; // Если нет курса, возвращаем оригинал
    }

    /**
     * Обновление курсов валют (вызывается по cron)
     */
    public function updateRates() {
        try {
            $date = date('d.m.Y');
            $url = $this->apiUrl . $date;

            // Получаем данные от API
            $xml = @file_get_contents($url);
            
            if (!$xml) {
                error_log('Failed to fetch exchange rates from nationalbank.kz');
                return false;
            }

            $data = simplexml_load_string($xml);
            
            if (!$data) {
                error_log('Failed to parse XML from nationalbank.kz');
                return false;
            }

            $updated = 0;

            // Парсим курсы
            foreach ($data->item as $item) {
                $currencyCode = (string)$item->title;
                $rateValue = (float)$item->description; // Курс
                $quant = (int)$item->quant; // Количество единиц (обычно 1, но для UZS = 100)

                // Пропускаем если нет данных
                if (!$currencyCode || !$rateValue || !$quant) continue;

                // Вычисляем курс К тенге с учетом количества
                // Пример UZS: 100 UZS = 4.24 KZT, значит 1 UZS = 4.24/100 = 0.0424 KZT
                $rateToKZT = $rateValue / $quant;
                
                // Вычисляем курс ИЗ тенге
                // 1 KZT = 1 / 0.0424 = 23.585 UZS
                $rateFromKZT = 1 / $rateToKZT;

                // Обновляем/вставляем курс
                $existing = $this->db->fetch(
                    "SELECT id FROM exchange_rates WHERE from_currency = 'KZT' AND to_currency = ?",
                    [$currencyCode]
                );

                if ($existing) {
                    $this->db->update(
                        'exchange_rates',
                        [
                            'rate' => $rateFromKZT,
                            'fetched_at' => date('Y-m-d H:i:s')
                        ],
                        'id = ?',
                        [$existing['id']]
                    );
                } else {
                    $this->db->insert('exchange_rates', [
                        'from_currency' => 'KZT',
                        'to_currency' => $currencyCode,
                        'rate' => $rateFromKZT,
                        'fetched_at' => date('Y-m-d H:i:s')
                    ]);
                }

                $updated++;
            }

            error_log("Exchange rates updated: $updated currencies");
            return true;

        } catch (Exception $e) {
            error_log('Exchange rates update error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получение всех актуальных курсов
     */
    public function getAllRates() {
        return $this->db->fetchAll(
            "SELECT * FROM exchange_rates ORDER BY to_currency"
        );
    }
}

