const { app, BrowserWindow } = require('electron');
const path = require('path');
const fs = require('fs');

let mainWindow;

function loadConfig() {
    const configPath = path.join(__dirname, 'config.json');
    if (fs.existsSync(configPath)) {
        try {
            return JSON.parse(fs.readFileSync(configPath, 'utf8'));
        } catch (e) {
            console.error('Error reading config.json:', e);
        }
    }
    return {
        url: "http://127.0.0.1:8000",
        appName: "MMS Desktop",
        width: 1280,
        height: 800,
        autoHideMenuBar: true
    };
}

function createWindow() {
    const config = loadConfig();

    mainWindow = new BrowserWindow({
        width: config.width,
        height: config.height,
        title: config.appName,
        autoHideMenuBar: config.autoHideMenuBar,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            sandbox: true
        }
    });

    // Load URL
    mainWindow.loadURL(config.url).catch(err => {
        console.error("Gagal memuat URL:", err);
        mainWindow.loadURL('data:text/html;charset=utf-8,' + encodeURIComponent(`
            <html>
            <body style="font-family: sans-serif; text-align: center; padding: 50px; color: #333; background-color: #f8f9fa;">
                <div style="max-width: 500px; margin: auto; padding: 30px; border-radius: 8px; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <h2 style="color: #dc3545;">Gagal Menghubungkan ke Server</h2>
                    <p>Pastikan server web lokal Anda aktif atau alamat URL di <code>config.json</code> sudah benar.</p>
                    <p style="color: #666; font-size: 14px; background: #eee; padding: 10px; border-radius: 4px; font-family: monospace;">Target: ${config.url}</p>
                    <button onclick="location.reload()" style="padding: 10px 20px; font-size: 16px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; margin-top: 15px;">
                        Coba Lagi
                    </button>
                </div>
            </body>
            </html>
        `));
    });

    mainWindow.on('closed', function () {
        mainWindow = null;
    });
}

app.on('ready', createWindow);

app.on('window-all-closed', function () {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('activate', function () {
    if (mainWindow === null) {
        createWindow();
    }
});
