## Environment
 - Symfony: 7.3.2  
 - PHP: 8.4.11  
 - Database: SQLite  
 - Auth: JWT token (lexik/jwt-authentication-bundle)  
 - Postman for API testing  

## Short Description
 - A user can register, log in, and afterwards register for events.  
   If there is still available capacity, the response returns a success message.  
   If the event is full, the response contains the user's queue position.
   
 - Additionally, an admin user can create, delete, and edit events, as well as sign users up or remove them from events.
   
 - The project only contains REST endpoints, without any kind of frontend implementation or twig.html templates.  
   Every endpoint responds strictly with JSON and an HTTP status code, and JWT authentication is required for all endpoints (except `/login` and `/register`).

 - Data validation is handled with the Validator component, except for simple “id” parameters.

## Data Structure

### Entity
**User**  
- id, email, password, and a OneToMany relationship with its Registrations

**Event**  
- id, title, capacity, a date, and a OneToMany relationship with its Registrations

**Registration**  
- id, ManyToOne relationships with Event and User, qPosition – meaning the position in the queue (null if the registration was successful)

### Service
**QueueHandlerService**  
- Handles all queue-related logic, including additions, removals, and reordering queue positions when a registration is removed.

### Repository
**RegistrationRepository**  
- Contains helper SQL query methods used by QueueHandler, mainly for readability.

**UsersRepository** and **EventRepository** are unchanged; they assist with database queries.

### Controller
**EventsRestController**  
- Contains REST endpoints available for normal users, such as listing events and registering/unregistering.

**EventsAdminController**  
- Contains CRUD endpoints available to admin users, and also handles registering/unregistering other users for specific events.

**RegisterController**  
- Responsible for the `/register` endpoint used for user registration.

### Auth
- JWT token–based authentication, implemented via json_login in the `security.yaml` file.
- Firewall protection for endpoints, except for `/login` and `/register`.

## Test Data
- Generated using Doctrine Fixtures and Faker; all users have the password `test123asd`.
- A dedicated admin user is created for testing:  
  `admin@admin.com` / `admin123asd`.

## Running the Project

**Requirements**
- PHP, Composer, openssl (for JWT key generation), Symfony CLI

**Install dependencies**
- `composer install`

**Generate JWT keys**

-mkdir config/jwt

-openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

-openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

- The required passphrase can be found in the `.env` file (uploaded only for simplicity; it does not contain any sensitive real data).

**Database**
- php bin/console doctrine:database:create
  
- php bin/console doctrine:migrations:migrate
  
- php bin/console doctrine:fixtures:load

**Start Server**
- `symfony server:start`
