FROM php:8.2-apache

# ติดตั้ง extension ที่จำเป็นสำหรับเชื่อมต่อ MySQL และใช้งาน mbstring (ภาษาไทย)
RUN docker-php-ext-install pdo pdo_mysql mysqli mbstring

# เปิดใช้งาน mod_rewrite ของ Apache
RUN a2enmod rewrite

# คัดลอกไฟล์โปรเจกต์ทั้งหมดเข้าไปในเว็บเซิร์ฟเวอร์
COPY . /var/www/html/

# ตั้งสิทธิ์ให้เขียนไฟล์ในโฟลเดอร์ uploads ได้ (สำหรับอัปโหลดรูปโปรไฟล์)
RUN chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80