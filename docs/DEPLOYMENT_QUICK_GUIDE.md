# GOautodial Linux Setup Guide

Three-page deployment guide for developers

## Page 1 Prepare the server and upload the application

### 1 Prepare the deployment details

Use an Ubuntu 24.04 server for the web application and a compatible GOautodial Linux server for calling services. Prepare SSH access, the application domain, backend API domain, SIP domain, database credentials, and the matching backend installation package and SQL schemas.

Examples below use `crm.example.com`, `dialer.example.com`, and `/srv/goautodial/current`. Replace them with your deployment values. Point the domains' DNS records to their respective servers.

### 2 Install the web server and PHP

Run on the Ubuntu web server:

```bash
sudo apt update
sudo apt install -y apache2 mariadb-client composer certbot \
  python3-certbot-apache php8.3-fpm php8.3-cli \
  php8.3-mysql php8.3-curl php8.3-gd php8.3-mbstring \
  php8.3-intl php8.3-xml php8.3-zip php8.3-opcache php8.3-bcmath
sudo a2enmod rewrite ssl headers proxy_fcgi setenvif
sudo a2enconf php8.3-fpm
```

Install PHP IMAP separately if the selected email features require it.

### 3 Upload the project and install dependencies

Upload the project to `/tmp/goautodial-source` using SFTP or SCP. Then run as the deployment user:

```bash
sudo install -d -o "$(id -un)" -g "$(id -gn)" /srv/goautodial/releases/release-001
rsync -a --exclude='.git/' --exclude='.env*' \
  --exclude='tmp/' --exclude='vendor/' \
  --exclude='uploads/' --exclude='img/avatars/' \
  /tmp/goautodial-source/ /srv/goautodial/releases/release-001/
cd /srv/goautodial/releases/release-001
composer install --no-dev --optimize-autoloader
```

Keep the Composer lockfile with the release.

### 4 Set permissions and shared storage

For the first deployment, create shared storage and copy avatar defaults and upload rules:

```bash
sudo install -d -o www-data -g www-data -m 0750 \
  /srv/goautodial/shared/uploads /srv/goautodial/shared/avatars
sudo cp /tmp/goautodial-source/uploads/.htaccess /srv/goautodial/shared/uploads/
sudo cp -a /tmp/goautodial-source/img/avatars/. /srv/goautodial/shared/avatars/
sudo chown -R root:www-data /srv/goautodial/releases/release-001
sudo find /srv/goautodial/releases/release-001 -type d -exec chmod 0750 {} +
sudo find /srv/goautodial/releases/release-001 -type f -exec chmod 0640 {} +
sudo chown -R www-data:www-data /srv/goautodial/shared
sudo ln -s /srv/goautodial/shared/uploads uploads
sudo ln -s /srv/goautodial/shared/avatars img/avatars
sudo ln -s /srv/goautodial/releases/release-001 /srv/goautodial/current
```

## Page 2 Configure the application and HTTPS

### 1 Create the production configuration

From the release directory, copy the configuration template outside the application:

```bash
sudo install -d -o root -g www-data -m 0750 /etc/goautodial
sudo cp config/production.php.example /etc/goautodial/production.php
sudo nano /etc/goautodial/production.php
```

Fill these settings in the returned PHP array:

| Settings | Value to supply |
| --- | --- |
| `APP_ENV`, `APP_URL` | `production` and `https://crm.example.com` |
| `GO_API_URL`, `GO_API_USER`, `GO_API_PASSWORD` | HTTPS goAPIv2 URL and backend API account credentials |
| GOautodial, Asterisk and Kamailio `DB_*` settings | Private hosts, ports, schema names and account credentials |
| `SESSION_DRIVER`, `SESSION_ENCRYPTION_KEY` | `database` and a 64-character hexadecimal key |
| SIP, SMTP and integration settings | Values required by enabled features |

Generate the session key and paste its output into `SESSION_ENCRYPTION_KEY`:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
sudo chown root:www-data /etc/goautodial/production.php
sudo chmod 0640 /etc/goautodial/production.php
```

### 2 Connect PHP FPM to the configuration

Add this line inside the `[www]` pool in `/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
env[GOAUTODIAL_CONFIG_FILE] = /etc/goautodial/production.php
```

Use `deployment/php-production.ini` as the application's PHP settings reference.

### 3 Configure Apache and HTTPS

Create `/etc/apache2/sites-available/goautodial.conf`:

```apache
<VirtualHost *:80>
    ServerName crm.example.com
    DocumentRoot /srv/goautodial/current
    <Directory /srv/goautodial/current>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/goautodial-error.log
    CustomLog ${APACHE_LOG_DIR}/goautodial-access.log combined
</VirtualHost>
```

Enable the site and activate HTTPS:

```bash
sudo a2ensite goautodial.conf
sudo apache2ctl configtest
sudo systemctl restart php8.3-fpm apache2
sudo certbot --apache -d crm.example.com --redirect
sudo certbot renew --dry-run
```

Allow ports 80/443 and administrator SSH access. Keep database connections private. Serve at the domain root. For a TLS reverse proxy, preserve Host, forward `X-Forwarded-Proto: https`, and set its exact source IP in `TRUSTED_PROXIES`.

## Page 3 Set up the calling backend and start the application

### 1 Install the Linux calling backend

On the calling server, install the compatible GOautodial backend package with VICIdial/astguiclient, Asterisk, Kamailio and RTPengine. Use the installation procedure and service definitions supplied with that package.

Install the matching goAPIv2 release on the backend web server and enable HTTPS at the configured `GO_API_URL`. Configure its `astguiclient.conf` with database connections, server IP, directories and installed Asterisk version. Keep this configuration inaccessible over HTTP.

### 2 Import databases and configure accounts

Import the matching schemas into `goautodial`, `asterisk` and `kamailio` using the database administrator account. For each supplied schema file:

```bash
mysql -h DATABASE_HOST -u DATABASE_ADMIN -p DATABASE_NAME < SCHEMA_FILE.sql
```

Create the application's database accounts and grant access to their respective schemas. Ensure the GOautodial schema contains `go_sessions`; use `create_sessions.sql` when that table needs creating.

In the backend administration panel, configure the server, SIP trunk, DIDs, phones, user groups, campaigns, administrator and agent accounts. Match the API account/password format to `GO_API_USER` and `GO_API_PASSWORD`. Set the application base URL, timezone and enabled SMTP settings.

### 3 Configure browser calling

Configure the SIP WebSocket listener with a trusted certificate and set its hostname/port in the agent/server settings. Configure public/private IP mappings, RTPengine and the backend's signaling/media ports. Set `SIP_DOMAIN`, `SIP_WS_HOST` and `SIP_WS_PORT` when using `modules/GOagent/jsSIP.php`. Configure TURN when agents' networks require a media relay.

### 4 Enable scheduled jobs

Create `/etc/cron.d/goautodial-crm` with:

```cron
GOAUTODIAL_CONFIG_FILE=/etc/goautodial/production.php
0 * * * * www-data cd /srv/goautodial/current && /usr/bin/flock -n /tmp/goautodial-crm.lock /usr/bin/php job-scheduler.php >> /var/log/goautodial-crm.log 2>&1
```

Create the log file with ownership allowing `www-data` to append. Enable the separate VICIdial cron/keepalive processes and configure the backend's telephony services to start on boot.

### 5 Start the application

Run the connection checks as the application service user:

```bash
cd /srv/goautodial/current
sudo -u www-data env GOAUTODIAL_CONFIG_FILE=/etc/goautodial/production.php php bin/preflight.php
```

Open `https://crm.example.com`, sign in as an administrator and an agent, and confirm registration, inbound/outbound calls, two-way audio, transfer, dispositions, recordings and reports. Confirm enabled email features. Configure scheduled backups for databases, recordings, shared uploads and protected configuration, then open access to the intended users.
