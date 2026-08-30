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

## Home Assistant

Localkit integrates with Home Assistant over MQTT, automatically exposing your Petkit devices as entities via MQTT discovery. See the [Home Assistant integration guide](https://localkit.io/overview/homeassistant.html) for setup details.

## Documentation

For full setup instructions, the complete list of exposed entities, and detailed documentation, visit [localkit.io](https://localkit.io).
