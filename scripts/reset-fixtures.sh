#!/bin/bash

# reset-fixtures.sh
# Limpia todas las tablas excepto 'users' y 'refresh_tokens',
# luego carga los fixtures con --append para no perder el usuario registrado.

set -e

echo "==> Limpiando tablas (PostgreSQL CASCADE)..."
docker compose exec php php bin/console dbal:run-sql "TRUNCATE TABLE budget_category, budget, \"transaction\", category, chat_message, chat_conversation, tip RESTART IDENTITY CASCADE;"
echo "    Tablas limpiadas."

echo "==> Cargando fixtures (--append)..."
docker compose exec php php bin/console doctrine:fixtures:load --append --no-interaction
echo "    Fixtures cargados."

echo "==> Listo."
