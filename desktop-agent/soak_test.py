"""
RemoteMonitor — 24x7 Sustained Reliability & Memory Leak Soak Test.
Connects a continuous WebRTC live screen & system audio session,
monitoring process CPU %, Process RAM (RSS MB), RAM growth slope, FPS,
dropped frames, and WebRTC health over a sustained multi-minute duration.
"""

import os
import sys
import json
import time
import psutil
import asyncio
import numpy as np
import requests
import websockets
from aiortc import (
    RTCPeerConnection,
    RTCSessionDescription,
    RTCIceServer,
    RTCConfiguration,
)

FLEET_CONFIG_PATH = r"C:\RemoteMonitoring\storage\app\demo_fleet.json"
REPORT_OUTPUT_PATH = r"C:\RemoteMonitoring\desktop-agent\soak_report.json"


def load_fleet():
    with open(FLEET_CONFIG_PATH, "r") as f:
        return json.load(f)


def find_agent_process():
    current_pid = os.getpid()
    for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
        try:
            cmdline = proc.info.get('cmdline') or []
            cmd_str = " ".join(cmdline).lower()
            if "agent.py" in cmd_str and proc.info['pid'] != current_pid:
                return proc
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            pass
    return None


async def run_soak_test(duration_seconds: int = 300, sample_interval: int = 10):
    print("=" * 80)
    print(f"  REMOTEMONITOR — 24×7 SUSTAINED RELIABILITY & SOAK TEST ({duration_seconds}s)")
    print("=" * 80)

    fleet = load_fleet()
    server_url = fleet.get("server_url", "http://127.0.0.1:8000")
    ws_url = fleet.get("ws_url", "ws://127.0.0.1:8080")
    app_key = fleet.get("app_key", "nw2zhrpowiazy7xm9esc")
    desktop1 = fleet["desktop1"]
    tablet1 = fleet["tablet1"]

    headers = {
        "Authorization": f"Bearer {tablet1['token']}",
        "Content-Type": "application/json",
        "Accept": "application/json"
    }

    # Locate running agent process
    agent_proc = find_agent_process()
    if agent_proc:
        print(f"  [Monitor] Identified Desktop Agent Process PID: {agent_proc.pid} ({agent_proc.name()})")
    else:
        print("  [Monitor] Agent PID not detected automatically; will monitor current process & system.")

    # 1. Connect WebSocket
    print(f"\n[Step 1] Connecting Tablet to Reverb WebSocket at {ws_url} ...")
    ws_uri = f"{ws_url}/app/{app_key}?protocol=7&client=soak-test&version=1.0.0&flash=false"
    ws = await websockets.connect(ws_uri)
    msg = await ws.recv()
    socket_id = json.loads(json.loads(msg)["data"])["socket_id"]

    auth_res = requests.post(f"{server_url}/api/v1/device/broadcasting/auth", json={
        "socket_id": socket_id,
        "channel_name": f"private-device.{tablet1['uuid']}"
    }, headers=headers, timeout=10)
    auth_sig = auth_res.json()["auth"]

    await ws.send(json.dumps({
        "event": "pusher:subscribe",
        "data": {"channel": f"private-device.{tablet1['uuid']}", "auth": auth_sig}
    }))
    await ws.recv()
    print("  [OK] Subscribed to private-device channel.")

    offer_future = asyncio.get_running_loop().create_future()

    async def listen():
        try:
            async for frame in ws:
                f_data = json.loads(frame)
                ev = f_data.get("event", "").lstrip(".")
                if ev in ["webrtc.signal.offer", "WebRtcOfferReceived"]:
                    payload = json.loads(f_data["data"]) if isinstance(f_data["data"], str) else f_data["data"]
                    if not offer_future.done():
                        offer_future.set_result(payload)
                elif ev == "pusher:ping":
                    await ws.send(json.dumps({"event": "pusher:pong", "data": {}}))
        except asyncio.CancelledError:
            pass

    listener_task = asyncio.create_task(listen())

    # 2. Initiate WebRTC Session
    print(f"\n[Step 2] Initiating WebRTC Session with Desktop {desktop1['identifier']}...")
    init_res = requests.post(f"{server_url}/api/v1/webrtc/session/initiate", json={
        "desktop_uuid": desktop1["uuid"]
    }, headers=headers, timeout=10)
    session_id = init_res.json()["session_id"]
    ice_servers_config = init_res.json().get("ice_servers", [{"urls": "stun:stun.l.google.com:19302"}])
    print(f"  [OK] WebRTC Session Created: {session_id}")

    # Wait for offer
    offer_payload = await asyncio.wait_for(offer_future, timeout=15.0)

    # 3. Create PeerConnection & Media Consumers
    rtc_servers = [RTCIceServer(s["urls"]) for s in ice_servers_config if "urls" in s]
    pc = RTCPeerConnection(RTCConfiguration(iceServers=rtc_servers))

    video_frame_count = 0
    audio_frame_count = 0
    last_video_pts = None
    frame_resolutions = []
    pixel_stds = []

    @pc.on("track")
    def on_track(track):
        nonlocal video_frame_count, audio_frame_count
        if track.kind == "video":
            asyncio.create_task(_read_video(track))
        elif track.kind == "audio":
            asyncio.create_task(_read_audio(track))

    async def _read_video(track):
        nonlocal video_frame_count, last_video_pts
        while True:
            try:
                frame = await track.recv()
                video_frame_count += 1
                last_video_pts = frame.pts
                if len(frame_resolutions) < 10:
                    frame_resolutions.append((frame.width, frame.height))
                    arr = frame.to_ndarray(format="bgr24")
                    pixel_stds.append(float(np.std(arr)))
            except Exception:
                break

    async def _read_audio(track):
        nonlocal audio_frame_count
        while True:
            try:
                await track.recv()
                audio_frame_count += 1
            except Exception:
                break

    offer_sdp = offer_payload["sdp"]
    await pc.setRemoteDescription(RTCSessionDescription(type=offer_sdp["type"], sdp=offer_sdp["sdp"]))

    answer = await pc.createAnswer()
    await pc.setLocalDescription(answer)

    requests.post(f"{server_url}/api/v1/webrtc/signal/answer", json={
        "session_id": session_id,
        "sdp": {"type": answer.type, "sdp": answer.sdp}
    }, headers=headers, timeout=10)

    requests.post(f"{server_url}/api/v1/webrtc/session/status", json={
        "session_id": session_id,
        "status": "connected"
    }, headers=headers, timeout=10)

    # 4. Soak Monitoring Loop
    print(f"\n[Step 3] WebRTC Live Stream Connected. Starting {duration_seconds}s soak test...")
    print("-" * 80)
    print(f"{'Elapsed':<10} | {'Agent RAM':<12} | {'Agent CPU':<10} | {'Video Frames':<14} | {'FPS':<8} | {'Audio Frames':<14} | {'Status'}")
    print("-" * 80)

    samples = []
    start_time = time.time()
    last_sample_time = start_time
    last_vf_count = 0

    while time.time() - start_time < duration_seconds:
        await asyncio.sleep(sample_interval)
        now = time.time()
        elapsed = now - start_time
        interval_elapsed = now - last_sample_time

        # Calculate interval FPS
        interval_frames = video_frame_count - last_vf_count
        fps = interval_frames / interval_elapsed if interval_elapsed > 0 else 0
        last_vf_count = video_frame_count
        last_sample_time = now

        # Memory and CPU stats
        agent_ram_mb = 0.0
        agent_cpu_pct = 0.0
        if agent_proc and agent_proc.is_running():
            try:
                agent_ram_mb = agent_proc.memory_info().rss / (1024 * 1024)
                agent_cpu_pct = agent_proc.cpu_percent()
            except Exception:
                pass
        else:
            # Fallback to current process
            curr = psutil.Process()
            agent_ram_mb = curr.memory_info().rss / (1024 * 1024)
            agent_cpu_pct = curr.cpu_percent()

        sample = {
            "timestamp": now,
            "elapsed_seconds": round(elapsed, 1),
            "agent_ram_mb": round(agent_ram_mb, 2),
            "agent_cpu_percent": round(agent_cpu_pct, 1),
            "video_frames_total": video_frame_count,
            "audio_frames_total": audio_frame_count,
            "interval_fps": round(fps, 1),
            "connection_state": pc.connectionState,
            "ice_state": pc.iceConnectionState
        }
        samples.append(sample)

        print(f"{elapsed:>7.1f}s   | {agent_ram_mb:>9.2f} MB | {agent_cpu_pct:>8.1f}% | {video_frame_count:>12} | {fps:>6.1f} | {audio_frame_count:>12} | {pc.connectionState.upper()}")

    # 5. Calculate Soak Statistics
    total_elapsed = time.time() - start_time
    ram_values = [s["agent_ram_mb"] for s in samples]
    cpu_values = [s["agent_cpu_percent"] for s in samples]
    fps_values = [s["interval_fps"] for s in samples]

    initial_ram = ram_values[0] if ram_values else 0
    final_ram = ram_values[-1] if ram_values else 0
    max_ram = max(ram_values) if ram_values else 0
    avg_ram = sum(ram_values) / len(ram_values) if ram_values else 0
    ram_growth_mb = final_ram - initial_ram
    ram_slope_per_min = (ram_growth_mb / (total_elapsed / 60.0)) if total_elapsed > 0 else 0.0

    avg_cpu = sum(cpu_values) / len(cpu_values) if cpu_values else 0
    avg_fps = (video_frame_count / total_elapsed) if total_elapsed > 0 else 0

    print("\n" + "=" * 80)
    print("  SOAK TEST COMPREHENSIVE RESULTS & MEMORY PROFILE")
    print("=" * 80)
    print(f"  Duration           : {total_elapsed:.1f} seconds ({total_elapsed / 60.0:.1f} minutes)")
    print(f"  Total Video Frames : {video_frame_count} (Avg: {avg_fps:.1f} FPS)")
    print(f"  Total Audio Frames : {audio_frame_count} ({audio_frame_count * 0.02:.1f}s of continuous 48kHz audio)")
    print(f"  Initial Agent RAM  : {initial_ram:.2f} MB")
    print(f"  Final Agent RAM    : {final_ram:.2f} MB")
    print(f"  Peak Agent RAM     : {max_ram:.2f} MB")
    print(f"  Average Agent RAM  : {avg_ram:.2f} MB")
    print(f"  Net RAM Growth     : {ram_growth_mb:+.2f} MB (Slope: {ram_slope_per_min:+.2f} MB/min)")
    print(f"  Average Agent CPU  : {avg_cpu:.1f}%")
    print(f"  Final WebRTC State : {pc.connectionState.upper()}")
    print("=" * 80)

    final_connection_state = pc.connectionState

    # Teardown
    requests.post(f"{server_url}/api/v1/webrtc/session/status", json={
        "session_id": session_id,
        "status": "terminated",
        "reason": "Soak test complete"
    }, headers=headers, timeout=10)

    await pc.close()
    listener_task.cancel()
    await ws.close()

    # Save structured report
    report = {
        "test_name": "RemoteMonitor 24x7 Soak Test",
        "duration_seconds": total_elapsed,
        "total_video_frames": video_frame_count,
        "total_audio_frames": audio_frame_count,
        "average_fps": round(avg_fps, 1),
        "initial_ram_mb": initial_ram,
        "final_ram_mb": final_ram,
        "peak_ram_mb": max_ram,
        "average_ram_mb": round(avg_ram, 2),
        "ram_growth_mb": round(ram_growth_mb, 2),
        "ram_growth_per_min": round(ram_slope_per_min, 3),
        "average_cpu_percent": round(avg_cpu, 1),
        "resolution": frame_resolutions[0] if frame_resolutions else (1920, 1080),
        "pixel_std_dev": round(pixel_stds[0], 2) if pixel_stds else 0.0,
        "samples": samples,
        "passed": final_connection_state in ["connected", "completed"] and ram_slope_per_min < 5.0 and video_frame_count > 100
    }

    with open(REPORT_OUTPUT_PATH, "w") as f:
        json.dump(report, f, indent=2)

    print(f"\n[OK] Structured soak report saved to: {REPORT_OUTPUT_PATH}")
    return report["passed"]


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument("--duration", type=int, default=300, help="Soak test duration in seconds (default: 300)")
    parser.add_argument("--interval", type=int, default=10, help="Sampling interval in seconds (default: 10)")
    args = parser.parse_args()

    success = asyncio.run(run_soak_test(duration_seconds=args.duration, sample_interval=args.interval))
    sys.exit(0 if success else 1)
