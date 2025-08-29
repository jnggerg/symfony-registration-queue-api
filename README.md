# Symfony Event-Registration queue system API

## Környezet 
 - Symfony: 7.3.2
 - PHP: 8.4.11
 - Adatbázis: SQLite
 - Auth: JWT token (lexik/jwt-authentication-bundle)
## Rövid leírás
 - Felhasználó tud regisztrálni, bejelentkezni, majd ezek után tud az eseményekre regisztrálni. Ha van még hely, akkor sikeres üzenet a válasz, ha nincs, akkor a válaszban benne van a sorszáma.
   
 - Ezen felül az admin felhasználó tud létrehozni, törölni, szerkeszteni eseményeket, felhasználókat fel- és lejelentkeztetni eseményekről.
   
 - Csakis REST endpointokat tartalmaz a projekt, semmi féle frontend megvalósítással / twig.html-el. Minden endpoint szigorúan JSON-nel és egy HTTP kóddal válaszol, és JWT token szükséges az endpointok (kivéve /login, /register) eléréséhez.

## Adatok struktúrája
### Entity
**User** 
- id, email, jelszó, illetve egy OneToMany kapcsolat Registrationjeivel
  
**Event**
- id, cím, kapacitás, egy dátum, ehhez tartozó Registration-ökkel OneToMany kapcsolat
  
**Registration**
- id, Event és User ManyToOne kapcsolatokkal, qPosition - tehát a várolistában lévő pozíció (null, ha sikeresen regisztrált)

### Service
**QueueHandlerService**
- Minden várolistával kapcsolatos logikát ez kezeli, hozzáadást, törlést, illetve törlés esetén a sorszámok eltolását.
  
### Repository
**RegistrationRepository**
- Ez tartalmazza a QueueHandler által használ segédmetódus SQL query-ket, leginkább az olvashatóság kedvéért.
  
**UsersRepository** és **EventRepository** érintetlen, ezek segítenek az adatbázisból való lekérdezésekbe.

### Controller
**EventsRestController**
- Ebben vannak a normális felhasználóknak elérhető Rest endpointok, mint az események lekérdezése, jelentkezés / lejelentkezés.
  
**EventsAdminController**
- Az admin jogokkal rendlkező felhasználóknal elérhető CRUD endpointok, illetve szolgál más felhasználók le és feljelentkeztetésére egy adott eseményre is.
  
**RegisterController**
- A regisztráláshoz szükséges /register endpointért felelős kontroller.
  
### Auth
- JWT token alapú autentikáció, security.yaml fileban implementált json_loginnal.
  
- Firewall az endpointok védéséhez, kivétel a /login és /register.

## Teszt adatok
- Generálás: Doctrine Fixtures és Faker, minden felhasználó jelszava `test123asd`.
  
- Külön létrehozott admin felhasználó tesztelésre: `admin@admin.com` , `admintest123`.

## Futtatás
**Követelmények**
- PHP, Composer, openssl (JWT token generálásához), Symfony CLI
  
**Dependency-k letöltése**
- composer install
  
**JWT kulcs generálása**

-mkdir config/jwt

-openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

-openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

**Adatbázis**
- php bin/console doctrine:database:create
  
- php bin/console doctrine:migrations:migrate
  
- php bin/console doctrine:fixtures:load

**Szerver indítása**
- Symfony server:start

