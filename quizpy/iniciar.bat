@echo off
chcp 65001 >nul
cd /d "%~dp0"
title QuizPy

where python >nul 2>nul
if errorlevel 1 (
  echo.
  echo  No se encontro Python.
  echo  Descargalo de https://www.python.org/downloads/ y marca "Add python.exe to PATH" al instalar.
  echo.
  pause
  exit /b 1
)

if not exist venv (
  echo Creando entorno virtual...
  python -m venv venv
)
call venv\Scripts\activate.bat
echo Instalando dependencias...
pip install -q -r requirements.txt
python app.py
pause
