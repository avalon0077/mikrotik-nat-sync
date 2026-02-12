Markdown
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
```bash
/ip service set www-ssl disabled=no port=9443
Note: We recommend creating a dedicated user with specific firewall permissions.

📦 Installation
Place the plugin in /var/www/pelican/plugins/mikrotik-nat-sync.

Go to Plugins in the Pelican Admin Panel and click Install.

Configure your connection settings via the Gear icon.

🇺🇦 Українською
🚀 Можливості
Повна автоматизація: Автоматично керує правилами DST-NAT на основі активних алокацій.

Безпека: Список "Заборонених портів" для захисту системних сервісів.

Розумні теги: Керує лише своїми правилами через коментар Pelican:.

Зручне налаштування: Налаштуйте IP, логін, пароль та інтервали прямо в адмінці.

🛠 Налаштування MikroTik
Увімкніть REST API для можливості віддаленого керування:

Bash
/ip service set www-ssl disabled=no port=9443
Порада: Створіть окремого користувача з правами на роботу лише з Firewall.

📦 Встановлення
Скопіюйте плагін у /var/www/pelican/plugins/mikrotik-nat-sync.

Перейдіть у розділ Plugins в адмінці та натисніть Install.

Введіть дані для підключення через іконку шестерні.

Developed with AI Assistance (Gemini) > Розроблено за допомогою ШІ (Gemini)
