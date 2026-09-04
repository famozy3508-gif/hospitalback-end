FROM php:8.2-apache

# ติดตั้งไลบรารีระบบที่จำเป็นก่อน (oniguruma สำหรับ mbstring, libcurl สำหรับ curl)
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# ติดตั้ง extension ที่จำเป็นสำหรับเชื่อมต่อ MySQL, mbstring (ภาษาไทย) และ curl (อัปโหลดรูปไป Cloudinary)
RUN docker-php-ext-install pdo pdo_mysql mysqli mbstring curl

# เปิดใช้งาน mod_rewrite ของ Apache
RUN a2enmod rewrite

# คัดลอกไฟล์โปรเจกต์ทั้งหมดเข้าไปในเว็บเซิร์ฟเวอร์
COPY . /var/www/html/

# สร้างโฟลเดอร์ uploads/avatars เอง (เผื่อไม่มีอยู่ใน repo เพราะ .gitignore บล็อกไว้)
# แล้วตั้งสิทธิ์ให้เขียนไฟล์ในโฟลเดอร์นี้ได้ (สำหรับอัปโหลดรูปโปรไฟล์)
RUN mkdir -p /var/www/html/uploads/avatars \
    && chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80