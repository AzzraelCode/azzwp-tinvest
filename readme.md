# Описание

Плагин для Wordpress для экспорта сделок, открытых позиций, денег и прочего с брокерского аккаунта
в Тинькофф Инвестиции через API в Google Sheets. 

## Нужно для установки плагина 

- PHP >= 7.2 (для google-api-php-client)
- Wordpress (у меня работает на WP > 5.6)
- composer
- curl
- Токен в API Тинькофф Инвестиции
- Проект в Google Cloud Platform

### 1. Создание Сервисного Аккаунта

Создай в Google Cloud Platform проект с Sheets API, создай сервисный аккаунт
и скачай json в папку creds в файл sacc.json.

В options.php пропиши емейл сервисного аккаунта.

### 2. Установка google-api-php-client

```
cd /.../azzrael.ru/wp-content/plugins/azz-tinvest
composer update # установится google-api-php-client
```

https://developers.google.com/sheets/api/quickstart/php
https://github.com/googleapis/google-api-php-client

### 3. Страница в Wordpress
Затем пропиши шорткат на любую страницу wordpress

```
[azztinvestsc]
```
## Полезные ссылки

https://tinkoffcreditsystems.github.io/invest-openapi/
https://tinkoffcreditsystems.github.io/invest-openapi/swagger-ui/
