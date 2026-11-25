FROM php:8.4-fpm-alpine

# Instalar dependências do sistema
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    nginx \
    postgresql-dev \          
    postgresql-client \       
    libpq-dev \
    nodejs \
    npm \
    jq               

# Instalar extensões PHP necessárias para Laravel
RUN docker-php-ext-install \
    pdo_pgsql \
    gd \
    zip \
    pcntl

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Criar diretório para configurações do Nginx
RUN mkdir -p /etc/nginx/conf.d

# Copiar configuração do Nginx para o local correto
COPY nginx/default.conf /etc/nginx/conf.d/
COPY nginx/nginx.conf /etc/nginx/nginx.conf

# Copiar script de inicialização
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html

# Instalar Laravel Installer e adicionar ao PATH
RUN composer global require laravel/installer
ENV PATH="$PATH:/root/.composer/vendor/bin"

WORKDIR /var/www/html

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
