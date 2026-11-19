@echo off
setlocal
set "TARGET=%LOCALAPPDATA%\SectorSign\App"
if not exist "%TARGET%" mkdir "%TARGET%"
copy /Y "%~dp0SectorSign.Windows.exe" "%TARGET%\SectorSign.Windows.exe" >nul
powershell -NoProfile -ExecutionPolicy Bypass -Command "$w=New-Object -ComObject WScript.Shell;$s=$w.CreateShortcut([Environment]::GetFolderPath('Desktop')+'\Sector Sign.lnk');$s.TargetPath=$env:LOCALAPPDATA+'\SectorSign\App\SectorSign.Windows.exe';$s.WorkingDirectory=$env:LOCALAPPDATA+'\SectorSign\App';$s.Save()"
if errorlevel 1 (
 echo No se pudo crear el acceso directo. Abre manualmente "%TARGET%\SectorSign.Windows.exe".
) else (
 echo Sector Sign instalado para el usuario actual.
)
pause
