using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Text.Json;
using System.Windows.Forms;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;

namespace SectorSignWindows;

internal static class Program
{
    [STAThread]
    static void Main()
    {
        Application.SetHighDpiMode(HighDpiMode.PerMonitorV2);
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);
        Application.Run(new SectorSignForm());
    }
}

internal sealed class LocalSettings
{
    public string Server { get; set; } = "https://sectorsign.sectorsolutionssac.com";
    public string Mode { get; set; } = "mando";
}

internal sealed class SectorSignForm : Form
{
    private const string ClientVersion = "0.11.4-rc1";
    private readonly string appFolder = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), "SectorSign");
    private LocalSettings settings = new();
    private readonly ToolStrip strip = new();
    private readonly ToolStripLabel status = new("Preparando…");
    private readonly WebView2 browser = new() { Dock = DockStyle.Fill, DefaultBackgroundColor = Color.White };
    private bool kiosk;

    public SectorSignForm()
    {
        Text = "Sector Sign · Windows";
        Width = 1250;
        Height = 820;
        MinimumSize = new Size(360, 500);
        StartPosition = FormStartPosition.CenterScreen;
        BackColor = Color.FromArgb(245, 248, 253);
        Directory.CreateDirectory(appFolder);
        LoadSettings();

        strip.Dock = DockStyle.Top;
        strip.GripStyle = ToolStripGripStyle.Hidden;
        strip.RenderMode = ToolStripRenderMode.System;
        strip.Padding = new Padding(7, 5, 7, 5);
        strip.Items.Add(Button("Centro de Mando", () => SwitchMode("mando")));
        strip.Items.Add(Button("Reproductor", () => SwitchMode("reproductor")));
        strip.Items.Add(new ToolStripSeparator());
        strip.Items.Add(Button("Servidor", Configure));
        strip.Items.Add(Button("Recargar", () => browser.Reload()));
        strip.Items.Add(Button("Pantalla completa · F11", ToggleFullscreen));
        strip.Items.Add(new ToolStripSeparator());
        strip.Items.Add(status);
        strip.Items.Add(new ToolStripLabel("Sector Sign Windows " + ClientVersion));
        Controls.Add(browser);
        Controls.Add(strip);
        KeyPreview = true;
        KeyDown += (_, e) => { if (e.KeyCode == Keys.F11) { ToggleFullscreen(); e.Handled = true; } };
        Shown += async (_, _) => await InitializeBrowser();
    }

    private ToolStripButton Button(string name, Action callback)
    {
        var item = new ToolStripButton(name) { DisplayStyle = ToolStripItemDisplayStyle.Text };
        item.Click += (_, _) => callback();
        return item;
    }

    private string SettingsFile => Path.Combine(appFolder, "settings.json");
    private void LoadSettings()
    {
        try { if (File.Exists(SettingsFile)) settings = JsonSerializer.Deserialize<LocalSettings>(File.ReadAllText(SettingsFile)) ?? new(); }
        catch { settings = new(); }
        if (!AllowedUrl(settings.Server, out _)) settings.Server = "https://sectorsign.sectorsolutionssac.com";
        if (settings.Mode is not ("mando" or "reproductor")) settings.Mode = "mando";
    }
    private void SaveSettings() => File.WriteAllText(SettingsFile, JsonSerializer.Serialize(settings, new JsonSerializerOptions { WriteIndented = true }));
    private static bool AllowedUrl(string value, out Uri uri)
    {
        if (!Uri.TryCreate(value, UriKind.Absolute, out uri!)) return false;
        if (uri.UserInfo.Length != 0 || uri.Query.Length != 0 || uri.Fragment.Length != 0) return false;
        if (uri.AbsolutePath != "/") return false;
        return uri.Scheme == Uri.UriSchemeHttps || (uri.Scheme == Uri.UriSchemeHttp && (uri.Host == "localhost" || uri.Host == "127.0.0.1"));
    }
    private async System.Threading.Tasks.Task InitializeBrowser()
    {
        try
        {
            var env = await CoreWebView2Environment.CreateAsync(userDataFolder: Path.Combine(appFolder, "BrowserProfile"));
            await browser.EnsureCoreWebView2Async(env);
            browser.CoreWebView2.Settings.IsStatusBarEnabled = false;
            browser.CoreWebView2.NewWindowRequested += (_, args) =>
            {
                args.Handled = true;
                if (args.Uri.StartsWith("https://", StringComparison.OrdinalIgnoreCase))
                    try { Process.Start(new ProcessStartInfo(args.Uri) { UseShellExecute = true }); } catch { }
            };
            browser.CoreWebView2.NavigationCompleted += (_, args) =>
            {
                status.Text = args.IsSuccess ? "Conectado a " + settings.Server : "Sin conexión · Reintentar";
            };
            Navigate();
        }
        catch (WebView2RuntimeNotFoundException)
        {
            MessageBox.Show("Se necesita Microsoft Edge WebView2 Runtime. Instálalo desde la página oficial de Microsoft y abre Sector Sign nuevamente.", "Sector Sign", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            Process.Start(new ProcessStartInfo("https://developer.microsoft.com/en-us/microsoft-edge/webview2/") { UseShellExecute = true });
        }
        catch (Exception e)
        {
            MessageBox.Show("No se pudo abrir Sector Sign: " + e.Message, "Sector Sign", MessageBoxButtons.OK, MessageBoxIcon.Error);
        }
    }
    private void Navigate()
    {
        if (browser.CoreWebView2 == null) return;
        var url = settings.Server.TrimEnd('/') + (settings.Mode == "reproductor" ? "/player.php" : "/");
        status.Text = "Conectando…";
        browser.CoreWebView2.Navigate(url);
        Text = settings.Mode == "reproductor" ? "Sector Sign · Reproductor" : "Sector Sign · Centro de Mando";
    }
    private void SwitchMode(string mode)
    {
        if (mode == settings.Mode) return;
        if (settings.Mode == "reproductor" && MessageBox.Show("¿Salir del modo Reproductor? Esto podría interrumpir la publicidad.", "Sector Sign", MessageBoxButtons.YesNo, MessageBoxIcon.Warning) != DialogResult.Yes) return;
        settings.Mode = mode;
        SaveSettings();
        Navigate();
    }
    private void Configure()
    {
        using var form = new Form { Text = "Dirección de Sector Sign", Width = 510, Height = 200, FormBorderStyle = FormBorderStyle.FixedDialog, MaximizeBox = false, MinimizeBox = false, StartPosition = FormStartPosition.CenterParent };
        var label = new Label { Text = "Dominio HTTPS de tu instalación:", AutoSize = true, Left = 18, Top = 20 };
        var input = new TextBox { Left = 18, Top = 49, Width = 454, Text = settings.Server };
        var error = new Label { Left = 18, Top = 84, Width = 460, Height = 30, ForeColor = Color.DarkRed };
        var ok = new Button { Text = "Guardar y conectar", Left = 302, Top = 121, Width = 170 };
        ok.Click += (_, _) => {
            var value = input.Text.Trim().TrimEnd('/') + "/";
            if (!AllowedUrl(value, out _)) { error.Text = "Usa un dominio HTTPS, sin rutas ni credenciales."; return; }
            settings.Server = value.TrimEnd('/'); SaveSettings(); form.DialogResult = DialogResult.OK; form.Close();
        };
        form.Controls.AddRange(new Control[] { label, input, error, ok });
        if (form.ShowDialog(this) == DialogResult.OK) Navigate();
    }
    private void ToggleFullscreen()
    {
        kiosk = !kiosk;
        if (kiosk) { strip.Visible = false; FormBorderStyle = FormBorderStyle.None; WindowState = FormWindowState.Maximized; }
        else { WindowState = FormWindowState.Normal; FormBorderStyle = FormBorderStyle.Sizable; strip.Visible = true; }
    }
}
