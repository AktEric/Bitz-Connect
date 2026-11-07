<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Remove metadados de imagens enviadas (EXIF com GPS, modelo do aparelho, XMP, comentários) sem recodificar os pixels.
 *
 * - JPEG: descarta APP1 (EXIF/XMP), APP13 (IPTC) e comentários; preserva só a orientação num EXIF mínimo,
 *   para fotos de celular não aparecerem deitadas.
 * - PNG: descarta eXIf, tEXt, zTXt, iTXt e tIME.
 * - WebP: descarta EXIF e XMP e limpa os sinalizadores correspondentes.
 * - Outros formatos (GIF) seguem como vieram.
 */
class ImageSanitizer
{
    /** Salva o arquivo já sanitizado e devolve o caminho no disco. */
    public static function store(UploadedFile $file, string $dir, string $disk = 'public'): string
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $clean = self::clean($bytes);
        $ext   = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path  = trim($dir, '/') . '/' . bin2hex(random_bytes(20)) . '.' . $ext;

        Storage::disk($disk)->put($path, $clean);

        return $path;
    }

    public static function clean(string $bytes): string
    {
        if (str_starts_with($bytes, "\xFF\xD8")) {
            return self::cleanJpeg($bytes);
        }
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return self::cleanPng($bytes);
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return self::cleanWebp($bytes);
        }

        return $bytes;
    }

    private static function cleanJpeg(string $b): string
    {
        $len = strlen($b);
        $out = "\xFF\xD8";
        $orientation = null;
        $i = 2;

        while ($i + 4 <= $len) {
            if ($b[$i] !== "\xFF") {
                break;
            }
            $marker = ord($b[$i + 1]);
            if ($marker === 0xFF) { // preenchimento
                $i++;
                continue;
            }
            if ($marker === 0xDA || $marker === 0xD9) { // início dos dados da imagem / fim
                break;
            }
            $size = (ord($b[$i + 2]) << 8) | ord($b[$i + 3]);
            if ($size < 2 || $i + 2 + $size > $len) {
                break;
            }
            $segment = substr($b, $i, 2 + $size);
            $payload = substr($segment, 4);

            if ($marker === 0xE1 && str_starts_with($payload, "Exif\0\0")) {
                $orientation ??= self::exifOrientation(substr($payload, 6));
            } elseif ($marker === 0xE1 || $marker === 0xED || $marker === 0xFE) {
                // XMP, IPTC e comentários: descartados
            } else {
                $out .= $segment;
            }
            $i += 2 + $size;
        }

        // Restante (dados da imagem em diante) segue intacto.
        $rest = substr($b, $i);
        if ($orientation !== null && $orientation > 1 && $orientation <= 8) {
            $out = "\xFF\xD8" . self::minimalExif($orientation) . substr($out, 2);
        }

        return $out . $rest;
    }

    /** Lê a tag Orientation (0x0112) do IFD0 de um bloco TIFF. */
    private static function exifOrientation(string $tiff): ?int
    {
        if (strlen($tiff) < 14) {
            return null;
        }
        $little = substr($tiff, 0, 2) === 'II';
        if (! $little && substr($tiff, 0, 2) !== 'MM') {
            return null;
        }
        $u16 = fn (int $o) => $little ? unpack('v', substr($tiff, $o, 2))[1] : unpack('n', substr($tiff, $o, 2))[1];
        $u32 = fn (int $o) => $little ? unpack('V', substr($tiff, $o, 4))[1] : unpack('N', substr($tiff, $o, 4))[1];

        $ifd = $u32(4);
        if ($ifd + 2 > strlen($tiff)) {
            return null;
        }
        $count = $u16($ifd);
        for ($n = 0; $n < $count; $n++) {
            $entry = $ifd + 2 + 12 * $n;
            if ($entry + 12 > strlen($tiff)) {
                return null;
            }
            if ($u16($entry) === 0x0112) {
                return $u16($entry + 8);
            }
        }

        return null;
    }

    /** APP1 com um único campo: Orientation. */
    private static function minimalExif(int $orientation): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"          // cabeçalho big-endian, IFD0 em 8
            . "\x00\x01"                              // 1 entrada
            . "\x01\x12\x00\x03\x00\x00\x00\x01"      // tag 0x0112, tipo SHORT, contagem 1
            . pack('n', $orientation) . "\x00\x00"    // valor
            . "\x00\x00\x00\x00";                     // sem próximo IFD
        $payload = "Exif\0\0" . $tiff;

        return "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
    }

    private static function cleanPng(string $b): string
    {
        $drop = ['eXIf', 'tEXt', 'zTXt', 'iTXt', 'tIME'];
        $out = substr($b, 0, 8);
        $len = strlen($b);
        $i = 8;
        while ($i + 12 <= $len) {
            $size = unpack('N', substr($b, $i, 4))[1];
            $type = substr($b, $i + 4, 4);
            $total = 12 + $size;
            if ($i + $total > $len) {
                $out .= substr($b, $i); // arquivo truncado: mantém o resto
                return $out;
            }
            if (! in_array($type, $drop, true)) {
                $out .= substr($b, $i, $total);
            }
            $i += $total;
        }

        return $out . substr($b, $i);
    }

    private static function cleanWebp(string $b): string
    {
        $len = strlen($b);
        $chunks = '';
        $i = 12;
        while ($i + 8 <= $len) {
            $type = substr($b, $i, 4);
            $size = unpack('V', substr($b, $i + 4, 4))[1];
            $total = 8 + $size + ($size % 2);
            if ($i + 8 + $size > $len) {
                $chunks .= substr($b, $i);
                break;
            }
            if ($type === 'EXIF' || $type === 'XMP ') {
                $i += $total;
                continue;
            }
            $chunk = substr($b, $i, $total);
            if ($type === 'VP8X' && $size >= 10) {
                // bits de ICC (0x20), EXIF (0x08) e XMP (0x04): mantém só os demais
                $flags = ord($chunk[8]) & ~0x0C;
                $chunk[8] = chr($flags);
            }
            $chunks .= $chunk;
            $i += $total;
        }

        return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
    }
}
