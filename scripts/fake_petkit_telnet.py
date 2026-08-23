#!/usr/bin/env python3
"""
Fake PetKit Telnet Listener
Simulates a PetKit BusyBox/Linux device over Telnet (default port 23)
to test LocalKit's web installer and device management actions without physical hardware.
"""

import sys
import socket
import threading
import time
import argparse
import re
import signal
import urllib.request
import urllib.error


import os
from pathlib import Path


def load_env(env_path: str = None) -> dict[str, str]:
    """Parse key-value pairs from a .env file."""
    env = {}
    if not env_path:
        candidates = [
            Path.cwd() / ".env",
            Path(__file__).resolve().parent / ".env",
            Path(__file__).resolve().parent.parent / ".env",
        ]
        for candidate in candidates:
            if candidate.is_file():
                env_path = str(candidate)
                break

    if env_path and os.path.isfile(env_path):
        with open(env_path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                if "=" in line:
                    k, v = line.split("=", 1)
                    k = k.strip()
                    v = v.strip().strip("'\"")
                    env[k] = v
    return env


def sigint_handler(sig, frame):
    print("\n[*] Stopping Fake Telnet Server...")
    sys.exit(0)


try:
    signal.signal(signal.SIGINT, sigint_handler)
    if hasattr(signal, "SIGTERM"):
        signal.signal(signal.SIGTERM, sigint_handler)
except Exception:
    pass


def check_url(url: str, timeout: float = 4.0) -> tuple[bool, str]:
    """Check if a URL can be retrieved via HTTP GET (simulating real wget on device)."""
    try:
        req = urllib.request.Request(
            url,
            headers={"User-Agent": "Wget/1.20.3 (linux-gnu)"}
        )
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            if resp.status == 200:
                return True, ""
            return False, f"wget: server returned error: HTTP/1.1 {resp.status} {resp.reason}"
    except urllib.error.HTTPError as e:
        return False, f"wget: server returned error: HTTP/1.1 {e.code} {e.reason}"
    except urllib.error.URLError as e:
        return False, f"wget: can't connect to remote host: {e.reason}"
    except Exception as e:
        return False, f"wget: error: {e}"


def get_d4sh_user_conf_json() -> str:
    """Anonymized real PetKit D4SH user.conf JSON."""
    return (
        "{\r\n"
        '\t"idInfo":\t{\r\n'
        '\t\t"dev_id":\t10000001,\r\n'
        '\t\t"dev_srt":\t"0123456789abcdef"\r\n'
        '\t},\r\n'
        '\t"wifiInfo":\t{\r\n'
        '\t\t"ssid":\t"LocalKit-WiFi",\r\n'
        '\t\t"pwd":\t"LocalKitPassword123"\r\n'
        '\t},\r\n'
        '\t"serverInfo":\t{\r\n'
        '\t\t"apiServers":\t["http://api-eu.petkt.com/6/", "", ""],\r\n'
        '\t\t"ipServers":\t["", "", ""],\r\n'
        '\t\t"dns":\t["", "", ""],\r\n'
        '\t\t"nextTick":\t3600,\r\n'
        '\t\t"linked":\t1\r\n'
        '\t},\r\n'
        '\t"ircut":\t{\r\n'
        '\t\t"d2n_iso":\t1200000,\r\n'
        '\t\t"lux_val":\t7500,\r\n'
        '\t\t"cut_lux":\t58000,\r\n'
        '\t\t"iso_offset":\t30000,\r\n'
        '\t\t"n2d_cut_iso":\t400000,\r\n'
        '\t\t"n2d_cut_gb":\t530,\r\n'
        '\t\t"n2d_gb_offset":\t8,\r\n'
        '\t\t"lock_time":\t3600,\r\n'
        '\t\t"ir_off_time":\t3600,\r\n'
        '\t\t"print":\t0,\r\n'
        '\t\t"version":\t1\r\n'
        '\t},\r\n'
        '\t"petbodydet_input_set":\t{\r\n'
        '\t\t"print_pet":\t0\r\n'
        '\t},\r\n'
        '\t"user_info":\t{\r\n'
        '\t\t"userId":\t1,\r\n'
        '\t\t"timezone":\t-4,\r\n'
        '\t\t"locale":\t"America/New_York",\r\n'
        '\t\t"language":\t"en_US"\r\n'
        '\t},\r\n'
        '\t"app_conf":\t{\r\n'
        '\t\t"timestamp_enable":\t1,\r\n'
        '\t\t"irlight_enable":\t1,\r\n'
        '\t\t"mic_enable":\t1,\r\n'
        '\t\t"factor1":\t1,\r\n'
        '\t\t"factor2":\t1,\r\n'
        '\t\t"foodWarn":\t0,\r\n'
        '\t\t"foodWarnRange":\t[480, 1200],\r\n'
        '\t\t"lightMode":\t1,\r\n'
        '\t\t"lightMultiRange":\t[[0, 1440]],\r\n'
        '\t\t"toneMode":\t0,\r\n'
        '\t\t"toneMultiRange":\t[[1320, 360]],\r\n'
        '\t\t"manualLock":\t0,\r\n'
        '\t\t"sche_enable":\t1,\r\n'
        '\t\t"CTime":\t0,\r\n'
        '\t\t"camera_enable":\t1,\r\n'
        '\t\t"cameraMultiRange":\t[[0, 1440]],\r\n'
        '\t\t"cameraRangeTable":\t[{\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}],\r\n'
        '\t\t"moveDetection":\t1,\r\n'
        '\t\t"moveSensitivity":\t1,\r\n'
        '\t\t"petDetection":\t1,\r\n'
        '\t\t"petSensitivity":\t3,\r\n'
        '\t\t"eatDetection":\t1,\r\n'
        '\t\t"eatSensitivity":\t3,\r\n'
        '\t\t"vomitDetection":\t0,\r\n'
        '\t\t"detectInterval":\t0,\r\n'
        '\t\t"detectMultiRange":\t[[0, 1440]],\r\n'
        '\t\t"feedPicture":\t1,\r\n'
        '\t\t"eatVideo":\t1,\r\n'
        '\t\t"soundEnable":\t0,\r\n'
        '\t\t"systemSoundEnable":\t0,\r\n'
        '\t\t"feedSound":\t1,\r\n'
        '\t\t"volume":\t9,\r\n'
        '\t\t"selectedSound":\t-1,\r\n'
        '\t\t"surplusControl":\t0,\r\n'
        '\t\t"surplusStandard":\t2,\r\n'
        '\t\t"smartFrame":\t1,\r\n'
        '\t\t"upload":\t1,\r\n'
        '\t\t"log_upload":\t1,\r\n'
        '\t\t"attireId":\t-1,\r\n'
        '\t\t"logo_cn":\t0,\r\n'
        '\t\t"capacity":\t[]\r\n'
        '\t},\r\n'
        '\t"other":\t{\r\n'
        '\t\t"log_level":\t5,\r\n'
        '\t\t"logSaveFlag":\t1,\r\n'
        '\t\t"ali_or_oci":\t1,\r\n'
        '\t\t"minArea":\t6000,\r\n'
        '\t\t"minScore":\t25,\r\n'
        '\t\t"mtusize":\t1450,\r\n'
        '\t\t"IspHz":\t50,\r\n'
        '\t\t"rpt_batV":\t4116,\r\n'
        '\t\t"foodFontPrint":\t0,\r\n'
        '\t\t"accDomainTime":\t0,\r\n'
        '\t\t"OMS_dis_RTC":\tfalse,\r\n'
        '\t\t"last_reset_time":\t0,\r\n'
        '\t\t"trackerLimit":\t20,\r\n'
        '\t\t"trackerInterval":\t20\r\n'
        '\t}\r\n'
        "}"
    )


def get_w7h_user_conf_json() -> str:
    """Anonymized real PetKit W7H user.conf JSON."""
    return (
        "{\r\n"
        '\t"idInfo":\t{\r\n'
        '\t\t"dev_id":\t10000002,\r\n'
        '\t\t"dev_srt":\t"0123456789abcdef",\r\n'
        '\t\t"agency":\t0\r\n'
        '\t},\r\n'
        '\t"wifiInfo":\t{\r\n'
        '\t\t"ssid":\t"LocalKit-WiFi",\r\n'
        '\t\t"pwd":\t"LocalKitPassword123"\r\n'
        '\t},\r\n'
        '\t"serverInfo":\t{\r\n'
        '\t\t"apiServers":\t["http://api-eu.petkt.com/6/", "", ""],\r\n'
        '\t\t"ipServers":\t["", "", ""],\r\n'
        '\t\t"dns":\t["", "", "", "", "", "", "", "", "", ""],\r\n'
        '\t\t"nextTick":\t3600,\r\n'
        '\t\t"linked":\t1\r\n'
        '\t},\r\n'
        '\t"ircut":\t{\r\n'
        '\t\t"d2n_iso":\t2600000,\r\n'
        '\t\t"lux_val":\t7000,\r\n'
        '\t\t"cut_lux":\t58000,\r\n'
        '\t\t"iso_offset":\t28000,\r\n'
        '\t\t"n2d_iso":\t20000,\r\n'
        '\t\t"n2d_cut_iso":\t450000,\r\n'
        '\t\t"n2d_cut_gb":\t530,\r\n'
        '\t\t"n2d_gb_offset":\t8,\r\n'
        '\t\t"lock_time":\t3600,\r\n'
        '\t\t"ir_off_time":\t3600,\r\n'
        '\t\t"print":\t0,\r\n'
        '\t\t"version":\t3\r\n'
        '\t},\r\n'
        '\t"petbodydet_input_set":\t{\r\n'
        '\t\t"print_pet":\t0\r\n'
        '\t},\r\n'
        '\t"user_info":\t{\r\n'
        '\t\t"userId":\t1,\r\n'
        '\t\t"timezone":\t0,\r\n'
        '\t\t"locale":\t"America/New_York",\r\n'
        '\t\t"language":\t"en_US"\r\n'
        '\t},\r\n'
        '\t"weigh_calibr":\t{\r\n'
        '\t\t"zero_val":\t939550,\r\n'
        '\t\t"low_val":\t959550,\r\n'
        '\t\t"high_val":\t979550,\r\n'
        '\t\t"slop_l":\t2.0499999523162842,\r\n'
        '\t\t"slop_h":\t2.0499999523162842,\r\n'
        '\t\t"temp_cal":\t5,\r\n'
        '\t\t"water_l_cal":\t0,\r\n'
        '\t\t"water_h_cal":\t0\r\n'
        '\t},\r\n'
        '\t"go_wc_cnt":\t{\r\n'
        '\t\t"count":\t0,\r\n'
        '\t\t"zero_timestamp":\t1700000000\r\n'
        '\t},\r\n'
        '\t"app_conf":\t{\r\n'
        '\t\t"timestamp_enable":\t1,\r\n'
        '\t\t"night":\t1,\r\n'
        '\t\t"microphone":\t1,\r\n'
        '\t\t"microLight":\t1,\r\n'
        '\t\t"cameraLight":\t1,\r\n'
        '\t\t"addWaterSwitch":\t1,\r\n'
        '\t\t"addWaterMode":\t2,\r\n'
        '\t\t"autoWaterChange":\t1,\r\n'
        '\t\t"waterChangeCycle":\t1,\r\n'
        '\t\t"waterChangeTime":\t0,\r\n'
        '\t\t"lightMode":\t1,\r\n'
        '\t\t"lightMultiRange":\t[[19, 19]],\r\n'
        '\t\t"smartFrame":\t1,\r\n'
        '\t\t"vomitDetection":\t0,\r\n'
        '\t\t"cleanWaterLackLight":\t1,\r\n'
        '\t\t"cleanWaterEmptyLight":\t1,\r\n'
        '\t\t"wasteWaterFullLight":\t1,\r\n'
        '\t\t"wlDisturbMode":\t0,\r\n'
        '\t\t"wlDisturbMultiRange":\t[[19, 19]],\r\n'
        '\t\t"toneMode":\t1,\r\n'
        '\t\t"toneMultiRange":\t[[19, 19]],\r\n'
        '\t\t"awDisturbMode":\t0,\r\n'
        '\t\t"awDisturbMultiRange":\t[[19, 19]],\r\n'
        '\t\t"manualLock":\t0,\r\n'
        '\t\t"camera":\t1,\r\n'
        '\t\t"cameraMultiRange":\t[[0, 1440]],\r\n'
        '\t\t"lightAssist":\t1,\r\n'
        '\t\t"lightAssistMultiRange":\t[[0, 1440]],\r\n'
        '\t\t"distrubMultiRange":\t[[19, 19]],\r\n'
        '\t\t"wifiLightAssistMultiRange":\t[],\r\n'
        '\t\t"cameraRangeTable":\t[{\r\n'
        '\t\t\t\t"wday":\t0,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t1,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t2,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t3,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t4,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t5,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t6,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}],\r\n'
        '\t\t"lightRangeTable":\t[{\r\n'
        '\t\t\t\t"wday":\t0,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t1,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t2,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t3,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t4,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t5,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t6,\r\n'
        '\t\t\t\t"rangeSub":\t[0]\r\n'
        '\t\t\t}],\r\n'
        '\t\t"toiletLightRangeTable":\t[{\r\n'
        '\t\t\t\t"wday":\t0,\r\n'
        '\t\t\t\t"rangeSub":\t[]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t1,\r\n'
        '\t\t\t\t"rangeSub":\t[]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t2,\r\n'
        '\t\t\t\t"rangeSub":\t[]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t3,\r\n'
        '\t\t\t\t"rangeSub":\t[]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t4,\r\n'
        '\t\t\t\t"rangeSub":\t[]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t5,\r\n'
        '\t\t\t\t"rangeSub":\t[]\r\n'
        '\t\t\t}, {\r\n'
        '\t\t\t\t"wday":\t6,\r\n'
        '\t\t\t\t"rangeSub":\t[]\r\n'
        '\t\t\t}],\r\n'
        '\t\t"moveDetection":\t0,\r\n'
        '\t\t"moveSensitivity":\t0,\r\n'
        '\t\t"detectInterval":\t0,\r\n'
        '\t\t"detectMultiRange":\t[],\r\n'
        '\t\t"soundEnable":\t1,\r\n'
        '\t\t"systemSoundEnable":\t0,\r\n'
        '\t\t"volume":\t5,\r\n'
        '\t\t"selectedSound":\t-1,\r\n'
        '\t\t"upload":\t1,\r\n'
        '\t\t"disturbMode":\t0,\r\n'
        '\t\t"wifiLightAssist":\t0,\r\n'
        '\t\t"preLive":\t0,\r\n'
        '\t\t"petDetection":\t1,\r\n'
        '\t\t"voice":\t0,\r\n'
        '\t\t"log_upload":\t1,\r\n'
        '\t\t"drinkDetection":\t1,\r\n'
        '\t\t"flushIntensity":\t2,\r\n'
        '\t\t"autoFlush":\t1,\r\n'
        '\t\t"flushTime":\t0,\r\n'
        '\t\t"flushCycle":\t1,\r\n'
        '\t\t"fountainMode":\t3,\r\n'
        '\t\t"fountainTime":\t3,\r\n'
        '\t\t"sleepTime":\t3,\r\n'
        '\t\t"heaterSwitch":\t0,\r\n'
        '\t\t"targetTemp":\t300,\r\n'
        '\t\t"capacity":\t[]\r\n'
        '\t},\r\n'
        '\t"other":\t{\r\n'
        '\t\t"log_level":\t5,\r\n'
        '\t\t"logSaveFlag":\t1,\r\n'
        '\t\t"ali_or_oci":\t1,\r\n'
        '\t\t"minArea":\t0,\r\n'
        '\t\t"minScore":\t0,\r\n'
        '\t\t"petCloseArea":\t100000,\r\n'
        '\t\t"trackerLimit":\t20,\r\n'
        '\t\t"trackerInterval":\t20,\r\n'
        '\t\t"mtusize":\t1450,\r\n'
        '\t\t"p2p_mtuSize":\t1400,\r\n'
        '\t\t"p2p_connType":\t0,\r\n'
        '\t\t"IspHz":\t60,\r\n'
        '\t\t"rpt_batV":\t0,\r\n'
        '\t\t"foodFontPrint":\t0,\r\n'
        '\t\t"sprayused":\t0,\r\n'
        '\t\t"sprayResetTime":\t0,\r\n'
        '\t\t"proxDisable":\t0,\r\n'
        '\t\t"sandTray_type":\t0\r\n'
        '\t},\r\n'
        '\t"user_sta":\t{\r\n'
        '\t\t"dev_sw":\t1,\r\n'
        '\t\t"need_clean_cnt":\t0,\r\n'
        '\t\t"reset_MCU_utc":\t0,\r\n'
        '\t\t"clean_water_add_f":\t0,\r\n'
        '\t\t"w7_err_info":\t{\r\n'
        '\t\t\t"tary_over_f":\t0,\r\n'
        '\t\t\t"tank_cle_low_f":\t0,\r\n'
        '\t\t\t"last_add_over_time":\t0\r\n'
        '\t\t},\r\n'
        '\t\t"flush_cycle_info":\t{\r\n'
        '\t\t\t"autoFlush":\t1,\r\n'
        '\t\t\t"flushCycle":\t1,\r\n'
        '\t\t\t"flushTime":\t0,\r\n'
        '\t\t\t"next_timed_flush_time":\t1700000000,\r\n'
        '\t\t\t"timezone_sec":\t0\r\n'
        '\t\t},\r\n'
        '\t\t"change_cycle_info":\t{\r\n'
        '\t\t\t"autoChange":\t1,\r\n'
        '\t\t\t"changeCycle":\t1,\r\n'
        '\t\t\t"changeTime":\t0,\r\n'
        '\t\t\t"next_timed_change_time":\t1700000000,\r\n'
        '\t\t\t"timezone_sec":\t0\r\n'
        '\t\t}\r\n'
        '\t}\r\n'
        "}"
    )


def get_model_user_conf(model: str) -> str:
    if model.upper() == "W7H":
        return get_w7h_user_conf_json()
    return get_d4sh_user_conf_json()


def execute_single_command(cmd: str, state: dict, send_raw, client_ip: str, model: str, should_fail: bool) -> tuple[int, bool]:
    """Execute a single shell command and return (exit_code, should_close_connection)."""
    cmd = cmd.strip()
    if not cmd:
        return 0, False

    # 1. exit
    if cmd == "exit":
        return 0, True

    # 2. reboot
    if cmd.startswith("reboot"):
        print("    [*] Simulating system reboot...")
        send_raw("The system is going down for reboot NOW!\r\n")
        send_raw("Sent SIGTERM to all processes\r\n")
        send_raw("Sent SIGKILL to all processes\r\n")
        send_raw("Requesting system reboot\r\n")
        time.sleep(0.4)
        return 0, True

    # 3. pktool
    if cmd.startswith("pktool"):
        parts = cmd.split()
        if len(parts) >= 4 and parts[1] == "cfg" and parts[2] == "dump":
            src = parts[3]
            dst = parts[4] if len(parts) > 4 else "/tmp/cfg"
            if "dev.conf" in src:
                # Dumps device info to destination
                return 0, False
            elif "user.conf" in src:
                cfg_line = "config.c:255" if model.upper() == "W7H" else "config.c:254"
                upd_line = "pk_update_dev.c:413" if model.upper() == "W7H" else "pk_update_dev.c:428"
                output = (
                    "shm_open O_RDWR\r\n"
                    f"inputPath({src}) outputPath({dst})\r\n"
                    f"\033[33mI[1700000000][petkit][{cfg_line}]\033[0m> [is encrypted] <\r\n"
                    f"\033[33mI[1700000000][petkit][{upd_line}]\033[0m\r\n"
                    "a1b2c3d4e5f60718293a4b5c6d7e8f90\r\n"
                    + get_model_user_conf(model) + "\r\n"
                )
                send_raw(output)
                return 0, False
            else:
                send_raw("shm_open O_RDWR\r\n")
                return 0, False

        elif len(parts) >= 4 and parts[1] == "cfg" and parts[2] == "save":
            src = parts[3]
            dst = parts[4] if len(parts) > 4 else "/opt/user.conf"
            if model.upper() == "W7H":
                output = (
                    "shm_open O_RDWR\r\n"
                    f"readFromPath({src}) saveToPath({dst})\r\n"
                    f"\033[33mI[1700000000][petkit][pk_update_dev.c:225]\033[0mupdate {dst} file\r\n"
                    "\033[33mI[1700000000][petkit][pk_update_dev.c:294]\033[0m-------- usr_config_save --------\r\n"
                    "--------md5is:b2c3d4e5f60718293a4b5c6d7e8f90a1\r\n"
                    f"\033[0mD[1700000000][petkit][config.c:659]\033[0mneed lock it, cp ({dst}) to (/param/user.conf)\r\n"
                    f"\033[33mI[1700000000][petkit][config.c:667]\033[0mconfig lock: lock_fd=3, f({dst}) to (/param/user.conf)\r\n"
                    f"\033[0mD[1700000000][petkit][config.c:724]\033[0mSuccessfully copied config from {dst} to /param/user.conf with matching MD5: c3d4e5f60718293a4b5c6d7e8f90a1b2\r\n"
                    "save_success!!!\r\n"
                )
            else:
                output = (
                    "shm_open O_RDWR\r\n"
                    f"readFromPath({src}) saveToPath({dst})\r\n"
                    f"\033[33mI[1700000000][petkit][pk_update_dev.c:239]\033[0mupdate {dst} file\r\n"
                    "\033[33mI[1700000000][petkit][pk_update_dev.c:309]\033[0m-------- usr_config_save --------\r\n"
                    "--------md5is:b2c3d4e5f60718293a4b5c6d7e8f90a1\r\n"
                    f"\033[0mD[1700000000][petkit][config.c:1067]\033[0mneed lock it, cp ({dst}) to (/param/user.conf)\r\n"
                    f"\033[33mI[1700000000][petkit][config.c:1075]\033[0mconfig lock: lock_fd=3, f({dst}) to (/param/user.conf)\r\n"
                    f"\033[0mD[1700000000][petkit][config.c:1132]\033[0mSuccessfully copied config from {dst} to /param/user.conf with matching MD5: c3d4e5f60718293a4b5c6d7e8f90a1b2\r\n"
                    "save_success!!!\r\n"
                )
            send_raw(output)
            return 0, False
        else:
            send_raw("pktool v1.2.0 (PetKit Device Tool)\r\n")
            return 0, False

    # 4. echo
    if cmd.startswith("echo"):
        m = re.match(r"^echo\s*(?:-n\s*)?(.*)$", cmd)
        text = m.group(1).strip() if m else ""
        if (text.startswith('"') and text.endswith('"')) or (text.startswith("'") and text.endswith("'")):
            text = text[1:-1]
        send_raw(f"{text}\r\n")
        return 0, False

    # 5. sed
    if cmd.startswith("sed"):
        if "/opt/version" in cmd or "version" in cmd:
            send_raw(f'{state["firmware"]}\r\n')
            return 0, False
        return 0, False  # In-place sed -i silently succeeds

    # 6. grep
    if cmd.startswith("grep"):
        if '"name"' in cmd:
            send_raw(f'    "name": "{model}",\r\n')
            return 0, False
        return 0, False

    # 7. cat
    if cmd.startswith("cat"):
        if "/opt/version" in cmd:
            send_raw(f'{{"firmwareVer": "{state["firmware"]}"}}\r\n')
            return 0, False
        elif "/opt/dev.conf" in cmd:
            send_raw(f'{{"name": "{model}", "firmwareVer": "{state["firmware"]}"}}\r\n')
            return 0, False
        elif "user.conf" in cmd or "/tmp/cfg" in cmd:
            send_raw(get_model_user_conf(model) + "\r\n")
            return 0, False
        return 0, False

    # 8. cd
    if cmd.startswith("cd"):
        parts = cmd.split()
        if len(parts) > 1:
            state["cwd"] = parts[1]
        return 0, False

    # 9. pwd
    if cmd == "pwd":
        send_raw(f"{state.get('cwd', '/root')}\r\n")
        return 0, False

    # 10. uname
    if cmd.startswith("uname"):
        send_raw("Linux petkit 3.10.14 #1 PREEMPT MIPS\r\n")
        return 0, False

    # 11. ls
    if cmd.startswith("ls"):
        send_raw("app_init.sh  app_start.sh  ca.crt  ctrl  go2rtc  go2rtc.yaml  logs  watchdog  watchdog.yaml\r\n")
        return 0, False

    # 12. ps
    if cmd.startswith("ps"):
        send_raw(
            "  PID USER       VSZ STAT COMMAND\r\n"
            "    1 root      1240 S    /sbin/init\r\n"
            "  142 root      1860 S    /opt/localkit/ctrl\r\n"
            "  155 root     14200 S    /opt/localkit/go2rtc\r\n"
            "  160 root      1940 S    /opt/localkit/watchdog\r\n"
            "  210 root      1420 S    telnetd -p 23\r\n"
        )
        return 0, False

    # 13. rm, mkdir, chmod, mv, set
    if any(cmd.startswith(prefix) for prefix in ("rm", "mkdir", "chmod", "mv", "set")):
        return 0, False

    # 14. wget
    if cmd.startswith("wget"):
        url_match = re.search(r"https?://[^\s'>]+", cmd)
        url = url_match.group(0) if url_match else ""
        if url:
            ok, err = check_url(url)
            if not ok:
                send_raw(f"{err}\r\n")
                return 1, False
            if "-O-" in cmd or "-qO-" in cmd:
                # wget streaming
                pass
            elif "-O" in cmd or "-q" in cmd:
                filename = url.rsplit("/", 1)[-1]
                if "-q" not in cmd:
                    send_raw(f"Connecting to {url}...\r\n'{filename}' saved\r\n")
        return 0, False

    # 15. Fallback for unrecognized commands
    cmd_name = cmd.split()[0] if cmd else ""
    send_raw(f"-sh: {cmd_name}: not found\r\n")
    return 127, False


def handle_client(conn: socket.socket, addr, model: str = "D4SH", should_fail: bool = False,
                  expected_user: str = None, expected_pass: str = None):
    client_ip, client_port = addr
    print(f"\n[+] New connection from {client_ip}:{client_port}")

    def send_raw(data: str):
        conn.sendall(data.encode("utf-8", errors="replace"))

    def read_line() -> str:
        buf = bytearray()
        conn.settimeout(0.5)
        while True:
            try:
                chunk = conn.recv(1)
            except (socket.timeout, TimeoutError):
                continue
            if not chunk:
                return buf.decode("utf-8", errors="replace").strip()
            if chunk == b"\n":
                return buf.decode("utf-8", errors="replace").strip()
            if chunk != b"\r":
                buf.extend(chunk)

    try:
        # Telnet authentication loop (up to 3 attempts, matching BusyBox telnetd)
        authenticated = False
        attempts = 0

        while not authenticated and attempts < 3:
            attempts += 1
            send_raw("petkit login: ")

            username = read_line()
            print(f"    [Auth] Received Username: {username}")

            send_raw("Password: ")
            password = read_line()
            print(f"    [Auth] Received Password: {'*' * len(password) if password else '(empty)'}")

            # Check credentials if configured in .env
            user_ok = (not expected_user) or (username == expected_user)
            pass_ok = (not expected_pass) or (password == expected_pass)

            if user_ok and pass_ok:
                authenticated = True
                print("    [Auth] Authentication successful.")
            else:
                print("    [Auth] Invalid credentials. Sending 'Login incorrect'.")
                send_raw("\r\nLogin incorrect\r\n\r\n")

        if not authenticated:
            print("    [Auth] Max login attempts exceeded. Disconnecting client.")
            send_raw("Connection closed by foreign host.\r\n")
            return

        # Send Shell Prompt matching real PetKit busybox PS1
        send_raw("[petkit:~]# ")

        session_state = {
            "model": model,
            "firmware": "895" if model == "D4SH" else "456",
            "cwd": "/root",
        }

        # Command loop
        while True:
            full_line = read_line()
            if not full_line:
                break
            print(f"    [Exec] Command: {full_line}")
            send_raw(f"{full_line}\r\n")

            # Special case: Full installer pipeline simulation
            if "wget" in full_line and "install" in full_line and ("|" in full_line or "sh" in full_line):
                print("    [*] Simulating LocalKit Universal Installer execution...")

                url_match = re.search(r"https?://[^\s'>]+", full_line)
                install_url = url_match.group(0) if url_match else f"http://{client_ip}:8080/scripts/install"

                print(f"    [*] Verifying installer URL is reachable: {install_url}")
                ok, err = check_url(install_url)
                if not ok:
                    print(f"    [!] Wget failed for {install_url}: {err}")
                    send_raw(f"{err}\r\n")
                    send_raw("[LocalKit] Error: Command exited with status 1\r\n")
                    break

                version = session_state["firmware"]

                if should_fail:
                    send_raw(f"Detected {model}\r\n")
                    time.sleep(0.3)
                    send_raw(f"Firmware mismatch: got '100', expected '{version}'\r\n")
                    send_raw("[LocalKit] Error: Command exited with status 1\r\n")
                    break

                # 1. Output from universal installer
                send_raw(f"Detected {model}\r\n")
                time.sleep(0.3)

                # 2. Output from model installer (d4sh.sh / w7h.sh)
                send_raw("Creating LOCALKIT_DIR: /opt/localkit\r\n")
                time.sleep(0.05)
                send_raw("Creating LOCALKIT_DIR: /opt/localkit/logs\r\n")
                time.sleep(0.05)
                send_raw("Creating FILES_DIR: /opt/localkit/files\r\n")
                time.sleep(0.05)
                send_raw("\r\nStarting downloads to /opt/localkit/files ...\r\n\r\n")
                time.sleep(0.1)

                files_to_download = [
                    "ctrl_patched",
                    "ca.crt",
                    "go2rtc",
                    "go2rtc.yaml",
                    "app_init.sh",
                    "app_start.sh",
                    "watchdog",
                    "watchdog.yaml",
                ]

                for filename in files_to_download:
                    send_raw(f"[DOWN] {filename}\r\n")
                    time.sleep(0.15)
                    send_raw(f"[ OK ] {filename}\r\n")
                    time.sleep(0.05)

                send_raw("[MOVE] Scripts to Dir\r\n")
                time.sleep(0.15)
                send_raw("\r\nDone. Files are located in: /opt/localkit/files\r\n")
                time.sleep(0.1)

                if model.upper() == "W7H":
                    pktool_output = (
                        "shm_open O_RDWR\r\n"
                        "inputPath(/opt/user.conf) outputPath(/tmp/cfg)\r\n"
                        "\033[33mI[1700000000][petkit][config.c:255]\033[0m> [is encrypted] <\r\n"
                        "\033[33mI[1700000000][petkit][pk_update_dev.c:413]\033[0m\r\n"
                        "a1b2c3d4e5f60718293a4b5c6d7e8f90\r\n"
                        + get_w7h_user_conf_json() + "\r\n"
                        "shm_open O_RDWR\r\n"
                        "readFromPath(/tmp/cfg) saveToPath(/opt/user.conf)\r\n"
                        "\033[33mI[1700000000][petkit][pk_update_dev.c:225]\033[0mupdate /opt/user.conf file\r\n"
                        "\033[33mI[1700000000][petkit][pk_update_dev.c:294]\033[0m-------- usr_config_save --------\r\n"
                        "--------md5is:b2c3d4e5f60718293a4b5c6d7e8f90a1\r\n"
                        "\033[0mD[1700000000][petkit][config.c:659]\033[0mneed lock it, cp (/opt/user.conf) to (/param/user.conf)\r\n"
                        "\033[33mI[1700000000][petkit][config.c:667]\033[0mconfig lock: lock_fd=3, f(/opt/user.conf) to (/param/user.conf)\r\n"
                        "\033[0mD[1700000000][petkit][config.c:724]\033[0mSuccessfully copied config from /opt/user.conf to /param/user.conf with matching MD5: c3d4e5f60718293a4b5c6d7e8f90a1b2\r\n"
                        "save_success!!!\r\n\r\n"
                        "Reset System API Service done\r\n"
                    )
                else:
                    pktool_output = (
                        "shm_open O_RDWR\r\n"
                        "inputPath(/opt/user.conf) outputPath(/tmp/cfg)\r\n"
                        "\033[33mI[1700000000][petkit][config.c:254]\033[0m> [is encrypted] <\r\n"
                        "\033[33mI[1700000000][petkit][pk_update_dev.c:428]\033[0m\r\n"
                        "a1b2c3d4e5f60718293a4b5c6d7e8f90\r\n"
                        + get_d4sh_user_conf_json() + "\r\n"
                        "shm_open O_RDWR\r\n"
                        "readFromPath(/tmp/cfg) saveToPath(/opt/user.conf)\r\n"
                        "\033[33mI[1700000000][petkit][pk_update_dev.c:239]\033[0mupdate /opt/user.conf file\r\n"
                        "\033[33mI[1700000000][petkit][pk_update_dev.c:309]\033[0m-------- usr_config_save --------\r\n"
                        "--------md5is:b2c3d4e5f60718293a4b5c6d7e8f90a1\r\n"
                        "\033[0mD[1700000000][petkit][config.c:1067]\033[0mneed lock it, cp (/opt/user.conf) to (/param/user.conf)\r\n"
                        "\033[33mI[1700000000][petkit][config.c:1075]\033[0mconfig lock: lock_fd=3, f(/opt/user.conf) to (/param/user.conf)\r\n"
                        "\033[0mD[1700000000][petkit][config.c:1132]\033[0mSuccessfully copied config from /opt/user.conf to /param/user.conf with matching MD5: c3d4e5f60718293a4b5c6d7e8f90a1b2\r\n"
                        "save_success!!!\r\n\r\n"
                        "Reset System API Service done\r\n"
                    )
                send_raw(pktool_output)

                if "exit" in full_line or ";" in full_line:
                    print("    [*] Installer finished. Closing connection.")
                    break
                else:
                    send_raw("[petkit:~]# ")
                continue

            # General command handling (support compound commands separated by ';' or '&&')
            sub_commands = re.split(r";|&&", full_line)
            should_close = False

            for sub_cmd in sub_commands:
                sub_cmd = sub_cmd.strip()
                if not sub_cmd:
                    continue
                code, close_conn = execute_single_command(
                    sub_cmd, session_state, send_raw, client_ip, model, should_fail
                )
                if close_conn:
                    should_close = True
                    break
                if code != 0 and "&&" in full_line:
                    break

            if should_close:
                break

            send_raw("[petkit:~]# ")

    except ConnectionResetError:
        print(f"[-] Client {client_ip}:{client_port} disconnected abruptly.")
    except Exception as e:
        print(f"[-] Error handling client {client_ip}:{client_port}: {e}")
    finally:
        try:
            conn.close()
        except Exception:
            pass
        print(f"[-] Connection closed for {client_ip}:{client_port}")


def main():
    parser = argparse.ArgumentParser(
        description="Fake PetKit Telnet Server for LocalKit testing")
    parser.add_argument("--host", default="0.0.0.0",
                        help="Host IP to bind (default: 0.0.0.0)")
    parser.add_argument("--port", "-p", type=int, default=23,
                        help="Port to listen on (default: 23)")
    parser.add_argument("--model", "-m", choices=["D4SH", "W7H"], default="D4SH",
                        type=str.upper, help="PetKit model to simulate (default: D4SH)")
    parser.add_argument("--fail", action="store_true",
                        help="Simulate an installer failure during execution")
    parser.add_argument("--env", help="Path to custom .env file")
    args = parser.parse_args()

    model = args.model
    env_data = load_env(args.env)
    expected_user = env_data.get("DEVICE_TELNET_USERNAME") or env_data.get("PETKIT_TELNET_USERNAME") or env_data.get("TELNET_USERNAME")
    expected_pass = env_data.get("DEVICE_TELNET_PASSWORD") or env_data.get("PETKIT_TELNET_PASSWORD") or env_data.get("TELNET_PASSWORD")

    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)

    try:
        server.bind((args.host, args.port))
    except PermissionError:
        print(f"\n[!] Permission denied binding to port {args.port}.")
        print(
            "[!] On Windows or Linux, binding to port 23 may require Administrator/root privileges.")
        print("[!] Right-click Command Prompt/PowerShell and select 'Run as Administrator', or use a higher port (e.g. -p 2323).\n")
        sys.exit(1)
    except OSError as e:
        print(f"\n[!] Error binding to {args.host}:{args.port} - {e}")
        print(
            "[!] Make sure no other service (or real Telnet server) is occupying this port.\n")
        sys.exit(1)

    server.listen(10)
    server.settimeout(0.5)
    print("=" * 65)
    print(f" Fake PetKit Telnet Server running on {args.host}:{args.port}")
    print(f" Simulating Model: {model} | Mode: {'Simulate Failure' if args.fail else 'Normal / Success'}")
    if expected_user and expected_pass:
        print(f" Auth: Enforcing credentials from .env (user: '{expected_user}')")
    else:
        print(" Auth: No credentials configured in .env (accepts any login)")
    print(" Press Ctrl+C to stop.")
    print("=" * 65)
    print(" Ready to accept connections from LocalKit installer...\n")

    try:
        while True:
            try:
                conn, addr = server.accept()
            except (socket.timeout, TimeoutError):
                continue
            client_thread = threading.Thread(
                target=handle_client,
                args=(conn, addr, model, args.fail, expected_user, expected_pass),
                daemon=True,
            )
            client_thread.start()
    except (KeyboardInterrupt, SystemExit):
        print("\n[*] Stopping Fake Telnet Server...")
    finally:
        try:
            server.close()
        except Exception:
            pass


if __name__ == "__main__":
    main()
