#!/bin/sh
set -e

PROJECT_NAME=project

if [ ! -f /app/manage.py ]; then
  echo "⚙️ Nenhum projeto Django encontrado. Criando..."
  django-admin startproject $PROJECT_NAME /app

  # O startproject gera "ALLOWED_HOSTS = []", que só aceita localhost e faz o
  # acesso pelo IP do servidor (ex.: http://10.13.3.30:58250/) cair em
  # DisallowedHost. Troca por uma lista lida da variável DJANGO_ALLOWED_HOSTS
  # (separada por vírgulas), com um padrão que já funciona no laboratório.
  SETTINGS=/app/$PROJECT_NAME/settings.py
  sed -i "s/^ALLOWED_HOSTS = \[\]$/import os\nALLOWED_HOSTS = os.environ.get('DJANGO_ALLOWED_HOSTS', '10.13.3.30,localhost,127.0.0.1').split(',')/" "$SETTINGS"
  if ! grep -q "DJANGO_ALLOWED_HOSTS" "$SETTINGS"; then
    echo "⚠️ Não foi possível ajustar ALLOWED_HOSTS em $SETTINGS" >&2
  fi
else
  echo "✅ Projeto Django encontrado"
fi

exec gunicorn $PROJECT_NAME.wsgi:application \
  --bind 0.0.0.0:8000 \
  --workers 2
