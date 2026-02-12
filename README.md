
# 🌐 MikroTik NAT Sync for Pelican Panel

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![Platform](https://img.shields.io/badge/platform-Pelican%20Panel-orange.svg)
![AI](https://img.shields.io/badge/Created%20with-AI%20Gemini-brightgreen.svg)

**MikroTik NAT Sync** — це плагін для автоматизації прокидання портів (Port Forwarding) між панеллю Pelican та маршрутизаторами MikroTik через REST API.

---

## 🇺🇸 English

### 🚀 Features
* **Full Automation**: Automatically creates/removes DST-NAT rules based on Pelican allocations.
* **Security First**: Define a "Forbidden Ports" list to protect sensitive services (SSH, SFTP, etc.).
* **Smart Tags**: Manages only its own rules using the `Pelican:` comment tag.
* **Easy Setup**: Configure everything (IP, credentials, intervals) directly in the Admin UI.

### 🛠 MikroTik Configuration
Enable the REST API on your router to allow communication:
```Bash
/ip service set www-ssl disabled=no port=9443
```
Note: We recommend creating a dedicated user with specific firewall permissions.

### 📦 Installation / Встановлення

**Method 1: Via Web Interface (Recommended)**
1. Copy the URL of this repository.
2. Go to **Plugins** -> **Import from URL** in your Pelican Admin Panel.
3. Paste the link and click **Import**.
4. Click **Install** and configure via the Gear icon.

**Method 2: Manual (CLI)**
1. Clone this repo to `/var/www/pelican/plugins/mikrotik-nat-sync`.
2. Head to the **Plugins** page and click **Install**.

---

## 🇺🇦 Українською

### 🚀 Можливості
* **Повна автоматизація**: Автоматично керує правилами DST-NAT на основі активних алокацій.

* **Безпека**: Список "Заборонених портів" для захисту системних сервісів.

* **Розумні теги**: Керує лише своїми правилами через коментар Pelican:.

* **Зручне налаштування**: Налаштуйте IP, логін, пароль та інтервали прямо в адмінці.

### 🛠 Налаштування MikroTik
Увімкніть REST API для можливості віддаленого керування:

```Bash
/ip service set www-ssl disabled=no port=9443
```
Порада: Створіть окремого користувача з правами на роботу лише з Firewall.

### 📦 Installation / Встановлення

**Спосіб 1: Через веб-інтерфейс (Рекомендовано)**
1. Скопіюйте посилання на цей репозиторій.
2. Перейдіть у розділ **Plugins** -> **Import from URL** в адмінці Pelican.
3. Вставте посилання та натисніть **Import**.
4. Натисніть **Install** та налаштуйте через іконку шестерні.

**Спосіб 2: Вручну (через консоль)**
1. Клонуйте репозиторій у папку `/var/www/pelican/plugins/mikrotik-nat-sync`.
2. Перейдіть на сторінку **Plugins** та натисніть **Install**.

>Developed with AI Assistance (Gemini) Розроблено за допомогою ШІ (Gemini)
