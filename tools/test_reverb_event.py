import asyncio
import json
import websockets
import requests
import subprocess
import time

async def main():
    # 1. Connect to Reverb
    ws_uri = "wss://holidays-hub-beliefs-ultra.trycloudflare.com/app/nw2zhrpowiazy7xm9esc?protocol=7&client=test&version=1.0"
    print("Connecting to Reverb...")
    async with websockets.connect(ws_uri) as ws:
        # Handshake
        msg = await ws.recv()
        data = json.loads(msg)
        socket_id = json.loads(data["data"])["socket_id"]
        print(f"Connected! Socket ID: {socket_id}")

        # 2. Get auth for private-device.0362ed27-d43d-4ca7-8db4-e221a3676ba2 (PC 1)
        # Using PC 1 token from crypto store
        import sys
        sys.path.append('desktop-agent')
        from crypto_storage import AgentCredentialStore
        store = AgentCredentialStore()
        cred = store.load_credentials()
        token = cred["device_token"]
        uuid = cred["device_uuid"]

        channel = f"private-device.{uuid}"
        auth_res = requests.post(
            "http://127.0.0.1:8000/api/v1/device/broadcasting/auth",
            json={"socket_id": socket_id, "channel_name": channel},
            headers={"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
        )
        print("Auth response:", auth_res.status_code, auth_res.text)
        auth_sig = auth_res.json()["auth"]

        # 3. Subscribe
        await ws.send(json.dumps({
            "event": "pusher:subscribe",
            "data": {"channel": channel, "auth": auth_sig}
        }))
        sub_resp = await ws.recv()
        print("Subscribed:", sub_resp)

        # 4. Trigger test broadcast in background
        print("Triggering test event via PHP artisan...")
        cmd = f'php tools/test_broadcast.php {uuid}'
        subprocess.Popen(cmd, shell=True)

        # 5. Await event
        print("Waiting for event on WebSocket...")
        try:
            event_msg = await asyncio.wait_for(ws.recv(), timeout=10.0)
            print(">>> RECEIVED EVENT OVER WEBSOCKET:")
            print(event_msg)
        except asyncio.TimeoutError:
            print("!!! TIMED OUT WAITING FOR EVENT !!!")

if __name__ == "__main__":
    asyncio.run(main())
