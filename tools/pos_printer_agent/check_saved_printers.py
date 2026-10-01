#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import platform
import re
import ssl
import subprocess
import sys
import urllib.parse
import urllib.request
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple

try:
    import serial
except Exception:
    serial = None


BASE_DIR = Path(__file__).resolve().parent
DEFAULT_CONFIG = BASE_DIR / "config.json"
DEFAULT_OUTPUT = BASE_DIR / "printer_connection_check.json"


def load_config(path: Path) -> Dict[str, Any]:
    with path.open("r", encoding="utf-8") as fh:
        return json.load(fh)


def normalize_mac(value: Any) -> str:
    return re.sub(r"[^0-9A-F]", "", str(value or "").upper())


def parse_com_name(text: str) -> str:
    match = re.search(r"\((COM\d+)\)", text or "", re.I)
    return match.group(1).upper() if match else ""


def run_command(cmd: List[str]) -> Dict[str, Any]:
    try:
        completed = subprocess.run(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            encoding="utf-8",
            errors="ignore",
            check=False,
        )
        return {
            "ok": completed.returncode == 0,
            "code": completed.returncode,
            "stdout": completed.stdout.strip(),
            "stderr": completed.stderr.strip(),
        }
    except Exception as exc:
        return {
            "ok": False,
            "code": -1,
            "stdout": "",
            "stderr": str(exc),
        }


def fetch_url_text(url: str, timeout: int = 8, headers: Optional[Dict[str, str]] = None) -> str:
    headers = headers or {}
    req = urllib.request.Request(url)
    for key, value in headers.items():
        req.add_header(key, value)
    errors: List[str] = []
    try:
        ctx = ssl.create_default_context()
        with urllib.request.urlopen(req, timeout=timeout, context=ctx) as response:
            body = response.read()
            if body:
                return body.decode("utf-8", errors="ignore")
            errors.append("urllib-default: empty")
    except Exception as exc:
        errors.append(f"urllib-default: {exc}")
    try:
        ctx = ssl._create_unverified_context()
        with urllib.request.urlopen(req, timeout=timeout, context=ctx) as response:
            body = response.read()
            if body:
                return body.decode("utf-8", errors="ignore")
            errors.append("urllib-unverified: empty")
    except Exception as exc:
        errors.append(f"urllib-unverified: {exc}")
    if platform.system().upper().startswith("WIN"):
        curl = run_command([
            "curl.exe",
            "-sS",
            "-L",
            "--connect-timeout",
            str(timeout),
            "--max-time",
            str(max(timeout + 5, 15)),
            *sum((["-H", f"{k}: {v}"] for k, v in headers.items()), []),
            url,
        ])
        if curl["ok"] and curl["stdout"]:
            return str(curl["stdout"])
        if curl["stderr"]:
            errors.append(f"curl: {curl['stderr']}")
    raise RuntimeError(" ; ".join(errors))


def normalize_printers(rows: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    result: List[Dict[str, Any]] = []
    seen_ports = set()
    for row in rows:
        if not isinstance(row, dict):
            continue
        printer_code = str(row.get("printer_code") or row.get("lokasi") or row.get("printer_name") or "").strip().upper()
        printer_name = str(row.get("printer_name") or printer_code).strip()
        mac_address = normalize_mac(row.get("mac_address") or row.get("mac") or "")
        python_port = int(row.get("python_port") or 0)
        paper_width_mm = 58 if int(row.get("paper_width_mm") or 80) == 58 else 80
        if not printer_code or not mac_address or python_port <= 0:
            continue
        if python_port in seen_ports:
            continue
        seen_ports.add(python_port)
        result.append({
            "printer_code": printer_code,
            "printer_name": printer_name,
            "mac_address": mac_address,
            "python_port": python_port,
            "paper_width_mm": paper_width_mm,
        })
    return result


def bootstrap_printers(config: Dict[str, Any]) -> Tuple[List[Dict[str, Any]], Dict[str, Any]]:
    api = config.get("api") or {}
    if not bool(api.get("enabled", True)):
        return [], {"ok": False, "source": "bootstrap", "message": "api.enabled = false"}
    base_url = str(api.get("base_url") or "").strip()
    if not base_url:
        return [], {"ok": False, "source": "bootstrap", "message": "api.base_url kosong"}
    endpoint = str(api.get("endpoint") or "/pos-printers/bootstrap").strip()
    if not endpoint.startswith("/"):
        endpoint = "/" + endpoint
    alt_endpoint = endpoint if "/index.php/" in endpoint else "/index.php" + endpoint
    agent_param = str(api.get("agent_name_param") or "agent_name").strip()
    api_key = str(api.get("key") or "").strip()
    timeout = int(api.get("timeout_seconds") or 8)
    params = {agent_param: str(config.get("agent_name") or platform.node() or "POS-PRINTER-AGENT-01")}
    headers = {
        "Accept": "application/json",
        "User-Agent": "CorePrinterLocalService/1.0",
    }
    if api_key:
        headers["X-Printer-Key"] = api_key
    attempts: List[str] = []
    for candidate in [endpoint, alt_endpoint]:
        url = base_url.rstrip("/") + candidate
        url += ("&" if "?" in url else "?") + urllib.parse.urlencode(params)
        try:
            raw = fetch_url_text(url, timeout=timeout, headers=headers)
            decoded = json.loads((raw or "").lstrip("\ufeff").strip())
            rows = decoded.get("data") if isinstance(decoded, dict) else decoded
            rows = rows if isinstance(rows, list) else []
            return normalize_printers(rows), {"ok": True, "source": "bootstrap", "url": url}
        except Exception as exc:
            attempts.append(f"{candidate}: {exc}")
    return [], {"ok": False, "source": "bootstrap", "message": " ; ".join(attempts)}


def windows_ports() -> List[Dict[str, Any]]:
    ps = (
        "Get-PnpDevice -Class Ports | "
        "Select-Object FriendlyName,InstanceId,Status,Class | ConvertTo-Json -Depth 3"
    )
    result = run_command(["powershell", "-NoProfile", "-Command", ps])
    if not result["ok"] or not result["stdout"]:
        return []
    rows = json.loads(result["stdout"])
    if isinstance(rows, dict):
        rows = [rows]
    ports: List[Dict[str, Any]] = []
    for row in rows:
        friendly_name = str(row.get("FriendlyName") or "")
        instance_id = str(row.get("InstanceId") or "")
        ports.append({
            "friendly_name": friendly_name,
            "instance_id": instance_id,
            "status": str(row.get("Status") or ""),
            "class": str(row.get("Class") or ""),
            "com_port": parse_com_name(friendly_name),
            "mac_address_guess": normalize_mac(instance_id),
        })
    return ports


def serial_probe(com_port: str) -> Dict[str, Any]:
    if not com_port:
        return {"ok": False, "message": "COM port kosong"}
    if serial is None:
        return {"ok": False, "message": "pyserial belum terpasang"}
    ser = None
    try:
        ser = serial.Serial(com_port, 9600, timeout=1, write_timeout=2)
        return {"ok": True, "message": "COM port bisa dibuka"}
    except Exception as exc:
        return {"ok": False, "message": str(exc)}
    finally:
        try:
            if ser:
                ser.close()
        except Exception:
            pass


def classify_probe_failure(message: str) -> Tuple[str, str]:
    text = str(message or "").strip()
    upper = text.upper()
    if "ELEMENT NOT FOUND" in upper or "1168" in upper:
        return "STALE_PORT", "COM terdaftar di Windows tetapi device Bluetooth belum benar-benar siap/aktif"
    if "SEMAPHORE TIMEOUT PERIOD HAS EXPIRED" in upper or " 121" in upper or "NONE, 121" in upper:
        return "PORT_TIMEOUT", "COM terdeteksi tetapi koneksi Bluetooth serial timeout saat dibuka"
    if "ACCESS IS DENIED" in upper or "PERMISSIONERROR" in upper or " 5)" in upper:
        return "PORT_BUSY", "COM kemungkinan sedang dipakai proses lain atau ditahan driver"
    return "PROBE_FAILED", "COM ditemukan tetapi gagal diuji buka"


def evaluate_printers(printers: List[Dict[str, Any]], ports: List[Dict[str, Any]], probe_open: bool) -> List[Dict[str, Any]]:
    results: List[Dict[str, Any]] = []
    for printer in printers:
        mac = normalize_mac(printer.get("mac_address"))
        matches = [row for row in ports if mac and mac in str(row.get("instance_id") or "").upper()]
        com_port = next((str(row.get("com_port") or "") for row in matches if row.get("com_port")), "")
        record: Dict[str, Any] = {
            "printer_code": printer.get("printer_code"),
            "printer_name": printer.get("printer_name"),
            "mac_address": mac,
            "python_port": int(printer.get("python_port") or 0),
            "paper_width_mm": int(printer.get("paper_width_mm") or 80),
            "port_match_count": len(matches),
            "com_port": com_port,
            "port_rows": matches,
        }
        if not mac:
            record["status"] = "INVALID"
            record["message"] = "MAC address kosong atau tidak valid"
        elif not matches:
            record["status"] = "DISCONNECTED"
            record["message"] = "MAC tidak ditemukan di Windows Ports"
        else:
            record["status"] = "CONNECTED"
            record["message"] = f"MAC ditemukan di {com_port or 'port tanpa COM'}"
            if probe_open:
                probe = serial_probe(com_port)
                record["serial_probe"] = probe
                if probe["ok"]:
                    record["status"] = "READY"
                    record["message"] = f"{record['message']} dan COM bisa dibuka"
                else:
                    status, meaning = classify_probe_failure(str(probe.get("message") or ""))
                    record["status"] = status
                    record["message"] = f"{record['message']} tetapi COM gagal dibuka: {probe['message']} ({meaning})"
        results.append(record)
    return results


def print_summary(payload: Dict[str, Any]) -> None:
    print(f"Source      : {payload['source']['mode']}")
    if payload["source"].get("url"):
        print(f"Bootstrap   : {payload['source']['url']}")
    if payload["source"].get("message"):
        print(f"Info        : {payload['source']['message']}")
    print(f"Generated   : {payload['generated_at']}")
    print("")
    for row in payload["printers"]:
        print(f"[{row['status']}] {row['printer_name']} / {row['printer_code']}")
        print(f"  MAC       : {row['mac_address']}")
        print(f"  Python    : {row['python_port']}")
        print(f"  COM       : {row.get('com_port') or '-'}")
        print(f"  Message   : {row['message']}")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Cek apakah printer tersimpan di config/bootstrap sudah tersambung di Windows")
    parser.add_argument("--config", default=str(DEFAULT_CONFIG), help="Path config.json")
    parser.add_argument("--no-probe-open", action="store_true", help="Jangan coba buka COM port")
    parser.add_argument("--json-out", default=str(DEFAULT_OUTPUT), help="Path output JSON hasil cek")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    config_path = Path(args.config)
    config = load_config(config_path)
    bootstrap_rows, bootstrap_meta = bootstrap_printers(config)
    if bootstrap_rows:
        printers = bootstrap_rows
        source = {
            "mode": "bootstrap",
            "url": bootstrap_meta.get("url"),
        }
    else:
        printers = normalize_printers(list(config.get("printers") or []))
        source = {
            "mode": "config-fallback",
            "message": bootstrap_meta.get("message") or "Bootstrap gagal, memakai daftar printer lokal",
        }
    ports = windows_ports() if platform.system() == "Windows" else []
    results = evaluate_printers(printers, ports, probe_open=not args.no_probe_open)
    payload = {
        "generated_at": datetime.now().isoformat(),
        "config_path": str(config_path),
        "source": source,
        "printer_count": len(results),
        "printers": results,
    }
    output_path = Path(args.json_out)
    with output_path.open("w", encoding="utf-8") as fh:
        json.dump(payload, fh, ensure_ascii=False, indent=2)
        fh.write("\n")
    print_summary(payload)
    print("")
    print(f"JSON saved  : {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
