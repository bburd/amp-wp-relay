> ## ⚠️ **Disclaimer**
>
> I'm not a professional developer — and my commit history probably makes that pretty clear.  
> This project was built by ChatGPT to suit my own needs, and I’m sharing it in case it helps someone else.  
> Use it at your own risk — no guarantees, warranties, or promises of support are provided.

# 🔍 What Is This Project?

This project is a real-time server status monitoring system for instances hosted on **AMP (Application Management Panel)**.

It lets you display the status of your configured instances directly on your WordPress website. You and your community can instantly see whether servers are online, how long they’ve been up, and how much CPU or RAM they’re using — all in a clean, live-updating visual.

![Relay Screenshot](https://i.ibb.co/Kt2ps5m/Screenshot-2025-05-24-231853.png)

---

# ⚙️ How Does It Work?

### AMP Relay Script (runs on your AMP server)

- A PHP file (`amp-status-relay.php`) securely logs into your AMP panel using an API account you set up with restricted permissions.
- It fetches important data like uptime, CPU/RAM usage, and whether the instance and application is running.
- A small file-based cache ensures faster responses and reduced load, using safe file locking.
- It sends that data back in a compact JSON format.
- You configure which AMP instances it should talk to via a simple `relay.json` file.

### WordPress Plugin (runs on your website)

- A plugin reads that JSON data from the relay.
- It saves a copy of the data temporarily to reduce traffic (15 min refresh).
- It displays server status as cards using a shortcode like `[amp_status alias="mcserver"]`.
- It has an admin settings page where you can:
  - Set the relay URL
  - Enter your private key
  - Manually refresh the server data with one click
  - Edit basic card styling settings

---

# 🛡️ Security & Caching Features
The relay uses a shared key you create and update regularly to protect access. This key is stored in a .env file and should never be exposed.

File-based caching (with locking) is used to reduce unnecessary AMP API calls and improve performance. It updates every 15 seconds by default.

Sensitive files (.env, relay.json, cache.json) are stored outside the web root by default and are not publicly accessible.

---

# 🧑‍💻 Who Is This For?

- Game server admins using AMP who want to show off their servers' uptime and health.
- Gaming communities who want to reassure players that servers are up before they join.
- Anyone managing multiple game servers and wants a public status board without using paid services.
- People like me who just like tinkering with projects that are actually over our head 🤪
