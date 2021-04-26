# producer.py
# This script will have two purposes:
# 1) subscribe to redis for events on 'leaderboard' channel
# 2) create websocket connection to the php script and notify it on updates
#    from the leaderboard channel
# Both 1) and 2) are async

import asyncio
import websockets
from aioredis import create_connection, Channel
from configparser import ConfigParser
LEADERBOARD = 'leaderboard'


async def subscribe_to_redis(path):
    conn = await create_connection(('localhost', 6379))

    # Set up a subscribe channel
    channel = Channel(f'{path}', is_pattern=False)
    await conn.execute_pubsub('subscribe', channel)
    return channel, conn


async def redis_loop(websocket, path):
    channel, conn = await subscribe_to_redis(path)
    try:
        while True:
            # Wait until data is published to this channel
            message = await channel.get()
            # Send unicode decoded data over to the websocket client
            await websocket.send(message.decode('utf-8'))

    except websockets.exceptions.ConnectionClosed:
        # Free up channel if websocket goes down
        await conn.execute_pubsub('unsubscribe', channel)
        conn.close()


# async loop for websockets and inside async loop for redis
async def ws_loop(url, port):
    uri = f'ws://{url}:{port}/{LEADERBOARD}'
    async with websockets.connect(uri) as websocket:
        await redis_loop(websocket, LEADERBOARD)

if __name__ == '__main__':
    # instantiate
    config = ConfigParser()

    # parse existing file
    config.read('config.ini')
    # Runs a server process on 8767. Just do 'python producer.py'
    loop = asyncio.get_event_loop()
    # loop.set_debug(True)

    loop.run_until_complete(ws_loop(config.get('main', 'listen_url'), config.get('main', 'local_port')))
    loop.run_forever()
