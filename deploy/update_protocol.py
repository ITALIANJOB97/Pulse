"""Manifest contract and bounded HTTPS transport used by the existing updater."""
from __future__ import annotations
import datetime as dt
import json
from pathlib import Path
import re
import time
import urllib.error
import urllib.request

SOURCE = json.loads(Path(__file__).with_name("update-source.json").read_text(encoding="utf-8"))
BASE_URL = SOURCE["baseUrl"]
MANIFEST_URL = BASE_URL + SOURCE["manifest"]
MAX_MANIFEST = 1024 * 1024
CHECK_INTERVAL = 6 * 3600
MAX_PACKAGE = 100 * 1024 * 1024
VERSION_PATTERN = r"(?:0|[1-9][0-9]{0,8})\.(?:0|[1-9][0-9]{0,8})\.(?:0|[1-9][0-9]{0,8})"

class DownloadCancelled(Exception):
    pass

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        raise ValueError("Update redirects are not permitted")

def version(value):
    # PULSE ships numeric stable triples; prerelease/build versions are rejected.
    if not isinstance(value, str) or not re.fullmatch(VERSION_PATTERN, value):
        raise ValueError("Invalid version")
    return tuple(map(int, value.split(".")))

def package_url(url):
    # Exact origin and one ZIP filename: no traversal, escapes, ports or queries.
    return isinstance(url, str) and bool(re.fullmatch(
        re.escape(BASE_URL) + r"[A-Za-z0-9][A-Za-z0-9_-]*(?:\.[A-Za-z0-9_-]+)*\.zip", url))

def parse_json(body):
    if len(body) > MAX_MANIFEST:
        raise ValueError("Manifest exceeds 1 MB")
    def pairs(items):
        result = {}
        for key, value in items:
            if key in result:
                raise ValueError("Duplicate JSON field")
            result[key] = value
        return result
    value = json.loads(body, object_pairs_hook=pairs)
    if not isinstance(value, dict):
        raise ValueError("Expected a JSON object")
    return value

def validate_manifest(data, clock=None):
    clock = clock or dt.datetime.now(dt.timezone.utc)
    if not isinstance(data, dict) or type(data.get("schemaVersion")) is not int or data["schemaVersion"] != 1 or data.get("program") != SOURCE["program"]:
        raise ValueError("Wrong program or manifest schema")
    version(data.get("version"))
    if not package_url(data.get("downloadUrl")):
        raise ValueError("Package URL is outside the official directory")
    sha = data.get("sha256")
    if not isinstance(sha, str) or not re.fullmatch(r"[a-fA-F0-9]{64}", sha):
        raise ValueError("Invalid SHA-256")
    minimum = data.get("minimumSupportedVersion", "0.0.0")
    if version(minimum) > version(data["version"]):
        raise ValueError("Minimum supported version exceeds release version")
    mandatory = data.get("mandatory", False)
    if type(mandatory) is not bool:
        raise ValueError("Invalid mandatory flag")
    size = data.get("size")
    if "size" in data and (type(size) is not int or not 0 < size <= MAX_PACKAGE):
        raise ValueError("Invalid package size")
    published = data.get("publishedAt")
    if published is not None:
        if not isinstance(published, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})", published):
            raise ValueError("Invalid publication date")
        date = dt.datetime.fromisoformat(published.replace("Z", "+00:00"))
        if date.tzinfo is None or date > clock + dt.timedelta(minutes=10):
            raise ValueError("Invalid publication date; check system clock")
    notes = data.get("releaseNotes", {})
    if not isinstance(notes, dict):
        raise ValueError("Invalid release notes")
    clean = {}
    for category in ("features", "improvements", "fixes"):
        items = notes.get(category, [])
        if not isinstance(items, list) or len(items) > 50 or any(not isinstance(x, str) or len(x) > 2000 for x in items):
            raise ValueError("Invalid release notes")
        clean[category] = items
    requirements = data.get("pulse", {})
    if not isinstance(requirements, dict):
        raise ValueError("Invalid PULSE requirements")
    php = requirements.get("phpMin", "8.3.0")
    version(php)
    updater = requirements.get("minUpdater", 4)
    schema = requirements.get("dbSchema", 1)
    if type(updater) is not int or updater < 1 or type(schema) is not int or not 1 <= schema <= 1000 or requirements.get("platform", "linuxmint22-amd64") != "linuxmint22-amd64":
        raise ValueError("Unsupported PULSE requirements")
    return {"version": data["version"], "url": data["downloadUrl"], "sha256": sha.lower(),
            "size": size, "mandatory": mandatory, "published_at": published, "release_notes": clean,
            "notes": {"en": "", "de": "", "fr": ""}, "min_from": minimum,
            "min_updater": updater, "php_min": php, "db_schema": schema, "protocol": 1}

def fetch(url, target, limit, *, etag=None, cancelled=None, progress=None, opener=None):
    if url not in (MANIFEST_URL, MANIFEST_URL + ".sig") and not package_url(url):
        raise ValueError("Untrusted update URL")
    target = Path(target)
    headers = {"User-Agent": "PULSE-Updater/4", "Accept": "application/octet-stream", "Cache-Control": "no-cache"}
    if isinstance(etag, str) and len(etag) <= 512 and not re.search(r"[\r\n]", etag):
        headers["If-None-Match"] = etag
    request = urllib.request.Request(url, headers=headers)
    started = time.monotonic()
    def check():
        if cancelled and cancelled():
            raise DownloadCancelled("Download cancelled")
        if time.monotonic() - started > 300:
            raise TimeoutError("Download time limit exceeded")
    try:
        check()
        opener = opener or urllib.request.build_opener(NoRedirect)
        try:
            response = opener.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            if error.code == 304 and etag:
                error.close()
                return {"not_modified": True, "etag": etag}
            raise
        with response, open(target, "wb") as output:
            if response.status != 200:
                raise ValueError("Unexpected HTTP response")
            length = response.headers.get("Content-Length")
            total = int(length) if length is not None and length.isdigit() else None
            if total is not None and total > limit:
                raise ValueError("Download exceeds size limit")
            count = 0
            while True:
                check()
                chunk = response.read(65536)
                check()
                if not chunk:
                    break
                count += len(chunk)
                if count > limit:
                    raise ValueError("Download exceeds size limit")
                output.write(chunk)
                if progress:
                    progress(count, total, count / max(time.monotonic() - started, 0.001))
            if total is not None and count != total:
                raise ValueError("Truncated download")
            output.flush()
            import os
            os.fsync(output.fileno())
            tag = response.headers.get("ETag")
            if tag is not None and (len(tag) > 512 or re.search(r"[\r\n]", tag)):
                tag = None
            return {"not_modified": False, "etag": tag, "size": count}
    except BaseException:
        target.unlink(missing_ok=True)
        raise

def error_code(error):
    if isinstance(error, DownloadCancelled):
        return "cancelled"
    if isinstance(error, urllib.error.HTTPError):
        return "http_" + str(error.code) if error.code in (403, 404, 500) else "http_error"
    if isinstance(error, TimeoutError) or isinstance(error, urllib.error.URLError) and isinstance(error.reason, TimeoutError):
        return "timeout"
    if isinstance(error, urllib.error.URLError):
        return "offline"
    return "source_or_signature_unavailable"
