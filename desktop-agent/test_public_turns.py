import asyncio
from aiortc import RTCPeerConnection, RTCConfiguration, RTCIceServer

candidates_to_test = [
    ("turn:openrelay.metered.ca:80", "openrelayproject", "openrelayproject"),
    ("turn:openrelay.metered.ca:443", "openrelayproject", "openrelayproject"),
    ("turn:openrelay.metered.ca:443?transport=tcp", "openrelayproject", "openrelayproject"),
    ("turn:standard.relay.metered.ca:80", "openrelayproject", "openrelayproject"),
    ("turn:standard.relay.metered.ca:443", "openrelayproject", "openrelayproject"),
    ("turn:relay.metered.ca:80", "openrelayproject", "openrelayproject"),
    ("turn:relay.metered.ca:443", "openrelayproject", "openrelayproject"),
    ("turn:global.turn.twilio.com:3478", "test", "test"),
    ("turn:numb.viagenie.ca:3478", "webrtc@live.com", "muazkh"),
    ("turn:turn.bistri.com:80", "homeo", "homeo"),
    ("turn:turn.anyfirewall.com:443?transport=tcp", "webrtc", "webrtc"),
]

async def check(url, user, cred):
    try:
        config = RTCConfiguration([RTCIceServer(url, username=user, credential=cred)])
        pc = RTCPeerConnection(configuration=config)
        pc.addTransceiver('video')
        offer = await pc.createOffer()
        await pc.setLocalDescription(offer)
        await asyncio.sleep(2)
        relays = [l for l in pc.localDescription.sdp.splitlines() if 'typ relay' in l]
        await pc.close()
        if relays:
            print(f"[FOUND WORKING TURN] {url} -> {relays[0]}")
            return True
        else:
            print(f"[NO RELAY] {url}")
    except Exception as e:
        print(f"[ERROR] {url}: {e}")
    return False

async def main():
    for url, user, cred in candidates_to_test:
        await check(url, user, cred)

if __name__ == '__main__':
    asyncio.run(main())
