@echo off
rem Knowledge Hub: satu klik untuk memasang dan menjalankan semuanya.
rem Syarat: Docker Desktop dan Git for Windows (sudah termasuk Git Bash).
rem Aman dijalankan ulang. Pertama kali butuh sekitar 5 sampai 10 menit.

cd /d "%~dp0"
title Knowledge Hub - setup

set "DOCKER_BIN=D:\Docker\resources\bin"
if exist "%DOCKER_BIN%\docker.exe" set "PATH=%PATH%;%DOCKER_BIN%"

echo.
echo [1/4] Memeriksa Docker...
where docker >nul 2>&1
if errorlevel 1 goto :tanpa_docker

docker info >nul 2>&1
if not errorlevel 1 goto :docker_siap

echo Docker Desktop belum jalan. Menyalakan, tunggu sebentar...
start "" "D:\Docker\Docker Desktop.exe"
set /a tunggu=0

:tunggu_docker
ping -n 6 127.0.0.1 >nul
docker info >nul 2>&1
if not errorlevel 1 goto :docker_siap
set /a tunggu+=5
if %tunggu% GEQ 240 goto :docker_lama
goto :tunggu_docker

:docker_siap
echo Docker siap.

echo.
echo [2/4] Mencari Git Bash...
set "BASH="
for %%P in ("%ProgramFiles%\Git\bin\bash.exe" "%ProgramFiles(x86)%\Git\bin\bash.exe" "%LocalAppData%\Programs\Git\bin\bash.exe") do (
  if not defined BASH if exist %%P set "BASH=%%~P"
)
if defined BASH goto :bash_siap

for /f "delims=" %%G in ('where git 2^>nul') do (
  if not defined BASH if exist "%%~dpG..\bin\bash.exe" set "BASH=%%~dpG..\bin\bash.exe"
)
if not defined BASH goto :tanpa_bash

:bash_siap
echo Git Bash: %BASH%

echo.
echo [3/4] Menyalakan container...
docker compose up -d
if errorlevel 1 goto :gagal_compose
ping -n 11 127.0.0.1 >nul

echo.
echo [4/4] Memasang Drupal, modul, tema, dan konten contoh. Jangan ditutup...
set "CHERE_INVOKING=1"
"%BASH%" -l setup/restore.sh
if errorlevel 1 goto :gagal

set "PORT=8080"
if exist .env (
  for /f "tokens=1,* delims==" %%A in (.env) do (
    if "%%A"=="DRUPAL_PORT" set "PORT=%%B"
  )
)

echo.
echo ==================================================
echo  Selesai. Buka di browser:
echo  http://127.0.0.1:%PORT%
echo.
echo  Masuk di http://127.0.0.1:%PORT%/user/login
echo    demo_superadmin  sandi demo   Super Admin
echo    demo_admin       sandi demo   Admin
echo    demo_auditor     sandi demo   Auditor
echo.
echo  Pakai 127.0.0.1, bukan localhost.
echo ==================================================
if not defined KH_NOPAUSE pause
exit /b 0

:tanpa_docker
echo Docker belum terpasang. Pasang Docker Desktop dulu:
echo https://www.docker.com/products/docker-desktop/
goto :gagal

:docker_lama
echo Docker tidak siap setelah 4 menit. Buka Docker Desktop manual,
echo tunggu sampai berjalan, lalu jalankan setup.bat lagi.
goto :gagal

:tanpa_bash
echo Git Bash tidak ditemukan. Pasang Git for Windows dulu:
echo https://git-scm.com/download/win
goto :gagal

:gagal_compose
echo Docker compose gagal. Kemungkinan port 8080 dipakai program lain.
echo Buat file .env berisi satu baris: DRUPAL_PORT=8081
echo lalu jalankan setup.bat lagi.

:gagal
echo.
echo Setup gagal. Baca pesan di atas.
if not defined KH_NOPAUSE pause
exit /b 1