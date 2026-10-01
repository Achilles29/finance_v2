#!/usr/bin/env python3
"""DB-free trust-boundary smoke test for the local POS printer agent."""

from __future__ import annotations

import importlib.util
import sys
import tempfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
AGENT_PATH = ROOT / "tools" / "pos_printer_agent" / "agent.py"
CHECKER_PATH = ROOT / "tools" / "pos_printer_agent" / "check_saved_printers.py"
CONFIG_EXAMPLE_PATH = ROOT / "tools" / "pos_printer_agent" / "config.example.json"
README_PATH = ROOT / "tools" / "pos_printer_agent" / "README.md"
REQUIREMENTS_PATH = ROOT / "tools" / "pos_printer_agent" / "requirements.txt"
WINDOWS_INSTALLER_PATH = ROOT / "tools" / "pos_printer_agent" / "install_windows_task.bat"
WINDOWS_UNINSTALLER_PATH = ROOT / "tools" / "pos_printer_agent" / "uninstall_windows_task.bat"
LINUX_INSTALLER_PATH = ROOT / "tools" / "pos_printer_agent" / "install_linux_service.sh"
LINUX_UNINSTALLER_PATH = ROOT / "tools" / "pos_printer_agent" / "uninstall_linux_service.sh"
POS_AGENT_PATH = ROOT / "application" / "controllers" / "Pos_printer_agent.php"
POS_PATH = ROOT / "application" / "controllers" / "Pos.php"
LEGACY_GUIDE_PATH = ROOT / "application" / "views" / "pos" / "printer_guide.php"
CONFIG_GUIDE_PATH = ROOT / "application" / "views" / "pos" / "printer_guide_config.php"
ROUTES_PATH = ROOT / "application" / "config" / "routes.php"


def load_agent_module():
    try:
        import flask  # noqa: F401
    except Exception as exc:
        print(f"FAIL: Flask dependency unavailable: {type(exc).__name__}")
        return None
    spec = importlib.util.spec_from_file_location("printer_agent_smoke_module", AGENT_PATH)
    if spec is None or spec.loader is None:
        print("FAIL: unable to load printer agent module")
        return None
    module = importlib.util.module_from_spec(spec)
    try:
        spec.loader.exec_module(module)
    except Exception as exc:
        print(f"FAIL: printer agent import failed: {type(exc).__name__}")
        return None
    return module


def assert_equal(actual, expected, message: str) -> None:
    if actual != expected:
        raise AssertionError(f"{message}: expected {expected!r}, got {actual!r}")


def assert_true(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def assert_link_is_guarded(source: str, link_token: str, label: str) -> None:
    guard = "<?php if ($canAgentProvision): ?>"
    end_guard = "<?php endif; ?>"
    positions = []
    cursor = 0
    while True:
        position = source.find(link_token, cursor)
        if position < 0:
            break
        positions.append(position)
        cursor = position + len(link_token)
    assert_true(positions, f"{label} link is missing")
    for position in positions:
        guard_start = source.rfind(guard, 0, position)
        guard_end = source.find(end_guard, guard_start + len(guard)) if guard_start >= 0 else -1
        assert_true(
            guard_start >= 0 and guard_end >= 0 and position < guard_end,
            f"{label} link is not guarded by exact provisioning boolean",
        )


def run_http_smoke(agent) -> None:
    valid_origin = "https://finance.example.test"
    foreign_origin = "https://foreign.example.test"
    calls = []

    def fake_safe_print(*args) -> None:
        calls.append(args)

    app = agent.create_printer_app(
        {
            "lokasi": "KASIR",
            "printer_code": "KASIR",
            "mac": "AABBCCDDEEFF",
            "python_port": 3010,
            "paper_width_mm": 80,
        },
        fake_safe_print,
        {"api": {"base_url": valid_origin + "/finance", "allowed_origins": []}},
    )
    client = app.test_client()

    response = client.post(
        "/cetak",
        json={"text": "smoke", "copies": 1},
        headers={"Origin": valid_origin},
    )
    assert_equal(response.status_code, 200, "valid origin POST status")
    assert_equal(response.headers.get("Access-Control-Allow-Origin"), valid_origin, "valid origin ACAO")
    assert_equal(response.headers.get("Vary"), "Origin", "valid origin Vary")
    assert_equal(len(calls), 1, "valid origin safe_print call count")

    for rejected_origin in (foreign_origin, "", "null"):
        response = client.post(
            "/cetak",
            data=b"not-json",
            headers={"Origin": rejected_origin, "Content-Type": "application/json"},
        )
        assert_equal(response.status_code, 403, f"rejected origin {rejected_origin!r} status")
        assert_true("Access-Control-Allow-Origin" not in response.headers, "rejected origin must not receive ACAO")
        assert_equal(len(calls), 1, "rejected origin must not call safe_print")

    response = client.post("/cetak", data=b"not-json", headers={"Content-Type": "application/json"})
    assert_equal(response.status_code, 403, "missing origin status")
    assert_equal(len(calls), 1, "missing origin must not call safe_print")

    response = client.options(
        "/cetak",
        headers={
            "Origin": valid_origin,
            "Access-Control-Request-Method": "POST",
            "Access-Control-Request-Headers": "Content-Type",
        },
    )
    assert_equal(response.status_code, 204, "valid preflight status")
    assert_equal(response.headers.get("Access-Control-Allow-Origin"), valid_origin, "preflight ACAO")
    assert_equal(response.headers.get("Vary"), "Origin", "preflight Vary")
    assert_true("POST" in response.headers.get("Access-Control-Allow-Methods", ""), "preflight methods")
    assert_true("Content-Type" in response.headers.get("Access-Control-Allow-Headers", ""), "preflight headers")

    response = client.options(
        "/cetak",
        headers={"Origin": foreign_origin, "Access-Control-Request-Method": "POST"},
    )
    assert_equal(response.status_code, 403, "foreign preflight status")
    assert_true("Access-Control-Allow-Origin" not in response.headers, "foreign preflight must not receive ACAO")

    response = client.get("/health", headers={"Origin": valid_origin})
    assert_equal(response.status_code, 200, "health status")
    assert_true("Access-Control-Allow-Origin" not in response.headers, "health must not receive ACAO")

    offline_origin = "http://pos-offline.example.test:8080"
    offline_calls = []
    offline_app = agent.create_printer_app(
        {"lokasi": "OFFLINE", "mac": "AABBCCDDEEFF", "python_port": 3011, "paper_width_mm": 80},
        lambda *args: offline_calls.append(args),
        {"api": {"base_url": "", "enabled": False, "allowed_origins": [offline_origin]}},
    )
    response = offline_app.test_client().post(
        "/cetak", json={"text": "offline"}, headers={"Origin": offline_origin}
    )
    assert_equal(response.status_code, 200, "offline allowlist POST status")
    assert_equal(len(offline_calls), 1, "offline allowlist safe_print call count")


def run_lifecycle_smoke(agent) -> None:
    current = [{"printer_code": "KASIR", "mac_address": "AABBCCDDEEFF", "python_port": 3010, "paper_width_mm": 80}]
    changed = [{"printer_code": "KASIR", "mac_address": "AABBCCDDEEFF", "python_port": 3011, "paper_width_mm": 80}]
    with tempfile.TemporaryDirectory() as temp_dir:
        config_path = Path(temp_dir) / "config.json"
        service = agent.PrinterService(
            {
                "agent_name": "TEST-AGENT",
                "api": {"enabled": True, "refresh_seconds": 5},
                "printers": current,
            },
            config_path=config_path,
        )
        service.started_ports = {3010: service.printers[0]}
        service.fetch_printers_from_api = lambda: service.normalize_printers(changed, source="smoke")
        service.refresh_printers_if_needed()
        assert_true(service.restart_required, "connection change must require a controlled restart")
        assert_true("Restart Local Printer Agent" in service.restart_reason, "restart reason is missing")
        assert_equal(service.started_ports[3010]["python_port"], 3010, "old port must stay untouched until restart")
        status = service.service_status()
        assert_equal(status["state"], "restart_required", "health service state")

        service.config["printers"] = current
        service.write_config_atomically()
        saved = config_path.read_text(encoding="utf-8")
        assert_true('"printers"' in saved, "atomic runtime config was not written")

    assert_equal(agent.AGENT_PROTOCOL_VERSION, 2, "agent protocol version")
    service = agent.PrinterService({"api": {"enabled": False}, "printers": []})
    service.validate_bootstrap_contract({"agent_contract": {"min_protocol": 1, "max_protocol": 2}})
    try:
        service.validate_bootstrap_contract({"agent_contract": {"min_protocol": 3, "max_protocol": 4}})
    except agent.AgentError:
        pass
    else:
        raise AssertionError("unsupported server protocol must fail closed")


def run_source_smoke() -> None:
    agent_source = AGENT_PATH.read_text(encoding="utf-8")
    checker_source = CHECKER_PATH.read_text(encoding="utf-8")
    config_example = CONFIG_EXAMPLE_PATH.read_text(encoding="utf-8")
    readme_source = README_PATH.read_text(encoding="utf-8")
    requirements_source = REQUIREMENTS_PATH.read_text(encoding="utf-8")
    pos_agent_source = POS_AGENT_PATH.read_text(encoding="utf-8")
    pos_source = POS_PATH.read_text(encoding="utf-8")
    legacy_guide_source = LEGACY_GUIDE_PATH.read_text(encoding="utf-8")
    config_guide_source = CONFIG_GUIDE_PATH.read_text(encoding="utf-8")
    routes_source = ROUTES_PATH.read_text(encoding="utf-8")

    assert_true("from flask_cors import" not in agent_source, "global Flask CORS import remains")
    assert_true("CORS(" not in agent_source, "global CORS(app) remains")
    assert_true("key_query_param" not in agent_source, "agent query key fallback remains")
    assert_true("key_query_param" not in checker_source, "checker query key fallback remains")
    assert_true('"allowed_origins"' in config_example, "config example lacks allowed_origins")
    assert_true('"key_query_param"' not in config_example, "config example documents query key fallback")
    assert_true("allowed_origins" in readme_source, "agent README lacks allowed_origins")
    assert_true("X-Printer-Key" in readme_source, "agent README lacks header-only bootstrap key")
    assert_true("POS_PRINTER_AGENT_KEYS" in readme_source, "agent README lacks per-agent pairing guidance")
    assert_true("flask-cors" not in requirements_source, "unused Flask CORS dependency remains")
    for path, label in (
        (WINDOWS_INSTALLER_PATH, "Windows service installer"),
        (WINDOWS_UNINSTALLER_PATH, "Windows service uninstaller"),
        (LINUX_INSTALLER_PATH, "Linux service installer"),
        (LINUX_UNINSTALLER_PATH, "Linux service uninstaller"),
    ):
        assert_true(path.is_file(), f"{label} is missing")
    assert_true("install_windows_task" in pos_source, "Windows service installer is not downloadable")
    assert_true("install_linux_service" in pos_source, "Linux service installer is not downloadable")
    assert_true("POS_PRINTER_AGENT_KEYS" in pos_agent_source, "per-agent pairing map is missing")
    assert_true("POS_PRINTER_BOOTSTRAP_KEY_PREVIOUS" in pos_agent_source, "grace key rotation is missing")
    assert_true("agent_contract" in pos_agent_source, "bootstrap does not advertise protocol contract")
    assert_true("restart_required" in agent_source, "agent does not report controlled restart state")
    assert_true("write_config_atomically" in agent_source, "agent config persistence is not atomic")
    assert_true("public function printer_bootstrap()" not in pos_source, "legacy Pos printer bootstrap remains")
    assert_true(
        "$route['pos/printers/bootstrap'] = 'pos_printer_agent/bootstrap';" in routes_source,
        "official slash printer bootstrap route target changed",
    )
    assert_true(
        "$route['pos-printers/bootstrap'] = 'pos_printer_agent/bootstrap';" in routes_source,
        "official hyphen printer bootstrap route target changed",
    )
    assert_true(
        "$route['pos/printers/download/(:any)'] = 'pos/printer_download/$1';" in routes_source,
        "printer download route changed",
    )

    download_start = pos_source.index("public function printer_download(")
    download_end = pos_source.index("\n    public function ", download_start + 1)
    download_source = pos_source[download_start:download_end]
    provisioning_gate = "if (in_array($key, ['config_json', 'agent_bundle'], true))"
    assert_true(provisioning_gate in download_source, "download provisioning selector is not exact")
    assert_true(
        "$this->require_permission('pos.printer.connection', 'edit');" in download_source,
        "download provisioning gate does not require connection edit",
    )
    assert_true(
        "printer_config_permission_page" not in download_source,
        "download provisioning gate uses the fallback permission helper",
    )
    gate_offset = download_source.index("$this->require_permission('pos.printer.connection', 'edit');")
    assert_true(
        gate_offset < download_source.index("$this->printer_download_bundle();"),
        "agent bundle is built before the exact permission gate",
    )
    assert_true(
        gate_offset < download_source.index("$this->build_printer_agent_config_json("),
        "agent config is generated before the exact permission gate",
    )
    assert_true(
        "$this->require_permission('pos.printer.index', 'view');" in download_source,
        "static printer downloads lost the legacy view permission",
    )

    for method_name in ("public function printer_guide()", "public function printer_guide_config()"):
        method_start = pos_source.index(method_name)
        method_end = pos_source.index("\n    public function ", method_start + 1)
        method_source = pos_source[method_start:method_end]
        assert_true(
            "'can_agent_provision' => $this->can('pos.printer.connection', 'edit')" in method_source,
            f"{method_name} does not pass the exact provisioning boolean",
        )
    for guide_name, guide_source in (
        ("legacy printer guide", legacy_guide_source),
        ("config printer guide", config_guide_source),
    ):
        assert_true(
            "$canAgentProvision = !empty($can_agent_provision);" in guide_source,
            f"{guide_name} does not default provisioning permission to false",
        )
    assert_link_is_guarded(legacy_guide_source, "download/config_json", "legacy config.json")
    assert_link_is_guarded(config_guide_source, "printerDownloadUrl('config_json')", "config guide config.json")
    assert_link_is_guarded(config_guide_source, "printerDownloadUrl('agent_bundle')", "config guide agent bundle")

    permission_helper_start = pos_source.index(
        "private function printer_config_permission_page(string $pageCode): string"
    )
    permission_helper_end = pos_source.index(
        "\n    private function stock_live_filters", permission_helper_start
    )
    permission_helper = pos_source[permission_helper_start:permission_helper_end]
    assert_true("table_exists('sys_page')" in permission_helper, "printer permission registry presence check is missing")
    assert_true("where('page_code', $pageCode)" in permission_helper, "printer permission registry lookup changed")
    assert_true("return $hasPage ? $pageCode : 'pos.printer.index';" in permission_helper, "printer permission fallback changed")
    assert_true(
        "return $this->can($this->printer_config_permission_page($pageCode), $action);" in permission_helper,
        "printer capability checks do not use the active registry resolver",
    )
    assert_true(
        "$this->require_permission($this->printer_config_permission_page($pageCode), $action);" in permission_helper,
        "printer guards do not use the active registry resolver",
    )

    active_permission_contracts = {
        "printer_connections": "'pos.printer.connection', 'view'",
        "printer_general": "'pos.printer.general', 'view'",
        "printer_layouts": "'pos.printer.layout', 'view'",
        "printer_rules": "'pos.printer.rule', 'view'",
    }
    for method_name, permission_args in active_permission_contracts.items():
        method_start = pos_source.index(f"public function {method_name}()")
        method_end = pos_source.index("\n    public function ", method_start + 1)
        method_source = pos_source[method_start:method_end]
        assert_true(
            f"$this->require_printer_config_permission({permission_args});" in method_source,
            f"{method_name} no longer uses its active registry permission guard",
        )

    for source_name, source in (("Pos_printer_agent", pos_agent_source), ("Pos", pos_source)):
        assert_true("getenv('POS_PRINTER_BOOTSTRAP_KEY')" in source, f"{source_name} key guard missing")
        assert_true("get_request_header('X-Printer-Key'" in source, f"{source_name} header guard missing")
        assert_true("$this->input->get('key'" not in source, f"{source_name} query key fallback remains")
        assert_true("set_status_header(503)" in source, f"{source_name} missing-key 503 missing")
        assert_true("set_status_header(403)" in source, f"{source_name} invalid-key 403 missing")

    pos_agent_bootstrap = pos_agent_source.index("public function bootstrap()")
    pos_agent_guard = pos_agent_source.index("verify_printer_agent_key()", pos_agent_bootstrap)
    pos_agent_model_load = pos_agent_source.index("$this->load->model('Pos_print_model')", pos_agent_bootstrap)
    assert_true(pos_agent_guard < pos_agent_model_load, "agent model loads before key guard")


def main() -> int:
    try:
        run_source_smoke()
    except AssertionError as exc:
        print(f"FAIL: {exc}")
        return 1
    print("PASS: printer agent static/source smoke")

    if "--source-only" in sys.argv[1:]:
        print("PASS: printer agent source contract complete; Flask HTTP runtime not executed")
        return 0

    agent = load_agent_module()
    if agent is None:
        return 1
    try:
        run_http_smoke(agent)
        run_lifecycle_smoke(agent)
    except AssertionError as exc:
        print(f"FAIL: {exc}")
        return 1
    print("PASS: printer agent trust smoke (DB-free)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
