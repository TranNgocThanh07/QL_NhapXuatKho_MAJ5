@echo off
cd /d "D:\Cong_Viec\Cty_MinhAnh\Website\QL_NhapXuatKho_MAJ5\HTTPS"

:: Đọc biến môi trường
for /f "tokens=1,2 delims==" %%a in (.env) do (
    if "%%a"=="APP_PORT" set APP_PORT=%%b
    if "%%a"=="HTTPS_PORT" set HTTPS_PORT=%%b
    if "%%a"=="USE_HTTPS" set USE_HTTPS=%%b
)

:: Khởi động PHP server trong nền (không hiện cửa sổ mới)
start /B php -S localhost:%APP_PORT% -t public

:: Khởi động Caddy ở chế độ tiền cảnh (hiện log trong cùng cửa sổ)
if "%USE_HTTPS%"=="true" (
    echo.
    echo [HE THONG] PHP Server: http://localhost:%APP_PORT%
    echo [HE THONG] Caddy HTTPS: https://localhost:%HTTPS_PORT%
    echo.
    caddy run
) else (
    echo.
    echo [CANH BAO] Dang chay HTTP khong bao mat: http://localhost:%APP_PORT%
    echo.
    pause
)