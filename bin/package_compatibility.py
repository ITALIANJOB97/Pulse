"""Check release archives with the updater's unchanged, filesystem-only validators.

Do not import the privileged service on the build workstation. Only its four
pure archive functions and literal package limits are loaded, without running
module-level service setup, configuration, network or database operations.
"""
import ast
import hashlib
import os
from pathlib import Path, PurePosixPath
import re
import shutil
import stat
import tempfile
import zipfile


def package_validator(source: Path):
    tree = ast.parse(source.read_text(encoding="utf-8"), filename=str(source))
    names = {"safe_member", "digest", "validate_tree", "extract_release"}
    constants = {"APP_FILES", "APP_DIRS", "MAX_ZIP", "MAX_EXPANDED"}
    scope = {"Path": Path, "PurePosixPath": PurePosixPath, "hashlib": hashlib,
             "os": os, "re": re, "shutil": shutil, "stat": stat, "zipfile": zipfile}
    functions = []
    for node in tree.body:
        if isinstance(node, ast.Assign):
            for target in node.targets:
                if isinstance(target, ast.Name) and target.id in constants:
                    scope[target.id] = ast.literal_eval(node.value) if not isinstance(node.value, ast.BinOp) else _limit(node.value)
        if isinstance(node, ast.FunctionDef) and node.name in names:
            functions.append(node)
    if {node.name for node in functions} != names or not constants <= scope.keys():
        raise ValueError("Unsupported updater validator; review package compatibility")
    exec(compile(ast.Module(body=functions, type_ignores=[]), str(source), "exec"), scope)
    return scope["extract_release"]


def _limit(node):
    if isinstance(node, ast.Constant) and type(node.value) is int:
        return node.value
    if isinstance(node, ast.BinOp) and isinstance(node.op, ast.Mult):
        return _limit(node.left) * _limit(node.right)
    raise ValueError("Unsupported package limit expression")


def verify_package(archive: Path, updater: Path):
    extract = package_validator(updater)
    with tempfile.TemporaryDirectory(prefix="pulse-package-check-") as directory:
        scratch = Path(directory).resolve()
        if scratch.parent != Path(tempfile.gettempdir()).resolve() or not scratch.name.startswith("pulse-package-check-"):
            raise ValueError("Unexpected temporary validation path")
        return extract(archive, scratch / "package")
