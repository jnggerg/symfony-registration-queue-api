# Symfony Event-Registration queue system API

## A projekt Symfony 7.3.2-ben (PHP 8.4.11) készült.
Az adatbázishoz SQLiteot használtam, teszteléshez az adokat Doctrine Fixture-ökben generáltam, helyenként Fakerrel (mindegyik felhasználó jelszava "test123asd"). Külön admin felhasználó az admin-only endpointokhoz: admin@admin.com|admintest123

Szinte minden osztályt automatikusan hoztam létre maker-bundle-vel (user,entity,security,migration,fixture,controller).
A projekt összes endpointja REST endpoint, szigorúan json-nel válaszol, HTTP kódokkal, minden requesthez kell Bearer token auth.
Az authorizáció JWT tokennel történik, a config\packages\security.yaml-ben configurált firewall alapján, a lexik/jwt-authentication-bundle használatával.

A projekt alapvető felépítése:
    - 3 Entity:
        - User (id, jelszó / email, illetve egy OneToMany kapcsolat Registration-ökkel)
        - Event (id, Cím, kapacitás, egy dátum, ehhez tartozó Registration-ökkel OneToMany kapcsolat)
        - Registration (id, Event, User ManyToOne kapcsolatokkal, qPosition, tehát a várolistában lévő pozíció)
    - Minden várolistával kapcsolatos logikát a src\Service\QueueHandlerService kezeli.
    - A felhasználónak elérhető Route-ok az EventsRestController-ben vannak implementálva.
    - Admin endpointok (create,delete,edit Event, stb.) az EventsAdminController-ben.
    - A security.yaml kezeli a tűzfalat, az összes endpoint csak bejelentkezett felhasználóknak érhető el, különben 403 error-ral válaszol.
    - A src\Repository\RegistrationRepository osztályban vannak definiálva az SQL query segédmetódusok, amik a QueueHandlerService-ben vannak alkalmazva, leginkább az olvashatóság kedvéért.

Összes használt package listája:
composer require doctrine
composer require doctrine/doctrine-migrations-bundle
composer require --dev doctrine/doctrine-fixtures-bundle
composer require --dev maker-bundle
composer require --dev fakerphp/faker
composer require security
composer require validator
composer remove symfony/twig-bundle
composer require symfony/serializer
composer require lexik/jwt-authentication-bundle

A JWT tokenhez konfigurációhoz openssl-t használtam:
openssl genrsa -out config/jwt/private.pem -aes256 4096
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
