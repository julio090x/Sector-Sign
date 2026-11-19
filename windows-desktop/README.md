# Sector Sign para Windows (Centro de Mando y Reproductor)

Aplicación de escritorio Windows 10/11, x64, construida con .NET 8 WinForms y Microsoft Edge WebView2.

- Un solo `.exe`, modos Centro de Mando y Reproductor.
- La primera conexión utiliza `https://sectorsign.sectorsolutionssac.com` (editable en Servidor).
- Puede conservar sesión en el perfil WebView2 del usuario. No guarda contraseñas ni tokens en `settings.json`.
- Respeta las URL de cada sección `?vista=...` (F5/atrás/adelante desde el sitio).
- F11 muestra pantalla completa; salir del reproductor requiere confirmación.
- WebView2 requiere Microsoft Edge WebView2 Runtime. Windows 11 suele traerlo, pero debe comprobarse en el equipo.
- **Importante:** el modo receptor Windows depende del almacenamiento de WebView2. NO ofrece la garantía de caché nativa verificada implementada en Android para video offline. Comprueba desconexión en tu equipo antes de usarlo con publicidad crítica.
- **Importante:** el `.exe` se entrega **sin firma Authenticode**. Una firma Windows de distribución requiere certificado de firma de código del propietario (distinto de la firma de las APK Android). No instalar en equipos ajenos sin realizar pruebas.

Para desarrollo: `dotnet publish SectorSign.Windows.csproj -r win-x64 -c Release --self-contained true -p:PublishSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=true -o publish`.
