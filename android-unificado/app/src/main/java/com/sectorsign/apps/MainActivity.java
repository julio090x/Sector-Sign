package com.sectorsign.apps;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.os.Bundle;
import android.graphics.Color;
import android.content.SharedPreferences;
import android.content.res.Configuration;
import android.net.http.SslError;
import android.text.InputType;
import android.view.KeyEvent;
import android.view.View;
import android.view.WindowManager;
import android.webkit.PermissionRequest;
import android.webkit.SslErrorHandler;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.ScrollView;
import android.content.Intent;
import android.net.Uri;
import android.webkit.ValueCallback;
import android.webkit.WebResourceError;
import android.os.Build;

import java.net.URI;
import java.util.Locale;

/**
 * The two installable products share the SAME web UI and server code.
 * Only their app identifiers and entry routes differ by Gradle product flavor.
 * No JavaScript-to-native bridge, no password exposure, no skipped TLS checks.
 */
public final class MainActivity extends Activity {
    private static final String SETTINGS = "sector_sign_settings";
    private static final String URL_KEY = "server_url";
    private WebView webView;
    private String expectedHost;
    private String expectedScheme;
    private int expectedPort;
    private ValueCallback<Uri[]> fileSelection;
    private static final int PICK_MEDIA = 731;
    private String text(String es, String en, String pt) {
        String l=Locale.getDefault().getLanguage();return l.equals("en")?en:l.equals("pt")?pt:es;
    }

    @Override public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        if (BuildConfig.IS_PLAYER) {
            getWindow().addFlags(WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON);
            fullscreen();
        }
        SharedPreferences prefs = getSharedPreferences(SETTINGS, MODE_PRIVATE);
        String url = prefs.getString(URL_KEY, "");
        if (url == null || !validUrl(url)) showSetup(url);
        else openProduct(url);
    }

    private void fullscreen() {
        if (BuildConfig.IS_PLAYER) getWindow().getDecorView().setSystemUiVisibility(
            View.SYSTEM_UI_FLAG_IMMERSIVE_STICKY | View.SYSTEM_UI_FLAG_FULLSCREEN |
            View.SYSTEM_UI_FLAG_HIDE_NAVIGATION | View.SYSTEM_UI_FLAG_LAYOUT_HIDE_NAVIGATION |
            View.SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN | View.SYSTEM_UI_FLAG_LAYOUT_STABLE
        );
    }

    private static boolean isPrivateIPv4(String host) {
        if (host == null || !host.matches("[0-9]{1,3}(\\.[0-9]{1,3}){3}")) return false;
        String[] p = host.split("\\.");
        int[] n = new int[4];
        try { for (int i = 0; i < 4; i++) {
            n[i] = Integer.parseInt(p[i]);
            if (n[i] < 0 || n[i] > 255) return false;
        }} catch (NumberFormatException e) {return false;}
        return n[0] == 10 || (n[0] == 192 && n[1] == 168) ||
               (n[0] == 172 && n[1] >= 16 && n[1] <= 31) ||
               n[0] == 127;
    }

    private static boolean validUrl(String text) {
        try {
            URI u = new URI(text.trim());
            String scheme = u.getScheme(), host = u.getHost();
            if (scheme == null || host == null || u.getUserInfo() != null || u.getFragment() != null || u.getQuery() != null ||
                (u.getPath() != null && !u.getPath().isEmpty() && !u.getPath().equals("/"))) return false;
            if (u.getPort() > 65535 || u.getPort() == 0) return false;
            if (scheme.equalsIgnoreCase("https")) return true;
            return scheme.equalsIgnoreCase("http") && (isPrivateIPv4(host) || host.equalsIgnoreCase("localhost"));
        } catch (Exception ignored) { return false; }
    }

    private void showSetup(String current) {
        if(fileSelection!=null){fileSelection.onReceiveValue(null);fileSelection=null;} if (webView != null) { webView.destroy(); webView = null; }
        LinearLayout root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        int pad=(int)(24*getResources().getDisplayMetrics().density); root.setPadding(pad,pad*2,pad,pad);
        root.setBackgroundColor(Color.rgb(12, 25, 46));
        TextView title = new TextView(this);
        title.setText(BuildConfig.IS_PLAYER ? text("Sector Sign · Reproductor","Sector Sign · Player","Sector Sign · Reprodutor") : text("Sector Sign · Centro de mando","Sector Sign · Command Center","Sector Sign · Central"));
        title.setTextColor(Color.WHITE);
        title.setTextSize(25);
        root.addView(title);
        TextView version=new TextView(this);
        version.setText("v"+BuildConfig.VERSION_NAME+" · Última actualización: "+BuildConfig.RELEASED_AT);
        version.setTextColor(Color.rgb(148, 197, 255));
        version.setTextSize(13);
        version.setPadding(0, 12, 0, 4);
        root.addView(version);
        TextView description = new TextView(this);
        description.setText(text("Conecta con tu centro de mando. Usa la misma dirección HTTPS del servidor en ambos equipos. Para red local se permite una IP privada.","Connect to your command center. Use the same HTTPS server address on both devices. Private IP addresses are supported for local networks.","Conecte à central. Use o mesmo endereço HTTPS do servidor em ambos os dispositivos. IPs privados são permitidos na rede local."));
        description.setTextColor(Color.rgb(205, 219, 239));
        description.setTextSize(16);
        description.setPadding(0, 24, 0, 12);
        root.addView(description);
        EditText address = new EditText(this);
        address.setTextColor(Color.WHITE);
        address.setHintTextColor(Color.LTGRAY);
        address.setSingleLine(true);
        address.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        address.setHint("https://carteleria.midominio.com");
        address.setText(current != null && !current.isEmpty() ? current : "https://");
        root.addView(address);
        Button open = new Button(this);
        open.setText(text("Conectar","Connect","Conectar"));
        root.addView(open);
        TextView status = new TextView(this);
        status.setTextColor(Color.rgb(255, 193, 167));
        status.setPadding(0, 12, 0, 0);
        root.addView(status);
        open.setOnClickListener(v -> {
            String input = address.getText().toString().trim().replaceAll("/+$", "");
            if (!validUrl(input)) { status.setText("Dirección no válida. Usa HTTPS o IP privada HTTP."); return; }
            getSharedPreferences(SETTINGS, MODE_PRIVATE).edit().putString(URL_KEY, input).apply();
            openProduct(input);
        });
        ScrollView scroll=new ScrollView(this);scroll.setFillViewport(true);scroll.addView(root);setContentView(scroll);
        fullscreen();
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void openProduct(String url) {
        try {
            URI u = new URI(url);
            expectedHost = u.getHost().toLowerCase(Locale.ROOT);
            expectedScheme = u.getScheme().toLowerCase(Locale.ROOT);
            expectedPort = u.getPort();
        } catch (Exception ignored) { showSetup(url); return; }
        webView = new WebView(this);
        webView.setBackgroundColor(BuildConfig.IS_PLAYER ? Color.BLACK : Color.WHITE);
        WebSettings s = webView.getSettings();
        s.setJavaScriptEnabled(true);
        s.setUserAgentString(s.getUserAgentString()+" SectorSign/"+BuildConfig.VERSION_NAME+" Model/"+Build.MODEL.replaceAll("[^A-Za-z0-9._-]","_"));
        s.setDomStorageEnabled(true);
        s.setDatabaseEnabled(true);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(true); // Explicit user-selected content URIs from Android document picker only.
        s.setAllowFileAccessFromFileURLs(false);
        s.setAllowUniversalAccessFromFileURLs(false);
        s.setJavaScriptCanOpenWindowsAutomatically(false);
        s.setSupportMultipleWindows(false);
        s.setMediaPlaybackRequiresUserGesture(!BuildConfig.IS_PLAYER);
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        if (android.os.Build.VERSION.SDK_INT >= 26) s.setSafeBrowsingEnabled(true);
        webView.setWebChromeClient(new WebChromeClient() {
            @Override public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
                if(BuildConfig.IS_PLAYER)return false;
                if(fileSelection!=null)fileSelection.onReceiveValue(null);
                fileSelection=callback;
                Intent pick=new Intent(Intent.ACTION_OPEN_DOCUMENT);
                pick.addCategory(Intent.CATEGORY_OPENABLE);pick.setType("*/*");
                pick.putExtra(Intent.EXTRA_MIME_TYPES,new String[]{"image/*","video/*"});
                pick.putExtra(Intent.EXTRA_ALLOW_MULTIPLE,true);
                try{startActivityForResult(pick,PICK_MEDIA);}catch(Exception e){fileSelection.onReceiveValue(null);fileSelection=null;}
                return true;
            }
            @Override public void onPermissionRequest(PermissionRequest request) { request.deny(); }
        });
        webView.setWebViewClient(new WebViewClient() {
            @Override public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                return !sameOrigin(request.getUrl().getScheme(), request.getUrl().getHost(), request.getUrl().getPort());
            }
            @Override public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                handler.cancel();
            }
        });
        setContentView(webView);
        webView.loadUrl(url + BuildConfig.ENTRY_PATH);
        fullscreen();
    }

    @Override protected void onActivityResult(int request, int result, Intent data) {
        super.onActivityResult(request,result,data);
        if(request!=PICK_MEDIA||fileSelection==null)return;
        Uri[] values=null;
        if(result==RESULT_OK&&data!=null){
            if(data.getClipData()!=null){int n=data.getClipData().getItemCount();values=new Uri[n];for(int i=0;i<n;i++)values[i]=data.getClipData().getItemAt(i).getUri();}
            else if(data.getData()!=null)values=new Uri[]{data.getData()};
        }
        fileSelection.onReceiveValue(values);fileSelection=null;
    }
    private boolean sameOrigin(String scheme, String host, int port) {
        return scheme != null && host != null && scheme.equalsIgnoreCase(expectedScheme) &&
               host.equalsIgnoreCase(expectedHost) && port == expectedPort;
    }

    /** Back/Menu/F1 opens the server setting; a second tap can restore the same origin. */
    @Override public boolean onKeyDown(int code, KeyEvent event) {
        if (code == KeyEvent.KEYCODE_MENU || code == KeyEvent.KEYCODE_F1) {
            showSetup(getSharedPreferences(SETTINGS, MODE_PRIVATE).getString(URL_KEY, ""));
            return true;
        }
        return super.onKeyDown(code, event);
    }
    @Override public void onBackPressed() {
        showSetup(getSharedPreferences(SETTINGS, MODE_PRIVATE).getString(URL_KEY, ""));
    }
    @Override public void onResume() { super.onResume(); fullscreen(); if (webView != null) webView.onResume(); }
    @Override public void onPause() { if (webView != null) webView.onPause(); super.onPause(); }
    @Override public void onConfigurationChanged(Configuration cfg) { super.onConfigurationChanged(cfg); fullscreen(); }
    @Override public void onDestroy() { if(fileSelection!=null){fileSelection.onReceiveValue(null);fileSelection=null;} if (webView != null) { webView.destroy(); webView = null; } super.onDestroy(); }
}
