import asyncio
from aiortc import RTCPeerConnection, RTCConfiguration, RTCIceServer

async def test():
    config = RTCConfiguration([
        RTCIceServer('stun:stun.cloudflare.com:3478'),
        RTCIceServer('turn:openrelay.metered.ca:443', username='openrelayproject', credential='openrelayproject')
    ])
    pc = RTCPeerConnection(configuration=config)
    pc.addTransceiver('video')
    offer = await pc.createOffer()
    await pc.setLocalDescription(offer)
    print("Gathering...")
    await asyncio.sleep(4)
    print("Local SDP candidates:")
    for line in pc.localDescription.sdp.splitlines():
        if 'candidate' in line:
            print(" ", line)
    await pc.close()

if __name__ == '__main__':
    asyncio.run(test())
