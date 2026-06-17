# Lukman Session Manager

A lightweight, framework-agnostic, and standalone PHP session management library.

## Requirements

- PHP 8.2 or higher

## Installation

You can install this package via Composer:

```bash
composer require lukman-ss/session
```

---

## Usage Examples

### 1. Initialize ArraySessionHandler (In-Memory)
An in-memory storage handler useful for unit tests or transient sessions.

```php
use Lukman\Session\Handlers\ArraySessionHandler;

$handler = new ArraySessionHandler();
```

### 2. Initialize FileSessionHandler (Disk)
A filesystem handler that stores sessions on disk securely.

```php
use Lukman\Session\Handlers\FileSessionHandler;

// Automatically creates the directory if it doesn't exist
$handler = new FileSessionHandler('/path/to/sessions');
```

### 3. Basic SessionStore Lifecycle (start, get, put, save)
Use `SessionStore` directly to start a session, get, set (put), and save session data.

```php
use Lukman\Session\SessionStore;
use Lukman\Session\SessionIdGenerator;

$store = new SessionStore($handler, new SessionIdGenerator());

// Start the session (loads data from the handler, generates new ID if needed)
$store->start();

// Check and save session values
$store->put('user_id', 42);

if ($store->has('user_id')) {
    $userId = $store->get('user_id'); // 42
}

// Persist the changes to the storage handler
$store->save();
```

### 4. Nested Arrays via Dot Notation
You can read, write, check, and forget nested session data structures using dot notation.

```php
$store->start();

// Automatically creates nested arrays
$store->put('user.profile.name', 'Lukman');
$store->put('user.profile.role', 'Administrator');

// Retrieve nested values
$name = $store->get('user.profile.name'); // 'Lukman'

// Check existence
if ($store->has('user.profile.role')) {
    // forget nested keys
    $store->forget('user.profile.role');
}
```

### 5. Flash Data (One-Time Sessions)
Flash data is available in the next request and automatically deleted afterward.

```php
$store->start();

// Set flash data for the next request
$store->flash('status', 'Profile updated successfully!');

// Set flash data only for the current request
$store->now('info', 'Reading log file...');

// Age flash data (typically run at the end of the request/response cycle)
// - Removes old flash data
// - Marks new flash data as old
$store->ageFlashData();

// Keep specific flash data or reflash all
$store->keep('status');
$store->reflash();
```

### 6. CSRF Token via `token()`
Generate and maintain a secure CSRF token in the session.

```php
$store->start();

// Get the current token, or automatically generate one if not present
$token = $store->token();

// Forcefully regenerate the CSRF token
$newToken = $store->regenerateToken();
```

### 7. Security Lifecycle (regenerate, invalidate, destroy)
Secure your application by managing session IDs and lifecycle transitions.

```php
$store->start();

// Regenerate the session ID keeping all data (optionally destroy the old session in handler)
$store->regenerate(true);

// Destroy the current session in storage
$store->destroy();

// Flush all data, destroy current session, and regenerate ID (log out)
$store->invalidate();
```

### 8. SessionManager Configuration & Usage
Utilize `SessionManager` to configure and boot sessions easily with different drivers.

```php
use Lukman\Session\SessionManager;

$config = [
    'driver'   => 'file',
    'lifetime' => 120, // in minutes (automatically converted to 7200 seconds TTL)
    'files'    => __DIR__ . '/sessions',
];

$manager = new SessionManager($config);

// Get the default store
$store = $manager->store();
$store->start();

// Access specific driver stores
$arrayStore = $manager->driver('array');
```

---

## Testing

To run the unit test suite:

```bash
composer test
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
