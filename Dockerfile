RUN chmod -R 755 /var/www/html
RUN chown -R www-data:www-data /var/www/html
# اجرای وب‌سرور آپاچی
CMD ["apache2-foreground"]
