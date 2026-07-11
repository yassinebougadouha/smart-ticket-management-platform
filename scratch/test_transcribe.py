import requests

def main():
    # Let's request the Laravel URL
    url = "http://localhost:8605/api/v1/voice/transcribe"
    
    # We need a CSRF token and auth cookie?
    # Actually, we can call the support_api directly on port 8600!
    # Wait, the proxy goes to FastAPI on port 8600. Let's call it directly to see if it's a FastAPI validation error!
    url_direct = "http://localhost:8600/api/v1/voice/transcribe"
    
    # Let's prepare a dummy WAV file (riff header + empty data)
    # 44 bytes header for basic WAV
    wav_header = b'RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x44\xac\x00\x00\x88\x58\x01\x00\x02\x00\x10\x00data\x00\x00\x00\x00'
    
    files = {
        'file': ('test.wav', wav_header, 'audio/wav')
    }
    
    # Since direct call requires authentication, let's pass a header
    # Let's use the X-Laravel-User-Id fallback
    headers = {
        'X-Laravel-User-Id': '213'
    }
    
    res = requests.post(url_direct, files=files, headers=headers)
    print("Direct Status:", res.status_code)
    print("Direct Response:", res.text)

if __name__ == '__main__':
    main()
