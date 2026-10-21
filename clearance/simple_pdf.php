<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(403);
    exit('Forbidden');
}

final class SimplePdf
{
    private const PAGE_WIDTH = 841.89;
    private const PAGE_HEIGHT = 595.28;

    /** @var list<string> */
    private array $commands = [];
    /** @var array<string,array<string,mixed>> */
    private array $images = [];
    private array $previewCommands = [];
    private array $previewImages = [];
    private string $previewStroke = 'rgb(0,0,0)';
    private string $previewFill = 'rgb(0,0,0)';

    public function __construct(private bool $preview = false) {}

    private static function svgEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function previewSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT
            . '" role="img" aria-label="Your clearance paper" style="font-family:Arial,Helvetica,sans-serif"><rect width="100%" height="100%" fill="white"/>'
            . implode('', $this->previewCommands) . '</svg>';
    }

    public function pageWidth(): float
    {
        return self::PAGE_WIDTH;
    }

    public function pageHeight(): float
    {
        return self::PAGE_HEIGHT;
    }

    public function setStroke(float $r, float $g, float $b): void
    {
        $this->commands[] = sprintf('%.3F %.3F %.3F RG', $r, $g, $b);
        $this->previewStroke = sprintf('rgb(%d,%d,%d)',round($r*255),round($g*255),round($b*255));
    }

    public function setFill(float $r, float $g, float $b): void
    {
        $this->commands[] = sprintf('%.3F %.3F %.3F rg', $r, $g, $b);
        $this->previewFill = sprintf('rgb(%d,%d,%d)',round($r*255),round($g*255),round($b*255));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.6): void
    {
        $this->commands[] = sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $width, $x1, $y1, $x2, $y2);
        if ($this->preview) { $this->previewCommands[] = sprintf('<line x1="%.2F" y1="%.2F" x2="%.2F" y2="%.2F" stroke="%s" stroke-width="%.2F"/>',$x1,self::PAGE_HEIGHT-$y1,$x2,self::PAGE_HEIGHT-$y2,$this->previewStroke,$width); }
    }

    public function rect(float $x, float $y, float $width, float $height, bool $fill = false, float $lineWidth = 0.6): void
    {
        $this->commands[] = sprintf('%.2F w %.2F %.2F %.2F %.2F re %s', $lineWidth, $x, $y, $width, $height, $fill ? 'B' : 'S');
        if ($this->preview) { $this->previewCommands[] = sprintf('<rect x="%.2F" y="%.2F" width="%.2F" height="%.2F" fill="%s" stroke="%s" stroke-width="%.2F"/>',$x,self::PAGE_HEIGHT-$y-$height,$width,$height,$fill ? $this->previewFill : 'none',$this->previewStroke,$lineWidth); }
    }

    public function text(float $x, float $y, string $text, float $size = 9, bool $bold = false, string $align = 'left', float $boxWidth = 0): void
    {
        $encoded = self::encodeText($text);
        if ($align !== 'left' && $boxWidth > 0) {
            $estimatedWidth = self::estimatedTextWidth($encoded, $size, $bold);
            $x += $align === 'center' ? max(0, ($boxWidth - $estimatedWidth) / 2) : max(0, $boxWidth - $estimatedWidth);
        }
        $font = $bold ? 'F2' : 'F1';
        $this->commands[] = sprintf('BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', $font, $size, $x, $y, self::escapePdfString($encoded));
        if ($this->preview) {
            $display = iconv('Windows-1252','UTF-8',$encoded);
            $this->previewCommands[] = sprintf('<text x="%.2F" y="%.2F" font-size="%.2F" font-weight="%s" fill="%s">%s</text>',$x,self::PAGE_HEIGHT-$y,$size,$bold ? 'bold' : 'normal',$this->previewFill,self::svgEscape($display === false ? '' : $display));
        }
    }

    /** @return list<string> */
    public function wrap(string $text, float $size, float $maxWidth, bool $bold = false): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        if ($words === []) {
            return [''];
        }
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && self::estimatedTextWidth(self::encodeText($candidate), $size, $bold) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    }

    public function addImage(string $imageData, string $mimeType): string
    {
        $name = 'Im' . (count($this->images) + 1);
        $this->images[$name] = $mimeType === 'image/jpeg'
            ? self::readJpeg($imageData)
            : self::readPng($imageData);
        if ($this->preview) { $this->previewImages[$name] = 'data:' . ($mimeType === 'image/jpeg' ? 'image/jpeg' : 'image/png') . ';base64,' . base64_encode($imageData); }
        return $name;
    }

    public function image(string $name, float $x, float $y, float $width, float $height): void
    {
        if (!isset($this->images[$name])) {
            throw new RuntimeException('The requested PDF image is unavailable.');
        }
        $this->commands[] = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $width, $height, $x, $y, $name);
        if ($this->preview) { $this->previewCommands[] = sprintf('<image x="%.2F" y="%.2F" width="%.2F" height="%.2F" href="%s"/>',$x,self::PAGE_HEIGHT-$y-$height,$width,$height,$this->previewImages[$name]); }
    }

    public function imageFitted(string $name, float $x, float $y, float $width, float $height): void
    {
        if (!isset($this->images[$name])) {
            throw new RuntimeException('The requested PDF image is unavailable.');
        }
        $image = $this->images[$name];
        $scale = min($width / $image['width'], $height / $image['height']);
        $drawWidth = $image['width'] * $scale;
        $drawHeight = $image['height'] * $scale;
        $this->image($name, $x + ($width - $drawWidth) / 2, $y + ($height - $drawHeight) / 2, $drawWidth, $drawHeight);
    }

    public function output(): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $nextObject = 6;
        $resourceEntries = [];
        foreach ($this->images as $name => $image) {
            $alphaObject = null;
            if (isset($image['alpha'])) {
                $alphaObject = $nextObject++;
                $objects[$alphaObject] = self::imageObject($image['width'], $image['height'], 'DeviceGray', $image['alpha'], 'FlateDecode');
            }
            $imageObject = $nextObject++;
            $objects[$imageObject] = self::imageObject(
                $image['width'],
                $image['height'],
                $image['color_space'],
                $image['data'],
                $image['filter'],
                $alphaObject
            );
            $resourceEntries[] = '/' . $name . ' ' . $imageObject . ' 0 R';
        }

        $content = implode("\n", $this->commands) . "\n";
        $contentObject = $nextObject++;
        $objects[$contentObject] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
        $xObjects = $resourceEntries === [] ? '' : ' /XObject << ' . implode(' ', $resourceEntries) . ' >>';
        $objects[3] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 4 0 R /F2 5 0 R >>%s >> /Contents %d 0 R >>',
            self::PAGE_WIDTH,
            self::PAGE_HEIGHT,
            $xObjects,
            $contentObject
        );

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        $maxObject = max(array_keys($objects));
        for ($number = 1; $number <= $maxObject; $number++) {
            if (!isset($objects[$number])) {
                throw new RuntimeException('The PDF object structure is invalid.');
            }
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $objects[$number] . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n0000000000 65535 f \n";
        for ($number = 1; $number <= $maxObject; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";
        return $pdf;
    }

    private static function imageObject(int $width, int $height, string $colorSpace, string $data, ?string $filter, ?int $alphaObject = null): string
    {
        $dictionary = sprintf(
            '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8',
            $width,
            $height,
            $colorSpace
        );
        if ($filter !== null) {
            $dictionary .= ' /Filter /' . $filter;
        }
        if ($alphaObject !== null) {
            $dictionary .= ' /SMask ' . $alphaObject . ' 0 R';
        }
        $dictionary .= ' /Length ' . strlen($data) . " >>\nstream\n";
        return $dictionary . $data . "\nendstream";
    }

    /** @return array<string,mixed> */
    private static function readJpeg(string $data): array
    {
        $info = @getimagesizefromstring($data);
        if ($info === false || ($info['mime'] ?? '') !== 'image/jpeg') {
            throw new RuntimeException('A JPEG image used by the PDF is invalid.');
        }
        return [
            'width' => (int) $info[0],
            'height' => (int) $info[1],
            'color_space' => ($info['channels'] ?? 3) === 1 ? 'DeviceGray' : 'DeviceRGB',
            'filter' => 'DCTDecode',
            'data' => $data,
        ];
    }

    /** @return array<string,mixed> */
    private static function readPng(string $data): array
    {
        if (substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new RuntimeException('A PNG image used by the PDF is invalid.');
        }
        $offset = 8;
        $width = $height = $bitDepth = $colorType = $interlace = null;
        $compressed = '';
        while ($offset + 12 <= strlen($data)) {
            $length = unpack('N', substr($data, $offset, 4))[1];
            $type = substr($data, $offset + 4, 4);
            $chunk = substr($data, $offset + 8, $length);
            $offset += 12 + $length;
            if ($type === 'IHDR') {
                $header = unpack('Nwidth/Nheight/Cbit/Ccolor/Ccompression/Cfilter/Cinterlace', $chunk);
                $width = $header['width'];
                $height = $header['height'];
                $bitDepth = $header['bit'];
                $colorType = $header['color'];
                $interlace = $header['interlace'];
            } elseif ($type === 'IDAT') {
                $compressed .= $chunk;
            } elseif ($type === 'IEND') {
                break;
            }
        }
        if (!$width || !$height || $bitDepth !== 8 || !in_array($colorType, [0, 2, 4, 6], true) || $interlace !== 0) {
            throw new RuntimeException('The PDF requires standard non-interlaced 8-bit PNG images.');
        }
        $channels = [0 => 1, 2 => 3, 4 => 2, 6 => 4][$colorType];
        $raw = zlib_decode($compressed);
        $rowBytes = $width * $channels;
        if (!is_string($raw) || strlen($raw) !== ($rowBytes + 1) * $height) {
            throw new RuntimeException('A PNG image used by the PDF could not be decoded.');
        }
        $rows = [];
        $position = 0;
        $previous = array_fill(0, $rowBytes, 0);
        for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
            $filter = ord($raw[$position++]);
            $source = substr($raw, $position, $rowBytes);
            $position += $rowBytes;
            $row = [];
            for ($index = 0; $index < $rowBytes; $index++) {
                $value = ord($source[$index]);
                $left = $index >= $channels ? $row[$index - $channels] : 0;
                $up = $previous[$index] ?? 0;
                $upperLeft = $index >= $channels ? ($previous[$index - $channels] ?? 0) : 0;
                $row[$index] = match ($filter) {
                    0 => $value,
                    1 => ($value + $left) & 255,
                    2 => ($value + $up) & 255,
                    3 => ($value + intdiv($left + $up, 2)) & 255,
                    4 => ($value + self::paeth($left, $up, $upperLeft)) & 255,
                    default => throw new RuntimeException('A PNG image uses an unsupported filter.'),
                };
            }
            $rows[] = $row;
            $previous = $row;
        }

        $pixels = '';
        $alpha = '';
        foreach ($rows as $row) {
            for ($index = 0; $index < count($row); $index += $channels) {
                if ($colorType === 0) {
                    $pixels .= chr($row[$index]);
                } elseif ($colorType === 2) {
                    $pixels .= chr($row[$index]) . chr($row[$index + 1]) . chr($row[$index + 2]);
                } elseif ($colorType === 4) {
                    $pixels .= chr($row[$index]);
                    $alpha .= chr($row[$index + 1]);
                } else {
                    $pixels .= chr($row[$index]) . chr($row[$index + 1]) . chr($row[$index + 2]);
                    $alpha .= chr($row[$index + 3]);
                }
            }
        }
        $result = [
            'width' => $width,
            'height' => $height,
            'color_space' => in_array($colorType, [0, 4], true) ? 'DeviceGray' : 'DeviceRGB',
            'filter' => 'FlateDecode',
            'data' => gzcompress($pixels, 6),
        ];
        if ($alpha !== '') {
            $result['alpha'] = gzcompress($alpha, 6);
        }
        return $result;
    }

    private static function paeth(int $left, int $up, int $upperLeft): int
    {
        $estimate = $left + $up - $upperLeft;
        $leftDistance = abs($estimate - $left);
        $upDistance = abs($estimate - $up);
        $cornerDistance = abs($estimate - $upperLeft);
        return $leftDistance <= $upDistance && $leftDistance <= $cornerDistance
            ? $left : ($upDistance <= $cornerDistance ? $up : $upperLeft);
    }

    private static function encodeText(string $text): string
    {
        $normalized = str_replace(["\r", "\n", "\t"], ' ', $text);
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $normalized);
        return is_string($encoded) ? $encoded : preg_replace('/[^\x20-\x7E]/', '?', $normalized);
    }

    private static function escapePdfString(string $text): string
    {
        $result = '';
        for ($index = 0; $index < strlen($text); $index++) {
            $byte = ord($text[$index]);
            if ($text[$index] === '\\' || $text[$index] === '(' || $text[$index] === ')') {
                $result .= '\\' . $text[$index];
            } elseif ($byte < 32 || $byte > 126) {
                $result .= sprintf('\\%03o', $byte);
            } else {
                $result .= $text[$index];
            }
        }
        return $result;
    }

    private static function estimatedTextWidth(string $text, float $size, bool $bold): float
    {
        return strlen($text) * $size * ($bold ? 0.55 : 0.50);
    }
}
