import asyncio
import logging
logging.basicConfig(level=logging.DEBUG)
import aioice.turn

async def main():
    try:
        class DummyProto(asyncio.DatagramProtocol):
            pass
        transport, proto = await aioice.turn.create_turn_endpoint(
            DummyProto,
            ('openrelay.metered.ca', 443),
            username='openrelayproject',
            password='openrelayproject',
            lifetime=600
        )
        print("Success! Relayed address:", proto.relayed_address)
        transport.close()
    except Exception as e:
        print("Exception caught:", type(e), e)

asyncio.run(main())
