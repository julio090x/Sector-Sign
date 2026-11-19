@echo off
setlocal
if exist "%USERPROFILE%\Desktop\Sector Sign.lnk" del "%USERPROFILE%\Desktop\Sector Sign.lnk"
if exist "%LOCALAPPDATA%\SectorSign\App\SectorSign.Windows.exe" del "%LOCALAPPDATA%\SectorSign\App\SectorSign.Windows.exe"
echo Se eliminó la aplicación. Las preferencias y los datos del perfil se conservan en %%LOCALAPPDATA%%\SectorSign\ para evitar pérdidas.
pause
