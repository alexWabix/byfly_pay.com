#!/bin/bash

# ByFly Payment Center - Development Server
# Запускает PHP встроенный сервер и Vite dev server

echo "======================================"
echo "ByFly Payment Center - Dev Mode"
echo "======================================"
echo ""

# Проверка PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP не установлен"
    exit 1
fi

echo "✅ PHP version: $(php -v | head -n 1)"
echo ""

# Запуск PHP сервера для API (порт 8000)
echo "🚀 Starting PHP API server on http://localhost:8000"
php -S localhost:8000 -t . api/router-dev.php > api/logs/php-server.log 2>&1 &
PHP_PID=$!
echo "   PID: $PHP_PID"

# Даем время на запуск
sleep 2

# Проверка API
echo ""
echo "🔍 Testing API..."
curl -s http://localhost:8000/api/test.php > /dev/null
if [ $? -eq 0 ]; then
    echo "✅ API is responding"
else
    echo "❌ API not responding"
fi

echo ""
echo "======================================"
echo "Development servers are running:"
echo ""
echo "📡 API Backend:  http://localhost:8000/api/"
echo "🌐 Frontend:     npm run dev (run in another terminal)"
echo ""
echo "Test API:        http://localhost:8000/api/test.php"
echo ""
echo "Press Ctrl+C to stop servers"
echo "======================================"
echo ""

# Ждем сигнала завершения
trap "echo ''; echo 'Stopping servers...'; kill $PHP_PID 2>/dev/null; exit 0" SIGINT SIGTERM

wait $PHP_PID

