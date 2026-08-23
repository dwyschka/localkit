# Device Installer (Telnet) & Device Simulation

LocalKit includes a built-in web installer for NextGen PetKit devices (such as **YumShare Dual `D4SH`** and **Smart Fountain / Toilet `W7H`**). It connects to the target device over Telnet, runs the universal installation script, and streams the live terminal output directly into a Linux-styled terminal modal in the web interface.

---

## 1. Overview

The installer automates the setup of LocalKit patches and custom services on supported PetKit devices without requiring manual serial connection or SSH scripts.

```
┌─────────────────┐       1. Telnet Connection (auth)       ┌──────────────────┐
│                 │ ──────────────────────────────────────> │                  │
│  LocalKit Host  │                                         │  PetKit Device   │
│  (Laravel Web)  │ <────────────────────────────────────── │   (BusyBox ash)  │
│                 │       4. Live SSE Output Stream         │                  │
│                 │                                         │                  │
│                 │    2. wget http://<HOST>:8080/scripts/  │                  │
│  /scripts/      │ <────────────────────────────────────── │                  │
│  install        │       install (Universal Detector)      │                  │
└─────────────────┘                                         │                  │
                                                            │                  │
┌─────────────────┐                                         │                  │
│ tool.localkit.io│    3. wget http://tool.localkit.io/...  │                  │
│  (Localkit CDN) │ <────────────────────────────────────── │                  │
│                 │       Downloads Model Payload & Patches │                  │
└─────────────────┘                                         └──────────────────┘
```

### Installation Flow:
1. **Telnet Trigger**: LocalKit connects to the PetKit device over Telnet and issues a one-liner command.
2. **Universal Detection**: The device fetches `/scripts/install` from your local LocalKit host (`PETKIT_LOCAL_IP:8080`) which identifies the hardware model (`D4SH`, `W7H`) using `pktool`.
3. **Payload Download**: The device pulls the model-specific binaries and patched scripts directly from `http://tool.localkit.io/scripts/<model>/1.0.0/install`.
4. **Live Streaming**: All terminal progress, download statuses, and `pktool` config modifications are streamed back in real time via Server-Sent Events (SSE) to the LocalKit web UI.

### Key Capabilities:
- **Universal Model Auto-Detection**: Queries the device's firmware and configuration (`/opt/version`, `/opt/dev.conf`, `pktool`) to automatically install the appropriate model payload.
- **Live SSE Streaming**: Terminal output is streamed in real time to the web UI with full ANSI color code formatting and auto-scrolling.
- **Credential Protection**: Telnet credentials are kept server-side and automatically redacted from all output streams.
- **Reinstall Actions**: Trigger installs from the global "Install Device" button or device row actions.

---

## 2. Configuration (`.env`)

Configure the following variables in your `.env` file:

| Variable | Description | Example |
|---|---|---|
| `PETKIT_LOCAL_IP` | Local LAN IPv4 address of your LocalKit server (must be reachable by the device; **cannot be loopback/127.0.0.1**). | `10.0.0.168` |
| `DEVICE_TELNET_USERNAME` | Telnet username for PetKit NextGen devices (usually `root`). | `root` |
| `DEVICE_TELNET_PASSWORD` | Telnet password for PetKit NextGen devices. | `your_device_password` |

> [!IMPORTANT]
> `PETKIT_LOCAL_IP` is used by the device to download installer files over your local network via `wget`. Do not use `127.0.0.1` or `localhost`, as that resolves to the device itself.

---

## 3. Web UI Usage

1. Open the LocalKit Panel and navigate to **Devices**.
2. **Install a New Device**:
   - Click the **"Install Device"** header button.
   - Enter the target device's LAN IPv4 address.
   - Click **Run Installer** to begin streaming the installation.
3. **Reinstall Existing Device**:
   - On any NextGen device row, open the action menu and select **"Reinstall"**.
   - The device IP is pre-filled automatically.
4. **Reboot Device**:
   - Select **"Reboot (Telnet)"** to cleanly reboot the hardware over Telnet.

---

## 4. Mock PetKit Telnet Server (Development & Testing)

For local development and automated testing without physical PetKit hardware, LocalKit includes a high-fidelity Python-based Telnet daemon simulator located in `scripts/mock_petkit_telnet.py`.

### Features of the Simulator:
- Emulates authentic BusyBox Telnet login prompts (`petkit login: `, `Password: `, `[petkit:~]# `).
- Enforces credentials from `.env` or custom `.env` files.
- Simulates realistic device commands:
  - `pktool cfg dump` & `pktool cfg save` (with authentic firmware log headers and MD5 checksum handling)
  - `cat`, `grep`, `sed`, `mkdir`, `chmod`, `mv`, `rm`, `ps`, `uname`, `reboot`, `exit`
- Verifies that the LocalKit host installer URL is reachable during `wget` execution.
- Accurately replicates both **D4SH** (Feeder) and **W7H** (Fountain/Toilet) firmware structures.

### Launching the Simulator

#### Using PowerShell:
```powershell
# Run D4SH (Feeder) simulation (default):
.\scripts\start_mock_telnet.ps1 d

# Run W7H (Fountain / Toilet) simulation:
.\scripts\start_mock_telnet.ps1 w

# Custom port or failure simulation:
.\scripts\start_mock_telnet.ps1 -Model W7H -Port 2323
.\scripts\start_mock_telnet.ps1 -Model D4SH -Fail
```

#### Direct Python Invocation:
```bash
# Run D4SH simulation (default port 23):
python3 scripts/mock_petkit_telnet.py --model D4SH --port 23

# Run W7H simulation:
python3 scripts/mock_petkit_telnet.py --model W7H --port 23

# Simulate an install failure:
python3 scripts/mock_petkit_telnet.py --fail
```

---

## 5. Automated Tests

Run the test suite to verify Telnet services, sanitization, and installer endpoints:

```bash
docker exec Localkit php artisan test --filter=Telnet
docker exec Localkit php artisan test --filter=Installer
```

