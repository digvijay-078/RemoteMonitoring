import sys
import io
import time
import threading
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
import numpy as np
from PIL import Image

sys.path.append('desktop-agent')
from capture_screen import ScreenCapture

screen = ScreenCapture(target_fps=30)
latest_jpeg = None
lock = threading.Lock()
running = True

def capture_loop():
    global latest_jpeg, running
    print("[CaptureLoop] Started.")
    while running:
        t0 = time.time()
        frame = screen.capture_frame()
        if frame is not None:
            # BGR to RGB
            im = Image.fromarray(frame[:, :, [2, 1, 0]])
            # Resize for responsive mobile streaming
            im.thumbnail((1280, 720))
            buf = io.BytesIO()
            im.save(buf, format='JPEG', quality=60)
            with lock:
                latest_jpeg = buf.getvalue()
        elapsed = time.time() - t0
        delay = max(0.001, (1.0 / 30) - elapsed)
        time.sleep(delay)

class StreamHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass # Silence verbose access logs

    def do_GET(self):
        global latest_jpeg, running
        if 'frame' in self.path:
            with lock:
                data = latest_jpeg
            if data:
                self.send_response(200)
                self.send_header('Content-Type', 'image/jpeg')
                self.send_header('Content-Length', str(len(data)))
                self.send_header('Access-Control-Allow-Origin', '*')
                self.send_header('Cache-Control', 'no-cache')
                self.end_headers()
                self.wfile.write(data)
            else:
                self.send_error(503, "Frame not ready")
        elif 'video' in self.path:
            self.send_response(200)
            self.send_header('Content-Type', 'multipart/x-mixed-replace; boundary=frame')
            self.send_header('Cache-Control', 'no-cache, private')
            self.send_header('Access-Control-Allow-Origin', '*')
            self.end_headers()
            while running:
                with lock:
                    data = latest_jpeg
                if data:
                    try:
                        self.wfile.write(b'--frame\r\n')
                        self.wfile.write(b'Content-Type: image/jpeg\r\n')
                        self.wfile.write(f'Content-Length: {len(data)}\r\n\r\n'.encode('ascii'))
                        self.wfile.write(data)
                        self.wfile.write(b'\r\n')
                    except (BrokenPipeError, ConnectionResetError):
                        break
                time.sleep(1.0 / 30)
        else:
            self.send_response(200)
            self.send_header('Content-Type', 'text/plain')
            self.end_headers()
            self.wfile.write(b"OK")

if __name__ == '__main__':
    t = threading.Thread(target=capture_loop, daemon=True)
    t.start()
    server = ThreadingHTTPServer(('127.0.0.1', 8085), StreamHandler)
    print("[HTTP Stream Server] Listening on http://127.0.0.1:8085")
    try:
        server.serve_forever()
    finally:
        running = False
        screen.close()
