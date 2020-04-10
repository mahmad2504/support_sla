:loop
@php artisan sync:database
@timeout 60
goto :loop