# Описание

Плагин для Wordpress для экспорта сделок, открытых позиций, денег и прочего с брокерского аккаунта
в Тинькофф Инвестиции через API в Google Sheets. 

- В работе плагин смотри в [здесь](https://azzrael.ru/tinvest-export).
- Описание работы в видео на канале [Azzrael Code](https://www.youtube.com/channel/UCf6kozNejHoQuFhBDB8cfxA) на YouTube.

## Полезные ссылки
- Много полезного по работе с API Тинькофф Инвестиции в [плейлисте](https://www.youtube.com/playlist?list=PLWVnIRD69wY4ane3amNJSFQfls1inhaub).
- По работе с Google API в [плейлисте](https://www.youtube.com/playlist?list=PLWVnIRD69wY7DoPeDvwl2ndrfZMN8cARl) и конкретно с [Sheets API](https://www.youtube.com/playlist?list=PLWVnIRD69wY75tQAmyMFP-WBKXqJx8Wpq).
- Установка [google-api-php-client](https://github.com/googleapis/google-api-php-client)
- Доки по API Тинькофф Инвестиции ([1](https://tinkoffcreditsystems.github.io/invest-openapi/)) и ([2](https://tinkoffcreditsystems.github.io/invest-openapi/swagger-ui/))

## Нужно для установки плагина 

- PHP >= 7.2 (для google-api-php-client)
- Wordpress (у меня работает на WP > 5.6)
- composer
- curl
- Токен в API Тинькофф Инвестиции
- Проект в Google Cloud Platform

## Установка плагина

Просто скопировать в ```plugins``` недостаточно. Нужно:

### 1. Создать Сервисный Аккаунт

Создай в Google Cloud Platform проект с Sheets API, создай сервисный аккаунт
и скачай json в папку creds в файл sacc.json.

В options.php пропиши емейл сервисного аккаунта.

### 2. Установка google-api-php-client

```
cd /.../domain.tld/wp-content/plugins/azz-tinvest
composer update # установится google-api-php-client с зависимостями
```

### 3. Страница в Wordpress
Затем пропиши шорткат на любую страницу wordpress

```
[azztinvestsc]
```

