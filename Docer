# استفاده از نسخه رسمی پایتون/PHP همراه با وب‌سرور آپاچی
FROM php:8.2-apache

# نصب پیش‌نیازها و اکستنشن‌های مورد نیاز PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli

# فعال‌سازی mod_rewrite در آپاچی
RUN a2enmod rewrite

# تنظیم پوشه کاری کانتینر
WORKDIR /var/www/html

# کپی کردن تمام فایل‌های پروژه به داخل کانتینر
COPY . /var/www/html/

# تنظیم دسترسی‌های لازم برای وب‌سرور
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# تنظیم player_api.php به عنوان فایل اصلی و ورودی پروژه
RUN echo 'DirectoryIndex player_api.php index.php index.html' > /etc/apache2/conf-available/custom-entrypoint.conf \
    && a2enconf custom-entrypoint

# تنظیمات VirtualHost برای هدایت مسیرها به player_api.php
RUN sed -i 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\n    <Directory \/var\/www\/html>\n        Options Indexes FollowSymLinks\n        AllowOverride All\n        Require all granted\n        DirectoryIndex player_api.php index.php\n    <\/Directory>/' /etc/apache2/sites-available/000-default.conf

# باز کردن پورت ۸۰
EXPOSE 80

# اجرای وب‌سرور آپاچی
CMD ["apache2-foreground"]
