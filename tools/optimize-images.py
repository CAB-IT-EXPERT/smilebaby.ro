"""Create lightweight WebP derivatives without touching the source images.

Usage: python tools/optimize-images.py
The application automatically serves these files through optimized_image_url().
"""

from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageOps


ROOT = Path(__file__).resolve().parents[1]
RASTER_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp", ".gif"}


def prepare(image: Image.Image) -> Image.Image:
    image = ImageOps.exif_transpose(image)
    if image.mode in {"RGBA", "LA"} or (image.mode == "P" and "transparency" in image.info):
        return image.convert("RGBA")
    return image.convert("RGB")


def write_variant(source: Path, destination: Path, max_dimension: int, quality: int) -> tuple[int, int]:
    if destination.exists() and destination.stat().st_mtime >= source.stat().st_mtime:
        return source.stat().st_size, destination.stat().st_size

    with Image.open(source) as raw:
        image = prepare(raw)
        image.thumbnail((max_dimension, max_dimension), Image.Resampling.LANCZOS, reducing_gap=3.0)
        destination.parent.mkdir(parents=True, exist_ok=True)
        image.save(destination, "WEBP", quality=quality, method=6, exact=True)
    return source.stat().st_size, destination.stat().st_size


def optimize_uploads() -> tuple[int, int, int]:
    count = before = after = 0
    upload_root = ROOT / "uploads"
    for source in upload_root.rglob("*"):
        if not source.is_file() or source.suffix.lower() not in RASTER_EXTENSIONS:
            continue
        if source.name.endswith((".card.webp", ".display.webp")):
            continue
        base = source.with_suffix("")
        for variant, size, quality in (("display", 1600, 82), ("card", 720, 78)):
            destination = base.with_name(base.name + f".{variant}.webp")
            source_size, output_size = write_variant(source, destination, size, quality)
            before += source_size
            after += output_size
            count += 1
    return count, before, after


def optimize_assets() -> tuple[int, int, int]:
    count = before = after = 0
    asset_root = ROOT / "assets" / "images"
    for source in asset_root.rglob("*"):
        if not source.is_file() or source.suffix.lower() not in {".jpg", ".jpeg", ".png"}:
            continue
        destination = source.with_suffix(".webp")
        source_size, output_size = write_variant(source, destination, 1920, 82)
        before += source_size
        after += output_size
        count += 1
    return count, before, after


def human(value: int) -> str:
    return f"{value / 1024 / 1024:.1f} MB"


if __name__ == "__main__":
    upload_count, upload_before, upload_after = optimize_uploads()
    asset_count, asset_before, asset_after = optimize_assets()
    print(f"Upload variants: {upload_count} | source equivalents {human(upload_before)} -> {human(upload_after)}")
    print(f"Asset variants: {asset_count} | {human(asset_before)} -> {human(asset_after)}")
    print(f"Generated total: {upload_count + asset_count} files")
