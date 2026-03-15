#!/bin/sh
set -e

echo "==> Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

echo "==> Generating JWT keys if missing..."
mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
    openssl genpkey -algorithm RSA \
        -out config/jwt/private.pem \
        -pkeyopt rsa_keygen_bits:4096 \
        -pass pass:"${JWT_PASSPHRASE}"
    openssl pkey -in config/jwt/private.pem \
        -out config/jwt/public.pem \
        -pubout \
        -passin pass:"${JWT_PASSPHRASE}"
    echo "==> JWT keys generated."
else
    echo "==> JWT keys already exist, skipping."
fi

echo "==> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "==> Starting Symfony server..."
exec "$@"
