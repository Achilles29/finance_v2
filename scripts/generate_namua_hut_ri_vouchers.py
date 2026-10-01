from pathlib import Path

from reportlab.lib.colors import Color, HexColor, white
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.lib.utils import ImageReader


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "output" / "pdf"
OUTPUT_FILE = OUTPUT_DIR / "voucher-namua-hut-ri-18-24-agustus-2026.pdf"
LOGO_FILE = ROOT / "assets" / "roastery" / "logo 2.png"

RED = HexColor("#B3131B")
DEEP_RED = HexColor("#6E0713")
MAROON = HexColor("#681022")
INK = HexColor("#241C1B")
MUTED = HexColor("#6C6260")
PALE_RED = HexColor("#FFF3F2")
GOLD = HexColor("#B78A38")
CUT = HexColor("#B9B1AF")

PAGE_W, PAGE_H = A4
MARGIN_X = 7 * mm
MARGIN_Y = 7 * mm
COL_GAP = 3 * mm
ROW_GAP = 2.5 * mm
CARD_W = (PAGE_W - (2 * MARGIN_X) - COL_GAP) / 2
CARD_H = (PAGE_H - (2 * MARGIN_Y) - (4 * ROW_GAP)) / 5


def register_fonts() -> None:
    pdfmetrics.registerFont(TTFont("Arial", r"C:\Windows\Fonts\arial.ttf"))
    pdfmetrics.registerFont(TTFont("Arial-Bold", r"C:\Windows\Fonts\arialbd.ttf"))
    pdfmetrics.registerFont(TTFont("Arial-Narrow-Bold", r"C:\Windows\Fonts\ARIALNB.TTF"))
    pdfmetrics.registerFont(TTFont("Georgia-Bold", r"C:\Windows\Fonts\georgiab.ttf"))


def draw_centered(c: canvas.Canvas, text: str, center_x: float, y: float, font: str, size: float, color=INK) -> None:
    c.setFont(font, size)
    c.setFillColor(color)
    c.drawCentredString(center_x, y, text)


def draw_logo(c: canvas.Canvas, x: float, y: float, width: float) -> None:
    logo = ImageReader(str(LOGO_FILE))
    logo_w, logo_h = logo.getSize()
    height = width * logo_h / logo_w
    c.drawImage(logo, x, y, width=width, height=height, mask="auto", preserveAspectRatio=True)


def draw_voucher(c: canvas.Canvas, x: float, y: float, amount: int, code: str, serial: int, total: int) -> None:
    # Cutting guide.
    c.saveState()
    c.setStrokeColor(CUT)
    c.setLineWidth(0.45)
    c.setDash(2.4, 2.0)
    c.rect(x, y, CARD_W, CARD_H, stroke=1, fill=0)
    c.restoreState()

    inset = 1.4 * mm
    ix, iy = x + inset, y + inset
    iw, ih = CARD_W - (2 * inset), CARD_H - (2 * inset)

    c.saveState()
    c.setFillColor(white)
    c.setStrokeColor(MAROON)
    c.setLineWidth(0.7)
    c.roundRect(ix, iy, iw, ih, 2.6 * mm, stroke=1, fill=1)

    # Red independence panel.
    panel_w = 25 * mm
    path = c.beginPath()
    path.roundRect(ix, iy, iw, ih, 2.6 * mm)
    c.clipPath(path, stroke=0, fill=0)
    c.setFillColor(RED)
    c.rect(ix, iy, panel_w, ih, stroke=0, fill=1)
    c.setFillColor(DEEP_RED)
    c.circle(ix + 3 * mm, iy + ih - 3 * mm, 13 * mm, stroke=0, fill=1)
    c.setFillColor(Color(1, 1, 1, alpha=0.12))
    c.circle(ix + panel_w - 1 * mm, iy + 4 * mm, 15 * mm, stroke=0, fill=1)
    c.restoreState()

    panel_cx = ix + panel_w / 2
    draw_centered(c, "81", panel_cx, iy + 25.5 * mm, "Georgia-Bold", 26, white)
    draw_centered(c, "HUT RI", panel_cx, iy + 20.3 * mm, "Arial-Bold", 8.7, white)
    c.setStrokeColor(white)
    c.setLineWidth(0.8)
    c.line(panel_cx - 6 * mm, iy + 18 * mm, panel_cx + 6 * mm, iy + 18 * mm)
    draw_centered(c, "MERDEKA!", panel_cx, iy + 14 * mm, "Arial-Bold", 6.3, white)
    draw_centered(c, "17.08.1945", panel_cx, iy + 9.7 * mm, "Arial", 5.2, white)

    content_x = ix + panel_w
    content_w = iw - panel_w
    center_x = content_x + content_w / 2

    draw_logo(c, content_x + 4.5 * mm, iy + ih - 22.8 * mm, 22 * mm)
    c.setFillColor(GOLD)
    c.roundRect(ix + iw - 18.5 * mm, iy + ih - 8.7 * mm, 14 * mm, 4.8 * mm, 1.2 * mm, stroke=0, fill=1)
    draw_centered(c, "VOUCHER", ix + iw - 11.5 * mm, iy + ih - 7.2 * mm, "Arial-Bold", 5.2, white)

    draw_centered(c, "SENILAI", center_x, iy + 27.6 * mm, "Arial-Bold", 6.1, MUTED)
    draw_centered(c, f"Rp{amount:,.0f}".replace(",", "."), center_x, iy + 19.3 * mm, "Arial-Narrow-Bold", 22, MAROON)

    code_x = content_x + 5 * mm
    code_w = content_w - 10 * mm
    code_y = iy + 9.1 * mm
    c.setFillColor(PALE_RED)
    c.setStrokeColor(RED)
    c.setLineWidth(0.65)
    c.roundRect(code_x, code_y, code_w, 7.3 * mm, 1.5 * mm, stroke=1, fill=1)
    c.setFillColor(MUTED)
    c.setFont("Arial-Bold", 5.3)
    c.drawString(code_x + 2.4 * mm, code_y + 4.6 * mm, "KODE")
    c.setFillColor(DEEP_RED)
    c.setFont("Arial-Bold", 10.2)
    c.drawRightString(code_x + code_w - 2.4 * mm, code_y + 3.2 * mm, code)

    c.setFillColor(INK)
    c.setFont("Arial-Bold", 5.5)
    c.drawString(content_x + 5 * mm, iy + 5.6 * mm, "BERLAKU 18 - 24 AGUSTUS 2026")
    c.setFillColor(MUTED)
    c.setFont("Arial", 4.7)
    c.drawRightString(ix + iw - 4.5 * mm, iy + 2.8 * mm, f"{serial:02d}/{total:02d}")


def voucher_data() -> list[tuple[int, str, int, int]]:
    groups = [
        (70000, "SMADA70", 3),
        (60000, "SMADA60", 3),
        (50000, "SMADA50", 3),
        (40000, "SMADA40", 10),
    ]
    vouchers = []
    for amount, code, count in groups:
        for serial in range(1, count + 1):
            vouchers.append((amount, code, serial, count))
    return vouchers


def build_pdf() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    c = canvas.Canvas(str(OUTPUT_FILE), pagesize=A4, pageCompression=1)
    c.setTitle("Voucher NAMUA - HUT RI 2026")
    c.setAuthor("NAMUA Coffee Roasters")
    c.setSubject("Voucher berlaku 18 - 24 Agustus 2026")

    vouchers = voucher_data()
    for page_start in range(0, len(vouchers), 10):
        page_vouchers = vouchers[page_start : page_start + 10]
        for slot, (amount, code, serial, total) in enumerate(page_vouchers):
            col = slot % 2
            row = slot // 2
            x = MARGIN_X + col * (CARD_W + COL_GAP)
            y = PAGE_H - MARGIN_Y - CARD_H - row * (CARD_H + ROW_GAP)
            draw_voucher(c, x, y, amount, code, serial, total)
        c.showPage()

    c.save()
    print(OUTPUT_FILE)


if __name__ == "__main__":
    register_fonts()
    build_pdf()
