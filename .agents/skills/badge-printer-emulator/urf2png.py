import sys, struct, zlib

def decode_urf(data):
    assert data[:8] == b'UNIRAST\x00', data[:8]
    pages = struct.unpack('>I', data[8:12])[0]
    pos, out = 12, []
    for _ in range(pages):
        bpp, cs, duplex, quality = data[pos], data[pos+1], data[pos+2], data[pos+3]
        width, height, dpi = struct.unpack('>III', data[pos+12:pos+24])
        pos += 32
        bytes_pp = bpp // 8
        white = b'\xff' * bytes_pp
        rows = []
        while len(rows) < height:
            repeat = data[pos] + 1; pos += 1
            line = bytearray()
            while len(line) < width * bytes_pp:
                n = data[pos]; pos += 1
                if n == 0x80:
                    line += white * (width - len(line) // bytes_pp)
                elif n < 0x80:
                    line += data[pos:pos+bytes_pp] * (n + 1); pos += bytes_pp
                else:
                    count = 257 - n
                    line += data[pos:pos+count*bytes_pp]; pos += count*bytes_pp
            rows.extend([bytes(line)] * repeat)
        out.append((width, height, dpi, bpp, rows[:height]))
    return out

def to_gray(row, bpp):
    if bpp == 8:
        return row
    step = bpp // 8
    return bytes((row[i]*299 + row[i+1]*587 + row[i+2]*114)//1000 for i in range(0, len(row), step))

def dither(rows, width):
    buf = [list(r) for r in rows]
    for y in range(len(buf)):
        r = buf[y]
        for x in range(width):
            old = r[x]; new = 255 if old >= 128 else 0; r[x] = new; err = old - new
            if x + 1 < width: r[x+1] += err * 7 // 16
            if y + 1 < len(buf):
                n = buf[y+1]
                if x > 0: n[x-1] += err * 3 // 16
                n[x] += err * 5 // 16
                if x + 1 < width: n[x+1] += err // 16
    return [bytes(max(0, min(255, v)) for v in r) for r in buf]

def write_png(path, width, rows):
    raw = b''.join(b'\x00' + r for r in rows)
    def chunk(t, d): return struct.pack('>I', len(d)) + t + d + struct.pack('>I', zlib.crc32(t + d) & 0xffffffff)
    png = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('>IIBBBBB', width, len(rows), 8, 0, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(raw, 9)) + chunk(b'IEND', b'')
    open(path, 'wb').write(png)

src, prefix = sys.argv[1], sys.argv[2]
for i, (w, h, dpi, bpp, rows) in enumerate(decode_urf(open(src, 'rb').read())):
    gray = [to_gray(r, bpp) for r in rows]
    write_png(f'{prefix}-p{i+1}-gray.png', w, gray)
    write_png(f'{prefix}-p{i+1}-thermal.png', w, dither(gray, w))
    print(f'page {i+1}: {w}x{h}px @ {dpi}dpi = {w*25.4/dpi:.1f} x {h*25.4/dpi:.1f} mm, {bpp}bpp')
