# Dentistry Website Deployment

## Files to upload

Upload these project files to your hosting `htdocs`, `public_html`, or `htdocs/your-site` folder:

- `index.php`
- `doctor.php`
- `available_slots.php`
- `config.php`
- `assets/`

## Database settings

Create a MySQL database in your hosting control panel, then copy `config.local.example.php` to `config.local.php` on the server and update the values:

```php
return [
    'db_host' => 'YOUR_HOST',
    'db_user' => 'YOUR_DATABASE_USER',
    'db_pass' => 'YOUR_DATABASE_PASSWORD',
    'db_name' => 'YOUR_DATABASE_NAME',
];
```

When you open the website the first time, the tables are created automatically.

## Doctor login

- Username: `admin`
- Password: `123456`

Change these values in `doctor.php` before using the site publicly:

```php
$doctorUser = 'admin';
$doctorPass = '123456';
```

## Google indexing

After the site is online, add the final website URL in Google Search Console and request indexing.
