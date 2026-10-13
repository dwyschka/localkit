# T6 — Purobot Ultra, demo device

The order matches a real boot: without `DevSignup` there is no row in
`devices`, and every other request ends in `firstOrFail()` with a 404.

| File | |
| --- | --- |
| `DevSignup.http` | creates the device. `device_type` comes **from the path** (`/6/t6/…`), not from the header — the `t6` there is what `Device::definition()` maps to `PurobotUltra` |
| `DevServerinfo.http` | where the device talks to afterwards |
| `DevDeviceInfo.http` | the big config response (`toDeviceInfo()`), where the 44 settings end up |
| `DevMultiConfig.http` | the MultiRange blocks |
| `DevFeedGet.http` | the schedule — litter boxes use the same `feed` mechanism |
| `DevStateReport.http` | state query |
| `Heartbeat.http` | poll loop, with real signature calculation |

All values are **made up**: `id`, `sn`, the MACs and `secret` are demo data in
a valid format. The device is identified by `id` from the `X-Device` header
(`PetkitHeader::petkitId`) — the server does not verify the signature, it is
only there so the requests look like the real ones.

`firmware=951` is the version the T6 integration was read from.
