const http = require('http');
const httpProxy = require('http-proxy');
const fs = require('fs');
const path = require('path');

const PUBLIC_DIR = path.resolve(__dirname, '..', 'public');

const MIME_TYPES = {
    '.html': 'text/html',
    '.js': 'application/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
    '.png': 'image/png',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
    '.woff': 'font/woff',
    '.woff2': 'font/woff2',
    '.ttf': 'font/ttf',
    '.exe': 'application/vnd.microsoft.portable-executable',
    '.zip': 'application/zip',
};

// Global unhandled error absorption so proxy NEVER crashes on client disconnects
process.on('uncaughtException', (err) => {
    if (err.code === 'ECONNRESET' || err.code === 'EPIPE' || err.code === 'ETIMEDOUT') {
        return;
    }
    console.warn('[Proxy Process Error]:', err.message);
});

process.on('unhandledRejection', (reason) => {
    console.warn('[Proxy Unhandled Rejection]:', reason);
});

const proxyHttp = httpProxy.createProxyServer({
    target: 'http://127.0.0.1:8000',
    changeOrigin: true,
    xfwd: true,
    ws: false,
    timeout: 30000,
    proxyTimeout: 30000
});

const proxyReverb = httpProxy.createProxyServer({
    target: 'http://127.0.0.1:8081',
    changeOrigin: true,
    xfwd: true,
    ws: true,
    timeout: 30000,
    proxyTimeout: 30000
});

const proxyStream = httpProxy.createProxyServer({
    target: 'http://127.0.0.1:8085',
    changeOrigin: true,
    xfwd: true,
    ws: true,
    timeout: 30000,
    proxyTimeout: 30000
});

proxyHttp.on('error', (err, req, res) => {
    if (err.code !== 'ECONNRESET') {
        console.warn('[Proxy HTTP Error]:', err.code, err.message, req ? req.url : '');
    }
    if (res && !res.headersSent && typeof res.writeHead === 'function') {
        res.writeHead(502, { 'Content-Type': 'text/plain' });
        res.end('Bad Gateway: Laravel service initializing, please refresh in 3 seconds');
    }
});

proxyReverb.on('error', (err, req, res) => {
    if (err.code !== 'ECONNRESET') {
        console.warn('[Proxy Reverb Error]:', err.code, err.message);
    }
    if (res && !res.headersSent && typeof res.writeHead === 'function') {
        res.writeHead(502, { 'Content-Type': 'text/plain' });
        res.end('Bad Gateway: Reverb service unavailable');
    }
});

proxyStream.on('error', (err, req, res) => {
    if (err.code !== 'ECONNRESET') {
        console.warn('[Proxy Stream Error]:', err.code, err.message);
    }
    if (res && !res.headersSent && typeof res.writeHead === 'function') {
        res.writeHead(502, { 'Content-Type': 'text/plain' });
        res.end('Bad Gateway: Stream engine unavailable');
    }
});

let realtimeCache = {
    data: null,
    statusCode: 200,
    expiresAt: 0,
    isFetching: false,
    waiters: []
};

function handleRealtimeStatusProxy(req, res) {
    const now = Date.now();
    if (realtimeCache.data && now < realtimeCache.expiresAt) {
        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache',
            'X-Proxy-Cache': 'HIT',
            'Access-Control-Allow-Origin': '*'
        });
        res.end(realtimeCache.data);
        return;
    }

    if (realtimeCache.isFetching) {
        realtimeCache.waiters.push(res);
        return;
    }

    realtimeCache.isFetching = true;
    const reqHeaders = Object.assign({}, req.headers, {
        'host': '127.0.0.1:8000',
        'x-forwarded-host': req.headers['host'] || '127.0.0.1:8000',
        'x-forwarded-proto': req.headers['x-forwarded-proto'] || 'http'
    });

    const backendReq = http.request({
        hostname: '127.0.0.1',
        port: 8000,
        path: req.url,
        method: 'GET',
        headers: reqHeaders,
        timeout: 5000
    }, (backendRes) => {
        let body = '';
        backendRes.on('data', chunk => body += chunk);
        backendRes.on('end', () => {
            realtimeCache.isFetching = false;
            if (backendRes.statusCode === 200) {
                realtimeCache.data = body;
                realtimeCache.statusCode = 200;
                realtimeCache.expiresAt = Date.now() + 800; // 800ms TTL
            }

            const clientHeaders = Object.assign({}, backendRes.headers, {
                'X-Proxy-Cache': 'MISS',
                'Access-Control-Allow-Origin': '*'
            });

            if (!res.headersSent) {
                res.writeHead(backendRes.statusCode, clientHeaders);
                res.end(body);
            }

            while (realtimeCache.waiters.length > 0) {
                const waitingRes = realtimeCache.waiters.shift();
                if (!waitingRes.headersSent) {
                    waitingRes.writeHead(backendRes.statusCode, clientHeaders);
                    waitingRes.end(body);
                }
            }
        });
    });

    backendReq.on('error', (err) => {
        realtimeCache.isFetching = false;
        if (!res.headersSent) {
            res.writeHead(502, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ error: 'Backend unreachable', details: err.message }));
        }
        while (realtimeCache.waiters.length > 0) {
            const waitingRes = realtimeCache.waiters.shift();
            if (!waitingRes.headersSent) {
                waitingRes.writeHead(502, { 'Content-Type': 'application/json' });
                waitingRes.end(JSON.stringify({ error: 'Backend unreachable' }));
            }
        }
    });

    backendReq.end();
}

function tryServeStatic(req, res) {
    const parsedUrl = new URL(req.url, 'http://127.0.0.1');
    let pathname = decodeURIComponent(parsedUrl.pathname);

    // Prevent directory traversal
    const safePath = path.normalize(pathname).replace(/^(\.\.[\/\\])+/, '');
    const filePath = path.join(PUBLIC_DIR, safePath);

    if (filePath.startsWith(PUBLIC_DIR)) {
        try {
            if (fs.existsSync(filePath) && fs.statSync(filePath).isFile()) {
                const stat = fs.statSync(filePath);
                const ext = path.extname(filePath).toLowerCase();
                const mimeType = MIME_TYPES[ext] || 'application/octet-stream';
                
                const isDownload = pathname.startsWith('/downloads/');
                const cacheControl = (ext === '.exe' || isDownload) ? 'no-cache, no-store, must-revalidate' : 'public, max-age=86400';

                res.writeHead(200, {
                    'Content-Type': mimeType,
                    'Content-Length': stat.size,
                    'Accept-Ranges': 'bytes',
                    'Cache-Control': cacheControl,
                    'Access-Control-Allow-Origin': '*'
                });

                if (req.method === 'HEAD') {
                    res.end();
                    return true;
                }

                const stream = fs.createReadStream(filePath);
                stream.on('error', () => {
                    if (!res.headersSent) res.writeHead(500);
                    res.end();
                });
                stream.pipe(res);
                return true;
            }
        } catch (e) {
            // Fall back to dynamic proxy
        }
    }
    return false;
}

const server = http.createServer((req, res) => {
    req.on('error', () => {});
    res.on('error', () => {});

    const host = req.headers['host'] || '';
    if (host.includes('railway') || host.includes('trycloudflare.com') || host.includes('ngrok') || (req.headers['cf-visitor'] && req.headers['cf-visitor'].includes('https')) || req.headers['x-forwarded-proto'] === 'https') {
        req.headers['x-forwarded-proto'] = 'https';
        req.headers['x-forwarded-port'] = '443';
        req.headers['x-forwarded-host'] = host;
    }

    // Direct static asset serving (bypasses PHP single thread)
    if ((req.method === 'GET' || req.method === 'HEAD') && (
        req.url.startsWith('/admin-assets/') ||
        req.url.startsWith('/tablet-assets/') ||
        req.url.startsWith('/build/') ||
        req.url.startsWith('/downloads/') ||
        req.url === '/favicon.ico' ||
        req.url === '/robots.txt'
    )) {
        if (tryServeStatic(req, res)) {
            return;
        }
    }

    // In-memory micro-cache for fleet realtime status to prevent PHP request pileup
    if (req.method === 'GET' && req.url.startsWith('/admin/devices-realtime-status')) {
        handleRealtimeStatusProxy(req, res);
        return;
    }

    if (req.url.startsWith('/stream')) {
        proxyStream.web(req, res);
    } else if (req.url.startsWith('/app')) {
        proxyReverb.web(req, res);
    } else {
        proxyHttp.web(req, res);
    }
});

server.on('upgrade', (req, socket, head) => {
    socket.on('error', () => {});

    if (req.url.startsWith('/stream')) {
        proxyStream.ws(req, socket, head);
    } else if (req.url.startsWith('/app')) {
        proxyReverb.ws(req, socket, head);
    } else {
        proxyHttp.ws(req, socket, head);
    }
});

server.on('clientError', (err, socket) => {
    if (socket && !socket.destroyed) {
        socket.destroy();
    }
});

const PORT = process.env.PORT || 8088;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`[Unified Ingress Proxy] Listening on http://0.0.0.0:${PORT}`);
    console.log(`  -> Static assets served directly from public/`);
    console.log(`  -> HTTP traffic forwarded to Laravel on :8000`);
    console.log(`  -> WebSocket /app traffic forwarded to Reverb on :8081`);
    console.log(`  -> Live Stream /stream traffic forwarded to Stream Engine on :8085`);
});
