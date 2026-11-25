#!/bin/sh

# Garante que o diretório das homes dos alunos seja gravável pelo PHP-FPM.
# O bind mount em /srv/student-homes vem do host com o dono do host (UID 33),
# mas dentro do container Alpine o www-data tem UID 82 — sem este chown
# o controller falha com "mkdir(): Permission denied" no deploy.
if [ -d /srv/student-homes ]; then
    chown -R www-data:www-data /srv/student-homes
    chmod 750 /srv/student-homes
fi

# Adiciona o www-data ao grupo do /var/run/docker.sock (montado do host).
# Sem isso, chamadas curl --unix-socket /var/run/docker.sock feitas pelo
# PHP-FPM falham com "Permission denied" e o deploy responde HTTP 502.
if [ -S /var/run/docker.sock ]; then
    DOCKER_GID=$(stat -c '%g' /var/run/docker.sock)
    if [ -n "$DOCKER_GID" ] && [ "$DOCKER_GID" != "0" ]; then
        if ! getent group "$DOCKER_GID" >/dev/null; then
            addgroup -g "$DOCKER_GID" dockerhost
        fi
        DOCKER_GROUP=$(getent group "$DOCKER_GID" | cut -d: -f1)
        addgroup www-data "$DOCKER_GROUP" 2>/dev/null || true
    fi
fi

# Inicia PHP-FPM em background
php-fpm -D

# Inicia Nginx em primeiro plano
nginx -g 'daemon off;'
