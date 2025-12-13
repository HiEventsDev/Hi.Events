FROM laravelphp/vapor:php83

# Install ImageMagick with WebP support
# The imagemagick-webp package provides WebP delegate support
RUN apk add --update --no-cache \
    autoconf \
    g++ \
    imagemagick \
    imagemagick-dev \
    imagemagick-webp \
    libwebp \
    libwebp-dev \
    libtool \
    && pecl install imagick \
    && docker-php-ext-enable imagick

COPY . /var/task
