# Конфигурация Nginx для ByFly Payment Center

## ⚠️ ВАЖНО! Добавьте эти строки в ваш конфиг Nginx

### Откройте конфиг:
```bash
sudo nano /etc/nginx/sites-available/byfly-pay.com
```

### Полный пример конфигурации:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name byfly-pay.com www.byfly-pay.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name byfly-pay.com www.byfly-pay.com;

    root /var/www/html;
    index index.html index.php;

    # SSL Configuration (замените на ваши пути)
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Frontend routes (Vue SPA)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API routes
    location /api {
        try_files $uri $uri/ /api/index.php?$query_string;
        
        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
            
            # ⚠️ КРИТИЧЕСКИ ВАЖНО ДЛЯ АВТОРИЗАЦИИ:
            fastcgi_param HTTP_AUTHORIZATION $http_authorization;
            fastcgi_param REDIRECT_HTTP_AUTHORIZATION $http_authorization;
        }
    }

    # Кэширование статических файлов
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### Проверка и перезапуск:

```bash
# Проверка конфига
sudo nginx -t

# Если OK - перезапустите
sudo systemctl restart nginx
```

## 🔧 Альтернативный вариант для Apache

Если используете Apache вместо Nginx, добавьте в `.htaccess`:

```apache
# Authorization header для PHP
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

---

После настройки - админка будет работать идеально! ✅


