# Localkit

<p align="center">
<img src="resources/images/logo.svg" width="150px">
</p>


This project aims to provide local control of Petkit devices. It communicates directly with your devices on your local network and creates entities in Home Assistant over MQTT for seamless integration.

## Requirements

As an internal MQTT broker, [localkit-broker](https://github.com/dwyschka/localkit-broker) is required for the system to function correctly.

## Supported Devices

**Litter boxes**
- **Petkit Pura Max** (`t4`)
- **Petkit Purobot Crystal** (`t7`)
- **Petkit Purobot Ultra** (`t6`)

**Feeders**
- **Petkit Fresh Element 3** (`d3`)
- **Petkit Fresh Element Solo** (`d4`)
- **Petkit Yumshare Solo** (`d4h`)
- **Petkit Yumshare Dual** (`d4sh`)

**Water fountains**
- **Petkit Eversweet Ultra** (`w7h`)

**Bluetooth accessories**
- **K3 Spray** (`k3`)
- **Eversweet Fountain** (`w5`)

### Everything else

A device whose type is not in that list is still registered, served and kept online: sign-up, server
info, device info and the heartbeat are answered with the parts of the protocol that do not depend on
the type, so the device stays on this server instead of going looking for PetKit's cloud. It shows up
in the device list as *Unknown Device (`<type>`)* with an **unknown device** badge, and that is where
it ends — no actions, no settings, no state reporting, because none of that is known for a type we
have never seen.

What it does give you is a starting point: everything such a device publishes over MQTT is written to
`storage/logs/localkit_unknown_topics.log`, which is the raw material for adding real support for it.

## Provisioning

New devices can be pointed at Localkit over Bluetooth, straight from the panel — no PetKit app, no
cloud account and no DNS redirect. Open **Provisioning**, put the device into pairing mode, and hand
it your WiFi credentials, this server's address and its timezone.

Web Bluetooth needs a secure page and a Chromium-based browser: reach Localkit over HTTPS (the
container serves it on 443) or via `localhost`, in Chrome or Edge on desktop or Android. The page
says so itself when either is missing.

Credentials go from the browser to the device directly and never reach this server. If every device
joins the same network, set `LOCALKIT_PROVISIONING_WIFI_SSID` and `LOCALKIT_PROVISIONING_WIFI_PASSWORD`
in your `.env` and the form opens with them filled in.

The Bluetooth provisioning protocol — both the Ingenic (`0xAAA0`) and ESP32/BLUFI (`0xFFFF`)
variants — was mapped by [petkit-local](https://github.com/alex-so-3/petkit-local); the
implementation here is our own.

## Home Assistant

Localkit integrates with Home Assistant over MQTT, automatically exposing your Petkit devices as entities via MQTT discovery. See the [Home Assistant integration guide](https://localkit.io/overview/homeassistant.html) for setup details.

## Documentation

For full setup instructions, the complete list of exposed entities, and detailed documentation, visit [localkit.io](https://localkit.io).
