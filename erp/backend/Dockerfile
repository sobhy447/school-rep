FROM php:8.4-cli

# امتدادات PHP المطلوبة: pdo_mysql للقاعدة، gd لـ mPDF (توليد PDF عربي بالصور)
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpng-dev libonig-dev \
    libfreetype6-dev libjpeg62-turbo-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_mysql zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# ملف بيئة جاهز لـ Docker (MySQL) بدل sqlite، ثم توليد مفتاح التطبيق
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \
    && cp -n .env.docker .env || true \
    && php artisan key:generate --force

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
