FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libsqlite3-dev \
    zip \
    unzip \
    sqlite3 \
    python3 \
    python3-pip \
    python3-pil \
    nodejs \
    npm \
    dos2unix \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions including pcntl & posix for Reverb WebSockets
RUN docker-php-ext-install pdo pdo_sqlite mbstring bcmath pcntl posix

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Fix Windows CRLF line endings for Linux bash scripts
RUN dos2unix start.sh && chmod +x start.sh

# Install Composer & Node packages
RUN composer install --no-dev --optimize-autoloader
RUN npm install
RUN npm run build

# Setup directories and permissions
RUN mkdir -p database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs && \
    touch database/database.sqlite && \
    chmod -R 777 storage database bootstrap/cache

EXPOSE 8088

CMD ["bash", "start.sh"]
