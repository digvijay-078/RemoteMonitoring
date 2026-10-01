import asyncio
import websockets

async def test_ws():
    uri = "wss://holidays-hub-beliefs-ultra.trycloudflare.com/app/nw2zhrpowiazy7xm9esc?protocol=7&client=py-agent&version=1.0.0&flash=false"
    print("Connecting to:", uri)
    try:
        async with websockets.connect(uri, open_timeout=5) as ws:
            msg = await ws.recv()
            print("Received initial WS frame:", msg)
            return True
    except Exception as e:
        print("WebSocket connection failed:", e)
        return False

if __name__ == '__main__':
    asyncio.run(test_ws())
