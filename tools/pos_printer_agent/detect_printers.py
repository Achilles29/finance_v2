#!/usr/bin/env python3
import json
import platform
import re
import subprocess
import sys
from datetime import datetime


def run_command(cmd):
    try:
        completed = subprocess.run(
            cmd,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            encoding='utf-8',
            errors='ignore',
            check=False,
        )
        return {
            'ok': completed.returncode == 0,
            'code': completed.returncode,
            'stdout': completed.stdout.strip(),
            'stderr': completed.stderr.strip(),
        }
    except Exception as exc:
        return {
            'ok': False,
            'code': -1,
            'stdout': '',
            'stderr': str(exc),
        }


def extract_mac(text):
    if not text:
        return ''
    patterns = [
        r'([0-9A-F]{2}[:-]){5}[0-9A-F]{2}',
        r'DEV[_-]([0-9A-F]{12})',
        r'([0-9A-F]{12})',
    ]
    upper = str(text).upper()
    for pattern in patterns:
        match = re.search(pattern, upper)
        if not match:
            continue
        raw = match.group(0)
        if raw.startswith('DEV_') or raw.startswith('DEV-'):
            raw = raw.split('_')[-1].split('-')[-1]
        hex_only = re.sub(r'[^0-9A-F]', '', raw)
        if len(hex_only) == 12:
            return ':'.join(hex_only[i:i+2] for i in range(0, 12, 2))
    return ''


def parse_com_name(text):
    match = re.search(r'\((COM\d+)\)', text or '', re.I)
    return match.group(1).upper() if match else ''


def windows_printers():
    ps = (
        "Get-CimInstance Win32_Printer | "
        "Select-Object Name,DriverName,PortName,Default,WorkOffline,Shared | ConvertTo-Json -Depth 3"
    )
    result = run_command(['powershell', '-NoProfile', '-Command', ps])
    printers = []
    if result['ok'] and result['stdout']:
        try:
            rows = json.loads(result['stdout'])
            if isinstance(rows, dict):
                rows = [rows]
            for row in rows:
                printers.append({
                    'name': row.get('Name') or '',
                    'driver_name': row.get('DriverName') or '',
                    'port_name': row.get('PortName') or '',
                    'default': bool(row.get('Default')),
                    'work_offline': bool(row.get('WorkOffline')),
                    'shared': bool(row.get('Shared')),
                })
        except Exception:
            pass
    return printers, result


def windows_ports():
    ps = (
        "Get-PnpDevice -Class Ports | "
        "Select-Object FriendlyName,InstanceId,Status,Class | ConvertTo-Json -Depth 3"
    )
    result = run_command(['powershell', '-NoProfile', '-Command', ps])
    ports = []
    if result['ok'] and result['stdout']:
        try:
            rows = json.loads(result['stdout'])
            if isinstance(rows, dict):
                rows = [rows]
            for row in rows:
                friendly = row.get('FriendlyName') or ''
                instance_id = row.get('InstanceId') or ''
                ports.append({
                    'friendly_name': friendly,
                    'com_port': parse_com_name(friendly),
                    'status': row.get('Status') or '',
                    'class': row.get('Class') or '',
                    'instance_id': instance_id,
                    'mac_address_guess': extract_mac(instance_id),
                })
        except Exception:
            pass
    return ports, result


def windows_bluetooth_devices():
    ps = (
        "Get-PnpDevice | "
        "Where-Object { $_.Class -eq 'Bluetooth' -or $_.InstanceId -like 'BTH*' } | "
        "Select-Object FriendlyName,InstanceId,Status,Class | ConvertTo-Json -Depth 3"
    )
    result = run_command(['powershell', '-NoProfile', '-Command', ps])
    devices = []
    if result['ok'] and result['stdout']:
        try:
            rows = json.loads(result['stdout'])
            if isinstance(rows, dict):
                rows = [rows]
            seen = set()
            for row in rows:
                friendly = row.get('FriendlyName') or ''
                instance_id = row.get('InstanceId') or ''
                key = (friendly, instance_id)
                if key in seen:
                    continue
                seen.add(key)
                devices.append({
                    'friendly_name': friendly,
                    'status': row.get('Status') or '',
                    'class': row.get('Class') or '',
                    'instance_id': instance_id,
                    'mac_address_guess': extract_mac(instance_id),
                })
        except Exception:
            pass
    return devices, result


def linux_printers():
    result = run_command(['bash', '-lc', 'lpstat -p -d 2>/dev/null || true'])
    printers = []
    default_name = ''
    for line in (result['stdout'] or '').splitlines():
        line = line.strip()
        if line.startswith('printer '):
            parts = line.split()
            if len(parts) >= 2:
                printers.append({'name': parts[1], 'raw': line})
        elif 'system default destination:' in line:
            default_name = line.split(':', 1)[-1].strip()
    for row in printers:
        row['default'] = row['name'] == default_name
    return printers, result


def linux_bluetooth_devices():
    result = run_command(['bash', '-lc', 'bluetoothctl devices 2>/dev/null || true'])
    devices = []
    for line in (result['stdout'] or '').splitlines():
        line = line.strip()
        match = re.match(r'^Device\s+([0-9A-F:]{17})\s+(.+)$', line, re.I)
        if match:
            devices.append({
                'mac_address': match.group(1).upper(),
                'friendly_name': match.group(2).strip(),
            })
    return devices, result


def linux_rfcomm():
    result = run_command(['bash', '-lc', 'rfcomm 2>/dev/null || true'])
    rows = []
    for line in (result['stdout'] or '').splitlines():
        line = line.strip()
        if not line:
            continue
        mac = extract_mac(line)
        dev = ''
        match = re.match(r'^(rfcomm\d+):', line)
        if match:
            dev = '/dev/' + match.group(1)
        rows.append({'device': dev, 'raw': line, 'mac_address_guess': mac})
    return rows, result


def main():
    system = platform.system()
    payload = {
        'generated_at': datetime.now().isoformat(),
        'platform': {
            'system': system,
            'release': platform.release(),
            'version': platform.version(),
            'machine': platform.machine(),
        },
        'notes': [
            'Script ini dijalankan di laptop/PC lokal, bukan di server core.',
            'MAC address Bluetooth di Windows kadang tidak selalu terbaca jelas dari driver; kolom mac_address_guess adalah hasil deteksi terbaik yang tersedia.',
            'Kalau printer sudah bisa Print Test Page dari OS, biasanya nama spooler lebih penting daripada MAC untuk mode spooler.',
        ],
    }

    if system == 'Windows':
        printers, printers_raw = windows_printers()
        ports, ports_raw = windows_ports()
        bt_devices, bt_raw = windows_bluetooth_devices()
        payload['printers'] = printers
        payload['ports'] = ports
        payload['bluetooth_devices'] = bt_devices
        payload['raw_status'] = {
            'printers_ok': printers_raw['ok'],
            'ports_ok': ports_raw['ok'],
            'bluetooth_ok': bt_raw['ok'],
        }
    else:
        printers, printers_raw = linux_printers()
        bt_devices, bt_raw = linux_bluetooth_devices()
        rfcomm_rows, rfcomm_raw = linux_rfcomm()
        payload['printers'] = printers
        payload['bluetooth_devices'] = bt_devices
        payload['rfcomm'] = rfcomm_rows
        payload['raw_status'] = {
            'printers_ok': printers_raw['ok'],
            'bluetooth_ok': bt_raw['ok'],
            'rfcomm_ok': rfcomm_raw['ok'],
        }

    json.dump(payload, sys.stdout, indent=2, ensure_ascii=True)
    sys.stdout.write('\n')


if __name__ == '__main__':
    main()
