> # ⚠️ **Important Security Notice**
>
> Update your **AMP_SHARED_KEY** regularly (Use backups/game wipes/calendar reminders or whatever it takes to remember)!
> - Change it in your `.env` file on the AMP server (opt/amp-status).
> - Then update it in WordPress under Settings > **AMP Server Status**.
> 
> ### Keeping your key secure is critical to preventing unauthorized access.


# AMP Status Relay – Install Guide

## 🧰 Requirements for AMP

- Ubuntu/Debian AMP server  
- AMP instances running on `localhost`  
- PHP, cURL, and a web server (e.g., Nginx)
- Create an account in AMP and a role for it with restricted permissions (Allow ONLY the "Manage" permission for each instance configured, deny ALL others)

---

## 🔧 Step 1: Install PHP & Web Server

```bash
sudo apt update
sudo apt install php php-cli php-curl php-fpm unzip nginx -y
```

---

## 📂 Step 2: Setup Directory

```bash
sudo mkdir -p /opt/amp-status
cd /opt/amp-status
```

Upload the following files to this directory:

- `amp-status-relay.php`
- `.env`
- `relay.json`

---

## 📝 Step 3: Configure `.env`

Create a long key, and change it regularly.
```env
AMP_SHARED_KEY=YourSecureKeyHere
```

---

## ⚙️ Step 4: Configure `relay.json`

```json
{
  "logging": false,  <-------- Logging to /opt/amp-status/amp-relay.log
  "instances": {
    "Minecraft: Server Name": {  <-------- Displays on card
      "host": "http://127.0.0.1:8081/",  <-------- Should always be http://127.0.0.1:xxxx the port numbers for the instances usually start at 8081 and go up
      "username": "api_user", <-------- A user you create in AMP that you should restrict permissions for via a role
      "password": "api_pass", <-------- Make it a decent password and don't be afraid to change it occasionally
      "ip_port": "serverip:port", <-------- Displays on the card, leave blank to hide it.
      "connect": "steam://run/440900//+connect%20123.45.67.8:9098", <-------- A link displayed on the card as 🔗 Connect, leave blank to hide it.
      "alias": "mcserver"  <-------- Used for the shortcode [amp_status alias="mcserver"]
    },
    "Rust: Server Name": {
      "host": "http://127.0.0.1:8082/",
      "username": "api_user",
      "password": "api_pass",
      "ip_port": "",
      "connect": "",
      "alias": "rustserver"
    }
  }
}
```

---

## 🔐 Step 5: Set Permissions

```bash
sudo chown -R www-data:www-data /opt/amp-status
sudo chmod -R 750 /opt/amp-status
```

**Important:** Make sure your web server user (e.g., `www-data`) can read/write to the directory.

---

## ⚙️ Step 6: Configure Nginx for PHP

Ensure your Nginx site config contains a block like this or is uncommented to process PHP files:

```nginx
Can be located in /etc/nginx/sites-available/default
	# pass PHP scripts to FastCGI server
	#
	location ~ \.php$ {
	include snippets/fastcgi-php.conf;
	fastcgi_pass unix:/run/php/php8.2-fpm.sock; # Adjust PHP version as needed.
	}
```

Without this, PHP files like `amp-status-relay.php` will not be executed.

---

## 📄 Step 7: Place the Relay Script

Place the `amp-status-relay.php` file in your web server’s public directory, usually:

```bash
/var/www/html/
```

Make sure it's accessible via the URL:

```
https://panel.yourdomain.xyz/amp-status-relay.php?key=YourSecureKeyHere
```

---

## 📄 Best Practice: Confirm `/opt/amp-status` is Not Publicly Accessible

To verify that sensitive files like `.env`, `relay.json`, or `cache.json` aren't accessible via the web:

```bash
curl -I https://yourdomain.com/opt/amp-status/.env
```

If you get `403 Forbidden` or `404 Not Found`, you're safe. If you see `200 OK`, **fix immediately**.

If you *are* using `/opt/amp-status` within your web root (not recommended), block it with Nginx:

```nginx
location ~* /\.(env|json|log)$ {
    deny all;
    return 403;
}
```

---

# AMP Server Status WordPress Plugin Setup Guide

This guide walks you through installing and configuring the `amp-status.php` plugin on your WordPress site to display real-time AMP server data.

---

## 🧰 Requirements

- WordPress site  
- AMP Status Relay already running  
- PHP 7.4+ with `curl` enabled

---

## 📁 Step 1: Create the Plugin

1. Log into your WordPress site via FTP or file manager.
2. Navigate to:

   ```
   wp-content/plugins/
   ```
3. Create a new folder:

   ```
   amp-status
   ```
4. Inside the new folder, upload or create a file named:

   ```
   amp-status.php
   ```
5. If you created a new file instead of uploading. Open amp-status.php with a text editor and copy/paste the code into this file.

---

## ⚙️ Step 2: Activate the Plugin

1. Log in to your WordPress Admin Dashboard.
2. Go to:

   ```
   Plugins > Installed Plugins
   ```
3. Find **AMP Server Status** and click **Activate**.

---

## 🛠 Step 3: Configure the Plugin

1. Go to:

   ```
   Settings > AMP Server Status
   ```

2. Fill in the following fields:

   - **Relay URL:** Full URL to your AMP relay (e.g., `https://panel.example.com/amp-status-relay.php`)
   - **Relay Key:** Secure key from your `.env` file on the AMP server

3. Click **Save Changes** to apply settings.

---

## 🥪 Step 4: Use the Shortcode

Place this shortcode anywhere on your WordPress site (alias configured in the relay.json in /opt/amp-status):

```shortcode
[amp_status alias="mcserver"]
```

Recommended usage:

- Add a new page or block in Elementor or the Classic Editor  
- Paste the shortcode where you want the AMP status cards to appear

---

## ✅ Done!

You should now see server status cards generated by the AMP Relay data on your site.

---

## ❓ Optional

1. Go to:

   ```
   Plugins > Plugin File Editor (select the correct plugin file!)
   ```

2. Edit the style of the status cards here.
