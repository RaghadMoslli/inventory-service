# استخدام صورة PHP رسمية مع إصدار يدعم لارافل
FROM php:8.2-fpm

# تثبيت الحزم المطلوبة والتوسعات (Extensions) اللازمة للارافل وقاعدة البيانات
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# تنظيف الكاش لتقليل حجم الصورة
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# تثبيت توسعات PHP المطلوبة
RUN apt-get update && apt-get install -y libpq-dev && \
    docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# تثبيت برنامج Composer لإدارة الحزم
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# تحديد مجلد العمل داخل الحاوية
WORKDIR /var/www/html

# نسخ ملفات المشروع إلى الحاوية
COPY . .

# تثبيت حزم المشروع عبر Composer
RUN composer install --no-dev --optimize-autoloader

# ضبط الصلاحيات للمجلدات الحساسة
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تشغيل خدمة PHP-FPM
EXPOSE 9000
CMD ["php-fpm"]