FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    ca-certificates \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli pdo pdo_mysql soap zip gd mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY php.ini /usr/local/etc/php/

ENV APACHE_DOCUMENT_ROOT=/data/systems/PosVentasPOO

WORKDIR /data/systems/PosVentasPOO

COPY . /data/systems/PosVentasPOO

RUN sed -ri \
    's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf \
    && printf '<Directory /data/systems/PosVentasPOO>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' > /etc/apache2/conf-available/posventas.conf \
    && a2enconf posventas \
    && chown -R www-data:www-data /data/systems/PosVentasPOO

