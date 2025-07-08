#!/bin/bash
set -e
echo "Instalando phpoffice/phpspreadsheet..."
composer require phpoffice/phpspreadsheet:^1.8.2

echo "Instalando vlucas/phpdotenv..."
composer require vlucas/phpdotenv

echo "Instalando symfony/mailer..."
composer require symfony/mailer

echo "✅ Instalación completa."
