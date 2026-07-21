FROM php:8.2-apache

# نصب ماژول‌های مورد نیاز PHP برای API و دریافت ویدیوها
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli curl

# فعال‌سازی ماژول‌های rewrite و headers در آپاچی
RUN a2enmod rewrite headers

WORKDIR /var/www/html

COPY . /var/www/html/

# مجوز دادن به فایل .htaccess
RUN sed -i '/<Directory \/var\/www\/html>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# تنظیم دسترسی‌های فایل
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# تنظیم فایل ورودی پیش‌فرض به player_api.php
RUN echo 'DirectoryIndex player_api.php index.php index.html' > /etc/apache2/conf-available/custom-entry.conf \
    && a2enconf custom-entry

EXPOSE 80

CMD ["apache2-foreground"]

RUN chmod -R 755 /var/www/html
RUN chown -R www-data:www-data /var/www/html
# اجرای وب‌سرور آپاچی
CMD ["apache2-foreground"]
