#!/usr/bin/env python3
from __future__ import annotations

import argparse
import base64
import io
import json
import logging
from logging.handlers import RotatingFileHandler
import os
import platform
import re
import subprocess
import sys
import threading
import time
import urllib.parse
import urllib.request
import urllib.error
import ssl
from pathlib import Path
from typing import Any, Callable, Dict, List, Optional

from flask import Flask, jsonify, make_response, request

try:
    import serial
except Exception:
    serial = None

try:
    from PIL import Image
except Exception:
    Image = None

try:
    import qrcode
except Exception:
    qrcode = None

BASE_DIR = Path(__file__).resolve().parent
DEFAULT_CONFIG = BASE_DIR / "config.json"
DEFAULT_LOG = BASE_DIR / "agent.log"
AGENT_VERSION = "1.2.0"
# Protocol is deliberately independent from the application release number.
# A server advertises its supported range in bootstrap; a newer server can then
# fail safely instead of silently sending a shape an old local agent misreads.
AGENT_PROTOCOL_VERSION = 2


class AgentError(Exception):
    pass


def load_config(path: Path) -> Dict[str, Any]:
    if not path.exists():
        raise AgentError(f"Config belum ada: {path}")
    with path.open("r", encoding="utf-8") as fh:
        return json.load(fh)


def setup_logging(config: Dict[str, Any], verbose: bool = False) -> None:
    log_path = Path(config.get("log_file") or DEFAULT_LOG)
    log_path.parent.mkdir(parents=True, exist_ok=True)
    max_bytes = max(64 * 1024, int(config.get("log_max_bytes", 2 * 1024 * 1024) or 2 * 1024 * 1024))
    backup_count = max(1, min(10, int(config.get("log_backup_count", 3) or 3)))
    handlers = [
        RotatingFileHandler(log_path, maxBytes=max_bytes, backupCount=backup_count, encoding="utf-8"),
        logging.StreamHandler(sys.stdout),
    ]
    logging.basicConfig(
        level=logging.DEBUG if verbose else logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
        handlers=handlers,
    )


def _origin_from_url(value: Any, allow_path: bool = False) -> Optional[str]:
    raw = str(value or "").strip()
    if not raw or raw.lower() == "null" or "*" in raw:
        return None
    try:
        parsed = urllib.parse.urlsplit(raw)
        hostname = parsed.hostname
        port = parsed.port
    except ValueError:
        return None
    if parsed.scheme.lower() not in ("http", "https") or not hostname:
        return None
    if parsed.username is not None or parsed.password is not None:
        return None
    if not allow_path and (parsed.path not in ("", "/") or parsed.query or parsed.fragment):
        return None
    normalized_host = hostname.lower()
    if ":" in normalized_host and not normalized_host.startswith("["):
        normalized_host = "[" + normalized_host + "]"
    default_port = 80 if parsed.scheme.lower() == "http" else 443
    port_suffix = "" if port in (None, default_port) else ":" + str(port)
    return parsed.scheme.lower() + "://" + normalized_host + port_suffix


def allowed_browser_origins(config: Optional[Dict[str, Any]]) -> frozenset[str]:
    config = config if isinstance(config, dict) else {}
    api = config.get("api") if isinstance(config.get("api"), dict) else {}
    origins = set()
    base_origin = _origin_from_url(api.get("base_url"), allow_path=True)
    if base_origin:
        origins.add(base_origin)

    configured = api.get("allowed_origins") or []
    if isinstance(configured, str):
        configured = [configured]
    if isinstance(configured, (list, tuple, set)):
        for candidate in configured:
            origin = _origin_from_url(candidate)
            if origin:
                origins.add(origin)
    return frozenset(origins)


def create_printer_app(
    printer: Dict[str, Any],
    safe_print: Callable[..., None],
    config: Optional[Dict[str, Any]] = None,
    status_provider: Optional[Callable[[], Dict[str, Any]]] = None,
) -> Flask:
    """Build one local printer app without constructing PrinterService or using a DB."""
    allowed_origins = allowed_browser_origins(config)
    location = str(printer.get("lokasi") or printer.get("printer_code") or "POS-PRINTER")
    printer_code = str(printer.get("printer_code") or location)
    printer_mac = str(printer.get("mac") or printer.get("mac_address") or "")
    printer_port = int(printer.get("python_port") or 0)
    default_paper = int(printer.get("paper_width_mm") or 80)
    app = Flask("pos_printer_agent_" + location)

    def request_origin() -> Optional[str]:
        raw_origin = request.headers.get("Origin")
        if raw_origin is None or raw_origin.strip() == "" or raw_origin.strip().lower() == "null":
            return None
        origin = _origin_from_url(raw_origin)
        return origin if origin in allowed_origins else None

    def with_cors(response: Any, origin: str) -> Any:
        response.headers["Access-Control-Allow-Origin"] = origin
        response.headers["Vary"] = "Origin"
        return response

    @app.get("/health")
    def health():
        status = status_provider() if status_provider is not None else {}
        return jsonify({
            "status": "success",
            "data": {
                "lokasi": location,
                "printer_code": printer_code,
                "python_port": printer_port,
                "mac_address": printer_mac,
                "agent_version": AGENT_VERSION,
                "agent_protocol_version": AGENT_PROTOCOL_VERSION,
                "service": status,
            }
        })

    @app.route("/cetak", methods=["POST", "OPTIONS"], provide_automatic_options=False)
    def cetak():
        origin = request_origin()
        if origin is None:
            return jsonify({"status": "error", "message": "Origin tidak diizinkan."}), 403

        if request.method == "OPTIONS":
            response = make_response("", 204)
            response.headers["Access-Control-Allow-Methods"] = "POST"
            response.headers["Access-Control-Allow-Headers"] = "Content-Type"
            return with_cors(response, origin)

        payload = request.get_json(force=True, silent=True) or {}
        text = str(payload.get("text") or "")
        if text.strip() == "":
            return with_cors(jsonify({"status": "error", "message": "Struk kosong."}), origin), 400
        paper = 58 if int(payload.get("paper_width_mm") or default_paper or 80) == 58 else 80
        copies = max(1, min(10, int(payload.get("copies") or 1)))
        cut_mode = str(payload.get("cut_mode") or "PARTIAL").strip().upper()
        if cut_mode not in ("NONE", "PARTIAL", "FULL"):
            cut_mode = "PARTIAL"
        open_drawer = str(payload.get("open_drawer") or "").strip().lower() in ("1", "true", "yes", "on")
        try:
            safe_print(printer_mac, text, paper, copies, open_drawer, cut_mode)
            return with_cors(jsonify({"status": "success", "message": f"Berhasil dicetak ({copies} salinan)."}), origin)
        except Exception as exc:
            logging.exception("Gagal cetak %s: %s", location, exc)
            return with_cors(jsonify({"status": "error", "message": str(exc)}), origin), 500

    return app


class PrinterService:
    def __init__(self, config: Dict[str, Any], config_path: Optional[Path] = None):
        self.config = config
        self.config_path = config_path
        self.hostname = str(config.get("agent_name") or platform.node() or "POS-PRINTER-AGENT-01").strip()
        self.api = config.get("api", {}) or {}
        self.logo = config.get("logo", {}) or {}
        self.retry_seconds = max(3, int(config.get("retry_seconds", 10) or 10))
        self.refresh_seconds = max(5, int(self.api.get("refresh_seconds", 30) or 30))
        self.printers = self.normalize_printers(config.get("printers") or [], source="config")
        self.started_ports: Dict[int, Dict[str, Any]] = {}
        # Satu perangkat Bluetooth/COM dapat dipakai oleh beberapa role printer.
        # Akses serial harus bergantian agar request BAR/KITCHEN/CHECKER tidak
        # saling berebut membuka port yang sama.
        self.physical_printer_locks: Dict[str, threading.RLock] = {}
        self.physical_printer_locks_guard = threading.Lock()
        self.last_refresh_at = 0.0
        self.started_at = time.time()
        self.restart_required = False
        self.restart_reason = ""

    def run(self) -> int:
        logging.info(
            "Printer service start | host=%s | version=%s | protocol=%s",
            self.hostname,
            AGENT_VERSION,
            AGENT_PROTOCOL_VERSION,
        )
        printers = self.ensure_printers_loaded()
        if not printers:
            raise AgentError("Tidak ada printer aktif yang bisa dijalankan.")
        self.start_printer_servers(printers)
        while True:
            self.refresh_printers_if_needed()
            time.sleep(1)

    def validate_once(self) -> int:
        printers = self.ensure_printers_loaded()
        if not printers:
            raise AgentError("Tidak ada printer aktif yang bisa dijalankan.")
        for printer in printers:
            logging.info(
                "READY | %s | port=%s | mac=%s | paper=%smm",
                printer.get("lokasi") or printer.get("printer_code"),
                printer.get("python_port"),
                printer.get("mac"),
                printer.get("paper_width_mm"),
            )
        logging.info("Validasi selesai. Jalankan tanpa --once untuk mode service.")
        return 0

    def service_status(self) -> Dict[str, Any]:
        """Non-secret state exposed only on the local loopback health endpoint."""
        return {
            "state": "restart_required" if self.restart_required else "ready",
            "agent_name": self.hostname,
            "uptime_seconds": max(0, int(time.time() - self.started_at)),
            "restart_required": self.restart_required,
            "restart_reason": self.restart_reason if self.restart_required else "",
            "active_printer_count": len(self.started_ports),
        }

    def ensure_printers_loaded(self) -> List[Dict[str, Any]]:
        enabled = bool(self.api.get("enabled", True))
        if enabled:
            while True:
                try:
                    rows = self.fetch_printers_from_api()
                    if rows:
                        self.sync_printers(rows, source="api")
                        return self.printers
                    logging.warning("Bootstrap printer tidak mengembalikan data aktif.")
                except Exception as exc:
                    logging.warning("Gagal ambil printer dari API: %s", exc)
                if self.printers:
                    return self.printers
                time.sleep(self.retry_seconds)
        return self.printers

    def normalize_printers(self, rows: List[Dict[str, Any]], source: str = "config") -> List[Dict[str, Any]]:
        printers = []
        used_ports = set()
        for row in rows:
            if not isinstance(row, dict):
                continue
            lokasi = (row.get("lokasi_printer") or row.get("lokasi") or row.get("printer_code") or row.get("printer_name") or "").strip().upper()
            mac = self.normalize_mac(row.get("mac_address") or row.get("mac") or "")
            python_port = int(row.get("python_port") or 0)
            paper = 58 if int(row.get("paper_width_mm") or 80) == 58 else 80
            if not lokasi:
                logging.warning("[%s] Skip printer tanpa lokasi/role.", source)
                continue
            if not mac:
                logging.warning("[%s] Skip %s: mac_address kosong.", source, lokasi)
                continue
            if python_port <= 0:
                logging.warning("[%s] Skip %s: python_port tidak valid.", source, lokasi)
                continue
            if python_port in used_ports:
                logging.warning("[%s] Skip %s: python_port %s duplikat.", source, lokasi, python_port)
                continue
            used_ports.add(python_port)
            printers.append({
                "lokasi": lokasi,
                "printer_code": (row.get("printer_code") or lokasi).strip().upper(),
                "printer_name": (row.get("printer_name") or lokasi).strip(),
                "mac": mac,
                "python_port": python_port,
                "paper_width_mm": paper,
            })
        return printers

    def fetch_printers_from_api(self) -> List[Dict[str, Any]]:
        base_url = str(self.api.get("base_url") or "").strip()
        if not base_url:
            raise AgentError("api.base_url kosong.")
        endpoint = str(self.api.get("endpoint") or "/pos-printers/bootstrap").strip()
        if not endpoint.startswith("/"):
            endpoint = "/" + endpoint
        alt_endpoint = endpoint if "/index.php/" in endpoint else "/index.php" + endpoint
        agent_param = str(self.api.get("agent_name_param") or "agent_name").strip()
        api_key = str(self.api.get("key") or "").strip()
        timeout = int(self.api.get("timeout_seconds") or 8)
        params = {agent_param: self.hostname}
        attempts = []
        for candidate in [endpoint, alt_endpoint]:
            url = base_url.rstrip("/") + candidate
            url += ("&" if "?" in url else "?") + urllib.parse.urlencode(params)
            req = urllib.request.Request(url)
            req.add_header("Accept", "application/json")
            req.add_header("User-Agent", "FinancePrinterLocalService/" + AGENT_VERSION)
            req.add_header("X-Printer-Agent-Version", AGENT_VERSION)
            req.add_header("X-Printer-Agent-Protocol", str(AGENT_PROTOCOL_VERSION))
            if api_key:
                req.add_header("X-Printer-Key", api_key)
            try:
                raw = self.fetch_url_text(req, timeout=timeout)
                decoded = json.loads((raw or "").lstrip("\ufeff").strip())
                if isinstance(decoded, dict):
                    self.validate_bootstrap_contract(decoded)
                    status = str(decoded.get("status") or "").lower()
                    if status and status != "success":
                        raise AgentError(str(decoded.get("message") or "bootstrap error"))
                    rows = decoded.get("data") or []
                elif isinstance(decoded, list):
                    rows = decoded
                else:
                    rows = []
                return self.normalize_printers(rows, source="api")
            except Exception as exc:
                attempts.append(f"{candidate}: {exc}")
        raise AgentError(" ; ".join(attempts) or "bootstrap gagal")

    def validate_bootstrap_contract(self, decoded: Dict[str, Any]) -> None:
        contract = decoded.get("agent_contract")
        # Older Finance instances did not advertise this field. Keep those
        # installations working; a newer server can explicitly set a minimum
        # protocol when it ships a breaking bootstrap format.
        if contract is None:
            return
        if not isinstance(contract, dict):
            raise AgentError("Kontrak bootstrap printer tidak valid.")
        try:
            min_protocol = int(contract.get("min_protocol", 1) or 1)
            max_protocol = int(contract.get("max_protocol", AGENT_PROTOCOL_VERSION) or AGENT_PROTOCOL_VERSION)
        except (TypeError, ValueError):
            raise AgentError("Versi kontrak bootstrap printer tidak valid.")
        if min_protocol > max_protocol or not (min_protocol <= AGENT_PROTOCOL_VERSION <= max_protocol):
            raise AgentError(
                "Versi Local Printer Agent tidak cocok dengan server "
                f"(agent protocol {AGENT_PROTOCOL_VERSION}; server mendukung {min_protocol}-{max_protocol}). "
                "Unduh paket agent terbaru dari Finance lalu restart service."
            )

    def start_printer_servers(self, printers: List[Dict[str, Any]]) -> None:
        for printer in printers:
            port = int(printer["python_port"])
            if port in self.started_ports:
                continue
            thread = threading.Thread(target=self.start_flask_server, args=(printer,), daemon=True)
            thread.start()
            self.started_ports[port] = printer
            logging.info(
                "Printer %s siap | localhost:%s | MAC %s | %smm",
                printer["lokasi"],
                port,
                printer["mac"],
                printer["paper_width_mm"],
            )

    def refresh_printers_if_needed(self) -> None:
        if not bool(self.api.get("enabled", True)):
            return
        now = time.time()
        if now - self.last_refresh_at < self.refresh_seconds:
            return
        self.last_refresh_at = now
        try:
            rows = self.fetch_printers_from_api()
            if rows:
                if self.started_ports and self.printer_signature(rows) != self.printer_signature(self.printers):
                    self.restart_required = True
                    self.restart_reason = (
                        "Koneksi printer berubah di Finance. Restart Local Printer Agent "
                        "agar port lama dilepas dan konfigurasi baru dipakai."
                    )
                    logging.warning(self.restart_reason)
                    return
                self.sync_printers(rows, source="api-refresh")
        except Exception as exc:
            logging.warning("Refresh printer API gagal: %s", exc)

    @staticmethod
    def printer_signature(rows: List[Dict[str, Any]]) -> tuple:
        return tuple(sorted(
            (
                str(row.get("printer_code") or "").strip().upper(),
                str(row.get("mac") or row.get("mac_address") or "").strip().upper(),
                int(row.get("python_port") or 0),
                int(row.get("paper_width_mm") or 80),
            )
            for row in rows
        ))

    def sync_printers(self, rows: List[Dict[str, Any]], source: str = "api") -> None:
        normalized = self.normalize_printers(rows, source=source)
        if not normalized:
            return
        existing_by_code = {
            str(printer.get("printer_code") or "").strip().upper(): printer
            for printer in self.printers
        }
        merged: List[Dict[str, Any]] = []
        seen_codes = set()
        for row in normalized:
            code = str(row.get("printer_code") or "").strip().upper()
            current = existing_by_code.get(code)
            if current is not None:
                current.clear()
                current.update(row)
                merged.append(current)
            else:
                merged.append(dict(row))
            seen_codes.add(code)
        for code, current in existing_by_code.items():
            if code not in seen_codes:
                merged.append(current)
                logging.warning("[%s] Printer %s sudah tidak aktif di API. Service lama tetap hidup sampai restart.", source, code)
        self.printers = merged
        self.persist_runtime_config()
        self.start_printer_servers(self.printers)

    def persist_runtime_config(self) -> None:
        if self.config_path is None:
            return
        try:
            self.config["printers"] = [
                {
                    "lokasi": printer.get("lokasi"),
                    "printer_code": printer.get("printer_code"),
                    "printer_name": printer.get("printer_name"),
                    "mac_address": printer.get("mac"),
                    "python_port": int(printer.get("python_port") or 0),
                    "paper_width_mm": int(printer.get("paper_width_mm") or 80),
                }
                for printer in self.printers
            ]
            self.write_config_atomically()
        except Exception as exc:
            logging.warning("Gagal menulis ulang config lokal: %s", exc)

    def write_config_atomically(self) -> None:
        if self.config_path is None:
            return
        target = self.config_path
        target.parent.mkdir(parents=True, exist_ok=True)
        temporary = target.with_name("." + target.name + ".tmp")
        fd = os.open(str(temporary), os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        try:
            with os.fdopen(fd, "w", encoding="utf-8") as fh:
                json.dump(self.config, fh, ensure_ascii=False, indent=2)
                fh.write("\n")
                fh.flush()
                os.fsync(fh.fileno())
            os.replace(str(temporary), str(target))
            try:
                os.chmod(str(target), 0o600)
            except OSError:
                # Windows does not support POSIX permissions; the agent still
                # keeps the atomic replacement property there.
                pass
        finally:
            if temporary.exists():
                try:
                    temporary.unlink()
                except OSError:
                    pass

    def start_flask_server(self, printer: Dict[str, Any]) -> None:
        app = create_printer_app(printer, self.safe_print, self.config, self.service_status)

        app.run(host="127.0.0.1", port=int(printer["python_port"]), debug=False, use_reloader=False)

    def safe_print(
        self,
        mac: str,
        text: str,
        paper_width_mm: int = 80,
        copies: int = 1,
        open_drawer: bool = False,
        cut_mode: str = "PARTIAL",
    ) -> None:
        lock_key = self.normalize_mac(mac) or str(mac or "UNKNOWN").strip().upper()
        queued_at = time.monotonic()
        with self.physical_printer_lock(lock_key):
            waited_seconds = time.monotonic() - queued_at
            if waited_seconds >= 0.2:
                logging.info(
                    "Cetak MAC %s menunggu %.1f detik karena perangkat fisik sedang mencetak slip lain.",
                    lock_key,
                    waited_seconds,
                )
            self.safe_print_to_device(mac, text, paper_width_mm, copies, open_drawer, cut_mode)

    def physical_printer_lock(self, mac: str) -> threading.RLock:
        key = self.normalize_mac(mac) or str(mac or "UNKNOWN").strip().upper()
        with self.physical_printer_locks_guard:
            lock = self.physical_printer_locks.get(key)
            if lock is None:
                lock = threading.RLock()
                self.physical_printer_locks[key] = lock
            return lock

    def safe_print_to_device(
        self,
        mac: str,
        text: str,
        paper_width_mm: int = 80,
        copies: int = 1,
        open_drawer: bool = False,
        cut_mode: str = "PARTIAL",
    ) -> None:
        if serial is None:
            raise AgentError("pyserial belum terpasang.")
        max_retry = max(1, int(self.config.get("print_retry_count", 2) or 2))
        last_error = None
        for attempt in range(1, max_retry + 1):
            ser = None
            try:
                com_port = self.resolve_printer_port(mac)
                if not com_port:
                    raise AgentError(f"COM port printer dari MAC {mac} tidak ditemukan.")
                ser = serial.Serial(com_port, 9600, timeout=3, write_timeout=5)
                logo_sources, clean_text = self.extract_logo_sources(text)
                barcode_values, clean_text = self.extract_barcode_values(clean_text)
                qr_values, clean_text = self.extract_qrcode_values(clean_text)
                safe_copies = max(1, min(10, int(copies or 1)))
                safe_cut_mode = str(cut_mode or "PARTIAL").strip().upper()
                for copy_index in range(safe_copies):
                    ser.write(b"\x1b\x40")
                    ser.write(b"\x1b\x32")
                    ser.write(b"\x1b\x74\x00")
                    if open_drawer and copy_index == 0:
                        ser.write(b"\x1b\x70\x00\x19\xfa")
                    for logo_source in logo_sources:
                        try:
                            if logo_source.get("type") == "base64":
                                raw = self.load_image_from_base64(str(logo_source.get("value") or ""))
                            else:
                                raw = self.load_image_from_url(str(logo_source.get("value") or ""))
                            mode = str(self.logo.get("mode", "esc_star")).strip().lower()
                            if mode == "raster":
                                logo_bytes = self.image_to_escpos_raster(raw, self.logo_max_width_dots(paper_width_mm), self.paper_canvas_width_dots(paper_width_mm))
                            else:
                                logo_bytes = self.image_to_escpos_esc_star(raw, self.logo_max_width_dots(paper_width_mm))
                            ser.write(b"\x1b\x61\x01")
                            self.write_bytes(ser, logo_bytes, 512, 0.008)
                            ser.write(b"\x1b\x61\x00")
                            ser.flush()
                        except Exception as exc:
                            logging.warning("Logo gagal dicetak %s: %s", logo_source.get("type") or "unknown", exc)
                    text_bytes = self.encode_text_with_feed_markers(clean_text)
                    self.write_bytes(ser, text_bytes, 512, 0.005)
                    for barcode in barcode_values:
                        try:
                            barcode_bytes = self.code128_barcode_payload(barcode)
                            if barcode_bytes:
                                ser.write(b"\n")
                                ser.write(b"\x1b\x61\x01")
                                self.write_bytes(ser, barcode_bytes, 256, 0.008)
                                ser.write(b"\x1b\x61\x00")
                                ser.write(b"\n")
                        except Exception as exc:
                            logging.warning("Barcode gagal dicetak %s: %s", barcode, exc)
                    for qr_value in qr_values:
                        try:
                            ser.write(b"\n")
                            ser.write(b"\x1b\x61\x01")
                            qr_bytes = self.qrcode_print_bytes(qr_value, paper_width_mm)
                            self.write_bytes(ser, qr_bytes, 512, 0.008)
                            ser.write(b"\x1b\x61\x00")
                            ser.write(b"\n")
                        except Exception as exc:
                            logging.warning("QR ulasan gagal dicetak: %s", exc)
                    ser.write(b"\n\n\n")
                    if safe_cut_mode == "FULL":
                        ser.write(b"\x1d\x56\x00")
                    elif safe_cut_mode == "PARTIAL":
                        ser.write(b"\x1d\x56\x01")
                ser.flush()
                ser.close()
                return
            except Exception as exc:
                last_error = exc
                logging.warning("Cetak gagal attempt %s/%s: %s", attempt, max_retry, exc)
                try:
                    if ser:
                        ser.close()
                except Exception:
                    pass
                time.sleep(1.5)
        raise AgentError(str(last_error) if last_error else "Gagal cetak.")

    def resolve_printer_port(self, mac: str) -> Optional[str]:
        if platform.system().upper().startswith("WIN"):
            return self.detect_com_from_mac(mac)
        return self.detect_rfcomm_from_mac(mac)

    def detect_com_from_mac(self, mac_target: str) -> Optional[str]:
        mac_target = self.normalize_mac(mac_target)
        if not mac_target:
            return None
        try:
            output = subprocess.check_output([
                "powershell",
                "-NoProfile",
                "-ExecutionPolicy", "Bypass",
                "Get-PnpDevice -Class Ports | Format-List InstanceId,Name"
            ], encoding="utf-8", errors="ignore")
        except Exception:
            return None
        lines = output.splitlines()
        for index, line in enumerate(lines):
            normalized = re.sub(r"[^0-9A-F]", "", line.upper())
            if mac_target in normalized:
                for next_line in lines[index:index + 3]:
                    match = re.search(r"\((COM\d+)\)", next_line, re.I)
                    if match:
                        return match.group(1).upper()
        return None

    def detect_rfcomm_from_mac(self, mac_target: str) -> Optional[str]:
        mac_target = self.normalize_mac(mac_target)
        if not mac_target:
            return None
        try:
            out = subprocess.check_output(["rfcomm"], encoding="utf-8", errors="ignore")
        except Exception:
            return None
        for line in out.splitlines():
            normalized = re.sub(r"[^0-9A-F]", "", line.upper())
            if mac_target in normalized:
                return "/dev/" + line.split(":", 1)[0].strip()
        return None

    def fetch_url_text(self, req: urllib.request.Request, timeout: int = 12) -> str:
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
            text = self.fetch_url_text_via_curl(req, timeout=timeout)
            if text not in (None, ""):
                return text
            errors.append("curl-fallback: gagal")
            text = self.fetch_url_text_via_powershell(req, timeout=timeout)
            if text not in (None, ""):
                return text
            errors.append("powershell-fallback: gagal")
        raise AgentError(" ; ".join(errors))

    def fetch_url_bytes(self, req: urllib.request.Request, timeout: int = 12) -> bytes:
        errors: List[str] = []
        try:
            ctx = ssl.create_default_context()
            with urllib.request.urlopen(req, timeout=timeout, context=ctx) as response:
                body = response.read()
                if body:
                    return body
                errors.append("urllib-default: empty")
        except Exception as exc:
            errors.append(f"urllib-default: {exc}")
        try:
            ctx = ssl._create_unverified_context()
            with urllib.request.urlopen(req, timeout=timeout, context=ctx) as response:
                body = response.read()
                if body:
                    return body
                errors.append("urllib-unverified: empty")
        except Exception as exc:
            errors.append(f"urllib-unverified: {exc}")
        if platform.system().upper().startswith("WIN"):
            body = self.fetch_url_bytes_via_curl(req, timeout=timeout)
            if body:
                return body
            errors.append("curl-fallback: gagal")
        raise AgentError(" ; ".join(errors))

    def fetch_url_text_via_curl(self, req: urllib.request.Request, timeout: int = 12) -> Optional[str]:
        headers: List[str] = []
        for key, value in req.header_items():
            headers.extend(["-H", f"{key}: {value}"])
        cmd = ["curl.exe", "-sS", "-L", "--connect-timeout", str(timeout), "--max-time", str(max(timeout + 5, 15)), *headers, req.full_url]
        try:
            proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
            if proc.returncode == 0:
                text = proc.stdout.decode("utf-8", errors="ignore")
                return text if text.strip() != "" else None
            logging.warning("curl fallback gagal: %s", proc.stderr.decode("utf-8", errors="ignore").strip())
        except Exception as exc:
            logging.warning("curl fallback exception: %s", exc)
        return None

    def fetch_url_bytes_via_curl(self, req: urllib.request.Request, timeout: int = 12) -> Optional[bytes]:
        headers: List[str] = []
        for key, value in req.header_items():
            headers.extend(["-H", f"{key}: {value}"])
        cmd = ["curl.exe", "-sS", "-L", "--connect-timeout", str(timeout), "--max-time", str(max(timeout + 5, 15)), *headers, req.full_url]
        try:
            proc = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
            if proc.returncode == 0 and proc.stdout:
                return proc.stdout
            logging.warning("curl binary fallback gagal: %s", proc.stderr.decode("utf-8", errors="ignore").strip())
        except Exception as exc:
            logging.warning("curl binary fallback exception: %s", exc)
        return None

    def fetch_url_text_via_powershell(self, req: urllib.request.Request, timeout: int = 12) -> Optional[str]:
        headers_map = {k: v for k, v in req.header_items()}
        header_parts = []
        for key, value in headers_map.items():
            safe_key = str(key).replace("'", "''")
            safe_value = str(value).replace("'", "''")
            header_parts.append("'{}'='{}'".format(safe_key, safe_value))
        header_ps = "@{" + "; ".join(header_parts) + "}"
        script = [
            "$ProgressPreference='SilentlyContinue'",
            "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12",
            f"$headers = {header_ps}",
            f"$resp = Invoke-WebRequest -UseBasicParsing -Uri '{req.full_url}' -Headers $headers -TimeoutSec {int(timeout)}",
            "[Console]::OutputEncoding = [System.Text.Encoding]::UTF8",
            "$resp.Content",
        ]
        try:
            proc = subprocess.run(["powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", "; ".join(script)], stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
            if proc.returncode == 0:
                return proc.stdout.decode("utf-8", errors="ignore")
            logging.warning("powershell fallback gagal: %s", proc.stderr.decode("utf-8", errors="ignore").strip())
        except Exception as exc:
            logging.warning("powershell fallback exception: %s", exc)
        return None

    def extract_logo_sources(self, text: str) -> tuple[list[dict], str]:
        sources = []
        clean_text = text or ""
        pattern_b64 = re.compile(r"\[\[LOGO_BASE64:(.*?)\]\]", re.S)
        for match in pattern_b64.findall(clean_text):
            value = "".join(str(match or "").split())
            if value:
                sources.append({"type": "base64", "value": value})
        clean_text = pattern_b64.sub("", clean_text)
        pattern = re.compile(r"\[\[LOGO_URL:(.*?)\]\]")
        for match in pattern.findall(clean_text):
            url = (match or "").strip()
            if url:
                sources.append({"type": "url", "value": url})
        clean_text = pattern.sub("", clean_text).lstrip("\n")
        return sources, clean_text

    def extract_barcode_values(self, text: str) -> tuple[list[str], str]:
        values = []
        clean_text = text or ""
        pattern = re.compile(r"\[\[BARCODE:(.*?)\]\]")
        for match in pattern.findall(clean_text):
            value = re.sub(r"[^0-9A-Z\\-_/\\.]", "", (match or "").strip().upper())
            if value:
                values.append(value[:80])
        clean_text = pattern.sub("", clean_text).rstrip() + "\n"
        return values, clean_text

    def extract_qrcode_values(self, text: str) -> tuple[list[str], str]:
        values = []
        clean_text = text or ""
        pattern = re.compile(r"\[\[QRCODE:(.*?)\]\]")
        for match in pattern.findall(clean_text):
            value = (match or "").strip()
            if value.startswith(("http://", "https://")):
                values.append(value[:500])
        clean_text = pattern.sub("", clean_text).rstrip() + "\n"
        return values, clean_text

    def encode_text_with_feed_markers(self, text: str) -> bytes:
        clean_text = self.normalize_text_for_printer(text)
        pattern = re.compile(r"\[\[FEED:(\d{1,3})\]\]\n?")
        output = bytearray()
        cursor = 0
        for match in pattern.finditer(clean_text):
            chunk = clean_text[cursor:match.start()]
            if chunk:
                output += self.encode_text_for_printer(chunk)
            dots = max(1, min(255, int(match.group(1) or 6)))
            output += b"\x1b\x4a" + bytes([dots])
            cursor = match.end()
        tail = clean_text[cursor:]
        if tail:
            output += self.encode_text_for_printer(tail)
        return bytes(output)

    def load_image_from_url(self, url: str) -> bytes:
        req = urllib.request.Request(url)
        req.add_header("User-Agent", "CorePrinterLocalService/1.0")
        timeout = int(self.logo.get("fetch_timeout_seconds", 10) or 10)
        return self.fetch_url_bytes(req, timeout=timeout)

    def load_image_from_base64(self, payload: str) -> bytes:
        raw = "".join(str(payload or "").split())
        if not raw:
            raise AgentError("Payload logo base64 kosong.")
        return base64.b64decode(raw, validate=False)

    def logo_max_width_dots(self, paper_width_mm: int) -> int:
        scale = float(self.logo.get("scale", 2.2) or 2.2)
        if int(paper_width_mm or 80) >= 76:
            return min(576, int(256 * scale))
        return min(384, int(192 * scale))

    def qrcode_max_width_dots(self, paper_width_mm: int) -> int:
        return 260 if int(paper_width_mm or 80) >= 76 else 184

    def qrcode_module_size(self, paper_width_mm: int) -> int:
        return 8 if int(paper_width_mm or 80) >= 76 else 6

    def qrcode_print_bytes(self, value: str, paper_width_mm: int) -> bytes:
        errors: List[str] = []
        try:
            qr_raw = self.qrcode_image_bytes(value)
            mode = str(self.logo.get("mode", "esc_star")).strip().lower()
            if mode == "raster":
                return self.image_to_escpos_raster(
                    qr_raw,
                    self.qrcode_max_width_dots(paper_width_mm),
                    self.paper_canvas_width_dots(paper_width_mm),
                )
            return self.image_to_escpos_esc_star(qr_raw, self.qrcode_max_width_dots(paper_width_mm))
        except Exception as exc:
            errors.append(str(exc))

        try:
            qr_raw = self.qrcode_remote_image_bytes(value, paper_width_mm)
            mode = str(self.logo.get("mode", "esc_star")).strip().lower()
            if mode == "raster":
                return self.image_to_escpos_raster(
                    qr_raw,
                    self.qrcode_max_width_dots(paper_width_mm),
                    self.paper_canvas_width_dots(paper_width_mm),
                )
            return self.image_to_escpos_esc_star(qr_raw, self.qrcode_max_width_dots(paper_width_mm))
        except Exception as exc:
            errors.append(str(exc))

        try:
            return self.native_escpos_qrcode_bytes(value, paper_width_mm)
        except Exception as exc:
            errors.append(str(exc))

        raise AgentError(" ; ".join(error for error in errors if error) or "QR tidak dapat diproses.")

    def qrcode_image_bytes(self, value: str) -> bytes:
        if qrcode is None or Image is None:
            raise AgentError("Library qrcode atau Pillow belum terpasang. Jalankan pip install -r requirements.txt.")
        image = qrcode.make(value, border=2)
        output = io.BytesIO()
        image.save(output, format="PNG")
        return output.getvalue()

    def qrcode_remote_image_bytes(self, value: str, paper_width_mm: int) -> bytes:
        size = 320 if int(paper_width_mm or 80) >= 76 else 240
        qr_url = (
            "https://api.qrserver.com/v1/create-qr-code/"
            f"?size={size}x{size}&ecc=M&data={urllib.parse.quote(str(value or '').strip(), safe='')}"
        )
        req = urllib.request.Request(qr_url)
        req.add_header("User-Agent", "CorePrinterLocalService/1.0")
        timeout = int(self.logo.get("fetch_timeout_seconds", 10) or 10)
        return self.fetch_url_bytes(req, timeout=timeout)

    def native_escpos_qrcode_bytes(self, value: str, paper_width_mm: int) -> bytes:
        data = str(value or "").strip().encode("utf-8")
        if not data:
            raise AgentError("Nilai QR kosong.")
        if len(data) > 7089:
            raise AgentError("Nilai QR terlalu panjang untuk ESC/POS native.")

        size = max(3, min(12, self.qrcode_module_size(paper_width_mm)))
        # Error correction M menjaga QR tetap padat dan kompatibel untuk kertas termal.
        error_level = 49
        payload_len = len(data) + 3
        pL = payload_len % 256
        pH = payload_len // 256

        return b"".join([
            b"\x1d\x28\x6b\x04\x00\x31\x41\x32\x00",
            b"\x1d\x28\x6b\x03\x00\x31\x43" + bytes([size]),
            b"\x1d\x28\x6b\x03\x00\x31\x45" + bytes([error_level]),
            b"\x1d\x28\x6b" + bytes([pL, pH]) + b"\x31\x50\x30" + data,
            b"\x1d\x28\x6b\x03\x00\x31\x51\x30",
        ])

    def paper_canvas_width_dots(self, paper_width_mm: int) -> int:
        return 256 if int(paper_width_mm or 80) >= 76 else 192

    def logo_max_height_dots(self) -> int:
        return int(self.logo.get("max_height_dots", 280) or 280)

    def trim_white_margins_bw(self, img_bw):
        try:
            inv = img_bw.point(lambda x: 255 if x == 0 else 0, "L")
            bbox = inv.getbbox()
            if bbox:
                return img_bw.crop(bbox)
        except Exception:
            pass
        return img_bw

    def image_to_escpos_raster(self, image_bytes: bytes, max_width: int, canvas_width: Optional[int] = None) -> bytes:
        if Image is None:
            raise AgentError("Pillow belum terpasang.")
        img = Image.open(io.BytesIO(image_bytes)).convert("L")
        w, h = img.size
        if w <= 0 or h <= 0:
            raise AgentError("Ukuran logo tidak valid.")
        max_height = self.logo_max_height_dots()
        scale = min(max_width / float(w), max_height / float(h), 1.0)
        if scale < 1.0:
            img = img.resize((max(1, int(w * scale)), max(1, int(h * scale))), Image.LANCZOS)
        threshold = int(self.logo.get("threshold", 180) or 180)
        img = img.point(lambda x: 0 if x < threshold else 255, "1")
        img = self.trim_white_margins_bw(img)
        if canvas_width and canvas_width > 0:
            if img.size[0] < canvas_width:
                bg = Image.new("1", (int(canvas_width), img.size[1]), 1)
                left = (int(canvas_width) - img.size[0]) // 2
                bg.paste(img, (left, 0))
                img = bg
        w, h = img.size
        width_bytes = (w + 7) // 8
        padded_width = width_bytes * 8
        if padded_width != w:
            padded = Image.new("1", (padded_width, h), 1)
            padded.paste(img, (0, 0))
            img = padded
            w = padded_width
        pixels = img.load()
        bitmap = bytearray()
        for y in range(h):
            for xb in range(width_bytes):
                b = 0
                for bit in range(8):
                    x = xb * 8 + bit
                    if pixels[x, y] == 0:
                        b |= (1 << (7 - bit))
                bitmap.append(b)
        xL = width_bytes & 0xFF
        xH = (width_bytes >> 8) & 0xFF
        yL = h & 0xFF
        yH = (h >> 8) & 0xFF
        return b"\x1d\x76\x30\x00" + bytes([xL, xH, yL, yH]) + bytes(bitmap)

    def image_to_escpos_esc_star(self, image_bytes: bytes, max_width: int) -> bytes:
        if Image is None:
            raise AgentError("Pillow belum terpasang.")
        img = Image.open(io.BytesIO(image_bytes)).convert("L")
        w, h = img.size
        if w <= 0 or h <= 0:
            raise AgentError("Ukuran logo tidak valid.")
        max_height = self.logo_max_height_dots()
        scale = min(max_width / float(w), max_height / float(h), 1.0)
        if scale < 1.0:
            img = img.resize((max(1, int(w * scale)), max(1, int(h * scale))), Image.LANCZOS)
        threshold = int(self.logo.get("threshold", 180) or 180)
        img = img.point(lambda x: 0 if x < threshold else 255, "1")
        img = self.trim_white_margins_bw(img)
        w, h = img.size
        pixels = img.load()
        output = bytearray()
        output += b"\x1b\x33\x18"
        for y in range(0, h, 24):
            nL = w & 0xFF
            nH = (w >> 8) & 0xFF
            output += b"\x1b\x2a\x21" + bytes([nL, nH])
            for x in range(w):
                for k in range(3):
                    b = 0
                    for bit in range(8):
                        yy = y + k * 8 + bit
                        if yy < h and pixels[x, yy] == 0:
                            b |= (1 << (7 - bit))
                    output.append(b)
            output += b"\n"
        output += b"\x1b\x32"
        return bytes(output)

    def normalize_text_for_printer(self, text: str) -> str:
        clean_text = (text or "").replace("\r\n", "\n").replace("\r", "\n")
        return re.sub(r"[^\x09\x0A\x20-\x7E]", "", clean_text)

    def encode_text_for_printer(self, text: str) -> bytes:
        clean_text = self.normalize_text_for_printer(text)
        try:
            return clean_text.encode("cp437", errors="replace")
        except Exception:
            return clean_text.encode("ascii", errors="replace")

    def write_bytes(self, printer, data: bytes, chunk_size: int = 512, pause_seconds: float = 0.01) -> None:
        if not data:
            return
        for i in range(0, len(data), chunk_size):
            printer.write(data[i:i + chunk_size])
            time.sleep(pause_seconds)

    def code128_barcode_payload(self, value: str) -> bytes:
        payload = re.sub(r"[^0-9A-Z\\-_/\\.]", "", (value or "").upper())[:80]
        if payload == "":
            return b""
        data = payload.encode("ascii", errors="ignore")
        total_len = len(data) + 2
        if total_len > 255:
            data = data[:253]
            total_len = len(data) + 2
        return (
            b"\x1d\x48\x02"
            + b"\x1d\x77\x03"
            + b"\x1d\x68\x50"
            + b"\x1d\x6b\x49"
            + bytes([total_len])
            + b"{B"
            + data
            + b"\n"
        )

    def normalize_mac(self, value: Any) -> str:
        return re.sub(r"[^0-9A-F]", "", str(value or "").upper())


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="POS printer local service")
    parser.add_argument("--config", default=str(DEFAULT_CONFIG), help="Path config.json")
    parser.add_argument("--once", action="store_true", help="Validasi config dan printer bootstrap lalu keluar")
    parser.add_argument("--version", action="store_true", help="Tampilkan versi agent dan protocol lalu keluar")
    parser.add_argument("--verbose", action="store_true", help="Log lebih detail")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    if args.version:
        print(f"Finance POS Printer Agent {AGENT_VERSION} (protocol {AGENT_PROTOCOL_VERSION})")
        return 0
    try:
        config = load_config(Path(args.config))
        setup_logging(config, args.verbose)
        service = PrinterService(config, config_path=Path(args.config))
        if args.once:
            return service.validate_once()
        return service.run()
    except KeyboardInterrupt:
        return 130
    except AgentError as exc:
        logging.error(str(exc))
        print(f"ERROR: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
